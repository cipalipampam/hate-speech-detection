"""
Scraper Service — Application Service Layer.

Mengelola:
1. Validasi sesi scraper sebelum memulai crawling.
2. Orkestrasi eksekusi scraping dengan Platform Concurrency Lock.
3. Agregasi, deduplikasi pandas, dan serialisasi data ke ScrapePostItem DTO.
4. Integrasi dengan JobManager untuk tracking status dan hasil.
"""

import asyncio
import logging
import uuid
from datetime import datetime
from typing import List, Optional, Tuple

import pandas as pd

from src.auth.session_manager import is_session_valid
from src.scraping import (
    ThreadsScrapeConfig,
    XScrapeConfig,
    run_threads_scraper,
    run_x_scraper,
)
from app.schemas.scraper_schema import (
    ScrapeJobResponse,
    ScrapeJobStatusResponse,
    ScrapePostItem,
    ScrapeRequest,
)
from .job_service import job_manager

logger = logging.getLogger(__name__)


class ScraperService:
    """Service untuk menangani seluruh logika bisnis scraping data media sosial."""

    @staticmethod
    def validate_session_for_platform(platform: str) -> Tuple[bool, List[str]]:
        """Memeriksa apakah profil sesi login platform yang diminta sudah siap."""
        plat = platform.lower().strip()
        errors = []

        if plat in ("x", "both"):
            x_status = is_session_valid("x")
            if not x_status.get("is_valid", False):
                errors.append(
                    "Sesi X (Twitter) tidak aktif. Login terlebih dahulu via POST /api/v1/auth/login-trigger/x"
                )

        if plat in ("threads", "both"):
            t_status = is_session_valid("threads")
            if not t_status.get("is_valid", False):
                errors.append(
                    "Sesi Threads tidak aktif. Login terlebih dahulu via POST /api/v1/auth/login-trigger/threads"
                )

        return len(errors) == 0, errors

    async def create_and_start_scrape_job(self, req: ScrapeRequest) -> ScrapeJobResponse:
        """Membuat job scraping baru dan meluncurkan background task ber-lock."""
        job_id = str(uuid.uuid4())

        job_manager.create_job(
            job_id=job_id,
            job_type="scrape",
            metadata={
                "platform": req.platform,
                "keywords": req.keywords,
                "total_scraped": 0,
                "data": None,
            },
        )

        # Luncurkan background task dan simpan strong reference via job_manager
        task = asyncio.create_task(self._execute_scrape_worker(job_id, req))
        job_manager.track_task(task)

        logger.info(f"ScraperService: Job {job_id} berhasil dibuat (platform={req.platform}, keywords={req.keywords})")

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

    async def _execute_scrape_worker(self, job_id: str, req: ScrapeRequest):
        """Worker internal yang menjalankan scraping dengan Platform Lock."""
        t_start = datetime.now()

        # Gunakan lock platform agar Playwright tidak collision pada browser profile
        async with job_manager.platform_lock(req.platform):
            job_manager.update_job(
                job_id,
                status="running",
                message="Scraping sedang berjalan di background...",
            )

            try:
                x_config = XScrapeConfig(
                    search_mode=req.search_mode,
                    max_links=req.max_links,
                    search_scroll=req.max_scroll_steps,
                    headless=req.headless,
                )
                threads_config = ThreadsScrapeConfig(
                    search_mode=req.search_mode,
                    max_links=req.max_links,
                    search_scroll=req.max_scroll_steps,
                    headless=req.headless,
                )

                frames = []
                platform = req.platform.lower().strip()

                def _on_progress(msg: str):
                    job_manager.update_job(job_id, message=msg)

                if platform in ("x", "both"):
                    res_x = await run_x_scraper(keywords=req.keywords, config=x_config, status_callback=_on_progress)
                    if res_x is not None and not res_x.empty:
                        frames.append(res_x)

                if platform in ("threads", "both"):
                    res_t = await run_threads_scraper(keywords=req.keywords, config=threads_config, status_callback=_on_progress)
                    if res_t is not None and not res_t.empty:
                        frames.append(res_t)

                # Gabungkan & deduplikasi
                if frames:
                    df = pd.concat(frames, ignore_index=True)
                    if "content" in df.columns and "source" in df.columns:
                        df = df.drop_duplicates(subset=["source", "content"]).reset_index(drop=True)
                else:
                    df = pd.DataFrame(columns=["platform", "source", "user_id", "type", "date", "content"])

                elapsed = round((datetime.now() - t_start).total_seconds(), 2)

                # Serialisasi baris dataframe ke DTO
                data_list = []
                for _, row in df.iterrows():
                    data_list.append(
                        ScrapePostItem(
                            platform=str(row.get("platform", "")),
                            source=str(row.get("source", "")),
                            user_id=str(row.get("user_id", "")),
                            type=str(row.get("type", "")),
                            date=str(row.get("date", "")) if row.get("date") else None,
                            content=str(row.get("content", "")),
                        )
                    )

                job_manager.update_job(
                    job_id,
                    status="success",
                    message=f"Scraping selesai. Berhasil mengumpulkan {len(data_list):,} postingan.",
                    total_scraped=len(data_list),
                    elapsed_seconds=elapsed,
                    data=data_list,
                )
                logger.info(f"ScraperService [Job {job_id}]: Selesai {len(data_list)} postingan dalam {elapsed}s.")

            except Exception as e:
                elapsed = round((datetime.now() - t_start).total_seconds(), 2)
                logger.error(f"ScraperService [Job {job_id}]: Gagal: {e}", exc_info=True)
                job_manager.update_job(
                    job_id,
                    status="error",
                    message="Scraping gagal karena terjadi error.",
                    elapsed_seconds=elapsed,
                    error_detail=str(e),
                )

    @staticmethod
    def get_job_status(job_id: str) -> Optional[ScrapeJobStatusResponse]:
        """Mengambil data status job scraping untuk endpoint polling."""
        job = job_manager.get_job(job_id)
        if job is None:
            return None

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


# Singleton instance
scraper_service = ScraperService()
