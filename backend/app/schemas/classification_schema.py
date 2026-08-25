"""
Pydantic Schema untuk Modul Klasifikasi IndoBERT & Pipeline API.

Model Klasifikasi:
    - ClassifySingleRequest    : Body request POST /api/v1/classify/single
    - ClassifyBatchRequest     : Body request POST /api/v1/classify/batch
    - LevelPrediction          : Hasil prediksi satu level (label + confidence + probabilities)
    - ClassifyItemResponse     : Hasil klasifikasi satu teks (level1 + level2 + is_hate_speech)
    - ClassifyBatchResponse    : Hasil klasifikasi batch
    - ModelInfoResponse        : Info model (GET /api/v1/classify/info)

Model Pipeline:
    - PipelineRunRequest       : Body request POST /api/v1/pipeline/run
    - PipelineJobResponse      : Response 202 Accepted saat pipeline job dibuat
    - Level2Breakdown          : Detail per sub-kategori
    - PlatformBreakdown        : Detail per platform
    - PipelineStatistics       : Ringkasan statistik hasil analisis
    - PipelineJobStatusResponse: Response polling GET /api/v1/pipeline/status/{job_id}
    - ExportFileItem           : Satu file CSV di storage/exports/
    - ExportListResponse       : Daftar file CSV tersedia
"""

from typing import Dict, List, Literal, Optional
from pydantic import BaseModel, Field


# ===========================================================================
# KLASIFIKASI INDOBERT
# ===========================================================================

# ---------------------------------------------------------------------------
# Request Models
# ---------------------------------------------------------------------------

class ClassifySingleRequest(BaseModel):
    """Request prediksi untuk satu kalimat teks."""

    text: str = Field(
        ...,
        min_length=1,
        description="Teks yang akan diklasifikasikan (bisa raw atau teks bersih).",
    )
    preprocess: bool = Field(
        default=True,
        description=(
            "Jika True, jalankan preprocessing (clean + normalize) sebelum inferensi. "
            "Set False jika teks sudah bersih."
        ),
    )

    class Config:
        json_schema_extra = {
            "example": {
                "text": "bunuh saja semua kafir yang ada di sini, mereka tidak layak hidup",
                "preprocess": True,
            }
        }


class ClassifyBatchRequest(BaseModel):
    """Request prediksi untuk kumpulan teks secara batch."""

    texts: List[str] = Field(
        ...,
        min_length=1,
        description="Daftar teks yang akan diklasifikasikan.",
    )
    preprocess: bool = Field(
        default=True,
        description="Jika True, jalankan preprocessing pada setiap teks sebelum inferensi.",
    )

    class Config:
        json_schema_extra = {
            "example": {
                "texts": [
                    "bunuh saja mereka semua!",
                    "saya setuju dengan kebijakan ini, sangat baik",
                    "biasa aja sih menurut saya",
                ],
                "preprocess": True,
            }
        }


# ---------------------------------------------------------------------------
# Response Models — Klasifikasi
# ---------------------------------------------------------------------------

class LevelPrediction(BaseModel):
    """Hasil prediksi untuk satu level klasifikasi."""

    label: str = Field(description="Label hasil prediksi.")
    confidence: float = Field(
        ge=0.0, le=1.0,
        description="Skor keyakinan model (0.0–1.0).",
    )
    probabilities: Dict[str, float] = Field(
        description="Distribusi probabilitas untuk semua kelas pada level ini.",
    )


class ClassifyItemResponse(BaseModel):
    """Hasil klasifikasi hierarkis satu teks dari IndoBERT."""

    text: str = Field(description="Teks input yang diklasifikasikan.")
    level1: LevelPrediction = Field(
        description="Prediksi Level 1: 'hate_speech' atau 'non_hate_speech'."
    )
    level2: LevelPrediction = Field(
        description=(
            "Prediksi Level 2 sub-kategori: "
            "tidak_relevan / delegitimasi_institusi / dehumanisasi / "
            "ajakan_kekerasan / hoax_pemicu_kebencian / kutukan_agama_personal."
        )
    )
    is_hate_speech: bool = Field(
        description="True jika Level 1 diprediksi sebagai 'hate_speech'."
    )


class ClassifyBatchResponse(BaseModel):
    """Hasil klasifikasi batch teks."""

    total: int = Field(description="Jumlah teks yang diklasifikasikan.")
    hate_speech_count: int = Field(description="Jumlah teks yang terdeteksi sebagai hate speech.")
    non_hate_speech_count: int = Field(description="Jumlah teks yang terdeteksi sebagai non-hate speech.")
    hate_speech_pct: float = Field(description="Persentase hate speech dari total teks (%).")
    data: List[ClassifyItemResponse] = Field(
        description="Daftar hasil klasifikasi per teks, sesuai urutan input."
    )


class ModelInfoResponse(BaseModel):
    """Informasi model IndoBERT yang sedang aktif (GET /api/v1/classify/info)."""

    model_name: str = Field(description="Nama pretrained model IndoBERT.")
    device: str = Field(description="Device inferensi: 'cuda' atau 'cpu'.")
    max_length: int = Field(description="Panjang token maksimum untuk tokenizer.")
    is_loaded: bool = Field(description="Apakah model sudah berhasil dimuat ke memori.")
    classes_lvl1: List[str] = Field(description="Daftar label kelas Level 1.")
    classes_lvl2: List[str] = Field(description="Daftar label kelas Level 2 (sub-kategori).")


# ===========================================================================
# PIPELINE END-TO-END
# ===========================================================================

# ---------------------------------------------------------------------------
# Request Models
# ---------------------------------------------------------------------------

class PipelineRunRequest(BaseModel):
    """Parameter request untuk menjalankan pipeline analisis penuh (end-to-end)."""

    keywords: List[str] = Field(
        ...,
        min_length=1,
        description="Daftar kata kunci pencarian. Minimal 1 keyword.",
    )
    platform: Literal["x", "threads", "both"] = Field(
        default="both",
        description="Platform yang akan di-scrape.",
    )
    search_mode: Literal["latest", "top"] = Field(
        default="latest",
        description="Mode pencarian scraper.",
    )
    max_links: int = Field(
        default=50, ge=1, le=500,
        description="Maksimum URL postingan yang dikumpulkan.",
    )
    max_scroll_steps: int = Field(
        default=300, ge=50, le=5000,
        description="Maksimum langkah scroll per postingan.",
    )
    headless: bool = Field(
        default=False,
        description="Jalankan browser headless (tanpa GUI).",
    )
    export_csv: bool = Field(
        default=True,
        description="Simpan hasil analisis ke file CSV di storage/exports/.",
    )

    class Config:
        json_schema_extra = {
            "example": {
                "keywords": ["RUU Polri", "tolak polisi"],
                "platform": "both",
                "search_mode": "latest",
                "max_links": 50,
                "max_scroll_steps": 300,
                "headless": False,
                "export_csv": True,
            }
        }


# ---------------------------------------------------------------------------
# Response Models — Pipeline
# ---------------------------------------------------------------------------

class PipelineJobResponse(BaseModel):
    """Response segera (202 Accepted) saat pipeline job berhasil dibuat."""

    job_id: str = Field(description="ID unik job untuk polling status.")
    status: Literal["queued", "running"] = Field(default="queued")
    message: str = Field(description="Pesan informasi pembuatan job.")
    platform: str = Field(description="Platform target scraping.")
    keywords: List[str] = Field(description="Keyword yang akan dicari.")


class Level2Breakdown(BaseModel):
    """Detail distribusi untuk satu sub-kategori Level 2."""

    count: int = Field(description="Jumlah data pada sub-kategori ini.")
    percentage: float = Field(description="Persentase dari total data (%).")


class PlatformBreakdown(BaseModel):
    """Distribusi hate speech untuk satu platform."""

    total: int = Field(description="Total postingan dari platform ini.")
    hate_speech: int = Field(description="Jumlah hate speech.")
    non_hate_speech: int = Field(description="Jumlah non-hate speech.")
    hate_pct: float = Field(description="Persentase hate speech (%).")


class PipelineStatistics(BaseModel):
    """Ringkasan statistik lengkap hasil analisis hate speech."""

    total_data: int = Field(description="Total postingan yang berhasil dianalisis.")
    hate_speech_count: int = Field(description="Jumlah postingan hate speech.")
    hate_speech_pct: float = Field(description="Persentase hate speech (%).")
    non_hate_speech_count: int = Field(description="Jumlah postingan non-hate speech.")
    non_hate_speech_pct: float = Field(description="Persentase non-hate speech (%).")
    avg_confidence_lvl1: float = Field(description="Rata-rata confidence Level 1 (%).")
    avg_confidence_lvl2: float = Field(description="Rata-rata confidence Level 2 (%).")
    level2_breakdown: Dict[str, Level2Breakdown] = Field(
        description="Distribusi per sub-kategori Level 2."
    )
    platform_breakdown: Dict[str, PlatformBreakdown] = Field(
        description="Distribusi hate speech per platform."
    )


class PipelineJobStatusResponse(BaseModel):
    """Response polling status pipeline job (GET /api/v1/pipeline/status/{job_id})."""

    job_id: str = Field(description="ID job.")
    status: Literal["queued", "running", "success", "error"] = Field(
        description="Status terkini: queued / running / success / error."
    )
    message: str = Field(description="Pesan status atau deskripsi error.")
    platform: Optional[str] = Field(default=None)
    keywords: Optional[List[str]] = Field(default=None)
    search_mode: Optional[str] = Field(default=None)
    total_data: int = Field(default=0, description="Jumlah data yang berhasil dianalisis.")
    elapsed_seconds: Optional[float] = Field(default=None, description="Durasi eksekusi (detik).")
    statistics: Optional[PipelineStatistics] = Field(
        default=None,
        description="Statistik lengkap (tersedia jika status='success').",
    )
    exported_file: Optional[str] = Field(
        default=None,
        description="Path atau nama file CSV hasil ekspor (jika export_csv=True).",
    )
    error_detail: Optional[str] = Field(
        default=None,
        description="Detail error (tersedia jika status='error').",
    )


class ExportFileItem(BaseModel):
    """Informasi satu file CSV di storage/exports/."""

    filename: str = Field(description="Nama file CSV.")
    size_bytes: int = Field(description="Ukuran file dalam bytes.")
    created_at: str = Field(description="Waktu pembuatan file (ISO 8601).")
    download_url: str = Field(description="URL endpoint untuk mendownload file ini.")


class ExportListResponse(BaseModel):
    """Daftar file CSV hasil analisis di storage/exports/."""

    total: int = Field(description="Jumlah file CSV yang tersedia.")
    files: List[ExportFileItem] = Field(description="Daftar file beserta informasinya.")
