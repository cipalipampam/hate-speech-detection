#!/bin/bash
# ==============================================================================
# entrypoint.sh — Backend FastAPI Startup Script
# ==============================================================================
# Dieksekusi setiap kali container backend dimulai.
# Memastikan semua direktori penting tersedia dan menjalankan Uvicorn server.
# ==============================================================================

set -e  # Exit on error

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  HATESENSE ID LAB — FastAPI Backend Startup                 ║"
echo "╚══════════════════════════════════════════════════════════════╝"

# ── 1. Pastikan direktori storage tersedia ────────────────────────────────────
echo "[1/4] Memeriksa direktori storage..."
mkdir -p /app/storage/sessions/x_profile
mkdir -p /app/storage/sessions/threads_profile
mkdir -p /app/storage/exports
mkdir -p /app/storage/dictionaries

# Pastikan kamusalay.csv ada (kamus normalisasi bahasa alay)
if [ ! -f /app/storage/dictionaries/kamusalay.csv ]; then
    echo "  ⚠  kamusalay.csv tidak ditemukan di storage/dictionaries/"
    echo "     Preprocessing normalisasi slang tidak akan berfungsi optimal."
fi
echo "  ✓ Direktori storage siap."

# ── 2. Periksa keberadaan model IndoBERT ─────────────────────────────────────
echo "[2/4] Memeriksa model IndoBERT..."
if [ ! -f /app/saved_models/best_model.pt ]; then
    echo "  ⚠  PERINGATAN: File 'saved_models/best_model.pt' tidak ditemukan!"
    echo "     Server akan tetap berjalan, namun endpoint klasifikasi TIDAK tersedia."
    echo "     Pastikan file best_model.pt (~499MB) ada di folder backend/saved_models/"
else
    MODEL_SIZE=$(stat -c%s /app/saved_models/best_model.pt 2>/dev/null || echo "0")
    MODEL_MB=$((MODEL_SIZE / 1048576))
    echo "  ✓ Model ditemukan: best_model.pt (${MODEL_MB} MB)"
fi

# ── 3. Periksa konfigurasi environment ───────────────────────────────────────
echo "[3/4] Memeriksa environment..."
echo "  APP_ENV : ${APP_ENV:-development}"
echo "  Port    : 8080"

# ── 4. Jalankan FastAPI Uvicorn Server ───────────────────────────────────────
echo "[4/4] Menjalankan FastAPI Uvicorn server..."
echo ""
echo "  ➜  API URL  : http://localhost:8080/"
echo "  ➜  Docs     : http://localhost:8080/docs"
echo "  ➜  ReDoc    : http://localhost:8080/redoc"
echo ""
echo "  Memuat model IndoBERT ke memori (bisa 30-60 detik)..."
echo ""

cd /app

# Linux tidak butuh ProactorEventLoop (itu khusus Windows)
# Saat di Linux/Docker, event loop default asyncio sudah mendukung subprocess
exec uvicorn main_api:app \
    --host 0.0.0.0 \
    --port 8080 \
    --reload \
    --reload-dir /app \
    --log-level info
