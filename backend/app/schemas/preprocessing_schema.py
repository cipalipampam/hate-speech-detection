"""
Pydantic Schema untuk Modul Preprocessing API.

Model:
    - PreprocessSingleRequest  : Body request POST /api/v1/preprocess/single
    - PreprocessSingleResponse : Hasil preprocessing 1 teks (raw vs clean)
    - PreprocessBatchRequest   : Body request POST /api/v1/preprocess/batch
    - PreprocessBatchResponse  : Hasil preprocessing list teks
"""

from typing import List
from pydantic import BaseModel, Field


# ---------------------------------------------------------------------------
# Request Models
# ---------------------------------------------------------------------------

class PreprocessSingleRequest(BaseModel):
    """Request preprocessing untuk satu kalimat teks."""

    text: str = Field(
        ...,
        min_length=1,
        description="Teks mentah yang akan dibersihkan dan dinormalisasi.",
        examples=["bgt tolol ga jelas pelayanannya!! 😡 #kecewa"],
    )

    class Config:
        json_schema_extra = {
            "example": {
                "text": "pelayanan bgt ga jelas!! parah bgt 😡 #kecewa @tokoh_publik"
            }
        }


class PreprocessBatchRequest(BaseModel):
    """Request preprocessing untuk kumpulan teks secara batch."""

    texts: List[str] = Field(
        ...,
        min_length=1,
        description="Daftar teks mentah yang akan dipreprocess. Maksimum 1000 item.",
        examples=[["bgt tolol ga jelas", "ini mah keren bgt!", "biasa aja sih"]],
    )

    class Config:
        json_schema_extra = {
            "example": {
                "texts": [
                    "pelayanan bgt ga jelas!! parah bgt 😡",
                    "keren sih produknya, recommended!",
                    "yg ini mah biasa aja ga ada yg spesial",
                ]
            }
        }


# ---------------------------------------------------------------------------
# Response Models
# ---------------------------------------------------------------------------

class PreprocessSingleResponse(BaseModel):
    """Hasil preprocessing satu teks — perbandingan raw vs clean."""

    raw_text: str = Field(description="Teks asli sebelum dipreprocess.")
    clean_text: str = Field(
        description=(
            "Teks bersih hasil pipeline: "
            "Regex Cleaning → Case Folding → Normalisasi Kamusalay. "
            "Siap digunakan sebagai input IndoBERT Tokenizer."
        )
    )


class PreprocessBatchResponse(BaseModel):
    """Hasil preprocessing batch teks."""

    total: int = Field(description="Jumlah teks yang berhasil dipreprocess.")
    data: List[PreprocessSingleResponse] = Field(
        description="Daftar hasil preprocessing per teks, sesuai urutan input."
    )
