"""Router API klasifikasi IndoBERT: inferensi satu teks (/classify/single)."""

import logging

from fastapi import APIRouter, HTTPException, status

from app.schemas.classification_schema import (
    ClassifyItemResponse,
    ClassifySingleRequest,
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
