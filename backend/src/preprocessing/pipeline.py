"""Preprocessing Pipeline: clean_text → case_folding → normalisasi slang (single, batch, DataFrame)."""

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
    """Jalankan clean_text → case_folding → normalize untuk satu teks."""
    text = clean_text(text)
    text = case_folding(text)
    text = normalizer.normalize(text)
    return text


# ---------------------------------------------------------------------------
# Class PreprocessingPipeline
# ---------------------------------------------------------------------------

class PreprocessingPipeline:
    """Orchestrator preprocessing terpadu: teks tunggal, batch, dan DataFrame."""

    def __init__(self, dictionary_path=None):
        """Muat kamus slang ke memori saat inisialisasi."""
        logger.info("Menginisialisasi PreprocessingPipeline...")
        self.normalizer = SlangNormalizer(dictionary_path=dictionary_path)
        logger.info(
            f"PreprocessingPipeline siap. "
            f"Kamus: {self.normalizer.dictionary_size:,} entri."
        )

    def transform_text(self, text: str) -> str:
        """Preprocess satu teks mentah."""
        return preprocess_text(text, self.normalizer)

    def transform_batch(self, texts: list) -> list:
        """Preprocess daftar teks; urutan hasil sama dengan urutan input."""
        return [self.transform_text(t) for t in texts]

    def transform_dataframe(
        self,
        df: pd.DataFrame,
        text_column: str = "content",
        overwrite_content: bool = True,
        show_progress: bool = True,
    ) -> pd.DataFrame:
        """Preprocess seluruh DataFrame; `overwrite_content=False` menyimpan hasil di kolom 'clean_text'."""
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
