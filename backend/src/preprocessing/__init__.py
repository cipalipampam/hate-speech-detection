"""
Modul Preprocessing Teks — src/preprocessing/__init__.py

Menyediakan pembersihan teks mentah (cleaning), normalisasi kata slang (kamusalay),
dan pipeline terpadu yang siap dikonsumsi oleh modul klasifikasi IndoBERT.

Ekspor Publik:
    Dari cleaner.py  : clean_text, case_folding, remove_duplicates
    Dari normalizer.py: SlangNormalizer, load_slang_dictionary
    Dari pipeline.py : PreprocessingPipeline, preprocess_text
"""

from .cleaner    import clean_text, case_folding, remove_duplicates
from .normalizer import SlangNormalizer, load_slang_dictionary
from .pipeline   import PreprocessingPipeline, preprocess_text

__all__ = [
    # Cleaner
    "clean_text",
    "case_folding",
    "remove_duplicates",
    # Normalizer
    "SlangNormalizer",
    "load_slang_dictionary",
    # Pipeline
    "PreprocessingPipeline",
    "preprocess_text",
]
