"""
Router API End-to-End Pipeline (/api/v1/pipeline).

Endpoints:
    POST /api/v1/pipeline/run
         Menjalankan alur analisis lengkap (Scrape → Preprocess → Classify → Export CSV).
         Langsung kembalikan job_id (202 Accepted) — pipeline berjalan di background.

    GET  /api/v1/pipeline/status/{job_id}
         Polling status pipeline: queued → running → success / error.
         Saat success, berisi statistik lengkap & path file CSV hasil ekspor.

    GET  /api/v1/pipeline/exports
         Mendaftarkan semua file CSV hasil analisis di storage/exports/.

    GET  /api/v1/pipeline/exports/{filename}
         Mendownload satu file CSV hasil analisis.

Catatan:
    Pipeline adalah operasi panjang (bisa 10–60+ menit).
    Menggunakan pola Background Task + In-Memory Job Store.
"""

import asyncio
import logging
import uuid
from datetime import datetime
from pathlib import Path
from typing import Dict

from fastapi import APIRouter, HTTPException, Request, status
from fastapi.responses import FileResponse

from configs.config import EXPORTS_DIR
from src.pipeline.end_to_end_pipeline import EndToEndPipeline
from app.schemas.classification_schema import (
    PipelineRunRequest,
    PipelineJobResponse,
    PipelineJobStatusResponse,
    PipelineStatistics,
    Level2Breakdown,
    PlatformBreakdown,
    ExportFileItem,
    ExportListResponse,
)

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/pipeline", tags=["End-to-End Analysis Pipeline"])

# ---------------------------------------------------------------------------
# In-Memory Job Store — { job_id: { status, message, statistics, exported_file, ... } }
# ---------------------------------------------------------------------------
_job_store: Dict[str, dict] = {}


# ---------------------------------------------------------------------------
# Background Task Worker
# ---------------------------------------------------------------------------

async def _run_pipeline_job(job_id: str, req: PipelineRunRequest):
    """
    Background task yang menjalankan pipeline end-to-end dan menyimpan
    hasilnya ke _job_store. Dipanggil oleh asyncio.create_task().
    """
    _job_store[job_id]["status"] = "running"
    _job_store[job_id]["message"] = "[Langkah 1/5] Memvalidasi sesi..."
    t_start = datetime.now()

    def _on_status(msg: str):
        """Callback untuk memperbarui message di job_store."""
        _job_store[job_id]["message"] = msg

    try:
        pipeline = EndToEndPipeline()
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
            _job_store[job_id].update({
                "status": "error",
                "message": result.get("message", "Pipeline gagal."),
                "elapsed_seconds": elapsed,
                "error_detail": str(result.get("errors", "")),
            })
            return

        # Bangun statistics object
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

        _job_store[job_id].update({
            "status": "success",
            "message": (
                f"Pipeline selesai. {result.get('total_data', 0):,} data dianalisis "
                f"dalam {elapsed:.1f} detik."
            ),
            "total_data": result.get("total_data", 0),
            "elapsed_seconds": elapsed,
            "statistics": statistics,
            "exported_file": exported_filename,
            "search_mode": req.search_mode,
        })
        logger.info(f"[Job {job_id}] Pipeline selesai: {result.get('total_data', 0)} data, {elapsed}s.")

    except Exception as e:
        elapsed = round((datetime.now() - t_start).total_seconds(), 2)
        logger.error(f"[Job {job_id}] Pipeline error: {e}", exc_info=True)
        _job_store[job_id].update({
            "status": "error",
            "message": "Pipeline gagal karena terjadi error tidak terduga.",
            "elapsed_seconds": elapsed,
            "error_detail": str(e),
        })


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post(
    "/run",
    response_model=PipelineJobResponse,
    status_code=status.HTTP_202_ACCEPTED,
    summary="Jalankan Pipeline Analisis Penuh (Background Job)",
    description=(
        "Menjalankan alur analisis hate speech secara lengkap dan otomatis: "
        "**Scrape** → **Preprocess** → **Klasifikasi IndoBERT** → **Export CSV**. "
        "Response langsung dikembalikan (202 Accepted) berisi `job_id`. "
        "Gunakan `GET /api/v1/pipeline/status/{job_id}` untuk memantau progress "
        "dan mengambil hasil statistik setelah selesai."
    ),
)
async def run_pipeline(req: PipelineRunRequest, request: Request) -> PipelineJobResponse:
    """Buat dan jalankan pipeline end-to-end di background."""
    # Pastikan model IndoBERT sudah siap
    predictor = getattr(request.app.state, "predictor", None)
    if predictor is None:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Model IndoBERT belum siap. Tunggu beberapa menit lalu coba lagi.",
        )

    # Buat job baru
    job_id = str(uuid.uuid4())
    _job_store[job_id] = {
        "status": "queued",
        "message": "Pipeline job dibuat dan menunggu dieksekusi.",
        "platform": req.platform,
        "keywords": req.keywords,
        "search_mode": req.search_mode,
        "total_data": 0,
        "elapsed_seconds": None,
        "statistics": None,
        "exported_file": None,
        "error_detail": None,
    }

    # Jalankan di background
    asyncio.create_task(_run_pipeline_job(job_id, req))
    logger.info(f"[Job {job_id}] Pipeline job dibuat: platform={req.platform}, keywords={req.keywords}")

    return PipelineJobResponse(
        job_id=job_id,
        status="queued",
        message=f"Pipeline job berhasil diinisialisasi (ID: {job_id}). Menunggu antrean pemrosesan...",
        platform=req.platform,
        keywords=req.keywords,
    )


@router.get(
    "/status/{job_id}",
    response_model=PipelineJobStatusResponse,
    summary="Status Job Pipeline",
    description=(
        "Polling status pipeline job berdasarkan `job_id`. "
        "Saat `status='success'`, field `statistics` berisi ringkasan lengkap hasil analisis "
        "dan `exported_file` berisi nama file CSV yang bisa didownload."
    ),
)
def get_pipeline_status(job_id: str) -> PipelineJobStatusResponse:
    """Kembalikan status dan hasil pipeline job."""
    job = _job_store.get(job_id)
    if job is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Pipeline job dengan ID '{job_id}' tidak ditemukan.",
        )

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


@router.get(
    "/exports",
    response_model=ExportListResponse,
    summary="Daftar File Hasil Ekspor",
    description=(
        "Mengembalikan daftar semua file CSV hasil analisis yang tersimpan "
        "di direktori `storage/exports/`. "
        "Setiap item berisi nama file, ukuran, waktu pembuatan, dan URL download."
    ),
)
def list_exports(request: Request) -> ExportListResponse:
    """Daftar semua file CSV di storage/exports/."""
    exports_dir = Path(EXPORTS_DIR)
    if not exports_dir.exists():
        return ExportListResponse(total=0, files=[])

    csv_files = sorted(
        exports_dir.glob("*.csv"),
        key=lambda f: f.stat().st_mtime,
        reverse=True,  # terbaru di atas
    )

    base_url = str(request.base_url).rstrip("/")
    file_items = []
    for f in csv_files:
        stat = f.stat()
        file_items.append(ExportFileItem(
            filename=f.name,
            size_bytes=stat.st_size,
            created_at=datetime.fromtimestamp(stat.st_mtime).isoformat(),
            download_url=f"{base_url}/api/v1/pipeline/exports/{f.name}",
        ))

    return ExportListResponse(total=len(file_items), files=file_items)


@router.get(
    "/exports/{filename}",
    summary="Download File Hasil Analisis",
    description=(
        "Mendownload satu file CSV hasil analisis dari direktori `storage/exports/`. "
        "Gunakan endpoint `GET /exports` untuk mendapatkan daftar nama file yang tersedia."
    ),
    response_class=FileResponse,
)
def download_export(filename: str) -> FileResponse:
    """Download file CSV hasil analisis."""
    # Sanitasi nama file (cegah path traversal)
    if ".." in filename or "/" in filename or "\\" in filename:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Nama file tidak valid.",
        )

    file_path = Path(EXPORTS_DIR) / filename
    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"File '{filename}' tidak ditemukan di storage/exports/.",
        )

    return FileResponse(
        path=str(file_path),
        media_type="text/csv",
        filename=filename,
    )
