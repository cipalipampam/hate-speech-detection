import asyncio
import logging
import os
import random
from pathlib import Path

import pandas as pd
from playwright.async_api import Page

logger = logging.getLogger("base_scraper")

# ---------------------------------------------------------------------------
# Konfigurasi Stealth Anti-Detection
# ---------------------------------------------------------------------------

STEALTH_ARGS = [
    "--disable-blink-features=AutomationControlled",
    "--no-first-run",
    "--no-default-browser-check",
    "--disable-infobars",
    "--disable-extensions",
]

STEALTH_SCRIPT = """
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
    Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en', 'id'] });
"""


# ---------------------------------------------------------------------------
# Browser Factory — Persistent Context
# ---------------------------------------------------------------------------

async def create_browser(playwright, profile_dir: str, headless: bool = False):
    """
    Membuka browser native (Chrome → Edge → Chromium fallback) dengan
    Playwright Persistent Context dan konfigurasi stealth anti-detection.

    Args:
        playwright : Instance async_playwright.
        profile_dir (str): Path ke direktori User Data Directory (persistent profile).
        headless (bool): False = tampilkan GUI browser.

    Returns:
        tuple: (context, page)

    Raises:
        RuntimeError: Jika tidak ada browser yang berhasil diluncurkan.
    """
    for channel in ("chrome", "msedge", None):
        try:
            launch_kwargs = {"headless": headless, "args": STEALTH_ARGS}
            if channel:
                launch_kwargs["channel"] = channel

            context = await playwright.chromium.launch_persistent_context(
                profile_dir, **launch_kwargs
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
# Utilitas Scroll & Delay
# ---------------------------------------------------------------------------

async def random_delay(delay_range: tuple):
    """Tunda eksekusi dengan durasi acak (mitigasi deteksi bot)."""
    await asyncio.sleep(random.uniform(*delay_range))


async def scroll_page(page: Page, times: int, delay_range: tuple):
    """
    Scroll halaman ke bawah sebanyak `times` kali dengan random delay di antaranya.
    Berhenti lebih awal jika halaman sudah tidak bisa di-scroll lagi.
    """
    prev_height = 0
    for _ in range(times):
        try:
            curr_height = await page.evaluate("document.body.scrollHeight")
            await page.evaluate("window.scrollTo(0, document.body.scrollHeight)")
            await random_delay(delay_range)
            if curr_height == prev_height:
                break
            prev_height = curr_height
        except Exception as e:
            logger.warning(f"scroll_page berhenti lebih awal: {e}")
            break


# ---------------------------------------------------------------------------
# Penanganan Data & Penyimpanan
# ---------------------------------------------------------------------------

STANDARD_SCRAPER_COLUMNS = ["platform", "source", "user_id", "type", "date", "content"]


def results_to_dataframe(raw_results: list[dict]) -> pd.DataFrame:
    """
    Konversi list of dict hasil scraping ke pandas DataFrame.
    Melakukan deduplikasi berdasarkan kolom (user_id, content) dan
    menata urutan kolom standar: platform, source, user_id, type, date, content.

    Args:
        raw_results: List of dict hasil scraping.

    Returns:
        pd.DataFrame: DataFrame bersih, siap diproses ke tahap preprocessing.
    """
    df = pd.DataFrame(raw_results)
    if not df.empty:
        if "user_id" in df.columns and "content" in df.columns:
            df = df.drop_duplicates(subset=["user_id", "content"])
            df = df.reset_index(drop=True)
        # Susun urutan kolom sesuai standar
        ordered_cols = [c for c in STANDARD_SCRAPER_COLUMNS if c in df.columns] + [
            c for c in df.columns if c not in STANDARD_SCRAPER_COLUMNS
        ]
        df = df[ordered_cols]
    return df


def save_dataframe(df: pd.DataFrame, path: str):
    """
    Menyimpan DataFrame ke file CSV dengan encoding UTF-8 BOM.
    Direktori akan dibuat otomatis jika belum ada.

    Args:
        df (pd.DataFrame): DataFrame yang akan disimpan.
        path (str): Path file CSV tujuan.
    """
    dirname = os.path.dirname(path)
    if dirname:
        os.makedirs(dirname, exist_ok=True)
    if not df.empty:
        ordered_cols = [c for c in STANDARD_SCRAPER_COLUMNS if c in df.columns] + [
            c for c in df.columns if c not in STANDARD_SCRAPER_COLUMNS
        ]
        df = df[ordered_cols]
    df.to_csv(path, index=False, encoding="utf-8-sig")
    logger.info(f"Dataset tersimpan: {path} ({len(df)} baris)")


def append_checkpoint(results: list[dict], path: str):
    """
    Menambahkan (append) data scraping baru ke file CSV checkpoint secara inkremental
    dengan urutan kolom standar: platform, source, user_id, type, date, content.
    Jika file belum ada, header akan ditulis otomatis.

    Args:
        results: List of dict baris baru yang akan ditambahkan.
        path (str): Path file CSV checkpoint.
    """
    if not results:
        return
    dirname = os.path.dirname(path)
    if dirname:
        os.makedirs(dirname, exist_ok=True)
    df = pd.DataFrame(results)
    if not df.empty:
        ordered_cols = [c for c in STANDARD_SCRAPER_COLUMNS if c in df.columns] + [
            c for c in df.columns if c not in STANDARD_SCRAPER_COLUMNS
        ]
        df = df[ordered_cols]
    file_exists = os.path.exists(path)
    df.to_csv(path, mode="a", index=False, header=not file_exists, encoding="utf-8-sig")
    logger.info(f"Checkpoint: +{len(results)} baris ditambahkan ke '{path}'.")


def check_profile_exists(profile_dir: str) -> bool:
    """
    Memeriksa apakah direktori persistent profile browser ada dan berisi data.

    Args:
        profile_dir (str): Path direktori profil browser.

    Returns:
        bool: True jika folder ada dan memiliki isi, False jika tidak.
    """
    p = Path(profile_dir)
    if not p.exists() or not p.is_dir():
        return False
    try:
        return any(p.iterdir())
    except Exception:
        return False
