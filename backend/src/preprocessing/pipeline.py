"""
Preprocessing Pipeline Orchestrator — Modul 3: Preprocessing & Normalisasi Teks.

Deskripsi:
    Menggabungkan seluruh proses preprocessing (Cleaning + Case Folding +
    Normalisasi Slang) menjadi satu antarmuka yang siap digunakan oleh
    service lain, main_cli, atau proses batch DataFrame.

Alur Pipeline (per teks):
    RAW TEXT
      ↓ clean_text()        — Hapus URL, @mention, #symbol, RT, emoji, non-ASCII
      ↓ case_folding()      — Huruf kecil semua
      ↓ normalizer.normalize() — Ganti kata slang → kata baku (kamusalay)
    CLEAN TEXT (siap untuk IndoBERT Tokenizer)

Kolom DataFrame Standar:
    Input  : ['platform', 'source', 'user_id', 'type', 'date', 'content']
    Output : ['platform', 'source', 'user_id', 'type', 'date', 'content'] (content berisi teks bersih hasil preprocessing)

Fungsi / Class:
    - preprocess_text(text: str, normalizer: SlangNormalizer) -> str
        Preprocess satu kalimat.

    - class PreprocessingPipeline
        - __init__(dictionary_path = None)
            Inisialisasi dengan memuat SlangNormalizer ke memori.
        - transform_text(text: str) -> str
            Preprocess satu teks.
        - transform_dataframe(df: pd.DataFrame, text_column: str = 'content') -> pd.DataFrame
            Preprocess seluruh DataFrame hasil scraping.
            Melakukan deduplikasi, lalu tambahkan kolom 'clean_text'.
        - transform_batch(texts: list) -> list
            Preprocess daftar teks secara batch.

Input:
    - Raw text (Teks kotor dari hasil scraping atau input user).

Output:
    - Clean text terstandarisasi siap dikonsumsi IndoBERT Tokenizer.
"""

import logging
import pandas as pd
from tqdm import tqdm

from .cleaner    import clean_text, case_folding, remove_duplicates
from .normalizer import SlangNormalizer

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Fungsi Tunggal
# ---------------------------------------------------------------------------

def preprocess_text(text: str, normalizer: SlangNormalizer) -> str:
    """
    Menjalankan pipeline preprocessing lengkap pada satu string teks.

    Alur:
        clean_text → case_folding → normalizer.normalize

    Args:
        text       (str)            : Teks mentah dari scraper.
        normalizer (SlangNormalizer): Instance normalizer yang sudah dimuat.

    Returns:
        str: Teks bersih siap untuk tokenisasi IndoBERT.
    """
    text = clean_text(text)
    text = case_folding(text)
    text = normalizer.normalize(text)
    return text


# ---------------------------------------------------------------------------
# Class PreprocessingPipeline
# ---------------------------------------------------------------------------

class PreprocessingPipeline:
    """
    Orchestrator pipeline preprocessing teks media sosial (X & Threads).

    Mengelola lifecycle SlangNormalizer (lazy load sekali saat inisialisasi)
    dan menyediakan antarmuka terpadu untuk preprocessing single, batch,
    maupun DataFrame.

    Attributes:
        normalizer (SlangNormalizer): Instance normalizer yang sudah dimuat.
    """

    def __init__(self, dictionary_path=None):
        """
        Inisialisasi pipeline & muat kamus slang ke memori.

        Args:
            dictionary_path: Path ke kamusalay.csv.
                             Jika None, menggunakan path dari configs/config.py.
        """
        logger.info("Menginisialisasi PreprocessingPipeline...")
        self.normalizer = SlangNormalizer(dictionary_path=dictionary_path)
        logger.info(
            f"PreprocessingPipeline siap. "
            f"Kamus: {self.normalizer.dictionary_size:,} entri."
        )

    def transform_text(self, text: str) -> str:
        """
        Preprocess satu string teks.

        Args:
            text (str): Teks mentah.

        Returns:
            str: Teks bersih.
        """
        return preprocess_text(text, self.normalizer)

    def transform_batch(self, texts: list) -> list:
        """
        Preprocess daftar teks secara batch.

        Args:
            texts (list[str]): Daftar teks mentah.

        Returns:
            list[str]: Daftar teks bersih dengan urutan yang sama.
        """
        return [self.transform_text(t) for t in texts]

    def transform_dataframe(
        self,
        df: pd.DataFrame,
        text_column: str = "content",
        overwrite_content: bool = True,
        show_progress: bool = True,
    ) -> pd.DataFrame:
        """
        Preprocess seluruh DataFrame hasil scraping.

        Tahapan:
            1. Salin DataFrame (tidak memodifikasi original in-place).
            2. Deduplikasi berdasarkan ['user_id', 'content'].
            3. Apply pipeline preprocessing ke kolom `text_column`.
            4. Jika `overwrite_content=True` (default):
               - Kolom `content` langsung diganti dengan teks hasil preprocessing.
               - Struktur kolom tetap sama persis: [platform, source/source_thread, user_id, type, date, content].
               Jika `overwrite_content=False`:
               - Teks bersih disimpan di kolom baru 'clean_text'.
            5. Hapus baris yang menghasilkan teks kosong setelah preprocessing.

        Args:
            df                (pd.DataFrame): DataFrame hasil scraping.
            text_column       (str)         : Nama kolom teks mentah. Default: 'content'.
            overwrite_content (bool)        : Jika True, timpa kolom `text_column` dengan hasil preprocessing.
                                              Jika False, buat kolom baru 'clean_text'. Default: True.
            show_progress     (bool)        : Tampilkan progress bar tqdm. Default: True.

        Returns:
            pd.DataFrame: DataFrame dengan teks yang telah dibersihkan & dinormalisasi.
        """
        if df.empty:
            logger.warning("transform_dataframe: DataFrame kosong, tidak ada yang diproses.")
            return df

        df = df.copy()

        # 1. Deduplikasi
        df = remove_duplicates(df)

        # 2. Pastikan kolom teks ada
        if text_column not in df.columns:
            raise ValueError(
                f"transform_dataframe: Kolom '{text_column}' tidak ditemukan di DataFrame. "
                f"Kolom yang tersedia: {list(df.columns)}"
            )

        # 3. Apply preprocessing dengan progress bar opsional
        total = len(df)
        logger.info(f"Memulai preprocessing {total:,} baris teks (kolom: '{text_column}')...")

        if show_progress:
            tqdm.pandas(desc="  Preprocessing", unit="row", colour="cyan")
            cleaned_series = df[text_column].progress_apply(self.transform_text)
        else:
            cleaned_series = df[text_column].apply(self.transform_text)

        target_col = text_column if overwrite_content else "clean_text"
        df[target_col] = cleaned_series

        # 4. Hapus baris dengan teks kosong setelah preprocessing
        before_filter = len(df)
        df = df[df[target_col].str.strip() != ""].reset_index(drop=True)
        after_filter = len(df)

        if before_filter != after_filter:
            logger.info(
                f"Filter teks kosong: {before_filter - after_filter} baris dibuang "
                f"({before_filter} → {after_filter})."
            )

        # 5. Susun urutan kolom agar konsisten dengan standar
        standard_cols = ["platform", "source", "source_thread", "user_id", "type", "date", "content"]
        ordered_cols = [c for c in standard_cols if c in df.columns] + [
            c for c in df.columns if c not in standard_cols
        ]
        df = df[ordered_cols]

        logger.info(f"Preprocessing selesai. {after_filter:,} baris teks bersih dihasilkan.")
        return df

    def __repr__(self) -> str:
        return (
            f"PreprocessingPipeline("
            f"normalizer={self.normalizer!r})"
        )
