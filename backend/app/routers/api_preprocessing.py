"""
Router API Preprocessing (/api/v1/preprocess).

Endpoints:
    POST /api/v1/preprocess/single
         Membersihkan dan menormalisasi satu kalimat teks (raw -> clean).
         Pipeline: Regex Cleaning -> Case Folding -> Normalisasi Kamusalay.

    POST /api/v1/preprocess/batch
         Membersihkan dan menormalisasi daftar teks secara batch (maks 1000 item).
"""

import logging

from fastapi import APIRouter, HTTPException, status

from app.schemas.preprocessing_schema import (
    PreprocessBatchRequest,
    PreprocessBatchResponse,
    PreprocessSingleRequest,
    PreprocessSingleResponse,
)
from app.services.classification_service import classification_service

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/preprocess", tags=["Preprocessing Teks"])


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post(
    "/single",
    response_model=PreprocessSingleResponse,
    summary="Preprocess Satu Teks",
    description=(
        "Membersihkan dan menormalisasi satu kalimat teks melalui pipeline 3 tahapan: "
        "Regex Cleaning -> Case Folding -> Normalisasi Kamusalay. "
        "Hasil clean_text siap dikonsumsi oleh IndoBERT Tokenizer."
    ),
)
def preprocess_single(body: PreprocessSingleRequest) -> PreprocessSingleResponse:
    """Jalankan pipeline preprocessing pada satu teks via ClassificationService preprocessor."""
    try:
        raw = body.text
        clean = classification_service._preprocessor.transform_text(raw)
        return PreprocessSingleResponse(raw_text=raw, clean_text=clean)
    except Exception as e:
        logger.error(f"Preprocessing single error: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Gagal memproses teks: {str(e)}",
        )


@router.post(
    "/batch",
    response_model=PreprocessBatchResponse,
    summary="Preprocess Batch Teks",
    description=(
        "Membersihkan dan menormalisasi daftar teks secara batch (maksimum 1000 item). "
        "Validasi kuota batch ditangani deklaratif oleh Pydantic. "
        "Response berisi daftar pasangan raw_text dan clean_text sesuai urutan input."
    ),
)
def preprocess_batch(body: PreprocessBatchRequest) -> PreprocessBatchResponse:
    """Jalankan pipeline preprocessing pada batch teks."""
    try:
        results = []
        for raw in body.texts:
            clean = classification_service._preprocessor.transform_text(raw)
            results.append(PreprocessSingleResponse(raw_text=raw, clean_text=clean))

        return PreprocessBatchResponse(total=len(results), data=results)
    except Exception as e:
        logger.error(f"Preprocessing batch error: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Gagal memproses batch teks: {str(e)}",
        )
