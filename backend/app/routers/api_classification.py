"""
Router API Klasifikasi IndoBERT (/api/v1/classify).

Endpoints:
    GET  /api/v1/classify/info
         Mengembalikan informasi model IndoBERT yang aktif.

    POST /api/v1/classify/single
         Memprediksi ujaran kebencian pada satu kalimat teks.
         Output: Level 1 (hate/non-hate) + Level 2 (6 sub-kategori) + confidence score.

    POST /api/v1/classify/batch
         Memprediksi ujaran kebencian pada daftar teks secara batch.
         Output: Hasil per teks + ringkasan statistik (jumlah & persentase hate speech).
"""

import logging

from fastapi import APIRouter, HTTPException, status

from app.schemas.classification_schema import (
    ClassifyBatchRequest,
    ClassifyBatchResponse,
    ClassifyItemResponse,
    ClassifySingleRequest,
    ModelInfoResponse,
)
from app.services.classification_service import classification_service

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/classify", tags=["Klasifikasi IndoBERT"])


# ---------------------------------------------------------------------------
# Helper Dependency
# ---------------------------------------------------------------------------

def _ensure_predictor_ready() -> None:
    """Pastikan predictor yang diinjeksikan ke service sudah siap digunakan."""
    if not classification_service.is_predictor_ready():
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=(
                "Model IndoBERT belum siap. "
                "Server mungkin masih dalam proses loading (~1-3 menit). "
                "Coba lagi sebentar."
            ),
        )
# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.get(
    "/info",
    response_model=ModelInfoResponse,
    summary="Informasi Model IndoBERT",
    description=(
        "Mengembalikan informasi model IndoBERT yang sedang aktif di server: "
        "nama pretrained model, device (CPU/GPU), panjang token maksimum, "
        "status loading, dan daftar label kelas Level 1 & Level 2."
    ),
)
def get_model_info() -> ModelInfoResponse:
    """Kembalikan metadata model IndoBERT yang aktif dari ClassificationService."""
    _ensure_predictor_ready()
    return classification_service.get_model_info()


@router.post(
    "/single",
    response_model=ClassifyItemResponse,
    summary="Klasifikasi Satu Teks",
    description=(
        "Memprediksi apakah satu kalimat teks mengandung ujaran kebencian menggunakan "
        "model IndoBERT Hierarchical Classifier. "
        "Jika `preprocess=True` (default), teks akan dipreprocess terlebih dahulu."
    ),
)
def classify_single(body: ClassifySingleRequest) -> ClassifyItemResponse:
    """Klasifikasi ujaran kebencian pada satu teks via ClassificationService."""
    _ensure_predictor_ready()
    try:
        return classification_service.classify_single_text(
            text=body.text,
            preprocess=body.preprocess,
        )
    except Exception as e:
        logger.error(f"Classify single error: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Gagal melakukan klasifikasi: {str(e)}",
        )


@router.post(
    "/batch",
    response_model=ClassifyBatchResponse,
    summary="Klasifikasi Batch Teks",
    description=(
        "Memprediksi ujaran kebencian pada daftar teks secara batch (maks 500 teks). "
        "Validasi kuota batch ditangani deklaratif oleh Pydantic. "
        "Response mencakup hasil per teks beserta ringkasan statistik."
    ),
)
def classify_batch(body: ClassifyBatchRequest) -> ClassifyBatchResponse:
    """Klasifikasi ujaran kebencian pada batch teks via ClassificationService."""
    _ensure_predictor_ready()
    try:
        return classification_service.classify_batch_texts(
            texts=body.texts,
            preprocess=body.preprocess,
        )
    except Exception as e:
        logger.error(f"Classify batch error: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Gagal melakukan klasifikasi batch: {str(e)}",
        )
