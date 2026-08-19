"""
Modul Klasifikasi IndoBERT — src/classification/__init__.py

Menyediakan pemuatan model IndoBERT fine-tuned (singleton) dan inferensi
klasifikasi ujaran kebencian hierarkis (Level 1 & Level 2).

Ekspor Publik:
    Dari model_loader.py : ModelLoader, IndoBERTHierarchicalClassifier, ClassificationHead
    Dari predictor.py    : HateSpeechPredictor
"""

from .model_loader import ModelLoader, IndoBERTHierarchicalClassifier, ClassificationHead
from .predictor    import HateSpeechPredictor

__all__ = [
    # Model Loader
    "ModelLoader",
    "IndoBERTHierarchicalClassifier",
    "ClassificationHead",
    # Predictor
    "HateSpeechPredictor",
]
