"""
Classification Service — Application Service Layer.

Mengelola:
1. Inferensi teks tunggal dan batch menggunakan HateSpeechPredictor IndoBERT.
2. Orkestrasi preprocessing teks (pembersihan & normalisasi Kamusalay).
3. Agregasi statistik hate speech (jumlah hate/non-hate, persentase).
"""

import logging
from typing import Any, Dict, List, Optional

from src.preprocessing.pipeline import PreprocessingPipeline
from app.schemas.classification_schema import (
    ClassifyBatchResponse,
    ClassifyItemResponse,
    LevelPrediction,
    ModelInfoResponse,
)

logger = logging.getLogger(__name__)


class ClassificationService:
    """Service untuk menangani inferensi klasifikasi IndoBERT dan kalkulasi statistik."""

    def __init__(self):
        # Inisialisasi pipeline preprocessing (ringan — hanya kamusalay.csv)
        self._preprocessor = PreprocessingPipeline()
        self._predictor: Optional[Any] = None

    def set_predictor(self, predictor: Optional[Any]) -> None:
        """Menyimpan predictor yang dipreload oleh lifespan aplikasi."""
        self._predictor = predictor

    def is_predictor_ready(self) -> bool:
        """Menandai apakah predictor telah diinjeksikan dan siap digunakan."""
        return self._predictor is not None

    def _get_predictor(self) -> Any:
        """Mengembalikan predictor bersama tanpa membuat instance model baru."""
        if self._predictor is None:
            raise RuntimeError("Model IndoBERT belum diinisialisasi.")
        return self._predictor

    def get_model_info(self) -> ModelInfoResponse:
        """Mengambil metadata arsitektur IndoBERT dari model loader."""
        loader = self._get_predictor().loader
        return ModelInfoResponse(
            model_name=loader.pretrained_model,
            device=loader.device,
            max_length=loader.max_length,
            is_loaded=loader.is_loaded(),
            classes_lvl1=loader.classes_lvl1,
            classes_lvl2=loader.classes_lvl2,
        )

    def classify_single_text(
        self,
        text: str,
        preprocess: bool = True,
    ) -> ClassifyItemResponse:
        """Melakukan klasifikasi teks tunggal dengan preprocessing opsional."""
        predictor = self._get_predictor()
        clean_text = self._preprocessor.transform_text(text) if preprocess else text
        result = predictor.predict_text(clean_text)
        result["text"] = clean_text
        return self._build_classify_item(result)

    def classify_batch_texts(
        self,
        texts: List[str],
        preprocess: bool = True,
    ) -> ClassifyBatchResponse:
        """Melakukan klasifikasi teks kumpulan batch dan menghitung ringkasan statistik."""
        predictor = self._get_predictor()
        if preprocess:
            processed_texts = [self._preprocessor.transform_text(t) for t in texts]
        else:
            processed_texts = texts

        results = predictor.predict_batch(processed_texts)

        items = []
        for i, res in enumerate(results):
            res["text"] = texts[i]  # Tampilkan teks asli sebelum preprocess
            items.append(self._build_classify_item(res))

        total = len(items)
        hate_count = sum(1 for item in items if item.is_hate_speech)
        hate_pct = round(hate_count / total * 100, 2) if total > 0 else 0.0

        return ClassifyBatchResponse(
            total=total,
            hate_speech_count=hate_count,
            non_hate_speech_count=total - hate_count,
            hate_speech_pct=hate_pct,
            data=items,
        )

    @staticmethod
    def _build_classify_item(result: Dict[str, Any]) -> ClassifyItemResponse:
        """Konversi dict output IndoBERT ke ClassifyItemResponse DTO."""
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


# Singleton instance
classification_service = ClassificationService()
