"""
FastAPI Server Entrypoint — Sistem Analisis Ujaran Kebencian.

Deskripsi:
    Server REST API untuk sistem deteksi hate speech menggunakan IndoBERT.
    Membungkus seluruh logika backend (auth, scraping, preprocessing, klasifikasi,
    dan pipeline end-to-end) sebagai HTTP endpoints yang dapat diakses oleh
    Frontend Laravel atau klien HTTP lainnya.

Fitur:
    - Lifespan Event: Model IndoBERT (~490 MB) dimuat 1x saat startup ke app.state
    - CORS Middleware: Mengizinkan semua origin (cocok untuk dev + Laravel frontend)
    - Swagger UI Otomatis: http://localhost:8000/docs
    - ReDoc Otomatis   : http://localhost:8000/redoc

Struktur Endpoint:
    /api/v1/auth/         → Autentikasi & sesi browser (X & Threads)
    /api/v1/scrape/       → Scraping data media sosial (background job)
    /api/v1/preprocess/   → Pembersihan & normalisasi teks
    /api/v1/classify/     → Inferensi IndoBERT (hate speech detection)
    /api/v1/pipeline/     → Analisis lengkap end-to-end + download hasil CSV

Cara Menjalankan:
    cd d:/skripsi/Project/hate-speech-detection/backend
    uvicorn main_api:app --reload --host 0.0.0.0 --port 8000
"""

import logging
import sys
from contextlib import asynccontextmanager
from pathlib import Path

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

# Pastikan direktori backend ada di sys.path
BACKEND_DIR = Path(__file__).resolve().parent
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

# Import routers
from app.routers.api_auth import router as auth_router
from app.routers.api_scraper import router as scraper_router
from app.routers.api_preprocessing import router as preprocessing_router
from app.routers.api_classification import router as classification_router
from app.routers.api_pipeline import router as pipeline_router

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s — %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("main_api")


# ---------------------------------------------------------------------------
# Lifespan Event: Preload Model IndoBERT saat startup
# ---------------------------------------------------------------------------

@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    Context manager untuk Lifespan Event FastAPI.

    Startup:
        Memuat model IndoBERT (~490 MB) ke memori GPU/CPU satu kali.
        Instance disimpan di app.state.predictor agar bisa diakses oleh semua router
        tanpa re-load berulang yang memakan waktu dan RAM.

    Shutdown:
        Membersihkan resource (opsional — Python GC menangani dealokasi memori).
    """
    # ── STARTUP ──────────────────────────────────────────────────────────────
    logger.info("=" * 60)
    logger.info("Memulai FastAPI Server — Sistem Analisis Ujaran Kebencian")
    logger.info("=" * 60)

    try:
        logger.info("Memuat model IndoBERT Hierarchical Classifier ke memori...")
        from src.classification.predictor import HateSpeechPredictor
        predictor = HateSpeechPredictor()
        app.state.predictor = predictor
        loader = predictor.loader
        logger.info(
            f"Model IndoBERT berhasil dimuat! "
            f"Device: {loader.device.upper()}, "
            f"Level 1: {len(loader.classes_lvl1)} kelas, "
            f"Level 2: {len(loader.classes_lvl2)} kelas."
        )
    except Exception as e:
        logger.error(f"GAGAL memuat model IndoBERT: {e}", exc_info=True)
        logger.warning(
            "Server tetap berjalan, namun endpoint klasifikasi & pipeline tidak tersedia "
            "sampai model berhasil dimuat. Periksa file 'saved_models/best_model.pt'."
        )
        app.state.predictor = None

    logger.info("Server siap menerima request.")
    logger.info("Dokumentasi API: http://localhost:8000/docs")
    logger.info("=" * 60)

    yield  # ← Server aktif melayani request

    # ── SHUTDOWN ─────────────────────────────────────────────────────────────
    logger.info("Server shutdown. Membersihkan resource...")
    app.state.predictor = None
    logger.info("Server berhasil dimatikan.")


# ---------------------------------------------------------------------------
# Inisialisasi Aplikasi FastAPI
# ---------------------------------------------------------------------------

app = FastAPI(
    title="🛡️ Sistem Analisis Ujaran Kebencian API",
    description=(
        "REST API Backend untuk sistem deteksi hate speech pada media sosial X (Twitter) & Threads (Meta). "
        "Menggunakan model **IndoBERT Hierarchical Classifier** yang di-fine-tune untuk "
        "klasifikasi 2 level: Level 1 (hate/non-hate) dan Level 2 (6 sub-kategori ujaran kebencian). "
        "\n\n"
        "### Alur Kerja\n"
        "1. **Auth** — Pastikan sesi browser X & Threads aktif\n"
        "2. **Scrape** — Kumpulkan postingan dari media sosial berdasarkan keyword\n"
        "3. **Preprocess** — Bersihkan & normalisasi teks dengan Kamusalay dictionary\n"
        "4. **Classify** — Deteksi hate speech menggunakan IndoBERT\n"
        "5. **Pipeline** — Jalankan semua langkah di atas secara otomatis\n"
        "\n"
        "**Proyek Skripsi** — Universitas | Teknik Informatika"
    ),
    version="1.0.0",
    contact={
        "name": "Backend AI & Data Pipeline",
    },
    lifespan=lifespan,
    docs_url="/docs",
    redoc_url="/redoc",
    openapi_url="/openapi.json",
)


# ---------------------------------------------------------------------------
# CORS Middleware
# ---------------------------------------------------------------------------

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],          # Development: izinkan semua origin
    allow_credentials=True,
    allow_methods=["*"],          # GET, POST, PUT, DELETE, OPTIONS, dll.
    allow_headers=["*"],          # Content-Type, Authorization, dll.
)


# ---------------------------------------------------------------------------
# Registrasi Routers
# ---------------------------------------------------------------------------

API_PREFIX = "/api/v1"

app.include_router(auth_router,           prefix=API_PREFIX)
app.include_router(scraper_router,        prefix=API_PREFIX)
app.include_router(preprocessing_router,  prefix=API_PREFIX)
app.include_router(classification_router, prefix=API_PREFIX)
app.include_router(pipeline_router,       prefix=API_PREFIX)


# ---------------------------------------------------------------------------
# Root Endpoint (Health Check)
# ---------------------------------------------------------------------------

@app.get("/", tags=["Health Check"], summary="Health Check & Info Server")
def root():
    """
    Endpoint root untuk health check.
    Mengembalikan status server dan link ke dokumentasi API.
    """
    from src.classification.model_loader import ModelLoader
    model_loaded = (
        app.state.predictor is not None
        and hasattr(app.state.predictor, "loader")
        and app.state.predictor.loader.is_loaded()
    )

    return {
        "status": "online",
        "service": "Sistem Analisis Ujaran Kebencian — FastAPI Backend",
        "version": "1.0.0",
        "model_status": "loaded" if model_loaded else "not_loaded",
        "docs": "/docs",
        "redoc": "/redoc",
        "endpoints": {
            "auth":         f"{API_PREFIX}/auth",
            "scrape":       f"{API_PREFIX}/scrape",
            "preprocess":   f"{API_PREFIX}/preprocess",
            "classify":     f"{API_PREFIX}/classify",
            "pipeline":     f"{API_PREFIX}/pipeline",
        },
    }
