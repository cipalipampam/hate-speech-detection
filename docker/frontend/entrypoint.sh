#!/bin/bash
# ==============================================================================
# entrypoint.sh — Frontend Laravel Auto-Setup & Startup Script
# ==============================================================================
# Script ini dijalankan SETIAP KALI container frontend dimulai.
# Secara otomatis menangani setup lengkap untuk FRESH CLONE:
#   - Composer install (jika vendor/ belum ada)
#   - .env generation (jika belum ada)
#   - APP_KEY generation (jika kosong)
#   - Wait for MySQL (hingga database siap menerima koneksi)
#   - php artisan migrate --force
#   - php artisan db:seed --force (jika tabel users masih kosong)
#   - npm install + npm run build (jika node_modules/ atau public/build belum ada)
#   - php artisan storage:link
#   - php artisan serve (server laravel)
# ==============================================================================

set -e

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  HATESENSE ID LAB — Laravel Frontend Auto-Setup             ║"
echo "╚══════════════════════════════════════════════════════════════╝"

cd /var/www/html

# ── STEP 1: Generate .env dari environment variables Docker ───────────────────
echo ""
echo "[1/8] Menyiapkan file .env..."

if [ ! -f ".env" ]; then
    echo "  ⟳ .env tidak ditemukan (fresh clone). Membuat .env dari variabel Docker..."
    cat > .env <<EOF
APP_NAME="${APP_NAME:-HateSense ID Lab}"
APP_ENV=${APP_ENV:-local}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-true}
APP_URL=${APP_URL:-http://localhost:8000}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=${LOG_LEVEL:-info}

DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-HateSpeech}
DB_USERNAME=${DB_USERNAME:-hatespeech_user}
DB_PASSWORD=${DB_PASSWORD:-hatespeech_pass}

SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}

CACHE_STORE=${CACHE_STORE:-database}

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="HateSense ID"

VITE_APP_NAME="HateSense ID Lab"

FASTAPI_BASE_URL=${FASTAPI_BASE_URL:-http://backend:8080/api/v1}
FASTAPI_TIMEOUT=${FASTAPI_TIMEOUT:-120}
EOF
    echo "  ✓ .env berhasil dibuat dari environment variables Docker."
else
    echo "  ✓ File .env sudah ada. Konfigurasi dipertahankan."
fi

# ── STEP 2: Composer Install (hanya jika vendor/ belum ada) ──────────────────
echo ""
echo "[2/8] Memeriksa PHP dependencies (Composer)..."

if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "  ⟳ vendor/ tidak ditemukan. Menjalankan composer install..."
    composer install \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --ignore-platform-req=php \
        --no-dev
    echo "  ✓ composer install selesai."
else
    echo "  ✓ vendor/ sudah ada. Skip composer install."
fi

# ── STEP 3: Generate APP_KEY (jika kosong) ────────────────────────────────────
echo ""
echo "[3/8] Memeriksa APP_KEY..."

APP_KEY_VALUE=$(grep -E "^APP_KEY=" .env | cut -d= -f2 | tr -d '"' | tr -d "'" | tr -d ' ')
if [ -z "$APP_KEY_VALUE" ] || [ "$APP_KEY_VALUE" = "null" ]; then
    echo "  ⟳ APP_KEY kosong. Mengenerate key baru..."
    php artisan key:generate --force --ansi
    echo "  ✓ APP_KEY berhasil digenerate."
else
    echo "  ✓ APP_KEY sudah ada."
fi

# ── STEP 4: Tunggu MySQL siap ─────────────────────────────────────────────────
echo ""
echo "[4/8] Menunggu MySQL database siap..."

MAX_RETRIES=60
RETRY_COUNT=0
RETRY_INTERVAL=3

until php -r "
    try {
        new PDO(
            'mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-HateSpeech}',
            '${DB_USERNAME:-hatespeech_user}',
            '${DB_PASSWORD:-hatespeech_pass}',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        exit(0);
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "  ✗ MySQL tidak bisa dihubungi setelah ${MAX_RETRIES} percobaan. Keluar."
        exit 1
    fi
    echo "  ⟳ MySQL belum siap (percobaan ke-${RETRY_COUNT}/${MAX_RETRIES}). Menunggu ${RETRY_INTERVAL}s..."
    sleep $RETRY_INTERVAL
done

echo "  ✓ MySQL siap menerima koneksi."

# ── STEP 5: Jalankan Migrasi ──────────────────────────────────────────────────
echo ""
echo "[5/8] Menjalankan database migration..."
php artisan migrate --force --ansi
echo "  ✓ Migrasi selesai."

# ── STEP 6: Seed database (jika fresh / tabel users kosong) ──────────────────
echo ""
echo "[6/8] Memeriksa apakah database perlu di-seed..."

USER_COUNT=$(php -r "
    try {
        \$pdo = new PDO(
            'mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-HateSpeech}',
            '${DB_USERNAME:-hatespeech_user}',
            '${DB_PASSWORD:-hatespeech_pass}'
        );
        \$stmt = \$pdo->query('SELECT COUNT(*) FROM users');
        echo \$stmt->fetchColumn();
    } catch (Exception \$e) {
        echo '0';
    }
" 2>/dev/null || echo "0")

if [ "$USER_COUNT" = "0" ]; then
    echo "  ⟳ Tabel users kosong. Menjalankan db:seed..."
    php artisan db:seed --force --ansi
    echo "  ✓ Seeding selesai. Akun default:"
    echo "    - admin@hatespeech.test (password: password)"
    echo "    - analyst@hatespeech.test (password: password)"
    echo "    - viewer@hatespeech.test (password: password)"
else
    echo "  ✓ Tabel users sudah memiliki ${USER_COUNT} akun. Skip seeding."
fi

# ── STEP 7: Build Asset Frontend dengan Vite ─────────────────────────────────
echo ""
echo "[7/8] Memeriksa asset frontend (npm / Vite)..."

if [ ! -d "node_modules" ] || [ ! -f "node_modules/.package-lock.json" ]; then
    echo "  ⟳ node_modules/ tidak ditemukan. Menjalankan npm install..."
    npm install --loglevel=warn
    echo "  ✓ npm install selesai."
fi

if [ ! -d "public/build" ] || [ -z "$(ls -A public/build 2>/dev/null)" ]; then
    echo "  ⟳ public/build/ belum ada. Menjalankan npm run build..."
    npm run build
    echo "  ✓ Vite build selesai."
else
    echo "  ✓ Asset sudah ada di public/build/. Skip build."
fi

# ── STEP 8: Storage Link & Cache ─────────────────────────────────────────────
echo ""
echo "[8/8] Finalisasi setup Laravel..."

# Storage link
php artisan storage:link --force 2>/dev/null || true
echo "  ✓ storage:link aktif."

# Clear caches untuk fresh start
php artisan config:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
echo "  ✓ Cache dibersihkan."

# ── Jalankan Laravel Server ───────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  Setup selesai! Menjalankan Laravel server...               ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  URL Web         : http://localhost:${FRONTEND_PORT:-8000}              ║"
echo "║  Admin Login     : admin@hatespeech.test / password         ║"
echo "║  FastAPI API     : http://localhost:${FASTAPI_PORT:-8080}/docs          ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

exec php artisan serve --host=0.0.0.0 --port=8000
