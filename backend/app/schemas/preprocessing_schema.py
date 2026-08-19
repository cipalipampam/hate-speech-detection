"""
Pydantic Schema untuk Modul Preprocessing.

Model yang didefinisikan:
    - PreprocessSingleRequest:
        - text: str
    - PreprocessBatchRequest:
        - texts: List[str]
    - PreprocessSingleResponse:
        - raw_text: str
        - clean_text: str
    - PreprocessBatchResponse:
        - total: int
        - data: List[PreprocessSingleResponse]
"""

# TODO: Definisikan Pydantic BaseModel untuk request & response preprocessing
