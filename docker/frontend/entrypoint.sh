#!/bin/bash
# ==============================================================================
# entrypoint.sh — Frontend Laravel Auto-Setup & Startup Script
# ==============================================================================
# Script ini dijalankan SETIAP KALI container frontend dimulai.
# Secara otomatis menangani setup lengkap untuk FRESH CLONE:
#   - Composer install (jika vendor/ belum ada)
#   - .env generation (jika belum ada)
#   - APP_KEY generation (jika kosong)
#   - Pembersihan cache bootstrap warisan host + package:discover
#     (mencegah "Class ... not found" dari packages.php/services.php host yang
#      dibuat dengan dev-dependency seperti laravel/pail & laravel/pao)
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

# ── STEP 2: Composer Install (hanya jika vendor/ belum ada / tidak sinkron) ──
echo ""
echo "[2/9] Memeriksa PHP dependencies (Composer)..."

# Pengecekan robust: vendor/ dianggap valid hanya jika:
#   1. vendor/autoload.php ada (dibutuhkan Laravel untuk bootstrap)
#   2. Jumlah file di vendor/ lebih dari 100 (menghindari folder vendor kosong/stub)
#   3. Hash composer.lock sama dengan saat install terakhir (volume tidak basi)
# Ini mencegah folder vendor setengah-kosong (misal terpush ke repo) menipu kondisi ini.
VENDOR_FILE_COUNT=$(find vendor -type f 2>/dev/null | wc -l)
LOCK_HASH=$(md5sum composer.lock 2>/dev/null | awk '{print $1}')
INSTALLED_LOCK_HASH=$(cat vendor/.composer-lock-hash 2>/dev/null || echo "")

if [ ! -f "vendor/autoload.php" ] || [ "$VENDOR_FILE_COUNT" -lt 100 ] || [ "$LOCK_HASH" != "$INSTALLED_LOCK_HASH" ]; then
    LOCK_STATE=$([ "$LOCK_HASH" = "$INSTALLED_LOCK_HASH" ] && echo sinkron || echo BEDA)
    echo "  ⟳ vendor/ perlu disiapkan (autoload.php: $([ -f vendor/autoload.php ] && echo ada || echo TIDAK ADA), file count: ${VENDOR_FILE_COUNT}, composer.lock: ${LOCK_STATE})."
    echo "     Menjalankan composer install..."
    composer install \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --ignore-platform-req=php \
        --no-dev
    echo "$LOCK_HASH" > vendor/.composer-lock-hash
    echo "  ✓ composer install selesai."
else
    echo "  ✓ vendor/ sudah lengkap dan sinkron dengan composer.lock (${VENDOR_FILE_COUNT} files). Skip composer install."
fi

# ── STEP 3: Bersihkan Cache Bootstrap Warisan Host ───────────────────────────
echo ""
echo "[3/9] Membersihkan cache bootstrap Laravel..."

# `bootstrap/cache` di-bind-mount dari host (./frontend), sehingga file hasil
# `php artisan` di Windows ikut terbawa ke container — termasuk packages.php /
# services.php yang mencantumkan dev-dependency (laravel/pail, laravel/pao)
# yang TIDAK ada di vendor container (composer install --no-dev).
# Gejala: Laravel\Pail\PailServiceProvider not found saat artisan dijalankan.
# Hapus di level filesystem (tanpa boot Laravel, karena app belum bisa boot),
# lalu bangun ulang manifest paket dari vendor container yang sebenarnya.
find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete 2>/dev/null || true
rm -f bootstrap/cache/*.php 2>/dev/null || true
echo "  ✓ Cache lama (packages / services / config / route) dibersihkan."
php artisan package:discover --ansi
echo "  ✓ Manifest paket dibangun ulang dari vendor container."

# ── STEP 4: Generate APP_KEY (jika kosong) ────────────────────────────────────
echo ""
echo "[4/9] Memeriksa APP_KEY..."

APP_KEY_VALUE=$(grep -E "^APP_KEY=" .env | cut -d= -f2 | tr -d '"' | tr -d "'" | tr -d ' ')
if [ -z "$APP_KEY_VALUE" ] || [ "$APP_KEY_VALUE" = "null" ]; then
    echo "  ⟳ APP_KEY kosong. Mengenerate key baru..."
    php artisan key:generate --force --ansi
    echo "  ✓ APP_KEY berhasil digenerate."
else
    echo "  ✓ APP_KEY sudah ada."
fi

# ── STEP 5: Tunggu MySQL siap ─────────────────────────────────────────────────
echo ""
echo "[5/9] Menunggu MySQL database siap..."

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

# ── STEP 6: Jalankan Migrasi ──────────────────────────────────────────────────
echo ""
echo "[6/9] Menjalankan database migration..."
php artisan migrate --force --ansi
echo "  ✓ Migrasi selesai."

# ── STEP 7: Seed database (jika fresh / tabel users kosong) ──────────────────
echo ""
echo "[7/9] Memeriksa apakah database perlu di-seed..."

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
else
    echo "  ✓ Tabel users sudah memiliki ${USER_COUNT} akun. Skip seeding."
fi

# ── STEP 8: Build Asset Frontend dengan Vite ─────────────────────────────────
echo ""
echo "[8/9] Memeriksa asset frontend (npm / Vite)..."

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

# ── STEP 9: Storage Link & Cache ─────────────────────────────────────────────
echo ""
echo "[9/9] Finalisasi setup Laravel..."

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

# CATATAN `--no-reload` (JANGAN dihapus saat jalan di Docker):
# Laravel ServeCommand menghitung $hasEnvironment = file_exists(base_path('.env')).
# Karena `frontend/.env` ada (bind-mount dari host), tanpa flag ini ia HANYA
# meneruskan variabel whitelist (APP_ENV, PATH, XDEBUG_*, ...) ke proses `php -S`
# dan membuang seluruh variabel lain dari environment container.
# Akibatnya DB_HOST/DB_USERNAME/DB_PASSWORD/FASTAPI_BASE_URL dari docker-compose
# diabaikan, lalu proses web jatuh ke nilai `frontend/.env` milik host lokal
# (DB_HOST=127.0.0.1, user root) → error "SQLSTATE[HY000] [2002] Connection refused"
# di setiap request. Dengan `--no-reload` seluruh environment container diteruskan
# ke server web, sehingga nilai dari docker-compose yang menang.
# Trade-off: server tidak auto-restart saat .env berubah (tidak relevan di container).
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
