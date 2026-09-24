"""Pipeline Service: orkestrasi pipeline end-to-end, statistik, dan berkas ekspor."""

import asyncio
import logging
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, Optional

from configs.config import EXPORTS_DIR
from src.pipeline.end_to_end_pipeline import EndToEndPipeline
from app.schemas.classification_schema import (
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
    """Service untuk orkestrasi pipeline end-to-end dan penyajian berkas ekspor."""

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

                # Bangun statistics DTO (toleran terhadap data parsial)
                statistics = self._build_statistics(result.get("statistics", {}))

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
    def _build_statistics(raw_stats: Any) -> PipelineStatistics:
        """Bangun DTO statistik dari dict hasil pipeline (toleran data parsial).

        Nilai yang hilang menjadi 0 atau diturunkan dari field lain, sehingga laporan
        yang sebagian tidak menggagalkan job yang sebenarnya sukses.
        """
        raw: Dict[str, Any] = raw_stats if isinstance(raw_stats, dict) else {}

        def angka(key: str, default: float | int = 0):
            nilai = raw.get(key, default)
            return default if nilai is None else nilai

        level2_breakdown: Dict[str, Level2Breakdown] = {}
        for kategori, nilai in (raw.get("level2_breakdown") or {}).items():
            if not isinstance(nilai, dict):
                continue
            level2_breakdown[str(kategori)] = Level2Breakdown(
                count=int(nilai.get("count") or 0),
                percentage=float(nilai.get("percentage") or 0.0),
            )

        platform_breakdown: Dict[str, PlatformBreakdown] = {}
        for nama, nilai in (raw.get("platform_breakdown") or {}).items():
            if not isinstance(nilai, dict):
                continue
            total = int(nilai.get("total") or 0)
            hate = int(nilai.get("hate_speech") or 0)
            # Invarian yang dijaga: hate_speech + non_hate_speech == total.
            non_hate = nilai.get("non_hate_speech")
            non_hate = total - hate if non_hate is None else int(non_hate)
            hate_pct = nilai.get("hate_pct")
            if hate_pct is None:
                hate_pct = round(hate / total * 100, 2) if total > 0 else 0.0
            platform_breakdown[str(nama)] = PlatformBreakdown(
                total=total,
                hate_speech=hate,
                non_hate_speech=non_hate,
                hate_pct=float(hate_pct),
            )

        return PipelineStatistics(
            total_data=int(angka("total_data")),
            hate_speech_count=int(angka("hate_speech_count")),
            hate_speech_pct=float(angka("hate_speech_pct", 0.0)),
            non_hate_speech_count=int(angka("non_hate_speech_count")),
            non_hate_speech_pct=float(angka("non_hate_speech_pct", 0.0)),
            avg_confidence_lvl1=float(angka("avg_confidence_lvl1", 0.0)),
            avg_confidence_lvl2=float(angka("avg_confidence_lvl2", 0.0)),
            level2_breakdown=level2_breakdown,
            platform_breakdown=platform_breakdown,
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
    def get_safe_export_path(filename: str) -> Optional[Path]:
        """Resolusi & validasi path berkas ekspor (anti path traversal)."""
        base_dir = Path(EXPORTS_DIR).resolve()
        target_path = (base_dir / filename).resolve()

        # `Path.is_relative_to` tersedia sejak Python 3.9 (proyek ini 3.13), sehingga
        # fallback lama berbasis `str.startswith` (yang lebih lemah) tidak diperlukan.
        if not target_path.is_relative_to(base_dir):
            logger.warning(f"Percobaan path traversal terdeteksi: {filename}")
            return None

        if not target_path.exists() or not target_path.is_file():
            return None

        return target_path


# Singleton instance
pipeline_service = PipelineService()

