"""
Router API Scraping (/api/v1/scrape).

Endpoints:
    POST /api/v1/scrape/run
         Menerima parameter scraping (platform, keywords, max_links, dll).
         Langsung mengembalikan job_id (202 Accepted) — scraping berjalan di background.
         Gunakan GET /status/{job_id} untuk polling hasil.

    GET  /api/v1/scrape/status/{job_id}
         Mengambil status dan hasil job scraping berdasarkan job_id.
         Status: queued -> running -> success / error
"""

import logging

from fastapi import APIRouter, HTTPException, status

from app.schemas.scraper_schema import (
    ScrapeJobResponse,
    ScrapeJobStatusResponse,
    ScrapeRequest,
)
from app.services.scraper_service import scraper_service

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/scrape", tags=["Scraping Data Media Sosial"])


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post(
    "/run",
    response_model=ScrapeJobResponse,
    status_code=status.HTTP_202_ACCEPTED,
    summary="Jalankan Scraping (Background Job)",
    description=(
        "Memulai job scraping data media sosial secara asinkron. "
        "Response langsung dikembalikan (202 Accepted) berisi `job_id`. "
        "Gunakan `GET /api/v1/scrape/status/{job_id}` untuk memantau progress "
        "dan mengambil hasil setelah selesai. "
        "Pastikan sesi login sudah aktif via GET /api/v1/auth/status sebelum scraping."
    ),
)
async def run_scrape(req: ScrapeRequest) -> ScrapeJobResponse:
    """Buat job scraping baru dan jalankan di background via ScraperService."""
    # 1. Validasi sesi scraper terlebih dahulu
    valid, errors = scraper_service.validate_session_for_platform(req.platform)
    if not valid:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": "Sesi tidak valid.", "errors": errors},
        )

    # 2. Delegasikan pembuatan dan peluncuran job ke ScraperService
    return await scraper_service.create_and_start_scrape_job(req)


@router.get(
    "/status/{job_id}",
    response_model=ScrapeJobStatusResponse,
    summary="Status Job Scraping",
    description=(
        "Mengambil status terkini dari job scraping berdasarkan `job_id`. "
        "Polling endpoint ini secara berkala untuk memantau progress. "
        "Jika `status='success'`, field `data` berisi daftar postingan hasil scraping. "
        "Jika `status='error'`, field `error_detail` berisi pesan error."
    ),
)
def get_scrape_status(job_id: str) -> ScrapeJobStatusResponse:
    """Kembalikan status dan hasil job scraping dari ScraperService."""
    job_status = scraper_service.get_job_status(job_id)
    if job_status is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Job dengan ID '{job_id}' tidak ditemukan.",
        )

    return job_status
