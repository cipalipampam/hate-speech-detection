# ==============================================================================
# Dockerfile — Backend FastAPI + IndoBERT PyTorch + Playwright Headless
# ==============================================================================
# Base: python:3.10-slim (Debian Bookworm, minimal footprint)
# Instalasi:
#   - System libs untuk PyTorch CPU/GPU, OpenCV, Chromium Playwright
#   - Python packages dari requirements.txt
#   - Playwright Chromium browser + dependencies
# ==============================================================================

FROM python:3.10-slim

# ── Labels ────────────────────────────────────────────────────────────────────
LABEL maintainer="HateSense ID Lab"
LABEL description="FastAPI + IndoBERT Hierarchical Classifier + Playwright"

# ── Environment Variables ─────────────────────────────────────────────────────
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
    PIP_NO_CACHE_DIR=0 \
    PIP_CACHE_DIR=/root/.cache/pip

# ── System Dependencies ───────────────────────────────────────────────────────
# Playwright Chromium butuh: libglib2.0, libnss3, libdbus-1, dll.
# curl dibutuhkan untuk healthcheck di docker-compose.yml
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Utilitas dasar
    curl \
    wget \
    ca-certificates \
    git \
    # Lib untuk Playwright/Chromium
    libglib2.0-0 \
    libnss3 \
    libnspr4 \
    libdbus-1-3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libcairo2 \
    libatspi2.0-0 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxext6 \
    libxrender1 \
    libgtk-3-0 \
    fonts-liberation \
    fonts-noto-cjk \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Working Directory ─────────────────────────────────────────────────────────
WORKDIR /app

# ── Install Python Dependencies ───────────────────────────────────────────────
# Copy requirements dulu (layer cache: hanya rebuild saat requirements berubah)
COPY requirements.txt .
RUN pip install --upgrade pip \
    && pip install -r requirements.txt

# ── Install Playwright Chromium Browser ──────────────────────────────────────
# Scraping X (Twitter) dan Threads menggunakan Playwright persistent context
RUN playwright install chromium \
    && playwright install-deps chromium

# ── Buat Direktori Storage yang Diperlukan ────────────────────────────────────
RUN mkdir -p \
    /app/storage/sessions/x_profile \
    /app/storage/sessions/threads_profile \
    /app/storage/exports \
    /app/storage/dictionaries \
    /app/saved_models

# ── Copy Entrypoint Script ────────────────────────────────────────────────────
COPY ../docker/backend/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

# ── Source code di-mount via volume, bukan di-copy ke image ──────────────────
# (Development mode: perubahan kode langsung terlihat tanpa rebuild image)
# COPY . .   ← Uncomment ini jika ingin production image yang self-contained

# ── Expose Port FastAPI ───────────────────────────────────────────────────────
EXPOSE 8080

# ── Entrypoint ────────────────────────────────────────────────────────────────
ENTRYPOINT ["/entrypoint.sh"]
