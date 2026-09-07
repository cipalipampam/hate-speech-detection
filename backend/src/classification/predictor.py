"""
Hate Speech Predictor Service — Modul 4: Klasifikasi IndoBERT.

Deskripsi:
    Modul inferensi (prediksi) menggunakan model IndoBERT Hierarchical Classifier
    yang sudah di-fine-tune di Google Colab.

    Mendukung 2 mode prediksi:
        1. predict_text(text)           → Prediksi 1 kalimat interaktif.
        2. predict_dataframe(df)        → Prediksi batch DataFrame (ribuan baris).

    Setiap prediksi menghasilkan:
        - label_lvl1       : "hate_speech" atau "non_hate_speech"
        - label_lvl2       : Salah satu dari 6 sub-kategori
        - confidence_lvl1  : Skor probabilitas Level 1 (0.0 – 1.0)
        - confidence_lvl2  : Skor probabilitas Level 2 (0.0 – 1.0)

Alur Inferensi:
    Teks bersih (content)
      ↓ AutoTokenizer (max_length=128, padding, truncation)
      ↓ IndoBERTHierarchicalClassifier.forward()
      ↓ Softmax → Probabilitas per kelas
      ↓ argmax → Label prediksi + confidence score
    Output: dict / DataFrame dengan kolom label & confidence

Kolom DataFrame Standar:
    Input  : ['platform', 'source', 'user_id', 'type', 'date', 'content']
    Output : ['platform', 'source', 'user_id', 'type', 'date', 'content',
              'label_lvl1', 'label_lvl2', 'confidence_lvl1', 'confidence_lvl2']

Class:
    - HateSpeechPredictor
        __init__(model_dir=None)
        predict_text(text: str) -> dict
        predict_batch(texts: list[str], batch_size: int = 32) -> list[dict]
        predict_dataframe(df, text_column, batch_size, show_progress) -> pd.DataFrame
"""

import logging
import math

import torch
import torch.nn.functional as F
import pandas as pd
from tqdm import tqdm

from .model_loader import ModelLoader

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Class HateSpeechPredictor
# ---------------------------------------------------------------------------

class HateSpeechPredictor:
    """
    Mesin prediksi ujaran kebencian hierarkis menggunakan IndoBERT.

    Menggunakan ModelLoader (singleton) untuk mengakses model & tokenizer
    yang sudah dimuat ke memori — sehingga tidak ada overhead re-load.

    Attributes:
        loader    (ModelLoader)   : Instance singleton model loader.
        model     (nn.Module)     : Model PyTorch dalam mode eval.
        tokenizer (AutoTokenizer) : Tokenizer IndoBERT.
        device    (str)           : 'cuda' atau 'cpu'.
    """

    def __init__(self, model_dir=None):
        """
        Inisialisasi predictor.

        Args:
            model_dir: Path ke folder saved_models/indobert_sentiment/.
                       Jika None, menggunakan path dari configs/config.py.
        """
        logger.info("Menginisialisasi HateSpeechPredictor...")
        self.loader    = ModelLoader(model_dir=model_dir)
        self.model     = self.loader.get_model()
        self.tokenizer = self.loader.get_tokenizer()
        self.device    = self.loader.get_device()

        self.classes_lvl1 = self.loader.classes_lvl1
        self.classes_lvl2 = self.loader.classes_lvl2
        self.max_length   = self.loader.max_length

        logger.info(
            f"HateSpeechPredictor siap. "
            f"Device: {self.device.upper()}, "
            f"Level 1: {len(self.classes_lvl1)} kelas, "
            f"Level 2: {len(self.classes_lvl2)} kelas."
        )

    # ── Prediksi Teks Tunggal ─────────────────────────────────────────────

    def predict_text(self, text: str) -> dict:
        """
        Melakukan prediksi untuk satu kalimat teks.

        Args:
            text (str): Teks bersih hasil preprocessing.

        Returns:
            dict: {
                "text": str,
                "level1": {"label": str, "confidence": float, "probabilities": dict},
                "level2": {"label": str, "confidence": float, "probabilities": dict},
                "is_hate_speech": bool,
            }
        """
        results = self.predict_batch([text])
        return results[0] if results else {}

    # ── Prediksi Batch (List of Strings) ──────────────────────────────────

    def predict_batch(self, texts: list, batch_size: int = 32) -> list:
        """
        Melakukan prediksi untuk kumpulan kalimat teks secara batch.

        Args:
            texts      (list[str]): Daftar teks bersih.
            batch_size (int)      : Jumlah sampel per batch. Default: 32.

        Returns:
            list[dict]: Daftar hasil prediksi per teks.
        """
        if not texts:
            return []

        all_results = []
        num_batches = math.ceil(len(texts) / batch_size)

        for batch_idx in range(num_batches):
            start = batch_idx * batch_size
            end   = min(start + batch_size, len(texts))
            batch_texts = texts[start:end]

            # Tokenisasi
            encodings = self.tokenizer(
                batch_texts,
                max_length=self.max_length,
                padding=True,
                truncation=True,
                return_attention_mask=True,
                return_token_type_ids=False,
                return_tensors="pt",
            )

            input_ids      = encodings["input_ids"].to(self.device)
            attention_mask = encodings["attention_mask"].to(self.device)

            # Forward pass (no gradient)
            with torch.no_grad():
                logits_lvl1, logits_lvl2 = self.model(input_ids, attention_mask)
                probs_lvl1 = F.softmax(logits_lvl1, dim=-1).cpu().numpy()
                probs_lvl2 = F.softmax(logits_lvl2, dim=-1).cpu().numpy()

            # Parse results
            for i, text in enumerate(batch_texts):
                p1 = probs_lvl1[i]
                p2 = probs_lvl2[i]

                pred_idx1 = int(p1.argmax())
                pred_idx2 = int(p2.argmax())

                label1 = self.classes_lvl1[pred_idx1]
                label2 = self.classes_lvl2[pred_idx2]

                _TIDAK_RELEVAN = "tidak_relevan"

                # ── Hierarchical Constraint (Two-Way Gating) ───────────────
                if label1 != "hate_speech":
                    # KASUS 1: Jika Level 1 adalah NON-HATE SPEECH, maka secara
                    # taksonomi hierarki Level 2 otomatis "tidak_relevan".
                    label2 = _TIDAK_RELEVAN
                    if _TIDAK_RELEVAN in self.classes_lvl2:
                        pred_idx2 = self.classes_lvl2.index(_TIDAK_RELEVAN)
                    confidence1 = float(p1[pred_idx1])
                    confidence2 = confidence1  # Keyakinan tidak relevan identik dengan keyakinan non-hate
                    prob_dist_lvl2 = {
                        cls: (1.0 if cls == _TIDAK_RELEVAN else 0.0)
                        for cls in self.classes_lvl2
                    }
                else:
                    # KASUS 2: Jika Level 1 adalah HATE SPEECH, maka Level 2
                    # tidak boleh "tidak_relevan". Jika argmax menghasilkan
                    # "tidak_relevan", pilih sub-kategori kebencian dengan probabilitas tertinggi.
                    if label2 == _TIDAK_RELEVAN:
                        candidate_indices = [
                            idx for idx, cls in enumerate(self.classes_lvl2)
                            if cls != _TIDAK_RELEVAN
                        ]
                        if candidate_indices:
                            best_idx = max(candidate_indices, key=lambda idx: p2[idx])
                            pred_idx2 = best_idx
                            label2 = self.classes_lvl2[pred_idx2]
                            logger.debug(
                                f"Hierarchical constraint applied: L1=hate_speech, "
                                f"L2 forced from 'tidak_relevan' → '{label2}' "
                                f"(p={p2[pred_idx2]:.4f})"
                            )
                    confidence1 = float(p1[pred_idx1])
                    confidence2 = float(p2[pred_idx2])
                    prob_dist_lvl2 = {
                        cls: round(float(p2[idx]), 4)
                        for idx, cls in enumerate(self.classes_lvl2)
                    }

                confidence1 = float(p1[pred_idx1])
                prob_dist_lvl1 = {
                    cls: round(float(p1[idx]), 4)
                    for idx, cls in enumerate(self.classes_lvl1)
                }

                all_results.append({
                    "text": text,
                    "level1": {
                        "label": label1,
                        "confidence": round(confidence1, 4),
                        "probabilities": prob_dist_lvl1,
                    },
                    "level2": {
                        "label": label2,
                        "confidence": round(confidence2, 4),
                        "probabilities": prob_dist_lvl2,
                    },
                    "is_hate_speech": (label1 == "hate_speech"),
                })

        return all_results

    # ── Prediksi DataFrame ────────────────────────────────────────────────

    def predict_dataframe(
        self,
        df: pd.DataFrame,
        text_column: str = "content",
        batch_size: int = 32,
        show_progress: bool = True,
    ) -> pd.DataFrame:
        """
        Melakukan prediksi pada seluruh DataFrame hasil preprocessing.

        Menambahkan 4 kolom baru:
            - label_lvl1       : Label prediksi Level 1 (hate_speech / non_hate_speech)
            - label_lvl2       : Label prediksi Level 2 (6 sub-kategori)
            - confidence_lvl1  : Skor keyakinan Level 1 (0.0 – 1.0)
            - confidence_lvl2  : Skor keyakinan Level 2 (0.0 – 1.0)

        Args:
            df            (pd.DataFrame): DataFrame hasil preprocessing.
            text_column   (str)         : Nama kolom teks. Default: 'content'.
            batch_size    (int)         : Jumlah sampel per batch. Default: 32.
            show_progress (bool)        : Tampilkan progress bar. Default: True.

        Returns:
            pd.DataFrame: DataFrame dengan 4 kolom klasifikasi tambahan.
        """
        if df.empty:
            logger.warning("predict_dataframe: DataFrame kosong.")
            return df

        if text_column not in df.columns:
            raise ValueError(
                f"Kolom '{text_column}' tidak ditemukan. "
                f"Kolom yang tersedia: {list(df.columns)}"
            )

        df = df.copy()
        texts = df[text_column].fillna("").astype(str).tolist()
        total = len(texts)

        logger.info(f"Memulai klasifikasi {total:,} baris teks (batch_size={batch_size})...")

        # Preallocate result columns
        labels_lvl1       = []
        labels_lvl2       = []
        confidences_lvl1  = []
        confidences_lvl2  = []

        num_batches = math.ceil(total / batch_size)
        iterator = range(num_batches)

        if show_progress:
            iterator = tqdm(
                iterator,
                desc="  Klasifikasi",
                unit="batch",
                total=num_batches,
                colour="magenta",
            )

        for batch_idx in iterator:
            start = batch_idx * batch_size
            end   = min(start + batch_size, total)
            batch_texts = texts[start:end]

            batch_results = self.predict_batch(batch_texts, batch_size=len(batch_texts))

            for res in batch_results:
                labels_lvl1.append(res["level1"]["label"])
                labels_lvl2.append(res["level2"]["label"])
                confidences_lvl1.append(res["level1"]["confidence"])
                confidences_lvl2.append(res["level2"]["confidence"])

        df["label_lvl1"]      = labels_lvl1
        df["label_lvl2"]      = labels_lvl2
        df["confidence_lvl1"] = confidences_lvl1
        df["confidence_lvl2"] = confidences_lvl2

        # Statistik ringkas
        hate_count    = sum(1 for l in labels_lvl1 if l == "hate_speech")
        nonhate_count = total - hate_count

        logger.info(
            f"Klasifikasi selesai. {total:,} baris diproses. "
            f"Hate Speech: {hate_count:,} ({hate_count/total*100:.1f}%), "
            f"Non-Hate: {nonhate_count:,} ({nonhate_count/total*100:.1f}%)"
        )

        return df

    def __repr__(self) -> str:
        return (
            f"HateSpeechPredictor("
            f"device={self.device}, "
            f"lvl1={len(self.classes_lvl1)} classes, "
            f"lvl2={len(self.classes_lvl2)} classes)"
        )
