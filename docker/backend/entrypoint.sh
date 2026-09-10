#!/bin/bash
# ==============================================================================
# entrypoint.sh — Backend FastAPI Startup Script
# ==============================================================================
# Dieksekusi setiap kali container backend dimulai.
# Memastikan semua direktori penting tersedia dan menjalankan Uvicorn server.
#
# Fitur baru: Virtual Display (Xvfb) + VNC + noVNC
#   - Xvfb membuat virtual screen :99 agar Playwright bisa buka browser GUI
#   - x11vnc mengekspor virtual screen via protokol VNC (port 5900, lokal only)
#   - websockify + noVNC mengekspor VNC ke WebSocket (port 6080)
#   - User bisa login scraper via browser: http://localhost:6080
# ==============================================================================

set -e  # Exit on error

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  HATESENSE ID LAB — FastAPI Backend Startup                 ║"
echo "╚══════════════════════════════════════════════════════════════╝"

# ── 1. Pastikan direktori storage tersedia ────────────────────────────────────
echo "[1/5] Memeriksa direktori storage..."
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
echo "[2/5] Memeriksa model IndoBERT..."
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

# ── 3. Jalankan Virtual Display (Xvfb) + VNC + noVNC ─────────────────────────
echo "[3/5] Menginisialisasi Virtual Display untuk Scraper GUI..."

# Bersihkan lock file lama jika ada (bisa terjadi setelah container crash)
rm -f /tmp/.X99-lock /tmp/.X11-unix/X99 2>/dev/null || true

# Jalankan Xvfb — virtual display :99 dengan resolusi 1280x800
Xvfb :99 -screen 0 1280x800x24 -ac +extension GLX +render -noreset &
XVFB_PID=$!
echo "  ✓ Xvfb virtual display :99 dimulai (PID: $XVFB_PID)"

# Tunggu Xvfb siap
sleep 1

# Set DISPLAY agar Playwright dan semua proses X11 pakai virtual display ini
export DISPLAY=:99

# Jalankan x11vnc — ekspor Xvfb ke protokol VNC (port 5900, lokal saja)
x11vnc -display :99 \
    -nopw \
    -listen localhost \
    -xkb \
    -forever \
    -shared \
    -quiet \
    -bg \
    -rfbport 5900
echo "  ✓ x11vnc VNC server aktif (port 5900, internal)"

# Cari lokasi noVNC (bisa beda tergantung distro/instalasi)
NOVNC_DIR="/usr/share/novnc"
if [ ! -d "$NOVNC_DIR" ]; then
    NOVNC_DIR="/usr/share/novnc/utils"
fi

# Buat index.html → vnc.html agar localhost:6080 langsung buka VNC viewer
# (tanpa ini, websockify hanya nampilin directory listing)
if [ ! -f "${NOVNC_DIR}/index.html" ]; then
    ln -sf "${NOVNC_DIR}/vnc.html" "${NOVNC_DIR}/index.html"
    echo "  ✓ noVNC index.html → vnc.html symlink dibuat"
fi

# Jalankan websockify + noVNC — bridge VNC ke WebSocket untuk akses via browser
websockify \
    --web /usr/share/novnc \
    --daemon \
    --log-file /tmp/websockify.log \
    6080 \
    localhost:5900
echo "  ✓ noVNC aktif — akses browser scraper di: http://localhost:6080"
echo "    (Buka setelah klik Re-Authenticate di dashboard)"

# ── 4. Periksa konfigurasi environment ───────────────────────────────────────
echo "[4/5] Memeriksa environment..."
echo "  APP_ENV : ${APP_ENV:-development}"
echo "  Port    : 8080 (API) | 6080 (noVNC)"

# Aktifkan Offline Mode HuggingFace jika cache model sudah ada
# (mencegah request jaringan ke HF Hub setiap startup = startup lebih cepat)
HF_CACHE_DIR="${HF_HOME:-/root/.cache/huggingface}/hub"
INDOBERT_CACHE_PATTERN="${HF_CACHE_DIR}/models--indobenchmark*"
if ls ${INDOBERT_CACHE_PATTERN} > /dev/null 2>&1; then
    export HF_HUB_OFFLINE=1
    echo "  ✓ HuggingFace cache ditemukan. Offline mode aktif (startup lebih cepat)."
else
    echo "  ⟳ HuggingFace cache belum ada. Download pertama akan dilakukan (butuh waktu)."
fi

# ── 5. Jalankan FastAPI Uvicorn Server ───────────────────────────────────────
echo "[5/5] Menjalankan FastAPI Uvicorn server..."
echo ""
echo "  ➜  API URL  : http://localhost:8080/"
echo "  ➜  Docs     : http://localhost:8080/docs"
echo "  ➜  noVNC    : http://localhost:6080"
echo ""
echo "  Memuat model IndoBERT ke memori (bisa 30-60 detik)..."
echo ""

cd /app

exec uvicorn main_api:app \
    --host 0.0.0.0 \
    --port 8080 \
    --reload \
    --reload-dir /app \
    --log-level info
