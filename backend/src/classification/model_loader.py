"""
Model Loader Service — Modul 4: Klasifikasi IndoBERT.

Deskripsi:
    Modul khusus untuk memuat arsitektur IndoBERT Multi-Head Hierarchical Classifier,
    tokenizer, dan bobot (weights) hasil fine-tuning dari Google Colab.

    Arsitektur model (berasal dari core/model.py):
        IndoBERT (indobenchmark/indobert-base-p1) → [CLS] → Dropout
            ├── Head Level 1 → 2 kelas (hate_speech, non_hate_speech)
            └── Head Level 2 → 6 kelas sub-kategori

    File artefak yang dibutuhkan di `saved_models/indobert_sentiment/`:
        - best_model.pt        : Bobot (weights) model PyTorch hasil fine-tuning (~490 MB).
        - label_mapping.json   : Pemetaan indeks → nama label Level 1 & Level 2.
        - model_config.json    : Metadata arsitektur (pretrained_model, max_length, num_classes, dll).

Strategi:
    - Singleton Pattern: Model PyTorch hanya di-load 1× ke memori (RAM / VRAM GPU).
    - Device Auto-Detect: CUDA (NVIDIA GPU) → CPU.
    - Model diset ke mode evaluasi (`model.eval()`), gradien dinonaktifkan.

Class:
    - ClassificationHead(nn.Module)
        Head klasifikasi: Linear → LayerNorm → GELU → Dropout → Linear.

    - IndoBERTHierarchicalClassifier(nn.Module)
        Model utama Multi-Task dengan 2 head (Level 1 & Level 2).

    - ModelLoader
        Singleton loader untuk memuat model + tokenizer + label mapping.
        __init__(model_dir) → load semua artefak.
        get_model()         → Instance model PyTorch (sudah .eval()).
        get_tokenizer()     → Instance AutoTokenizer.
        get_label_mapping() → Dict mapping indeks → nama label.
        get_device()        → String device ('cuda' / 'cpu').
"""

import json
import logging
from pathlib import Path

import torch
import torch.nn as nn
from transformers import AutoModel, AutoTokenizer

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Konstanta Default
# ---------------------------------------------------------------------------

# Default label classes (fallback jika label_mapping.json tidak ditemukan)
_DEFAULT_CLASSES_LVL1 = ["non_hate_speech", "hate_speech"]
_DEFAULT_CLASSES_LVL2 = [
    "tidak_relevan",
    "delegitimasi_institusi",
    "dehumanisasi",
    "ajakan_kekerasan",
    "hoax_pemicu_kebencian",
    "kutukan_agama_personal",
]

_DEFAULT_PRETRAINED_MODEL = "indobenchmark/indobert-base-p1"
_DEFAULT_MAX_LENGTH       = 128
_DEFAULT_DROPOUT_RATE     = 0.3


# ---------------------------------------------------------------------------
# Arsitektur Model (Porting dari core/model.py)
# ---------------------------------------------------------------------------

class ClassificationHead(nn.Module):
    """
    Classification Head: Linear → LayerNorm → GELU → Dropout → Linear.

    Digunakan sebagai head klasifikasi di atas representasi [CLS] IndoBERT.
    Terdapat 2 instance: satu untuk Level 1 (2 kelas), satu untuk Level 2 (6 kelas).
    """

    def __init__(
        self,
        in_features : int,
        hidden_size : int,
        num_classes : int,
        dropout_rate: float = _DEFAULT_DROPOUT_RATE,
    ):
        super().__init__()
        self.head = nn.Sequential(
            nn.Linear(in_features, hidden_size),
            nn.LayerNorm(hidden_size),
            nn.GELU(),
            nn.Dropout(dropout_rate),
            nn.Linear(hidden_size, num_classes),
        )

    def forward(self, x: torch.Tensor) -> torch.Tensor:
        return self.head(x)


class IndoBERTHierarchicalClassifier(nn.Module):
    """
    Arsitektur Multi-Task IndoBERT untuk Klasifikasi Ujaran Kebencian Hierarkis.

    Forward Pass:
        Text → IndoBERT → [CLS] token → Dropout
                                         ├── Head Lvl1 → Logits Level 1 (2 kelas)
                                         └── Head Lvl2 → Logits Level 2 (6 kelas)
    """

    def __init__(
        self,
        pretrained_model: str   = _DEFAULT_PRETRAINED_MODEL,
        num_classes_lvl1: int   = 2,
        num_classes_lvl2: int   = 6,
        dropout_rate    : float = _DEFAULT_DROPOUT_RATE,
    ):
        super().__init__()

        self.pretrained_model = pretrained_model
        self.num_classes_lvl1 = num_classes_lvl1
        self.num_classes_lvl2 = num_classes_lvl2

        # Backbone IndoBERT
        self.bert = AutoModel.from_pretrained(pretrained_model)
        hidden_size = self.bert.config.hidden_size  # 768 untuk indobert-base

        # Dropout Pooler
        self.dropout = nn.Dropout(dropout_rate)

        # Classification Heads
        self.head_lvl1 = ClassificationHead(
            in_features=hidden_size,
            hidden_size=256,
            num_classes=num_classes_lvl1,
            dropout_rate=dropout_rate,
        )
        self.head_lvl2 = ClassificationHead(
            in_features=hidden_size,
            hidden_size=256,
            num_classes=num_classes_lvl2,
            dropout_rate=dropout_rate,
        )

    def forward(
        self,
        input_ids     : torch.Tensor,
        attention_mask: torch.Tensor,
    ):
        """
        Returns:
            logits_lvl1 : Tensor (batch_size, num_classes_lvl1)
            logits_lvl2 : Tensor (batch_size, num_classes_lvl2)
        """
        outputs = self.bert(
            input_ids=input_ids,
            attention_mask=attention_mask,
        )

        # Ambil representasi vektor dari token pertama [CLS]
        cls_output = outputs.last_hidden_state[:, 0, :]   # [batch_size, 768]
        cls_output = self.dropout(cls_output)

        logits_lvl1 = self.head_lvl1(cls_output)          # [batch_size, 2]
        logits_lvl2 = self.head_lvl2(cls_output)          # [batch_size, 6]

        return logits_lvl1, logits_lvl2


# ---------------------------------------------------------------------------
# Singleton Model Loader
# ---------------------------------------------------------------------------

class ModelLoader:
    """
    Singleton loader untuk memuat model IndoBERT + Tokenizer + Label Mapping.

    Model hanya dimuat 1 kali ke memori. Pemanggilan berulang mengembalikan
    instance yang sama tanpa re-loading file ~490 MB.

    Args:
        model_dir: Path ke folder yang berisi best_model.pt, label_mapping.json,
                   dan model_config.json. Default: configs.config.MODEL_DIR.

    Attributes:
        model      (IndoBERTHierarchicalClassifier): Model PyTorch dalam mode eval.
        tokenizer  (AutoTokenizer)                 : Tokenizer IndoBERT.
        device     (str)                           : 'cuda' atau 'cpu'.
        classes_lvl1 (list[str])                   : Nama label Level 1.
        classes_lvl2 (list[str])                   : Nama label Level 2.
        max_length   (int)                         : Panjang token maksimum.
    """

    _instance = None

    def __new__(cls, *args, **kwargs):
        """Singleton: hanya satu instance yang pernah dibuat."""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            cls._instance._initialized = False
        return cls._instance

    def __init__(self, model_dir=None):
        if self._initialized:
            return

        # Resolusi path
        if model_dir is None:
            try:
                from configs.config import MODEL_DIR
                self._model_dir = Path(MODEL_DIR)
            except ImportError:
                self._model_dir = Path(__file__).resolve().parents[2] / "saved_models" / "indobert_sentiment"
        else:
            self._model_dir = Path(model_dir)

        # Cek ketersediaan file bobot terlebih dahulu
        weights_path = self._model_dir / "best_model.pt"
        if not weights_path.exists():
            raise FileNotFoundError(
                f"File bobot model tidak ditemukan: {weights_path}\n"
                f"Pastikan file 'best_model.pt' hasil training dari Google Colab "
                f"sudah diekstrak ke folder: {self._model_dir}"
            )

        # Auto-detect device
        self.device = "cuda" if torch.cuda.is_available() else "cpu"

        # 1. Load metadata & label mapping
        self._load_config()

        # 2. Load Tokenizer
        self._load_tokenizer()

        # 3. Load Model & Weights
        self._load_model()

        self._initialized = True

    # ── Private Loaders ───────────────────────────────────────────────────

    def _load_config(self):
        """Memuat model_config.json dan label_mapping.json."""
        config_path  = self._model_dir / "model_config.json"
        mapping_path = self._model_dir / "label_mapping.json"

        # model_config.json
        if config_path.exists():
            with open(config_path, "r", encoding="utf-8") as f:
                meta = json.load(f)
            self.pretrained_model = meta.get("pretrained_model", _DEFAULT_PRETRAINED_MODEL)
            self.max_length       = meta.get("max_length", _DEFAULT_MAX_LENGTH)
            self.num_classes_lvl1 = meta.get("num_classes_lvl1", len(_DEFAULT_CLASSES_LVL1))
            self.num_classes_lvl2 = meta.get("num_classes_lvl2", len(_DEFAULT_CLASSES_LVL2))
            self.dropout_rate     = meta.get("dropout_rate", _DEFAULT_DROPOUT_RATE)
            self.classes_lvl1     = meta.get("classes_lvl1", _DEFAULT_CLASSES_LVL1)
            self.classes_lvl2     = meta.get("classes_lvl2", _DEFAULT_CLASSES_LVL2)
            logger.info(f"Config dimuat dari: {config_path.name}")
        else:
            self.pretrained_model = _DEFAULT_PRETRAINED_MODEL
            self.max_length       = _DEFAULT_MAX_LENGTH
            self.num_classes_lvl1 = len(_DEFAULT_CLASSES_LVL1)
            self.num_classes_lvl2 = len(_DEFAULT_CLASSES_LVL2)
            self.dropout_rate     = _DEFAULT_DROPOUT_RATE
            self.classes_lvl1     = _DEFAULT_CLASSES_LVL1
            self.classes_lvl2     = _DEFAULT_CLASSES_LVL2
            logger.warning(f"model_config.json tidak ditemukan di {self._model_dir}, menggunakan default.")

        # label_mapping.json (override classes jika tersedia)
        if mapping_path.exists():
            with open(mapping_path, "r", encoding="utf-8") as f:
                mappings = json.load(f)
            self.classes_lvl1 = mappings.get("classes_lvl1", self.classes_lvl1)
            self.classes_lvl2 = mappings.get("classes_lvl2", self.classes_lvl2)
            logger.info(f"Label mapping dimuat dari: {mapping_path.name}")

        logger.info(
            f"Konfigurasi model: pretrained={self.pretrained_model}, "
            f"max_length={self.max_length}, lvl1={self.num_classes_lvl1} kelas, "
            f"lvl2={self.num_classes_lvl2} kelas, device={self.device}"
        )

    def _load_tokenizer(self):
        """Memuat AutoTokenizer dari pretrained model."""
        logger.info(f"Memuat tokenizer: {self.pretrained_model}...")
        self.tokenizer = AutoTokenizer.from_pretrained(self.pretrained_model)
        logger.info("Tokenizer berhasil dimuat.")

    def _load_model(self):
        """Memuat arsitektur model dan bobot dari best_model.pt."""
        weights_path = self._model_dir / "best_model.pt"

        if not weights_path.exists():
            raise FileNotFoundError(
                f"File bobot model tidak ditemukan: {weights_path}\n"
                f"Pastikan file 'best_model.pt' hasil training dari Google Colab "
                f"sudah diekstrak ke folder: {self._model_dir}"
            )

        logger.info(f"Memuat arsitektur IndoBERTHierarchicalClassifier...")
        self.model = IndoBERTHierarchicalClassifier(
            pretrained_model=self.pretrained_model,
            num_classes_lvl1=self.num_classes_lvl1,
            num_classes_lvl2=self.num_classes_lvl2,
            dropout_rate=self.dropout_rate,
        ).to(self.device)

        logger.info(f"Memuat bobot model dari: {weights_path.name} ({weights_path.stat().st_size / 1024 / 1024:.1f} MB)...")
        self.model.load_state_dict(
            torch.load(weights_path, map_location=self.device, weights_only=True)
        )
        self.model.eval()

        # Hitung parameter
        total_params     = sum(p.numel() for p in self.model.parameters())
        trainable_params = sum(p.numel() for p in self.model.parameters() if p.requires_grad)

        logger.info(
            f"✅ Model berhasil dimuat pada device: {self.device.upper()} "
            f"(Total params: {total_params:,}, Trainable: {trainable_params:,})"
        )

    # ── Public Accessors ─────────────────────────────────────────────────

    def get_model(self) -> IndoBERTHierarchicalClassifier:
        """Mengembalikan instance model PyTorch (sudah .eval())."""
        return self.model

    def get_tokenizer(self) -> AutoTokenizer:
        """Mengembalikan instance AutoTokenizer."""
        return self.tokenizer

    def get_device(self) -> str:
        """Mengembalikan string device ('cuda' / 'cpu')."""
        return self.device

    def get_label_mapping(self) -> dict:
        """Mengembalikan dictionary label mapping untuk Level 1 & Level 2."""
        return {
            "classes_lvl1": self.classes_lvl1,
            "classes_lvl2": self.classes_lvl2,
        }

    def is_loaded(self) -> bool:
        """Cek apakah model sudah berhasil dimuat."""
        return self._initialized and hasattr(self, "model")

    @classmethod
    def reset(cls):
        """Reset singleton (untuk testing). Memaksa re-load pada pemanggilan berikutnya."""
        cls._instance = None

    def __repr__(self) -> str:
        if self._initialized:
            return (
                f"ModelLoader(model={self.pretrained_model}, "
                f"device={self.device}, "
                f"lvl1={self.num_classes_lvl1} classes, "
                f"lvl2={self.num_classes_lvl2} classes)"
            )
        return "ModelLoader(not initialized)"
