# Python 3.10 reached end-of-life on 2026-10-01. 3.15.1 is the newest
# fully published slim image; Python 3.15.2 can still be used for local work.
ARG PYTHON_VERSION=3.15.1
FROM python:${PYTHON_VERSION}-slim

LABEL maintainer="HateSense ID Lab"
LABEL description="FastAPI + IndoBERT Hierarchical Classifier + Playwright + noVNC"

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
    PIP_NO_CACHE_DIR=0 \
    PIP_CACHE_DIR=/root/.cache/pip \
    DISPLAY=:99

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    wget \
    ca-certificates \
    git \
    procps \
    net-tools \
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
    xvfb \
    x11vnc \
    novnc \
    websockify \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY backend/requirements.txt .
RUN pip install --upgrade pip \
    && pip install -r requirements.txt

RUN playwright install chromium \
    && playwright install-deps chromium

RUN mkdir -p \
    /app/storage/sessions/x_profile \
    /app/storage/sessions/threads_profile \
    /app/storage/exports \
    /app/storage/dictionaries \
    /app/saved_models

COPY docker/backend/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 8080
EXPOSE 6080

ENTRYPOINT ["/entrypoint.sh"]
