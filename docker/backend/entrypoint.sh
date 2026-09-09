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
MODEL_PATH="/app/saved_models/best_model.pt"
TMP_PATH="/app/saved_models/best_model.pt.tmp"

if [ ! -f "$MODEL_PATH" ]; then
    echo "  ⟳ File 'saved_models/best_model.pt' belum ada di direktori lokal."
    DOWNLOAD_URL="${MODEL_DOWNLOAD_URL:-https://github.com/cipalipampam/hate-speech-detection/releases/latest/download/best_model.pt}"
    echo "     Mengunduh otomatis dari GitHub Releases..."
    echo "     URL: ${DOWNLOAD_URL}"
    mkdir -p /app/saved_models

    if curl -L -f --progress-bar -o "$TMP_PATH" "$DOWNLOAD_URL"; then
        FILE_SIZE=$(stat -c%s "$TMP_PATH" 2>/dev/null || echo "0")
        # Validasi: file harus minimal 400 MB (400000000 bytes) agar bukan error response HTML/JSON
        if [ "$FILE_SIZE" -gt 400000000 ]; then
            mv "$TMP_PATH" "$MODEL_PATH"
            echo "  ✓ Berhasil mengunduh best_model.pt!"
        else
            echo "  ⚠  Ukuran file terunduh tidak sesuai ($((FILE_SIZE / 1048576)) MB)."
            echo "     Kemungkinan release belum di-publish atau link belum siap."
            rm -f "$TMP_PATH"
        fi
    else
        echo "  ⚠  Gagal mengunduh model via curl (HTTP error atau masalah jaringan)."
        rm -f "$TMP_PATH"
    fi
fi

if [ -f "$MODEL_PATH" ]; then
    MODEL_SIZE=$(stat -c%s "$MODEL_PATH" 2>/dev/null || echo "0")
    MODEL_MB=$((MODEL_SIZE / 1048576))
    echo "  ✓ Model IndoBERT siap: best_model.pt (${MODEL_MB} MB)"
else
    echo "  ⚠  PERINGATAN: Model best_model.pt belum tersedia."
    echo "     Server tetap berjalan, namun endpoint klasifikasi akan mengembalikan status model belum dimuat."
    echo "     Jika upload release di GitHub sudah selesai, restart container untuk mencoba unduh ulang."
    echo "     Atau salin file best_model.pt (~499MB) secara manual ke backend/saved_models/"
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
