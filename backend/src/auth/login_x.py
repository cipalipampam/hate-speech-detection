"""
Login X (Twitter) Handler.

Membuka browser visual Playwright dengan Persistent Context pada direktori profil
`storage/sessions/x_profile/`, memfasilitasi user login manual ke akun X (Twitter),
melakukan polling verifikasi otomatis, lalu menyimpan seluruh state sesi secara
permanen ke disk.

Alur Kerja:
    1. Buka browser Chrome/Edge asli (GUI, headless=False) dengan Persistent Context.
    2. Arahkan ke halaman login X: https://x.com/i/flow/login
    3. Tampilkan instruksi di terminal untuk user melakukan login manual.
    4. Polling setiap 5 detik (maks 10 menit) hingga login terdeteksi via:
       - Cookie auth_token / twid terdeteksi, DAN URL sudah bukan /i/flow/login.
       - ATAU elemen DOM antarmuka akun terotentikasi terdeteksi.
    5. Tunggu 3 detik, tutup context, sesi tersimpan permanen ke disk.

Cara Menjalankan:
    cd apps/backend
    python -m src.auth.login_x
    # atau
    python src/auth/login_x.py

Catatan:
    - Setelah login berhasil, scraper X dapat langsung dijalankan.
    - Jika sesi sudah ada dan belum expired, login tidak perlu diulang.
    - Gunakan `python -m src.auth.session_manager` untuk cek status sesi.
"""

import asyncio
import logging
import sys
from pathlib import Path

from playwright.async_api import async_playwright

# Pastikan direktori backend ada di sys.path agar bisa import configs
BACKEND_DIR = Path(__file__).resolve().parents[2]
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

from configs.config import X_PROFILE_DIR, SCRAPER_CONFIG

logger = logging.getLogger("login_x")
if not logger.handlers:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")

# ---------------------------------------------------------------------------
# Konstanta
# ---------------------------------------------------------------------------

LOGIN_URL       = "https://x.com/i/flow/login"
MAX_WAIT_SEC    = 600       # Maks 10 menit (120 polling x 5 detik)
POLL_INTERVAL   = 5         # Interval cek sesi (detik)

STEALTH_ARGS = [
    "--disable-blink-features=AutomationControlled",
    "--no-first-run",
    "--no-default-browser-check",
    "--disable-infobars",
    "--disable-extensions",
]

# Selektor DOM antarmuka akun X yang terotentikasi
LOGGED_IN_SELECTORS = [
    '[data-testid="SideNav_AccountSwitcher_Button"]',
    '[data-testid="AppTabBar_Profile_Link"]',
    '[data-testid="SideNav_NewTweet_Button"]',
]

STEALTH_SCRIPT = """
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
    Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en', 'id'] });
"""


# ---------------------------------------------------------------------------
# Helper: Buka Browser dengan Persistent Context & Stealth
# ---------------------------------------------------------------------------

async def _create_browser(playwright, profile_dir: str, headless: bool = False):
    """
    Membuka browser native (Chrome → Edge → Chromium fallback) dengan
    Playwright Persistent Context dan konfigurasi stealth anti-detection.

    Args:
        playwright: Instance async_playwright.
        profile_dir (str): Path ke direktori User Data Directory.
        headless (bool): False = tampilkan GUI browser.

    Returns:
        tuple: (context, page)

    Raises:
        RuntimeError: Jika tidak ada browser yang berhasil diluncurkan.
    """
    for channel in ("chrome", "msedge", None):
        try:
            launch_kwargs = {
                "headless"  : headless,
                "args"      : STEALTH_ARGS,
            }
            if channel:
                launch_kwargs["channel"] = channel

            context = await playwright.chromium.launch_persistent_context(
                profile_dir,
                **launch_kwargs,
            )
            browser_name = channel or "Chromium (bundled)"
            logger.info(f"Browser terbuka: {browser_name} | Profile: {profile_dir}")

            page = context.pages[0] if context.pages else await context.new_page()
            await page.add_init_script(STEALTH_SCRIPT)

            return context, page

        except Exception as e:
            logger.debug(f"Gagal membuka {channel or 'chromium'}: {e}")
            continue

    raise RuntimeError(
        "Tidak dapat membuka browser. Pastikan Google Chrome atau Microsoft Edge "
        "terinstal di sistem Anda."
    )


# ---------------------------------------------------------------------------
# Fungsi Utama: Setup Login X
# ---------------------------------------------------------------------------

async def setup_x_login(profile_dir: str = None) -> bool:
    """
    Menjalankan alur setup login X (Twitter) secara interaktif.

    Membuka browser GUI, mengarahkan user ke halaman login, melakukan polling
    verifikasi sesi, lalu menyimpan sesi ke disk secara permanen.

    Args:
        profile_dir (str | None): Path direktori profil. Default: X_PROFILE_DIR dari config.

    Returns:
        bool: True jika login berhasil terdeteksi, False jika timeout/gagal.
    """
    if profile_dir is None:
        profile_dir = str(X_PROFILE_DIR)

    # Pastikan direktori profil ada
    Path(profile_dir).mkdir(parents=True, exist_ok=True)

    logger.info("=" * 60)
    logger.info("  X / TWITTER LOGIN SETUP")
    logger.info(f"  Profile Directory: {profile_dir}")
    logger.info("=" * 60)

    async with async_playwright() as p:
        try:
            context, page = await _create_browser(p, profile_dir=profile_dir, headless=False)
        except RuntimeError as e:
            logger.error(str(e))
            return False

        try:
            logger.info(f"Membuka halaman login: {LOGIN_URL}")
            await page.goto(LOGIN_URL, wait_until="domcontentloaded", timeout=60_000)

            print("\n" + "=" * 65)
            print("  SILAKAN LOGIN KE AKUN X (TWITTER) ANDA PADA BROWSER YANG TERBUKA.")
            print("  Masukkan username, password, dan selesaikan 2FA/CAPTCHA jika ada.")
            print("  Script akan otomatis mendeteksi ketika login berhasil.")
            print("  Sesi akan tersimpan otomatis setelah login berhasil.")
            print("=" * 65 + "\n")

            max_checks   = MAX_WAIT_SEC // POLL_INTERVAL
            logged_in    = False

            for check in range(1, max_checks + 1):
                await asyncio.sleep(POLL_INTERVAL)

                # --- Jika user menutup tab browser ---
                if page.is_closed():
                    logger.info("Tab browser ditutup oleh pengguna.")
                    try:
                        cookies = await context.cookies()
                        if any(
                            c.get("name") == "auth_token" and bool(c.get("value"))
                            for c in cookies
                        ):
                            logged_in = True
                    except Exception:
                        pass
                    break

                try:
                    # --- Cek 1: Cookie auth_token / twid ---
                    cookies     = await context.cookies()
                    has_auth    = any(c.get("name") == "auth_token" and bool(c.get("value")) for c in cookies)
                    has_twid    = any(c.get("name") == "twid"       and bool(c.get("value")) for c in cookies)
                    current_url = page.url.lower()

                    if (has_auth or has_twid) and "/i/flow/login" not in current_url:
                        logged_in = True
                        logger.info("✓ Cookie auth_token / twid terdeteksi — login berhasil!")
                        break

                    # --- Cek 2: Elemen DOM antarmuka akun terotentikasi ---
                    for selector in LOGGED_IN_SELECTORS:
                        try:
                            if await page.locator(selector).count() > 0:
                                logged_in = True
                                logger.info(f"✓ Elemen DOM akun terdeteksi ({selector}) — login berhasil!")
                                break
                        except Exception:
                            pass

                    if logged_in:
                        break

                except Exception as check_err:
                    logger.debug(f"Polling sesi (check {check}): {check_err}")

                # Log progres setiap 30 detik
                if check % (30 // POLL_INTERVAL) == 0:
                    logger.info(f"Menunggu login... ({check * POLL_INTERVAL} detik berlalu dari maks {MAX_WAIT_SEC} detik)")

            # --- Hasil Akhir ---
            if logged_in:
                await asyncio.sleep(3)  # Beri waktu agar data sesi penuh tersimpan ke disk
                print("\n" + "★" * 65)
                print(f"  SUKSES: LOGIN X BERHASIL!")
                print(f"  Sesi tersimpan di: {profile_dir}")
                print("  Anda sekarang dapat menjalankan scraper X.")
                print("★" * 65 + "\n")
            else:
                logger.warning("Login tidak terdeteksi dalam batas waktu maksimum.")
                logger.warning("Silakan jalankan kembali script ini dan selesaikan proses login.")

            return logged_in

        finally:
            try:
                await context.close()
            except Exception:
                pass


# ---------------------------------------------------------------------------
# Entrypoint
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    # Izinkan override path profil via argumen CLI:
    # python src/auth/login_x.py [custom_profile_dir]
    profile = sys.argv[1] if len(sys.argv) > 1 else str(X_PROFILE_DIR)
    result  = asyncio.run(setup_x_login(profile))
    sys.exit(0 if result else 1)
