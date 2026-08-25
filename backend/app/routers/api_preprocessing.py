"""
Router API Preprocessing (/api/v1/preprocess).

Endpoints:
    POST /api/v1/preprocess/single
         Membersihkan dan menormalisasi satu kalimat teks (raw → clean).
         Pipeline: Regex Cleaning → Case Folding → Normalisasi Kamusalay.

    POST /api/v1/preprocess/batch
         Membersihkan dan menormalisasi daftar teks secara batch (maks 1000 item).
"""

import logging

from fastapi import APIRouter, HTTPException, status

from src.preprocessing import PreprocessingPipeline
from app.schemas.preprocessing_schema import (
    PreprocessSingleRequest,
    PreprocessSingleResponse,
    PreprocessBatchRequest,
    PreprocessBatchResponse,
)

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/preprocess", tags=["Preprocessing Teks"])

# Inisialisasi pipeline satu kali saat modul diimpor (kamusalay.csv dimuat ke memori)
_pipeline = PreprocessingPipeline()


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.post(
    "/single",
    response_model=PreprocessSingleResponse,
    summary="Preprocess Satu Teks",
    description=(
        "Membersihkan dan menormalisasi satu kalimat teks melalui pipeline 3 tahapan: "
        "**Regex Cleaning** (hapus URL, @mention, #hashtag symbol, emoji, tanda baca) → "
        "**Case Folding** (huruf kecil) → "
        "**Normalisasi Kamusalay** (ganti slang/typo ke kata baku). "
        "Hasil `clean_text` siap dikonsumsi oleh IndoBERT Tokenizer."
    ),
)
def preprocess_single(body: PreprocessSingleRequest) -> PreprocessSingleResponse:
    """Jalankan pipeline preprocessing pada satu teks."""
    try:
        raw = body.text
        clean = _pipeline.transform_text(raw)
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
        "Setiap teks dijalankan melalui pipeline: "
        "Regex Cleaning → Case Folding → Normalisasi Kamusalay. "
        "Response berisi daftar pasangan `raw_text` dan `clean_text` sesuai urutan input."
    ),
)
def preprocess_batch(body: PreprocessBatchRequest) -> PreprocessBatchResponse:
    """Jalankan pipeline preprocessing pada batch teks."""
    if len(body.texts) > 1000:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Maksimum 1000 teks per request batch.",
        )
    try:
        results = []
        for raw in body.texts:
            clean = _pipeline.transform_text(raw)
            results.append(PreprocessSingleResponse(raw_text=raw, clean_text=clean))

        return PreprocessBatchResponse(total=len(results), data=results)
    except Exception as e:
        logger.error(f"Preprocessing batch error: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Gagal memproses batch teks: {str(e)}",
        )
