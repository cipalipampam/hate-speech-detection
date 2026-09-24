"""
Verifikasi pemisahan TEKS MENTAH vs TEKS BERSIH pada EndToEndPipeline.

Latar belakang bug:
    `transform_dataframe()` dipanggil tanpa `overwrite_content=False`, sehingga kolom
    'content' (teks mentah) ditimpa hasil preprocessing. Akibatnya file ekspor CSV
    hanya berisi satu versi teks (yang sudah bersih) dan frontend menyimpan teks
    bersih itu pada kolom `analysis_classifications.raw_content` — teks mentah hilang.

Yang diuji skrip ini (tanpa memuat model IndoBERT 499MB — predictor di-stub):
    1. execute_preprocessing() TIDAK mengubah kolom 'content' (teks mentah utuh).
    2. Kolom baru 'clean_text' berisi hasil preprocessing (URL/emoji/@mention dibuang).
    3. execute_classification() mengirim teks BERSIH ke model, bukan teks mentah.
    4. Kedua kolom bertahan sampai akhir pipeline → ikut ke file ekspor CSV,
       sehingga frontend bisa memetakan content→raw_content, clean_text→clean_content.

Cara menjalankan (dari direktori backend):
    python -m tests.check_pipeline_text_columns
    python -m tests.check_pipeline_text_columns --report laporan.txt

Exit code 0 = lulus, 1 = gagal.
"""

import sys
from pathlib import Path

import pandas as pd

BACKEND_DIR = Path(__file__).resolve().parents[1]
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

from src.pipeline.end_to_end_pipeline import EndToEndPipeline  # noqa: E402

# Teks mentah sengaja memuat URL, emoji, mention, hashtag, dan huruf kapital.
RAW_TEXT = (
    "PENGUMUMAN!! Lihat https://x.com/akun/status/123 \U0001F621 "
    "@akun #HateSpeech KAMU SEMUA memang bodoh"
)


class StubPredictor:
    """
    Pengganti HateSpeechPredictor yang tidak memuat model IndoBERT.

    Tugasnya hanya mencatat kolom teks yang diterima dari execute_classification()
    dan menyuntikkan kolom label palsu (struktur kolom tetap seperti aslinya).
    """

    def __init__(self) -> None:
        self.received_text_column = None
        self.received_samples: list = []

    def predict_dataframe(
        self,
        df: pd.DataFrame,
        text_column: str = "content",
        batch_size: int = 32,
        show_progress: bool = True,
    ) -> pd.DataFrame:
        self.received_text_column = text_column
        self.received_samples = df[text_column].fillna("").astype(str).tolist()

        out = df.copy()
        out["label_lvl1"] = "hate_speech"
        out["label_lvl2"] = "dehumanisasi"
        out["confidence_lvl1"] = 0.99
        out["confidence_lvl2"] = 0.98
        return out


def preview(df: pd.DataFrame, column: str, width: int = 70) -> str:
    """Cuplikan aman-encoding sebuah kolom (tahan bila kolom tidak ada)."""
    if column not in df.columns:
        return "<KOLOM TIDAK ADA>"
    return ascii(str(df.loc[0, column])[:width])


def main() -> int:
    failures: list = []

    df_raw = pd.DataFrame(
        [
            {
                "platform": "X",
                "source": "https://x.com/akun/status/123",
                "user_id": "akun",
                "type": "Original Post",
                "date": "2026-09-17T13:08:22.000Z",
                "content": RAW_TEXT,
            }
        ]
    )

    stub = StubPredictor()
    pipeline = EndToEndPipeline(predictor=stub)

    # ── Tahap 1: Preprocessing ──────────────────────────────────────────────
    df_clean = pipeline.execute_preprocessing(df_raw)

    if len(df_clean) != 1:
        failures.append(f"jumlah baris berubah setelah preprocessing: {len(df_clean)} (harus 1)")

    # (1) teks mentah harus utuh
    if "content" not in df_clean.columns:
        failures.append("kolom 'content' hilang setelah preprocessing")
    elif df_clean.loc[0, "content"] != RAW_TEXT:
        failures.append(
            "kolom 'content' tertimpa hasil preprocessing (teks mentah harus tetap utuh)"
        )

    # (2) clean_text harus ada dan benar-benar bersih
    if "clean_text" not in df_clean.columns:
        failures.append("kolom 'clean_text' tidak dihasilkan oleh preprocessing")
    else:
        clean = str(df_clean.loc[0, "clean_text"])
        if clean == RAW_TEXT:
            failures.append("kolom 'clean_text' identik dengan teks mentah (tidak dibersihkan)")
        if "http" in clean:
            failures.append(f"URL masih tersisa di 'clean_text': {clean!r}")
        if clean != clean.lower():
            failures.append(f"case folding tidak diterapkan pada 'clean_text': {clean!r}")

    # ── Tahap 2: Klasifikasi ────────────────────────────────────────────────
    df_classified = pipeline.execute_classification(df_clean)

    # (3) model harus menerima teks bersih
    if stub.received_text_column != "clean_text":
        failures.append(
            f"execute_classification mengirim kolom '{stub.received_text_column}' ke model "
            "(harus 'clean_text' = teks bersih)"
        )
    if stub.received_samples and "http" in stub.received_samples[0]:
        failures.append("teks mentah (mengandung URL) ikut dikirim ke model IndoBERT")

    # (4) kedua kolom bertahan sampai akhir → ikut ke ekspor CSV
    for column in ("content", "clean_text", "label_lvl1", "label_lvl2"):
        if column not in df_classified.columns:
            failures.append(f"kolom '{column}' tidak ada di DataFrame akhir (hilang sebelum ekspor)")

    header = df_classified.to_csv(index=False).splitlines()[0]
    for column in ("content", "clean_text"):
        if column not in header.split(","):
            failures.append(f"header CSV ekspor tidak memuat kolom '{column}': {header}")

    # ── Laporan ─────────────────────────────────────────────────────────────
    # `ascii()` dipakai agar emoji/non-ASCII tidak memicu UnicodeEncodeError di
    # console Windows (cp1252) maupun saat output di-redirect ke file.
    report = [
        "=" * 72,
        "  VERIFIKASI PEMISAHAN TEKS MENTAH vs TEKS BERSIH",
        "=" * 72,
        f"  content    : {preview(df_classified, 'content')}",
        f"  clean_text : {preview(df_classified, 'clean_text')}",
        f"  kolom CSV  : {ascii(header)}",
        "-" * 72,
    ]

    if failures:
        report.append(f"  HASIL: GAGAL ({len(failures)} masalah)")
        report.extend(f"    - {item}" for item in failures)
        exit_code = 1
    else:
        report.append("  HASIL: LULUS — teks mentah & teks bersih tersimpan terpisah.")
        exit_code = 0

    output = "\n".join(report)
    print(output)

    # Tulis juga ke file (UTF-8) bila diminta: --report <path>
    if "--report" in sys.argv:
        idx = sys.argv.index("--report")
        if idx + 1 < len(sys.argv):
            Path(sys.argv[idx + 1]).write_text(output + "\n", encoding="utf-8")

    return exit_code


if __name__ == "__main__":
    sys.exit(main())
