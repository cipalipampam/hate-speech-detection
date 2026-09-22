"""
Pipeline Service — Application Service Layer.

Mengelola:
1. Validasi kesiapan model IndoBERT sebelum menjalankan pipeline.
2. Orkestrasi alur end-to-end (Scrape -> Preprocess -> Classify -> Export) ber-lock.
3. Pengelolaan riwayat dan listing file CSV di storage/exports/.
4. Resolusi path file ekspor yang aman (Path Traversal Protection).
"""

import asyncio
import logging
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any, List, Optional

from configs.config import EXPORTS_DIR
from src.pipeline.end_to_end_pipeline import EndToEndPipeline
from app.schemas.classification_schema import (
    ExportFileItem,
    ExportListResponse,
    Level2Breakdown,
    PipelineJobResponse,
    PipelineJobStatusResponse,
    PipelineRunRequest,
    PipelineStatistics,
    PlatformBreakdown,
)
from .job_service import job_manager

logger = logging.getLogger(__name__)


class PipelineService:
    """Service untuk orkestrasi pipeline end-to-end dan manajemen file ekspor."""

    async def create_and_start_pipeline_job(
        self,
        req: PipelineRunRequest,
        predictor: Any,
    ) -> PipelineJobResponse:
        """Membuat job pipeline baru dan meluncurkan background task ber-lock."""
        job_id = str(uuid.uuid4())

        job_manager.create_job(
            job_id=job_id,
            job_type="pipeline",
            metadata={
                "platform": req.platform,
                "keywords": req.keywords,
                "search_mode": req.search_mode,
                "total_data": 0,
                "statistics": None,
                "exported_file": None,
            },
        )

        task = asyncio.create_task(self._execute_pipeline_worker(job_id, req, predictor))
        job_manager.track_task(task)

        logger.info(f"PipelineService: Job {job_id} dibuat (platform={req.platform}, keywords={req.keywords})")

        return PipelineJobResponse(
            job_id=job_id,
            status="queued",
            message=f"Pipeline job berhasil diinisialisasi (ID: {job_id}). Menunggu antrean pemrosesan...",
            platform=req.platform,
            keywords=req.keywords,
        )

    async def _execute_pipeline_worker(
        self,
        job_id: str,
        req: PipelineRunRequest,
        predictor: Any,
    ):
        """Worker internal yang menjalankan seluruh alur end-to-end dengan Platform Lock."""
        t_start = datetime.now()

        async with job_manager.platform_lock(req.platform):
            job_manager.update_job(
                job_id,
                status="running",
                message="[Langkah 1/5] Memvalidasi sesi...",
            )

            def _on_status(msg: str):
                job_manager.update_job(job_id, message=msg)

            try:
                # Injeksi predictor yang sudah dimuat di app.state (mencegah double-loading model)
                pipeline = EndToEndPipeline(predictor=predictor)
                result = await pipeline.run(
                    keywords=req.keywords,
                    platform=req.platform,
                    search_mode=req.search_mode,
                    max_links=req.max_links,
                    max_scroll_steps=req.max_scroll_steps,
                    headless=req.headless,
                    export_csv=req.export_csv,
                    status_callback=_on_status,
                )

                elapsed = round((datetime.now() - t_start).total_seconds(), 2)

                if result.get("status") in ("error", "warning"):
                    job_manager.update_job(
                        job_id,
                        status="error",
                        message=result.get("message", "Pipeline gagal."),
                        elapsed_seconds=elapsed,
                        error_detail=str(result.get("errors", "")),
                    )
                    return

                # Bangun statistics DTO
                raw_stats = result.get("statistics", {})
                lvl2_data = {
                    k: Level2Breakdown(count=v["count"], percentage=v["percentage"])
                    for k, v in raw_stats.get("level2_breakdown", {}).items()
                }
                plat_data = {
                    k: PlatformBreakdown(
                        total=v["total"],
                        hate_speech=v["hate_speech"],
                        non_hate_speech=v["non_hate_speech"],
                        hate_pct=v["hate_pct"],
                    )
                    for k, v in raw_stats.get("platform_breakdown", {}).items()
                }
                statistics = PipelineStatistics(
                    total_data=raw_stats.get("total_data", 0),
                    hate_speech_count=raw_stats.get("hate_speech_count", 0),
                    hate_speech_pct=raw_stats.get("hate_speech_pct", 0.0),
                    non_hate_speech_count=raw_stats.get("non_hate_speech_count", 0),
                    non_hate_speech_pct=raw_stats.get("non_hate_speech_pct", 0.0),
                    avg_confidence_lvl1=raw_stats.get("avg_confidence_lvl1", 0.0),
                    avg_confidence_lvl2=raw_stats.get("avg_confidence_lvl2", 0.0),
                    level2_breakdown=lvl2_data,
                    platform_breakdown=plat_data,
                )

                exported_file = result.get("exported_file")
                exported_filename = Path(exported_file).name if exported_file else None

                job_manager.update_job(
                    job_id,
                    status="success",
                    message=(
                        f"Pipeline selesai. {result.get('total_data', 0):,} data dianalisis "
                        f"dalam {elapsed:.1f} detik."
                    ),
                    total_data=result.get("total_data", 0),
                    elapsed_seconds=elapsed,
                    statistics=statistics,
                    exported_file=exported_filename,
                    search_mode=req.search_mode,
                )
                logger.info(f"PipelineService [Job {job_id}]: Selesai {result.get('total_data', 0)} data, {elapsed}s.")

            except Exception as e:
                elapsed = round((datetime.now() - t_start).total_seconds(), 2)
                logger.error(f"PipelineService [Job {job_id}]: Gagal: {e}", exc_info=True)
                job_manager.update_job(
                    job_id,
                    status="error",
                    message="Pipeline gagal karena terjadi error tidak terduga.",
                    elapsed_seconds=elapsed,
                    error_detail=str(e),
                )

    @staticmethod
    def get_job_status(job_id: str) -> Optional[PipelineJobStatusResponse]:
        """Mengambil status pipeline job berdasarkan job_id."""
        job = job_manager.get_job(job_id)
        if job is None:
            return None

        return PipelineJobStatusResponse(
            job_id=job_id,
            status=job["status"],
            message=job["message"],
            platform=job.get("platform"),
            keywords=job.get("keywords"),
            search_mode=job.get("search_mode"),
            total_data=job.get("total_data", 0),
            elapsed_seconds=job.get("elapsed_seconds"),
            statistics=job.get("statistics"),
            exported_file=job.get("exported_file"),
            error_detail=job.get("error_detail"),
        )

    @staticmethod
    def list_exports(base_url: str) -> ExportListResponse:
        """Mengambil daftar seluruh file ekspor CSV di storage/exports/."""
        exports_dir = Path(EXPORTS_DIR)
        if not exports_dir.exists():
            return ExportListResponse(total=0, files=[])

        csv_files = sorted(
            exports_dir.glob("*.csv"),
            key=lambda f: f.stat().st_mtime,
            reverse=True,
        )

        base = base_url.rstrip("/")
        file_items: List[ExportFileItem] = []
        for f in csv_files:
            stat = f.stat()
            file_items.append(
                ExportFileItem(
                    filename=f.name,
                    size_bytes=stat.st_size,
                    created_at=datetime.fromtimestamp(stat.st_mtime).isoformat(),
                    download_url=f"{base}/api/v1/pipeline/exports/{f.name}",
                )
            )

        return ExportListResponse(total=len(file_items), files=file_items)

    @staticmethod
    def get_safe_export_path(filename: str) -> Optional[Path]:
        """
        Menyelesaikan dan memvalidasi path file ekspor.
        Mencegah Path Traversal Vulnerability menggunakan Path.is_relative_to().
        """
        base_dir = Path(EXPORTS_DIR).resolve()
        target_path = (base_dir / filename).resolve()

        # Pastikan path berada di dalam direktori EXPORTS_DIR dan bertipe file reguler
        try:
            if not target_path.is_relative_to(base_dir):
                logger.warning(f"Percobaan path traversal terdeteksi: {filename}")
                return None
        except AttributeError:
            # Fallback untuk versi Python < 3.9 jika ada
            if not str(target_path).startswith(str(base_dir)):
                return None

        if not target_path.exists() or not target_path.is_file():
            return None

        return target_path


# Singleton instance
pipeline_service = PipelineService()

