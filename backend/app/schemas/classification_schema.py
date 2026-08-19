"""
Pydantic Schema untuk Modul Klasifikasi IndoBERT.

Model yang didefinisikan:
    - PredictSingleRequest:
        - text: str
    - PredictBatchRequest:
        - texts: List[str]
    - SentimentItemResponse:
        - text: str
        - sentiment: str  # "Positif" | "Netral" | "Negatif"
        - confidence: float
        - probabilities: Dict[str, float]
    - PredictBatchResponse:
        - total: int
        - summary: Dict[str, int]
        - data: List[SentimentItemResponse]
"""

# TODO: Definisikan Pydantic BaseModel untuk request & response inferensi IndoBERT
