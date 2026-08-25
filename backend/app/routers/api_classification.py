"""
Router API Klasifikasi IndoBERT (/api/v1/classify).

Endpoints:
    GET  /api/v1/classify/info
         Mengembalikan informasi model IndoBERT yang aktif (device, kelas, status loading).

    POST /api/v1/classify/single
         Memprediksi ujaran kebencian pada satu kalimat teks.
         Output: Level 1 (hate/non-hate) + Level 2 (6 sub-kategori) + confidence score.

    POST /api/v1/classify/batch
         Memprediksi ujaran kebencian pada daftar teks secara batch.
         Output: Hasil per teks + ringkasan statistik (jumlah & persentase hate speech).

Catatan:
    Model IndoBERT (~490 MB) hanya dimuat 1x saat server startup via Lifespan Event
    di main_api.py dan disimpan di request.app.state.predictor.
    Endpoint ini mengambil instance dari app.state agar tidak re-load setiap request.
"""

import logging

from fastapi import APIRouter, HTTPException, Request, status

from src.preprocessing import PreprocessingPipeline
from app.schemas.classification_schema import (
    ClassifySingleRequest,
    ClassifyBatchRequest,
    ClassifyItemResponse,
    ClassifyBatchResponse,
    LevelPrediction,
    ModelInfoResponse,
)

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/classify", tags=["Klasifikasi IndoBERT"])

# Pipeline preprocessing (diinisialisasi sekali, ringan — hanya load kamusalay.csv)
_preprocess_pipeline = PreprocessingPipeline()


# ---------------------------------------------------------------------------
# Helper
# ---------------------------------------------------------------------------

def _get_predictor(request: Request):
    """Ambil instance HateSpeechPredictor dari app.state (dipreload saat startup)."""
    predictor = getattr(request.app.state, "predictor", None)
    if predictor is None:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=(
                "Model IndoBERT belum siap. "
                "Server mungkin masih dalam proses loading (~1-3 menit). "
                "Coba lagi sebentar."
            ),
        )
    return predictor


def _build_classify_item(result: dict) -> ClassifyItemResponse:
    """Konversi dict output HateSpeechPredictor ke ClassifyItemResponse."""
    return ClassifyItemResponse(
        text=result["text"],
        level1=LevelPrediction(
            label=result["level1"]["label"],
            confidence=result["level1"]["confidence"],
            probabilities=result["level1"]["probabilities"],
        ),
        level2=LevelPrediction(
            label=result["level2"]["label"],
            confidence=result["level2"]["confidence"],
            probabilities=result["level2"]["probabilities"],
        ),
        is_hate_speech=result["is_hate_speech"],
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
def get_model_info(request: Request) -> ModelInfoResponse:
    """Kembalikan metadata model IndoBERT yang aktif."""
    predictor = _get_predictor(request)
    loader = predictor.loader

    return ModelInfoResponse(
        model_name=loader.pretrained_model,
        device=loader.device,
        max_length=loader.max_length,
        is_loaded=loader.is_loaded(),
        classes_lvl1=loader.classes_lvl1,
        classes_lvl2=loader.classes_lvl2,
    )


@router.post(
    "/single",
    response_model=ClassifyItemResponse,
    summary="Klasifikasi Satu Teks",
    description=(
        "Memprediksi apakah satu kalimat teks mengandung ujaran kebencian menggunakan "
        "model IndoBERT Hierarchical Classifier. "
        "Jika `preprocess=True` (default), teks akan dipreprocess terlebih dahulu "
        "sebelum dimasukkan ke tokenizer IndoBERT. "
        "Output mencakup prediksi **Level 1** (hate_speech / non_hate_speech) dan "
        "**Level 2** (6 sub-kategori ujaran kebencian) beserta confidence score."
    ),
)
def classify_single(body: ClassifySingleRequest, request: Request) -> ClassifyItemResponse:
    """Klasifikasi ujaran kebencian pada satu teks."""
    predictor = _get_predictor(request)
    try:
        text = body.text
        if body.preprocess:
            text = _preprocess_pipeline.transform_text(text)

        result = predictor.predict_text(text)
        # Pastikan teks yang ditampilkan ke user adalah teks asli (bukan clean)
        result["text"] = body.text
        return _build_classify_item(result)
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
        "Memprediksi ujaran kebencian pada daftar teks secara batch. "
        "Jika `preprocess=True` (default), setiap teks akan dipreprocess sebelum inferensi. "
        "Response mencakup hasil per teks beserta ringkasan statistik "
        "(jumlah & persentase hate speech dari total input)."
    ),
)
def classify_batch(body: ClassifyBatchRequest, request: Request) -> ClassifyBatchResponse:
    """Klasifikasi ujaran kebencian pada batch teks."""
    if len(body.texts) > 500:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail="Maksimum 500 teks per request batch klasifikasi.",
        )

    predictor = _get_predictor(request)
    try:
        raw_texts = body.texts

        # Preprocessing (opsional)
        if body.preprocess:
            processed_texts = [_preprocess_pipeline.transform_text(t) for t in raw_texts]
        else:
            processed_texts = raw_texts

        # Prediksi batch
        results = predictor.predict_batch(processed_texts)

        # Bangun response items (gunakan teks asli untuk ditampilkan)
        items = []
        for i, res in enumerate(results):
            res["text"] = raw_texts[i]  # tampilkan teks asli (sebelum preprocess)
            items.append(_build_classify_item(res))

        # Hitung statistik ringkas
        hate_count = sum(1 for item in items if item.is_hate_speech)
        total = len(items)
        hate_pct = round(hate_count / total * 100, 2) if total > 0 else 0.0

        return ClassifyBatchResponse(
            total=total,
            hate_speech_count=hate_count,
            non_hate_speech_count=total - hate_count,
            hate_speech_pct=hate_pct,
            data=items,
        )
    except Exception as e:
        logger.error(f"Classify batch error: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Gagal melakukan klasifikasi batch: {str(e)}",
        )
