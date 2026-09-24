"""Classification Service: inferensi IndoBERT satu teks (/classify/single)."""

import logging
from typing import Any, Dict, Optional

from src.preprocessing.pipeline import PreprocessingPipeline
from app.schemas.classification_schema import (
    ClassifyItemResponse,
    LevelPrediction,
)

logger = logging.getLogger(__name__)


class ClassificationService:
    """Service untuk inferensi klasifikasi IndoBERT (satu teks per request)."""

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
