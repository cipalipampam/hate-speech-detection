# ==============================================================================
# Dockerfile — Frontend Laravel 13 (PHP 8.4) + Node.js 20 + Composer
# ==============================================================================
# Base: php:8.4-cli (Debian Bookworm)
# Tambahan:
#   - Node.js 20 LTS (untuk npm & Vite build)
#   - Composer 2.x (PHP package manager)
#   - Ekstensi PHP: pdo_mysql, mbstring, exif, pcntl, bcmath, gd, zip, intl
# ==============================================================================

FROM php:8.4-cli

# ── Labels ────────────────────────────────────────────────────────────────────
LABEL maintainer="HateSense ID Lab"
LABEL description="Laravel 13 + Vite + PHP 8.4 Frontend Container"

# ── Environment ───────────────────────────────────────────────────────────────
ENV DEBIAN_FRONTEND=noninteractive \
    NODE_VERSION=20 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/root/.composer

# ── System Dependencies + PHP Extensions ─────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    zip \
    ca-certificates \
    gnupg \
    libpng-dev \
    libjpeg-dev \
    libfreetype-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Install Node.js 20 LTS ────────────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && node --version \
    && npm --version

# ── Install Composer ──────────────────────────────────────────────────────────
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

# ── Working Directory ─────────────────────────────────────────────────────────
WORKDIR /var/www/html

# ── Buat direktori storage Laravel dengan permission yang benar ───────────────
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ── Copy Entrypoint ───────────────────────────────────────────────────────────
COPY docker/frontend/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

# ── Source code di-mount via volume (development mode) ───────────────────────
# COPY . .   ← Uncomment untuk production image self-contained

# ── PHP Config Optimasi ───────────────────────────────────────────────────────
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/custom.ini

# ── Expose Port ───────────────────────────────────────────────────────────────
EXPOSE 8000

# ── Entrypoint ────────────────────────────────────────────────────────────────
ENTRYPOINT ["/entrypoint.sh"]
