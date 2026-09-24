"""
FastAPI Routers Package.

Menyimpan definisi endpoint REST API berdasarkan domain modul masing-masing.
"""

from .api_auth import router as auth_router
from .api_classification import router as classification_router
from .api_pipeline import router as pipeline_router

__all__ = [
    "auth_router",
    "classification_router",
    "pipeline_router",
]
