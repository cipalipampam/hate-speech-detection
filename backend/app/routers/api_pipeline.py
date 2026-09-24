"""Router API pipeline: jalankan analisis, polling status, unduh berkas CSV hasil."""

from fastapi import APIRouter, HTTPException, Request, status
from fastapi.responses import FileResponse

from app.schemas.classification_schema import (
    PipelineJobResponse,
    PipelineJobStatusResponse,
    PipelineRunRequest,
)
from app.services.pipeline_service import pipeline_service

router = APIRouter(prefix="/pipeline", tags=["End-to-End Analysis Pipeline"])


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
        "Scrape -> Preprocess -> Klasifikasi IndoBERT -> Export CSV. "
        "Response langsung dikembalikan (202 Accepted) berisi job_id. "
        "Gunakan GET /api/v1/pipeline/status/{job_id} untuk memantau progress."
    ),
)
async def run_pipeline(req: PipelineRunRequest, request: Request) -> PipelineJobResponse:
    """Buat dan jalankan pipeline end-to-end via PipelineService."""
    predictor = getattr(request.app.state, "predictor", None)
    if predictor is None:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Model IndoBERT belum siap. Tunggu beberapa menit lalu coba lagi.",
        )

    return await pipeline_service.create_and_start_pipeline_job(req, predictor)


@router.get(
    "/status/{job_id}",
    response_model=PipelineJobStatusResponse,
    summary="Status Job Pipeline",
    description=(
        "Polling status pipeline job berdasarkan job_id. "
        "Saat status='success', field statistics berisi ringkasan lengkap hasil analisis "
        "dan exported_file berisi nama file CSV yang bisa didownload."
    ),
)
def get_pipeline_status(job_id: str) -> PipelineJobStatusResponse:
    """Kembalikan status dan hasil pipeline job dari PipelineService."""
    job_status = pipeline_service.get_job_status(job_id)
    if job_status is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Pipeline job dengan ID '{job_id}' tidak ditemukan.",
        )

    return job_status


@router.get(
    "/exports/{filename}",
    summary="Download File Hasil Analisis",
    description=(
        "Mendownload satu file CSV hasil analisis dari direktori storage/exports/. "
        "Dilindungi dengan validasi Path Traversal (is_relative_to)."
    ),
    response_class=FileResponse,
)
def download_export(filename: str) -> FileResponse:
    """Download file CSV hasil analisis dengan proteksi path traversal."""
    safe_path = pipeline_service.get_safe_export_path(filename)
    if safe_path is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"File '{filename}' tidak valid atau tidak ditemukan di storage/exports/.",
        )

    return FileResponse(
        path=str(safe_path),
        media_type="text/csv",
        filename=filename,
    )
