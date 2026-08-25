"""
Pydantic Schema untuk Modul Scraping API.

Model:
    - ScrapeRequest          : Body request POST /api/v1/scrape/run
    - ScrapePostItem         : Satu data postingan hasil scraping
    - ScrapeJobResponse      : Response 202 Accepted saat job dibuat
    - ScrapeJobStatusResponse: Response polling GET /api/v1/scrape/status/{job_id}
"""

from typing import List, Literal, Optional
from pydantic import BaseModel, Field


# ---------------------------------------------------------------------------
# Request Models
# ---------------------------------------------------------------------------

class ScrapeRequest(BaseModel):
    """Parameter request untuk menjalankan scraping data media sosial."""

    platform: Literal["x", "threads", "both"] = Field(
        default="both",
        description="Platform yang akan di-scrape: 'x', 'threads', atau 'both'.",
    )
    keywords: List[str] = Field(
        ...,
        min_length=1,
        description="Daftar kata kunci pencarian. Minimal 1 keyword.",
        examples=[["RUU Polri", "tolak RUU"]],
    )
    search_mode: Literal["latest", "top"] = Field(
        default="latest",
        description="Mode pencarian: 'latest' (terbaru/recent) atau 'top' (terpopuler).",
    )
    max_links: int = Field(
        default=50,
        ge=1,
        le=500,
        description="Maksimum URL postingan yang dikumpulkan di Stage 1 (1–500).",
    )
    max_scroll_steps: int = Field(
        default=300,
        ge=50,
        le=5000,
        description="Maksimum langkah scroll per postingan di Stage 2 Deep Crawl (50–5000).",
    )
    headless: bool = Field(
        default=False,
        description="Jalankan browser headless (tanpa tampilan GUI). Default False.",
    )

    class Config:
        json_schema_extra = {
            "example": {
                "platform": "both",
                "keywords": ["RUU Polri", "tolak polisi"],
                "search_mode": "latest",
                "max_links": 50,
                "max_scroll_steps": 300,
                "headless": False,
            }
        }


# ---------------------------------------------------------------------------
# Response Models
# ---------------------------------------------------------------------------

class ScrapePostItem(BaseModel):
    """Representasi satu postingan hasil scraping."""

    platform: str = Field(description="Platform asal: 'X' atau 'Threads'.")
    source: str = Field(description="URL lengkap postingan/thread asal.")
    user_id: str = Field(description="Username / ID pengguna penulis postingan.")
    type: str = Field(description="Tipe konten: 'Original Post' atau 'Reply/Comment'.")
    date: Optional[str] = Field(default=None, description="Timestamp postingan.")
    content: str = Field(description="Isi teks postingan mentah.")


class ScrapeJobResponse(BaseModel):
    """Response segera (202 Accepted) saat job scraping berhasil dibuat."""

    job_id: str = Field(description="ID unik job untuk polling status.")
    status: Literal["queued", "running"] = Field(
        default="queued",
        description="Status awal job.",
    )
    message: str = Field(description="Pesan informasi pembuatan job.")
    platform: str = Field(description="Platform yang akan di-scrape.")
    keywords: List[str] = Field(description="Keyword yang akan dicari.")


class ScrapeJobStatusResponse(BaseModel):
    """Response polling status job scraping (GET /api/v1/scrape/status/{job_id})."""

    job_id: str = Field(description="ID job.")
    status: Literal["queued", "running", "success", "error"] = Field(
        description="Status terkini: queued / running / success / error."
    )
    message: str = Field(description="Pesan status atau error.")
    total_scraped: int = Field(default=0, description="Jumlah postingan berhasil dikumpulkan.")
    platform: Optional[str] = Field(default=None)
    keywords: Optional[List[str]] = Field(default=None)
    elapsed_seconds: Optional[float] = Field(default=None, description="Waktu eksekusi (detik).")
    data: Optional[List[ScrapePostItem]] = Field(
        default=None,
        description="Daftar postingan (tersedia jika status='success').",
    )
    error_detail: Optional[str] = Field(
        default=None,
        description="Detail pesan error (tersedia jika status='error').",
    )
