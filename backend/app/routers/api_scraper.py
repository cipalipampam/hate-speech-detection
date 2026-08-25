"""
Router API Scraping (/api/v1/scrape).

Endpoints:
    POST /api/v1/scrape/run
         Menerima parameter scraping (platform, keywords, max_links, dll).
         Langsung kembalikan job_id (202 Accepted) — scraping berjalan di background.
         Gunakan GET /status/{job_id} untuk polling hasil.

    GET  /api/v1/scrape/status/{job_id}
         Mengambil status dan hasil job scraping berdasarkan job_id.
         Status: queued → running → success / error

Catatan:
    Scraping bersifat long-running (5–60+ menit) dan membutuhkan browser GUI.
    Oleh karena itu menggunakan pola Background Task + In-Memory Job Store.
    Job store bersifat in-memory (dict) — sesuai kebutuhan skripsi.
"""

import asyncio
import logging
import uuid
from datetime import datetime
from typing import Dict

from fastapi import APIRouter, HTTPException, status

from src.scraping import run_x_scraper, run_threads_scraper, XScrapeConfig, ThreadsScrapeConfig
from src.auth.session_manager import is_session_valid
from app.schemas.scraper_schema import (
    ScrapeRequest,
    ScrapePostItem,
    ScrapeJobResponse,
    ScrapeJobStatusResponse,
)

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/scrape", tags=["Scraping Data Media Sosial"])

# ---------------------------------------------------------------------------
# In-Memory Job Store
# Format: { job_id: { status, message, data, total_scraped, elapsed_seconds, ... } }
# ---------------------------------------------------------------------------
_job_store: Dict[str, dict] = {}


# ---------------------------------------------------------------------------
# Background Task Worker
# ---------------------------------------------------------------------------

async def _run_scrape_job(job_id: str, req: ScrapeRequest):
    """
    Background task yang menjalankan scraping dan menyimpan hasilnya ke _job_store.
    Dipanggil oleh asyncio.create_task() — tidak memblokir HTTP response.
    """
    _job_store[job_id]["status"] = "running"
    _job_store[job_id]["message"] = "Scraping sedang berjalan..."
    t_start = datetime.now()

    try:
        x_config = XScrapeConfig(
            search_mode=req.search_mode,
            max_links=req.max_links,
            scan_max_steps=req.max_scroll_steps,
            headless=req.headless,
        )
        threads_config = ThreadsScrapeConfig(
            search_mode=req.search_mode,
            max_links=req.max_links,
            scan_max_steps=req.max_scroll_steps,
            headless=req.headless,
        )

        frames = []
        platform = req.platform.lower()

        if platform in ("x", "both"):
            res_x = await run_x_scraper(keywords=req.keywords, config=x_config)
            if res_x is not None and not res_x.empty:
                frames.append(res_x)

        if platform in ("threads", "both"):
            res_t = await run_threads_scraper(keywords=req.keywords, config=threads_config)
            if res_t is not None and not res_t.empty:
                frames.append(res_t)

        # Gabungkan & deduplikasi
        if frames:
            import pandas as pd
            df = pd.concat(frames, ignore_index=True)
            if "content" in df.columns and "source" in df.columns:
                df = df.drop_duplicates(subset=["source", "content"]).reset_index(drop=True)
        else:
            import pandas as pd
            df = pd.DataFrame(columns=["platform", "source", "user_id", "type", "date", "content"])

        elapsed = round((datetime.now() - t_start).total_seconds(), 2)

        # Serialisasi ke list dict untuk disimpan di job_store
        data_list = []
        for _, row in df.iterrows():
            data_list.append(ScrapePostItem(
                platform=str(row.get("platform", "")),
                source=str(row.get("source", "")),
                user_id=str(row.get("user_id", "")),
                type=str(row.get("type", "")),
                date=str(row.get("date", "")) if row.get("date") else None,
                content=str(row.get("content", "")),
            ))

        _job_store[job_id].update({
            "status": "success",
            "message": f"Scraping selesai. Berhasil mengumpulkan {len(data_list):,} postingan.",
            "total_scraped": len(data_list),
            "elapsed_seconds": elapsed,
            "data": data_list,
        })
        logger.info(f"[Job {job_id}] Scraping selesai: {len(data_list)} postingan, {elapsed}s.")

    except Exception as e:
        elapsed = round((datetime.now() - t_start).total_seconds(), 2)
        logger.error(f"[Job {job_id}] Scraping error: {e}", exc_info=True)
        _job_store[job_id].update({
            "status": "error",
            "message": "Scraping gagal karena terjadi error.",
            "elapsed_seconds": elapsed,
            "error_detail": str(e),
        })


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
        "**Pastikan sesi login sudah aktif** via `GET /api/v1/auth/status` sebelum scraping."
    ),
)
async def run_scrape(req: ScrapeRequest) -> ScrapeJobResponse:
    """Buat job scraping baru dan jalankan di background."""
    # Validasi sesi sebelum memulai
    platform = req.platform.lower()
    session_errors = []
    if platform in ("x", "both") and not is_session_valid("x"):
        session_errors.append("Sesi X (Twitter) tidak aktif. Login terlebih dahulu via POST /api/v1/auth/login-trigger/x")
    if platform in ("threads", "both") and not is_session_valid("threads"):
        session_errors.append("Sesi Threads tidak aktif. Login terlebih dahulu via POST /api/v1/auth/login-trigger/threads")

    if session_errors:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": "Sesi tidak valid.", "errors": session_errors},
        )

    # Buat job baru
    job_id = str(uuid.uuid4())
    _job_store[job_id] = {
        "status": "queued",
        "message": "Job scraping dibuat dan menunggu dieksekusi.",
        "platform": req.platform,
        "keywords": req.keywords,
        "total_scraped": 0,
        "elapsed_seconds": None,
        "data": None,
        "error_detail": None,
    }

    # Jalankan scraping di background (non-blocking)
    asyncio.create_task(_run_scrape_job(job_id, req))
    logger.info(f"[Job {job_id}] Scraping job dibuat: platform={req.platform}, keywords={req.keywords}")

    return ScrapeJobResponse(
        job_id=job_id,
        status="queued",
        message=(
            f"Job scraping berhasil dibuat (ID: {job_id}). "
            f"Polling status di: GET /api/v1/scrape/status/{job_id}"
        ),
        platform=req.platform,
        keywords=req.keywords,
    )


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
    """Kembalikan status dan hasil job scraping."""
    job = _job_store.get(job_id)
    if job is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Job dengan ID '{job_id}' tidak ditemukan.",
        )

    return ScrapeJobStatusResponse(
        job_id=job_id,
        status=job["status"],
        message=job["message"],
        total_scraped=job.get("total_scraped", 0),
        platform=job.get("platform"),
        keywords=job.get("keywords"),
        elapsed_seconds=job.get("elapsed_seconds"),
        data=job.get("data"),
        error_detail=job.get("error_detail"),
    )
