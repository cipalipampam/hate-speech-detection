"""
Threads (Meta) Scraper Service.

Mengimplementasikan 2-Stage Scraping Engine untuk platform Threads (Meta):
  - Stage 1 (Search Discovery): Mengumpulkan URL postingan dari halaman pencarian
    Threads secara otomatis dengan smart-scroll, klik tab 'Recent', dan filtering keyword.
  - Stage 2 (Deep Crawl): Membuka setiap URL postingan dan mengekstrak konten
    thread asli + seluruh reply menggunakan micro-step slow scan + reverse sweep.

Fungsi Publik:
    run_threads_scraper(keywords, urls, config, checkpoint_path, final_path) -> pd.DataFrame
        Pipeline scraping lengkap (Stage 1 + Stage 2) untuk input keyword / URL.

    collect_post_urls(page, keyword, config) -> list[str]
        Stage 1: Kumpulkan URL dari hasil pencarian Threads.

    deep_crawl_post(page, link, config) -> Stage2Outcome
        Stage 2: Crawl mendalam satu postingan + semua replynya.
        Mengembalikan data + status skip + alasan (kegagalan tidak senyap).

Format data output setiap baris:
    {
        "platform"       : "Threads",
        "source"         : str,   # URL postingan sumber
        "user_id"        : str,   # username penulis
        "type"           : str,   # "Original Post" | "Reply/Comment"
        "date"           : str,   # ISO-8601 dari <time datetime=>
        "content"        : str,   # teks bersih postingan / reply
    }
"""

import asyncio
import json
import logging
import os
import re
import sys
import urllib.parse
from dataclasses import dataclass, field
from datetime import datetime
from logging.handlers import RotatingFileHandler
from pathlib import Path
from typing import Callable, Optional

import pandas as pd
from parsel import Selector
from playwright.async_api import async_playwright, Page

# Pastikan BACKEND_DIR ada di sys.path
BACKEND_DIR = Path(__file__).resolve().parents[2]
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

from configs.config import THREADS_PROFILE_DIR, EXPORTS_DIR, SCRAPER_CONFIG
from src.utils.async_compat import ensure_proactor_loop
from src.scraping.base_scraper import (
    create_browser, random_delay, scroll_page,
    results_to_dataframe, save_dataframe, append_checkpoint, check_profile_exists,
)

logger = logging.getLogger("threads_scraper")
if not logger.handlers:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


def _ensure_file_logging() -> Optional[str]:
    """
    Pasang RotatingFileHandler agar log scraping Threads tersimpan ke disk.

    Sebelumnya log hanya keluar ke stdout, sehingga kegagalan Stage 2 tidak dapat
    diperiksa setelah kejadian. Handler dipasang pada root logger agar pesan dari
    modul pendukung (base_scraper, session_manager) ikut terekam.

    Returns:
        Optional[str]: Path file log bila berhasil, None bila gagal.
    """
    try:
        logs_dir = BACKEND_DIR / "logs"
        logs_dir.mkdir(parents=True, exist_ok=True)
        log_path = os.path.abspath(str(logs_dir / "threads_scraper.log"))

        root_logger = logging.getLogger()
        for handler in root_logger.handlers:
            if isinstance(handler, RotatingFileHandler) and getattr(handler, "baseFilename", None) == log_path:
                return log_path

        root_logger.setLevel(logging.INFO)
        file_handler = RotatingFileHandler(
            log_path, maxBytes=5_000_000, backupCount=3, encoding="utf-8"
        )
        file_handler.setLevel(logging.INFO)
        file_handler.setFormatter(
            logging.Formatter("%(asctime)s [%(levelname)s] %(name)s — %(message)s")
        )
        root_logger.addHandler(file_handler)
        logger.info(f"Log scraping Threads disimpan ke: {log_path}")
        return log_path
    except Exception as error:
        logger.debug(f"Gagal memasang file log: {error}")
        return None


class ThreadsScrapeAborted(RuntimeError):
    """
    Job scraping dihentikan karena kondisi yang tidak dapat dipulihkan.

    Dipakai agar kegagalan TIDAK terlihat sebagai "sukses dengan 0 baris",
    misalnya saat sesi tidak aktif (login wall) atau saat seluruh URL Stage 2
    di-skip karena Threads membounce ke beranda.
    """

    def __init__(self, reason: str, detail: str = ""):
        super().__init__(reason)
        self.reason = reason
        self.detail = detail


def _dump_stage1_urls(urls: list[str], keywords: list[str], direct_urls: list[str]) -> Optional[str]:
    """
    Simpan daftar URL hasil Stage 1 ke disk agar dapat diverifikasi terpisah.

    Tanpa ini, isi Stage 1 hanya ada di memori sehingga sulit memastikan apakah
    masalahnya "Stage 1 salah mengumpulkan URL" atau "Stage 2 salah membuka URL".

    Returns:
        Optional[str]: Path file JSON bila berhasil, None bila gagal/dilewati.
    """
    if not urls:
        return None
    try:
        target = Path(EXPORTS_DIR) / "threads_stage1_urls.json"
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(
            json.dumps(
                {
                    "generated_at": datetime.now().isoformat(timespec="seconds"),
                    "keywords"    : keywords,
                    "direct_urls" : direct_urls,
                    "total"       : len(urls),
                    "urls"        : urls,
                },
                indent=2,
                ensure_ascii=False,
            ),
            encoding="utf-8",
        )
        logger.info(f"Daftar URL Stage 1 disimpan: {target}")
        return str(target)
    except Exception as error:
        logger.debug(f"Gagal menyimpan daftar URL Stage 1: {error}")
        return None


# ---------------------------------------------------------------------------
# Path Default Output
# ---------------------------------------------------------------------------

DEFAULT_CHECKPOINT = str(EXPORTS_DIR / "threads_checkpoint.csv")
DEFAULT_OUTPUT     = str(EXPORTS_DIR / "threads_scraper_result.csv")

# Regex validasi URL postingan Threads yang valid:
# https://www.threads.com/@<username>/post/<post_id>
_THREADS_POST_URL_RE = re.compile(
    r'^https?://(?:www\.)?threads\.(?:net|com)/@[^/]+/post/[A-Za-z0-9_-]+/?$'
)


def _normalize_threads_url(url: str) -> str:
    """Hilangkan query/fragment agar URL hasil navigasi dapat divalidasi konsisten."""
    parts = urllib.parse.urlsplit(url)
    return urllib.parse.urlunsplit((
        parts.scheme.lower(),
        parts.netloc.lower(),
        parts.path.rstrip("/"),
        "",
        "",
    ))


def _is_threads_post_url(url: str) -> bool:
    return bool(_THREADS_POST_URL_RE.fullmatch(_normalize_threads_url(url)))


def _threads_post_path(url: str) -> str:
    """Path post untuk membandingkan URL awal dan URL akhir lintas domain Threads."""
    return urllib.parse.urlsplit(_normalize_threads_url(url)).path


# ---------------------------------------------------------------------------
# Identitas Halaman — Anti-Hijack & Anti-Salah-Label
#
# Latar belakang: Threads dapat membounce permalink post ke beranda SETELAH
# event `domcontentloaded`. Pengecekan sekali pakai pada saat itu saja TIDAK
# cukup: guard lolos, slow scan berjalan di DOM beranda, lalu semua item feed
# diberi label `source` = URL post yang diminta (data sampah + salah label).
#
# Solusi: verifikasi identitas halaman berulang (sebelum, menjelang, dan selama
# scan) menggunakan URL + keberadaan elemen milik post id yang diminta.
# ---------------------------------------------------------------------------

# Ambang jumlah langkah scan antar-verifikasi identitas (overhead kecil).
IDENTITY_CHECK_EVERY = 5

# Penanda halaman yang BUKAN postingan (login wall / beranda / explore).
_NON_POST_PATH_MARKERS = ("/login", "/explore", "/search", "/activity", "/settings")


class Stage2IdentityLost(RuntimeError):
    """
    Halaman Stage 2 berpindah dari postingan yang diminta.

    Dilempar saat verifikasi identitas gagal di tengah scan sehingga data yang
    sudah terkumpul TIDAK boleh disimpan (mencegah salah label feed beranda).
    """

    def __init__(self, reason: str):
        super().__init__(reason)
        self.reason = reason


@dataclass
class Stage2Outcome:
    """Hasil Stage 2 satu URL beserta alasan bila di-skip (agar gagal tidak senyap)."""

    data        : list = field(default_factory=list)
    skipped     : bool = False
    reason      : str  = ""

    def __bool__(self) -> bool:
        return bool(self.data)


_JS_CHECK_POST_IDENTITY = """
(expectedPostId) => {
    const anchors = Array.from(document.querySelectorAll('a[href*="/post/"]'));
    const own = anchors.filter(a => (a.getAttribute('href') || '').includes('/post/' + expectedPostId));
    let containers = 0;
    for (const a of own) {
        if (a.closest('div[data-pressable-container="true"], article')) containers++;
    }
    const timeEls = document.querySelectorAll('time[datetime]');
    return {
        url          : window.location.href,
        path         : window.location.pathname,
        pathOk       : window.location.pathname.indexOf('/post/' + expectedPostId) !== -1,
        ownAnchors   : own.length,
        containers   : containers,
        timeCount    : timeEls.length,
        hasLoginCta  : !!document.querySelector('a[href*="/login"]'),
        title        : document.title || '',
    };
}
"""


async def _verify_post_identity(page: Page, requested_url: str) -> tuple[bool, str]:
    """
    Pastikan halaman yang sedang terbuka benar-benar postingan yang diminta.

    Dijalankan sebelum, menjelang, dan selama Stage 2 scan. Ini yang menangkap
    kasus Threads membounce permalink ke beranda (sebelumnya tidak terdeteksi).

    Args:
        page          : Halaman Playwright aktif.
        requested_url : URL post yang seharusnya terbuka.

    Returns:
        tuple[bool, str]: (True, "") jika identitas cocok; (False, alasan) jika tidak.
    """
    expected_post_id = _extract_post_id(requested_url)
    if not expected_post_id:
        return False, f"post_id tidak dapat diekstrak dari '{requested_url}'"

    try:
        info = await page.evaluate(_JS_CHECK_POST_IDENTITY, expected_post_id)
    except Exception as error:
        return False, f"gagal mengevaluasi DOM: {error}"

    current_url = _normalize_threads_url(info.get("url") or "")
    current_path = urllib.parse.urlsplit(current_url).path

    if any(marker in current_path for marker in _NON_POST_PATH_MARKERS):
        return False, f"halaman berpindah ke jalur non-post ({current_path or '<kosong>'})"

    if not _is_threads_post_url(current_url):
        return False, (
            "halaman berpindah ke bukan-permalink "
            f"({current_url or '<kosong>'}; judul='{(info.get('title') or '')[:80]}')"
        )

    expected_path = _threads_post_path(requested_url)
    if _threads_post_path(current_url) != expected_path:
        return False, f"halaman berpindah ke post lain (diminta={expected_path}, sekarang={_threads_post_path(current_url)})"

    if not info.get("pathOk"):
        return False, f"path halaman tidak memuat id post yang diminta ('/{expected_post_id}')"

    if int(info.get("timeCount") or 0) <= 0:
        return False, "tidak ada elemen <time datetime> di DOM (konten post tidak ter-render)"

    # Sinyal tambahan: anchor permalink / container konten milik post yang diminta.
    # Sengaja TIDAK dijadikan syarat keras — struktur DOM Threads dapat berubah
    # (mis. anchor memakai bentuk lain) dan false-negative akan melewati semua post.
    # Peringatan ini tetap terekam agar perubahan struktur DOM terlihat di log.
    if int(info.get("ownAnchors") or 0) <= 0 or int(info.get("containers") or 0) <= 0:
        logger.warning(
            f"  Peringatan identitas: anchor=/post/{expected_post_id} "
            f"ditemukan={info.get('ownAnchors')}, container={info.get('containers')}. "
            "URL & <time> sudah cocok, scan dilanjutkan."
        )

    return True, ""


async def _click_in_app_anchor(page: Page, target_path: str) -> bool:
    """
    Klik anchor yang di-inject agar perpindahan ditangani router SPA Threads.

    Returns:
        bool: True bila klik berhasil dikirim (bukan jaminan halaman sudah pindah).
    """
    try:
        await page.evaluate(
            """(targetPath) => {
                const existing = document.getElementById('__threads_nav_probe');
                if (existing) existing.remove();
                const a = document.createElement('a');
                a.id = '__threads_nav_probe';
                a.href = targetPath;
                a.textContent = 'nav';
                a.style.position = 'fixed';
                a.style.left = '-9999px';
                document.body.appendChild(a);
                a.click();
            }""",
            target_path,
        )
        await random_delay((2.0, 3.0))
        await page.wait_for_timeout(2500)
        return True
    except Exception as error:
        logger.debug(f"Klik in-app gagal ({target_path}): {error}")
        return False


async def _open_post_in_app(page: Page, requested_url: str) -> bool:
    """
    Buka permalink post memakai router in-app Threads (klik anchor), bukan `page.goto`.

    Alasan (terverifikasi terhadap profil & sesi riil, 2026-09-23):
    navigasi top-level ke permalink SELALU dibalas Threads dengan HTTP 302 ke
    `https://www.threads.com/?injected_media_ids=...` — yaitu post dibuka di dalam
    feed beranda, bukan sebagai halaman post. Percobaan dengan host `.net` pun
    berakhir sama (301 → 302 → beranda). Sebaliknya, klik anchor membuat router SPA
    Threads yang menangani perpindahan, dan halaman post termuat normal
    (`pathHasPostId=True`, `<time datetime>` ada, jumlah balasan ter-render).

    Returns:
        bool: True jika identitas post terverifikasi setelah navigasi.
    """
    from urllib.parse import urlsplit

    target_path = urlsplit(requested_url).path

    # Percobaan 1: klik langsung dari halaman Threads yang sedang terbuka.
    if "threads.com" in _normalize_threads_url(page.url):
        if await _click_in_app_anchor(page, target_path):
            identity_ok, _ = await _verify_post_identity(page, requested_url)
            if identity_ok:
                return True
            logger.debug("  Klik in-app dari halaman saat ini belum mendarat di post. Coba dari beranda.")

    # Percobaan 2: mulai dari beranda — kondisi yang terbukti berhasil pada diagnostik.
    try:
        await page.goto("https://www.threads.com/", wait_until="domcontentloaded", timeout=60_000)
        await random_delay((1.5, 2.5))
    except Exception as error:
        logger.debug(f"Gagal membuka beranda Threads: {error}")

    if await _click_in_app_anchor(page, target_path):
        identity_ok, _ = await _verify_post_identity(page, requested_url)
        return identity_ok
    return False


# ---------------------------------------------------------------------------
# Verifikasi Sesi Live (host-aware)
#
# Validasi lama hanya memeriksa NAMA cookie (sessionid/ds_user_id) tanpa
# memastikan cookie tersebut berlaku untuk host `threads.com`. Cookie milik
# `.threads.net` atau `.instagram.com` tetap lolos, padahal halaman yang
# di-crawl adalah `threads.com`. Akibatnya Stage 1/Stage 2 berjalan dengan
# sesi yang tidak sah dan Threads membounce permalink ke beranda.
# ---------------------------------------------------------------------------

_LOGIN_MARKER_JS = """
() => {
    const visible = (el) => !!(el && el.offsetParent !== null);
    const hasVisible = (sel) => Array.from(document.querySelectorAll(sel)).some(visible);
    return {
        url         : window.location.href,
        createIcon  : hasVisible('svg[aria-label*="Create" i], svg[aria-label*="Buat" i]'),
        activityIcon: hasVisible('svg[aria-label*="Activity" i], svg[aria-label*="Aktivitas" i]'),
        profileIcon : hasVisible('svg[aria-label*="Profile" i], svg[aria-label*="Profil" i]'),
        hasLoginCta : hasVisible('a[href*="/login"]') || hasVisible('input[name="username"]'),
    };
}
"""


async def _check_logged_in_live(page: Page, navigate: bool = True) -> tuple[bool, str]:
    """
    Verifikasi status login Threads secara LIVE pada host `threads.com`.

    Args:
        page     : Halaman Playwright aktif.
        navigate : True bila boleh melakukan navigasi ke beranda threads.com.

    Returns:
        tuple[bool, str]: (logged_in, url_saat_ini)
    """
    try:
        if navigate and "threads.com" not in _normalize_threads_url(page.url):
            await page.goto("https://www.threads.com/", wait_until="domcontentloaded", timeout=60_000)
        await random_delay((2.0, 3.0))
        info = await page.evaluate(_LOGIN_MARKER_JS)
    except Exception as error:
        return False, f"gagal memuat threads.com: {error}"

    current_url = info.get("url") or ""
    if "/login" in current_url or "/accounts/login" in current_url:
        return False, current_url

    logged_in = bool(
        info.get("createIcon") or info.get("activityIcon") or info.get("profileIcon")
    )
    return logged_in, current_url


async def _looks_like_login_wall(page: Page) -> bool:
    """Deteksi halaman login wall (bukan login). Dipakai Stage 1 sebelum scroll."""
    try:
        info = await page.evaluate(_LOGIN_MARKER_JS)
    except Exception:
        return False

    current_url = info.get("url") or ""
    if "/login" in current_url or "/accounts/login" in current_url:
        return True

    has_nav = bool(
        info.get("createIcon") or info.get("activityIcon") or info.get("profileIcon")
    )
    return bool(info.get("hasLoginCta")) and not has_nav


# ---------------------------------------------------------------------------
# Noise / Filter UI Threads (Meta)
# ---------------------------------------------------------------------------

SYSTEM_EXACT_PHRASES = {
    "log in", "log masuk", "masuk", "sign up", "daftar",
    "continue with instagram", "lanjutkan dengan instagram",
    "continue with facebook", "lanjutkan dengan facebook",
    "report a problem", "laporkan masalah",
    "cookie policy", "kebijakan cookie",
    "privacy policy", "kebijakan privasi",
    "terms of service", "ketentuan layanan",
    "instagram", "threads",
}

NOISE_EXACT = {
    "Translate", "Terjemahkan",
    "See more", "Lihat lainnya",
    "AI info", "Verified",
    # Placeholder UI saat post belum punya balasan — bukan bagian dari konten.
    # Tanpa ini, teks tersebut menempel di akhir `content` (terlihat pada uji
    # verifikasi 2026-09-23: "... di korupsi No replies yet").
    "No replies yet", "Belum ada balasan",
    "Be the first to reply", "Jadilah yang pertama membalas",
}


def is_system_text(content: str) -> bool:
    return content.strip().lower() in SYSTEM_EXACT_PHRASES


# ---------------------------------------------------------------------------
# Konfigurasi Scraper
# ---------------------------------------------------------------------------

@dataclass
class ThreadsScrapeConfig:
    profile_dir       : str   = field(default_factory=lambda: str(THREADS_PROFILE_DIR))
    headless          : bool  = True
    search_mode       : str   = field(default_factory=lambda: SCRAPER_CONFIG.get("search_mode", "latest"))
    goto_timeout_ms   : int   = field(default_factory=lambda: SCRAPER_CONFIG["goto_timeout_ms"])
    delay_range       : tuple = field(default_factory=lambda: (1.5, 3.0))
    search_scroll     : int   = field(default_factory=lambda: SCRAPER_CONFIG["search_scroll"])
    max_links         : int   = field(default_factory=lambda: SCRAPER_CONFIG["max_links"])
    scan_step_px      : int   = field(default_factory=lambda: SCRAPER_CONFIG["scan_step_px"])
    scan_delay_range  : tuple = field(default_factory=lambda: (0.10, 0.25))
    scan_parse_every  : int   = 2
    scan_max_steps    : int   = field(default_factory=lambda: SCRAPER_CONFIG["scan_max_steps"])
    per_post_timeout_s: float = 1800.0


# ---------------------------------------------------------------------------
# JavaScript Snippet — Ekstraksi Komentar / Thread dari DOM
# ---------------------------------------------------------------------------

_SYSTEM_EXACT_JS = json.dumps(sorted(SYSTEM_EXACT_PHRASES))
_NOISE_JS        = json.dumps(sorted(NOISE_EXACT))

# Teks pembatas "akhir thread" di Threads. Item yang muncul SETELAH pembatas ini
# (mis. "Related threads") bukan bagian dari thread yang diminta, sehingga harus
# diabaikan oleh extractor JS — bukan hanya dihentikan oleh scroll scan.
_THREAD_DIVIDER_TEXTS = [
    "related threads", "postingan terkait", "suggested threads",
    "postingan yang disarankan", "more threads", "postingan lainnya",
    "thread lainnya", "rekomendasi",
]
_DIVIDER_TEXTS_JS = json.dumps(_THREAD_DIVIDER_TEXTS)

_JS_EXTRACT_COMMENTS = f"""
() => {{
    const SYSTEM_EXACT = new Set({_SYSTEM_EXACT_JS});
    const TIME_RE = /^\\d+[smhdw]$/i;
    const DATE_RE = /^(\\d{{1,2}}[\\/\\.-]\\d{{1,2}}[\\/\\.-]\\d{{2,4}}|\\d{{4}}[\\/\\.-]\\d{{1,2}}[\\/\\.-]\\d{{1,2}}|\\d{{1,2}}\\s+[a-z]{{3,9}}(\\s+\\d{{2,4}})?|[a-z]{{3,9}}\\s+\\d{{1,2}}(\\s+\\d{{2,4}})?)$/i;
    const COUNT_RE = /^\\d+([.,]\\d+)?[KMB]?$/i;
    const ACTION_NOISE_RE = /^(top|view activity|lihat aktivitas|reply to.*|balas ke.*|balas kepada.*|activity|share|bagikan|teratas)$/i;
    const NOISE = new Set({_NOISE_JS});
    const DIVIDER_TEXTS = {_DIVIDER_TEXTS_JS};

    // Cari elemen pembatas "akhir thread" (Related threads, dll). Item setelahnya
    // bukan bagian dari thread ini dan tidak boleh ikut terekstrak.
    let dividerEl = null;
    for (const el of document.querySelectorAll('h2, h3, h4, [role="heading"], div[dir="auto"], span[dir="auto"]')) {{
        const t = (el.textContent || '').trim().toLowerCase();
        if (!t || t.length >= 40) continue;
        if (DIVIDER_TEXTS.some(x => t === x || t.startsWith(x))) {{ dividerEl = el; break; }}
    }}

    const allPC = document.querySelectorAll('div[data-pressable-container="true"], article');
    const results = [];
    const seen = new Set();

    for (const el of allPC) {{
        // Lewati semua item yang berada SETELAH pembatas akhir thread.
        if (dividerEl && (dividerEl.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING)) continue;

        const timeEl = el.querySelector('time[datetime]');
        if (!timeEl) continue;
        const dateVal = timeEl.getAttribute('datetime') || '';
        if (!dateVal) continue;
        const timeText = (timeEl.textContent || '').trim();

        let username = '';
        const candidateLinks = el.querySelectorAll('a[href*="/@"]');
        for (const link of candidateLinks) {{
            const pos = link.compareDocumentPosition(timeEl);
            if (pos & Node.DOCUMENT_POSITION_FOLLOWING) {{
                const href = link.getAttribute('href') || '';
                const parts = href.split('/');
                for (const p of parts) {{
                    if (p.startsWith('@')) {{ username = p.slice(1); break; }}
                }}
                if (username) break;
            }}
        }}
        if (!username) continue;

        const dirAutos = el.querySelectorAll('[dir="auto"]');
        const tokens = [];
        for (const da of dirAutos) {{
            const nested = da.closest('div[data-pressable-container="true"]');
            if (nested && nested !== el && nested.querySelector('time[datetime]')) continue;
            const walker = document.createTreeWalker(da, NodeFilter.SHOW_TEXT, null);
            let node;
            while (node = walker.nextNode()) {{
                const t = node.textContent.trim();
                if (!t) continue;
                if (t === username || t === timeText || NOISE.has(t)) continue;
                if (TIME_RE.test(t)) continue;
                if (DATE_RE.test(t)) continue;
                if (COUNT_RE.test(t)) continue;
                if (ACTION_NOISE_RE.test(t)) continue;
                tokens.push(t);
            }}
        }}
        const content = tokens.join(' ').trim();
        if (!content || SYSTEM_EXACT.has(content.toLowerCase())) continue;

        const key = username + '|' + content;
        if (seen.has(key)) continue;
        seen.add(key);

        let type = 'Reply/Comment';
        // owner_post_id = post id pemilik item ini (dari anchor <time>).
        // Dipakai Python untuk menolak item yang bukan milik post yang diminta,
        // sehingga konten feed beranda tidak bisa salah dilabeli sebagai reply.
        let ownerPostId = '';
        const ownerAnchor = timeEl.closest('a[href*="/post/"]');
        if (ownerAnchor) {{
            const ownerMatch = (ownerAnchor.getAttribute('href') || '').match(/\\/post\\/([^/?#]+)/);
            if (ownerMatch) ownerPostId = ownerMatch[1];
        }}
        const url = window.location.href;
        const postIdMatch = url.match(/\\/post\\/([^/?#]+)/);
        const postId = postIdMatch ? postIdMatch[1] : '';
        if (postId && ownerPostId === postId) type = 'Original Post';

        results.push({{ user_id: username, date: dateVal, content: content, type: type, owner_post_id: ownerPostId }});
    }}
    return results;
}}
"""

_JS_EXTRACT_SEARCH_CARDS = """
() => {
    // Hanya ambil link yang benar-benar postingan Threads:
    // pola: /@username/post/<post_id>  (tanpa path tambahan setelahnya)
    const POST_URL_RE = /^https?:\\/\\/(?:www\\.)?threads\\.(?:net|com)\\/@[^/]+\\/post\\/[A-Za-z0-9_-]+\\/?$/;

    const links = document.querySelectorAll('a[href*="/post/"]');
    const results = [];
    const seenUrls = new Set();

    for (const link of links) {
        let url = link.href || '';
        // Bersihkan query string dan fragment, hapus trailing slash
        url = url.split('?')[0].split('#')[0].replace(/\\/$/, '');
        if (!url) continue;

        // Validasi: harus cocok pola URL postingan asli Threads
        if (!POST_URL_RE.test(url + '/')) continue;  // tambah / agar regex \\/$ cocok
        if (seenUrls.has(url)) continue;

        // Cari container post Threads terdekat
        const card = link.closest('div[data-pressable-container="true"], article') || link.parentElement;
        let fullText = '';
        if (card) {
            const dirAutos = card.querySelectorAll('[dir="auto"]');
            const tokens = [];
            for (const da of dirAutos) {
                const t = (da.textContent || '').trim();
                if (t) tokens.push(t);
            }
            fullText = tokens.length > 0 ? tokens.join(' ') : (card.textContent || '').trim();
        }

        seenUrls.add(url);
        results.push({ url: url, text: fullText });
    }
    return results;
}
"""


# ---------------------------------------------------------------------------
# Helper: Thread Divider & Auto-Expand
# ---------------------------------------------------------------------------

async def _check_thread_divider(page: Page) -> bool:
    """Deteksi tanda pembatas akhir reply di postingan Threads (Related Threads, dll.)."""
    try:
        return await page.evaluate(f"""() => {{
            const targets = {_DIVIDER_TEXTS_JS};
            const elements = document.querySelectorAll('h2, h3, h4, [role="heading"], div[dir="auto"], span[dir="auto"]');
            for (const el of elements) {{
                const txt = (el.textContent || '').trim().toLowerCase();
                if (txt.length > 0 && txt.length < 40) {{
                    for (const t of targets) {{
                        if (txt === t || txt.startsWith(t)) {{
                            const rect = el.getBoundingClientRect();
                            if (rect.top > 0 && rect.top < window.innerHeight + 300) return true;
                        }}
                    }}
                }}
            }}
            return false;
        }}""")
    except Exception as e:
        logger.debug(f"_check_thread_divider error: {e}")
        return False


async def _click_expand_buttons(page: Page) -> int:
    """Klik tombol 'Show replies' / expand di postingan Threads secara otomatis."""
    try:
        clicked = await page.evaluate("""() => {
            const patterns = [/show\\s+repl/i, /show\\s+more\\s+repl/i, /view\\s+repl/i,
                              /tampilkan\\s+balasan/i, /lihat\\s+balasan/i, /more\\s+repl/i,
                              /balasan\\s+lainnya/i, /\\d+\\s+repl/i, /\\d+\\s+balasan/i];
            let count = 0;
            const candidates = document.querySelectorAll('div[role="button"], span[role="button"], button');
            for (const el of candidates) {
                const text = (el.textContent || '').trim();
                if (!text || text.length > 80) continue;
                for (const pat of patterns) {
                    if (pat.test(text)) {
                        if (el.offsetWidth > 0 && el.offsetHeight > 0) {
                            const rect = el.getBoundingClientRect();
                            if (rect.top > -150 && rect.top < window.innerHeight + 350) {
                                el.click(); count++; break;
                            }
                        }
                    }
                }
            }
            return count;
        }""")
        return clicked or 0
    except Exception as e:
        logger.debug(f"_click_expand_buttons error: {e}")
        return 0


async def _collect_js_items(
    page: Page,
    source_link: str,
    collected: dict,
    expected_post_id: str = "",
) -> int:
    """
    Eksekusi JS extractor dan masukkan hasilnya ke dict 'collected' tanpa duplikasi.

    Args:
        expected_post_id: Post id yang seharusnya sedang dibuka. Bila diisi, item
            yang mengaku sebagai "Original Post" milik post id LAIN akan ditolak
            (anti salah-label feed beranda).
    """
    count_before = len(collected)
    rejected = 0
    try:
        items = await page.evaluate(_JS_EXTRACT_COMMENTS)
        for item in items:
            owner_post_id = item.get("owner_post_id") or ""
            if (
                expected_post_id
                and item.get("type") == "Original Post"
                and owner_post_id
                and owner_post_id != expected_post_id
            ):
                rejected += 1
                continue

            key = (item["user_id"], item["content"])
            if key not in collected:
                collected[key] = {
                    "platform"     : "Threads",
                    "source": source_link,
                    "user_id"      : item["user_id"],
                    "type"         : item["type"],
                    "date"         : item["date"],
                    "content"      : item["content"],
                }
    except Exception as e:
        logger.debug(f"JS extraction error: {e}")

    if rejected:
        logger.warning(
            f"  {rejected} item ditolak: mengaku Original Post milik post lain "
            f"(halaman saat ini bukan /post/{expected_post_id})."
        )
    return len(collected) - count_before


# ---------------------------------------------------------------------------
# Stage 2: Slow Scan (Micro-Step Forward + Reverse Sweep)
# ---------------------------------------------------------------------------

async def _slow_scan_comments(page: Page, source_link: str, config: ThreadsScrapeConfig) -> list[dict]:
    """
    Mengekstrak konten thread + semua reply dari halaman postingan Threads yang sudah dibuka.
    Menggunakan micro-step scroll ke bawah (forward scan) + sweep ke atas (reverse scan)
    untuk memastikan semua thread/reply ter-render dan ter-ekstrasi.

    Raises:
        Stage2IdentityLost: Jika halaman berpindah dari postingan yang diminta di
            tengah scan (mis. Threads membounce ke beranda). Seluruh data dibatalkan
            agar konten feed beranda tidak tersimpan dengan label post yang salah.
    """
    CONFIRM_ROUNDS  = 5
    LOG_EVERY       = 20
    EXPAND_EVERY    = 2
    REVERSE_STEP_PX = 250

    expected_post_id = _extract_post_id(source_link)
    collected: dict[tuple, dict] = {}
    confirm_streak = 0
    step = 0
    last_log_count = 0
    identity_lost_reason = ""

    for step in range(config.scan_max_steps):
        try:
            # Verifikasi identitas berkala: mencegah scan berlanjut di halaman yang
            # sudah dibounce Threads ke beranda / berpindah ke post lain.
            if expected_post_id and step % IDENTITY_CHECK_EVERY == 0:
                identity_ok, identity_reason = await _verify_post_identity(page, source_link)
                if not identity_ok:
                    identity_lost_reason = identity_reason
                    break

            has_divider = await _check_thread_divider(page)
            if has_divider and step >= 10:
                await _collect_js_items(page, source_link, collected, expected_post_id)
                logger.info(f"Thread divider terdeteksi pada step {step + 1}. Berhenti forward scan.")
                break

            got_new = False
            if step % config.scan_parse_every == 0:
                new_added = await _collect_js_items(page, source_link, collected, expected_post_id)
                got_new = new_added > 0
                if len(collected) - last_log_count >= LOG_EVERY:
                    logger.info(f"Progress: {len(collected)} komentar (step {step + 1})...")
                    last_log_count = len(collected)

            if step > 0 and step % EXPAND_EVERY == 0:
                expanded = await _click_expand_buttons(page)
                if expanded > 0:
                    logger.info(f"Auto-expand {expanded} reply thread(s)...")
                    await asyncio.sleep(1.5)

            metrics_before = await page.evaluate("() => ({ height: document.body.scrollHeight, scrollY: window.scrollY })")
            await page.evaluate(f"window.scrollBy({{ top: {config.scan_step_px}, behavior: 'auto' }})")
            await random_delay(config.scan_delay_range)
            metrics_after = await page.evaluate("() => ({ height: document.body.scrollHeight, scrollY: window.scrollY })")

            height_grew    = metrics_after["height"] > metrics_before["height"]
            scrolled_moved = abs(metrics_after["scrollY"] - metrics_before["scrollY"]) > 10

            if got_new or height_grew or scrolled_moved:
                confirm_streak = 0
                continue

            confirm_streak += 1
            logger.info(f"Konfirmasi stagnasi {confirm_streak}/{CONFIRM_ROUNDS} ({len(collected)} item)...")
            if confirm_streak >= CONFIRM_ROUNDS:
                break

            await _click_expand_buttons(page)
            await asyncio.sleep(1.0)
            nudge_added = await _collect_js_items(page, source_link, collected, expected_post_id)
            if nudge_added > 0:
                confirm_streak = 0

        except Exception as e:
            logger.warning(f"Forward scan berhenti pada step {step}: {e}")
            break

    # Halaman terbukti berpindah: seluruh data dibatalkan (anti salah-label).
    if identity_lost_reason:
        logger.error(
            f"IDENTITAS POST HILANG pada step {step + 1} untuk {source_link}: "
            f"{identity_lost_reason}. {len(collected)} item dibatalkan agar tidak salah label."
        )
        raise Stage2IdentityLost(identity_lost_reason)

    forward_count = len(collected)
    logger.info(f"Forward scan selesai: {forward_count} item dari {step + 1} langkah.")

    # Reverse sweep
    try:
        scroll_height = await page.evaluate("document.body.scrollHeight")
        current_pos   = scroll_height
        max_reverse   = int(scroll_height / REVERSE_STEP_PX) + 100
        reverse_steps = 0
        while current_pos > 0 and reverse_steps < max_reverse:
            await page.evaluate(f"window.scrollBy(0, -{REVERSE_STEP_PX})")
            await asyncio.sleep(0.25)
            await _collect_js_items(page, source_link, collected, expected_post_id)
            current_pos -= REVERSE_STEP_PX
            reverse_steps += 1
    except Exception as e:
        logger.warning(f"Reverse sweep berhenti: {e}")

    reverse_gained = len(collected) - forward_count
    if reverse_gained > 0:
        logger.info(f"Reverse sweep menambah {reverse_gained} item baru!")

    # Final settle pass
    try:
        final_expanded = await _click_expand_buttons(page)
        if final_expanded > 0:
            await asyncio.sleep(3.0)
        await random_delay((1.0, 1.8))
        await _collect_js_items(page, source_link, collected, expected_post_id)
    except Exception as e:
        logger.debug(f"Final settle dilewati: {e}")

    # HTML fallback via parsel
    try:
        html = await page.content()
        for item in _parse_post_page_html(html, source_link):
            key = (item["user_id"], item["content"])
            if key not in collected:
                collected[key] = item
    except Exception as e:
        logger.warning(f"HTML consolidation gagal: {e}")

    # Verifikasi terakhir: pastikan halaman masih postingan yang diminta sebelum
    # data dianggap sah. Menangkap bounce yang terjadi selama reverse sweep/settle.
    if expected_post_id:
        final_ok, final_reason = await _verify_post_identity(page, source_link)
        if not final_ok:
            logger.error(
                f"IDENTITAS POST HILANG di akhir scan untuk {source_link}: {final_reason}. "
                f"{len(collected)} item dibatalkan agar tidak salah label."
            )
            raise Stage2IdentityLost(final_reason)

    logger.info(f"Total komentar unik: {len(collected)}")
    return list(collected.values())


# ---------------------------------------------------------------------------
# HTML Fallback Parser (Parsel)
# ---------------------------------------------------------------------------

_TIME_AGO_RE     = re.compile(r'^\d+[smhdw]$', re.IGNORECASE)
_DATE_FORMAT_RE  = re.compile(r'^(\d{1,2}[\/\.-]\d{1,2}[\/\.-]\d{2,4}|\d{4}[\/\.-]\d{1,2}[\/\.-]\d{1,2}|\d{1,2}\s+[a-zA-Z]{3,9}(\s+\d{2,4})?|[a-zA-Z]{3,9}\s+\d{1,2}(\s+\d{2,4})?)$', re.IGNORECASE)
_COUNT_TOKEN_RE  = re.compile(r'^\d+([.,]\d+)?[KMB]?$', re.IGNORECASE)
_ACTION_NOISE_RE = re.compile(r'^(top|view activity|lihat aktivitas|reply to.*|balas ke.*|balas kepada.*|activity|share|bagikan|teratas)$', re.IGNORECASE)


def _extract_post_id(post_url: str) -> str:
    match = re.search(r"/post/([^/?#]+)", post_url)
    return match.group(1) if match else ""


def _parse_post_page_html(html: str, source_link: str) -> list[dict]:
    """Fallback parser berbasis Parsel untuk mengekstrak thread dari HTML statik."""
    sel = Selector(text=html)
    target_post_id = _extract_post_id(source_link)

    _PC_CONDITION = (
        'div[@data-pressable-container="true"]'
        '[not(ancestor::div[@data-pressable-container="true"])]'
        '[.//time[@datetime]]'
    )
    articles = sel.xpath(f'//div[@role="main"]//{_PC_CONDITION}')
    if not articles:
        articles = sel.xpath(f'//{_PC_CONDITION}')
    if not articles:
        articles = sel.xpath('//article[.//time[@datetime]]')

    results = []
    seen = set()

    for article in articles:
        # Hentikan jika sudah melewati pembatas "Related Threads"
        is_after_divider = article.xpath(
            'preceding-sibling::*[self::h2 or self::h3 or self::h4 or @role="heading"]'
            '[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "related threads") '
            'or contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "postingan terkait")]'
        )
        if is_after_divider:
            break

        # Ekstrak username
        href = article.xpath('.//a[contains(@href, "/@")][following::time[@datetime]][1]/@href').get()
        if not href:
            href = article.xpath('.//a[contains(@href, "/@")][1]/@href').get()
        username = ""
        if href:
            for segment in href.split("/"):
                if segment.startswith("@"):
                    username = segment.replace("@", "")
                    break

        date_val = article.xpath(".//time/@datetime").get()
        if not date_val or not username:
            continue

        # Ekstrak konten
        noise = NOISE_EXACT | {username}
        nested_containers = article.xpath('.//div[@data-pressable-container="true"][.//time[@datetime]]')
        nested_roots = {nc.root for nc in nested_containers}
        time_text_val = article.xpath('.//time/text()').get()
        time_text = time_text_val.strip() if time_text_val else ""

        dir_autos = article.xpath('.//*[@dir="auto"][not(ancestor::*[@dir="auto"])]')
        clean_tokens = []
        for da in dir_autos:
            ancestors = da.xpath('ancestor::div[@data-pressable-container="true"]')
            if any(anc.root in nested_roots for anc in ancestors):
                continue
            for t in da.xpath('.//text()').getall():
                token = t.strip()
                if not token or token in noise or token == time_text:
                    continue
                if _TIME_AGO_RE.match(token) or _DATE_FORMAT_RE.match(token):
                    continue
                if _COUNT_TOKEN_RE.match(token) or _ACTION_NOISE_RE.match(token):
                    continue
                clean_tokens.append(token)

        content = " ".join(clean_tokens).strip()
        if not content or is_system_text(content):
            continue

        dedup_key = (username, content)
        if dedup_key in seen:
            continue
        seen.add(dedup_key)

        # Klasifikasi Original Post / Reply
        post_type = "Reply/Comment"
        if target_post_id:
            time_href = article.xpath('.//time[@datetime]/ancestor::a[1]/@href').get()
            if time_href and target_post_id in time_href:
                post_type = "Original Post"

        results.append({
            "platform"      : "Threads",
            "source" : source_link,
            "user_id"       : username,
            "type"          : post_type,
            "date"          : date_val,
            "content"       : content,
        })
    return results


# ---------------------------------------------------------------------------
# Utilitas Keyword Matching
# ---------------------------------------------------------------------------

def _keyword_matches_text(text: str, keywords: list[str]) -> bool:
    """Cek apakah teks postingan relevan dengan keyword pencarian."""
    # Jika teks kosong atau hanya media, tetap anggap valid karena sudah
    # merupakan hasil kurasi dari endpoint search resmi Threads untuk query tersebut.
    if not text or not text.strip():
        return True

    text_lower    = text.lower()
    text_no_space = text_lower.replace(" ", "")
    for kw in keywords:
        clean          = kw.lstrip('#').lower()
        clean_no_space = clean.replace(" ", "")
        if clean in text_lower or (clean_no_space and clean_no_space in text_no_space):
            return True
        # Dukungan kecocokan kata individual untuk query majemuk
        words = [w for w in clean.split() if len(w) > 2]
        if words and any(w in text_lower for w in words):
            return True
    return False


# ---------------------------------------------------------------------------
# Stage 1: Search Discovery
# ---------------------------------------------------------------------------

async def collect_post_urls(
    page: Page,
    keyword: str,
    config: ThreadsScrapeConfig,
    status_callback: Optional[Callable[[str], None]] = None,
) -> list[str]:
    """
    Stage 1 — Search Discovery:
    Membuka halaman pencarian Threads dan mengumpulkan URL postingan yang relevan
    dengan keyword menggunakan smart scroll dan pre-filtering.

    Args:
        page           : Halaman Playwright yang sudah aktif.
        keyword        : Kata kunci utama pencarian.
        config         : Konfigurasi scraper.
        status_callback: Callback pelaporan progress real-time (opsional).

    Returns:
        list[str]: URL-URL postingan yang relevan (sudah di-deduplikasi).
    """
    # Kuota discovery berlaku per keyword; relevansi juga harus diuji terhadap
    # keyword pencarian yang sedang diproses, bukan keyword lain dalam job.
    match_keywords = [keyword]
    encoded_query  = urllib.parse.quote(keyword)
    search_url     = f"https://www.threads.com/search?q={encoded_query}&serp_type=default"

    is_top_mode = config.search_mode.lower() == "top"
    mode_label = "TOP / Terpopuler" if is_top_mode else "LATEST / Terbaru"

    logger.info(f"--- Stage 1: Mencari URL ({mode_label}) untuk keyword '{keyword}' ---")
    if status_callback:
        status_callback(f"[Threads] Mencari URL postingan ({mode_label}) untuk keyword '{keyword}'...")

    seen_urls: set[str] = set()
    ordered_urls: list[str] = []
    skipped_count = 0

    SCROLL_STEP_PX = 850
    STALE_LIMIT    = 20  # Toleransi stagnasi lebih tinggi (20 round) agar feed sempat me-render

    try:
        await page.goto(search_url, wait_until="domcontentloaded", timeout=config.goto_timeout_ms)
        await random_delay((2.5, 4.0))

        # Login wall harus dibedakan dari "keyword ini tidak punya hasil".
        # Sebelumnya keduanya sama-sama berakhir sebagai list kosong tanpa keterangan.
        if await _looks_like_login_wall(page):
            raise ThreadsScrapeAborted(
                f"Sesi Threads tidak aktif saat Stage 1 (login wall) untuk keyword '{keyword}'",
                detail=f"URL halaman: {page.url}",
            )

        # Jika mode LATEST, klik tab "Recent" / "Terbaru"
        if not is_top_mode:
            try:
                await asyncio.sleep(1.0)
                recent_tab = page.locator(
                    'div[role="tab"], span[role="tab"], a[role="tab"], button[role="tab"]'
                ).filter(has_text=re.compile(r"(?i)^(recent|terbaru)$"))
                if await recent_tab.count() > 0:
                    await recent_tab.first.click()
                    logger.info("✓ Berhasil beralih ke tab 'Recent / Terbaru' di Threads.")
                    await random_delay((2.5, 3.5))
                else:
                    # Fallback text locator
                    alt_tab = page.get_by_text(re.compile(r"^(Recent|Terbaru)$", re.IGNORECASE))
                    if await alt_tab.count() > 0:
                        await alt_tab.first.click()
                        logger.info("✓ Berhasil klik tab 'Recent / Terbaru' via text locator.")
                        await random_delay((2.5, 3.5))
                    else:
                        logger.warning("Tab 'Recent / Terbaru' tidak ditemukan di Threads, pencarian menggunakan tab default.")
            except Exception as e:
                logger.debug(f"Gagal klik tab Recent: {e}")
        else:
            logger.info("Menggunakan tab default 'Top / Terpopuler' di Threads.")

        stale_count = 0
        for scroll_round in range(1, config.search_scroll + 1):
            # Scroll normal ke bawah
            await page.evaluate(f"window.scrollBy({{ top: {SCROLL_STEP_PX}, behavior: 'smooth' }})")
            await random_delay(config.delay_range)

            cards = await page.evaluate(_JS_EXTRACT_SEARCH_CARDS)
            new_kept = 0
            new_skipped = 0
            for card in cards:
                url = card["url"]
                if url in seen_urls:
                    continue
                # Safety net Python: validasi URL adalah postingan Threads yang valid
                if not _is_threads_post_url(url):
                    logger.debug(f"  URL dibuang (bukan postingan valid): {url}")
                    continue
                seen_urls.add(url)
                if _keyword_matches_text(card["text"], match_keywords):
                    ordered_urls.append(url)
                    new_kept += 1
                else:
                    new_skipped += 1
                    skipped_count += 1

            if new_kept > 0:
                stale_count = 0
                msg = f"  Scroll #{scroll_round}: +{new_kept} URL relevan (total: {len(ordered_urls)}, dilewati: {skipped_count})"
                logger.info(msg)
                if status_callback:
                    status_callback(f"[Threads] Mengumpulkan URL ({len(ordered_urls)}/{config.max_links} URL relevan ditemukan)...")
            elif new_skipped > 0:
                stale_count = 0
            else:
                stale_count += 1

            if len(ordered_urls) >= config.max_links:
                logger.info(f"  Kuota {config.max_links} URL tercapai pada scroll #{scroll_round}.")
                if status_callback:
                    status_callback(f"[Threads] Kuota {config.max_links} URL berhasil tercapai.")
                break

            if stale_count >= STALE_LIMIT:
                logger.info(f"  Tidak ada post baru selama {STALE_LIMIT} scroll berturut-turut. Mengakhiri Stage 1.")
                break

        ordered_urls = ordered_urls[:config.max_links]
        summary_msg = f"Stage 1 selesai: {len(ordered_urls)} URL relevan dari {len(seen_urls)} total postingan terdeteksi."
        logger.info(summary_msg)
        if status_callback:
            status_callback(f"[Threads] {summary_msg}")

    except ThreadsScrapeAborted:
        # Tidak dipulihkan di level ini: diteruskan agar job berhenti dengan pesan jelas.
        raise
    except Exception as e:
        logger.warning(f"Stage 1 gagal untuk keyword '{keyword}': {e}")

    return ordered_urls


# ---------------------------------------------------------------------------
# Stage 2: Deep Crawl
# ---------------------------------------------------------------------------

async def deep_crawl_post(
    page: Page,
    link: str,
    config: ThreadsScrapeConfig,
    max_retries: int = 2,
    status_callback: Optional[Callable[[str], None]] = None,
) -> Stage2Outcome:
    """
    Stage 2 — Deep Crawl:
    Buka satu URL postingan Threads dan ekstrak konten thread asli + semua reply
    menggunakan slow scan.

    Strategi navigasi per URL:
      1. Navigasi in-app via router SPA Threads (`_open_post_in_app`) — jalur UTAMA.
         Terbukti berhasil pada verifikasi dengan sesi riil (2026-09-23).
      2. `page.goto` langsung — fallback terakhir; Threads membalas HTTP 302 ke
         beranda dengan `?injected_media_ids=...`, jadi biasanya hanya menghasilkan skip.

    Identitas halaman diverifikasi beberapa kali: tepat setelah load, setelah halaman
    sempat hydrate, dan berkala di dalam `_slow_scan_comments`. Ini mencegah scan
    berjalan di DOM beranda seperti yang terjadi sebelumnya.

    Args:
        page           : Halaman Playwright yang sudah aktif.
        link           : URL postingan Threads yang akan di-crawl.
        config         : Konfigurasi scraper.
        max_retries    : Jumlah attempt tambahan untuk error tak terduga.
        status_callback: Callback pelaporan status (opsional).

    Returns:
        Stage2Outcome: data hasil crawl + status skip + alasannya. Kegagalan tidak
        lagi dikembalikan sebagai list kosong tanpa keterangan.
    """
    logger.info(f"Deep Crawling : {link}")
    requested_url = _normalize_threads_url(link)
    last_reason = ""

    for attempt in range(1, max_retries + 2):
        # Attempt ganjil = navigasi in-app (jalur utama), attempt genap = fallback goto.
        # Dengan max_retries=2 urutannya: in-app -> goto -> in-app.
        use_in_app = attempt % 2 == 1
        try:
            if use_in_app:
                logger.info("  Navigasi in-app (router SPA Threads)...")
                if not await _open_post_in_app(page, requested_url):
                    last_reason = last_reason or "navigasi in-app tidak mendarat di post yang diminta"
                    continue
            else:
                # Fallback: goto top-level (diketahui dibalas 302 ke beranda).
                logger.info("  Fallback: page.goto langsung...")
                await page.goto(
                    requested_url,
                    wait_until="domcontentloaded",
                    timeout=config.goto_timeout_ms,
                )
                await random_delay(config.delay_range)

            # Verifikasi #1 — tepat setelah load (menangkap bounce cepat).
            identity_ok, reason = await _verify_post_identity(page, requested_url)

            # Verifikasi #2 — setelah halaman sempat hydrate. Inilah celah yang
            # sebelumnya lolos: bounce Threads terjadi SETELAH pengecekan pertama.
            if identity_ok:
                await page.wait_for_timeout(3000)
                identity_ok, reason = await _verify_post_identity(page, requested_url)

            if not identity_ok:
                last_reason = reason
                logger.warning(f"  Identitas post tidak cocok: {reason}")
                continue

            results = await asyncio.wait_for(
                _slow_scan_comments(page, requested_url, config),
                timeout=config.per_post_timeout_s,
            )
            return Stage2Outcome(data=results)

        except Stage2IdentityLost as error:
            last_reason = f"identitas post hilang di tengah scan ({error.reason})"
            logger.error(f"  {link}: {last_reason}")
            return Stage2Outcome(skipped=True, reason=last_reason)

        except asyncio.TimeoutError:
            last_reason = f"timeout {config.per_post_timeout_s:.0f}s saat scan"
            logger.warning(f"  Timeout pada {link}. Lanjut ke postingan berikutnya.")
            return Stage2Outcome(skipped=True, reason=last_reason)

        except Exception as error:
            last_reason = str(error)
            if attempt <= max_retries:
                wait_s = 2 ** attempt
                logger.warning(
                    f"  Error pada {link} (attempt {attempt}/{max_retries + 1}): {error}. "
                    f"Retry dalam {wait_s}s..."
                )
                await asyncio.sleep(wait_s)
            else:
                logger.warning(f"  Gagal setelah {max_retries + 1} attempt pada {link}.")

    reason = last_reason or "halaman tidak pernah menjadi post yang diminta"
    msg = (
        "Stage 2 melewati URL: diminta="
        f"{requested_url}, alasan={reason}"
    )
    logger.warning(msg)
    if status_callback:
        status_callback(f"[Threads] {msg}")
    return Stage2Outcome(skipped=True, reason=reason)


# ---------------------------------------------------------------------------
# Fungsi Publik Utama: run_threads_scraper
# ---------------------------------------------------------------------------

@ensure_proactor_loop
async def run_threads_scraper(
    keywords       : list[str] = None,
    direct_urls    : list[str] = None,
    config         : ThreadsScrapeConfig = None,
    checkpoint_path: str = DEFAULT_CHECKPOINT,
    final_path     : str = DEFAULT_OUTPUT,
    status_callback: Optional[Callable[[str], None]] = None,
) -> "pd.DataFrame":
    """
    Pipeline scraping Threads (Meta) lengkap:
      Stage 1: Kumpulkan URL dari pencarian Threads berdasarkan keyword.
      Stage 2: Deep crawl setiap URL untuk ekstraksi thread + reply.

    Args:
        keywords       : List keyword pencarian (bisa None jika hanya pakai direct_urls).
        direct_urls    : List URL postingan langsung (bisa None jika hanya pakai keywords).
        config         : Konfigurasi scraper (default: ThreadsScrapeConfig()).
        checkpoint_path: Path file CSV checkpoint inkremental.
        final_path     : Path file CSV hasil akhir.
        status_callback: Callback pelaporan progress real-time (opsional).

    Returns:
        pd.DataFrame: Seluruh data yang terkumpul (baris = 1 thread/reply).
    """
    import pandas as pd  # import lokal agar aman

    config      = config or ThreadsScrapeConfig()
    keywords    = keywords or []
    direct_urls = direct_urls or []

    # Log ke disk agar kegagalan dapat diperiksa setelah kejadian.
    _ensure_file_logging()

    # Validasi sesi (level filesystem)
    if not check_profile_exists(config.profile_dir):
        logger.error(
            f"Sesi Threads belum ditemukan di '{config.profile_dir}'. "
            "Jalankan 'python -m src.auth.login_threads' terlebih dahulu."
        )
        return pd.DataFrame()

    all_results     : list[dict] = []
    global_seen_urls: set[str]   = set()

    async with async_playwright() as p:
        try:
            context, page = await create_browser(p, config.profile_dir, config.headless)
        except RuntimeError as e:
            logger.error(str(e))
            return pd.DataFrame()

        try:
            # ── Pre-flight: verifikasi login LIVE pada host threads.com ────────
            # Menggantikan cek nama cookie lama yang tidak memeriksa host domain,
            # sehingga cookie .threads.net/.instagram.com ikut dianggap valid.
            cookies = await context.cookies()
            cookie_hosts = sorted({
                (c.get("domain") or "")
                for c in cookies
                if c.get("name") == "sessionid" and c.get("value")
            })
            logger.info(f"domain cookie sessionid terdeteksi: {cookie_hosts or '<tidak ada>'}")

            logged_in, current_url = await _check_logged_in_live(page)
            if not logged_in:
                reason = "Sesi Threads tidak aktif di threads.com (login wall terdeteksi)."
                detail = (
                    f"URL terakhir       : {current_url}\n"
                    f"domain sessionid   : {cookie_hosts or '<tidak ada>'}\n"
                    "Tindakan           : jalankan 'python -m src.auth.login_threads', "
                    "pastikan login selesai pada host threads.com, lalu ulangi scraping."
                )
                logger.error(f"{reason}\n{detail}")
                if status_callback:
                    status_callback(f"[Threads] {reason}")
                raise ThreadsScrapeAborted(reason, detail)

            session_urls: list[str] = []

            # Direct URLs
            for u in direct_urls:
                if u not in global_seen_urls:
                    global_seen_urls.add(u)
                    session_urls.append(u)

            # Stage 1 — Keyword Search
            for kw in keywords:
                urls = await collect_post_urls(
                    page,
                    kw,
                    config,
                    status_callback=status_callback,
                )
                for u in urls:
                    if u not in global_seen_urls:
                        global_seen_urls.add(u)
                        session_urls.append(u)

            logger.info(f"Total URL untuk di-crawl: {len(session_urls)}")

            # Simpan daftar URL Stage 1 ke disk agar isi Stage 1 dapat diverifikasi
            # terpisah (sebelumnya hanya ada di memori, tidak bisa diperiksa).
            _dump_stage1_urls(session_urls, keywords, direct_urls)

            if not session_urls:
                msg_empty = "Tidak ada URL yang ditemukan. Coba periksa keyword atau sesi login."
                logger.warning(msg_empty)
                if status_callback:
                    status_callback(f"[Threads] {msg_empty}")
                return pd.DataFrame()

            # Stage 2 — Deep Crawl
            total_urls = len(session_urls)
            skipped_urls: list[tuple[str, str]] = []

            for idx, link in enumerate(session_urls, start=1):
                logger.info(f"[{idx}/{total_urls}] {link}")
                if status_callback:
                    status_callback(f"[Threads] Tahap 2: Deep Crawl postingan {idx}/{total_urls} ({link})...")

                outcome = await deep_crawl_post(page, link, config, status_callback=status_callback)
                if outcome.data:
                    all_results.extend(outcome.data)
                    append_checkpoint(outcome.data, checkpoint_path)
                    logger.info(f"  ✓ {len(outcome.data)} item terkumpul dari {link}")
                elif outcome.skipped:
                    skipped_urls.append((link, outcome.reason))
                await random_delay(config.delay_range)

            # ── Fail-loud: bila SEMUA URL di-skip, ada masalah sistemik ────────
            # Sebelumnya kondisi ini berakhir sebagai "sukses 0 baris" sehingga
            # bug Stage 2 tidak terlihat.
            if total_urls and len(skipped_urls) == total_urls:
                reason = (
                    f"Stage 2 Threads gagal untuk SEMUA {total_urls} URL: halaman tidak pernah "
                    "menjadi post yang diminta (Threads membounce ke beranda / post lain)."
                )
                detail_lines = [f"- {url}: {why}" for url, why in skipped_urls[:10]]
                if len(skipped_urls) > 10:
                    detail_lines.append(f"- ... dan {len(skipped_urls) - 10} URL lainnya")
                detail = "\n".join(detail_lines)
                logger.error(f"{reason}\n{detail}")
                if status_callback:
                    status_callback(f"[Threads] {reason}")
                raise ThreadsScrapeAborted(reason, detail)

            if skipped_urls:
                logger.warning(
                    f"{len(skipped_urls)}/{total_urls} URL di-skip pada Stage 2 (rincian di bawah)."
                )
                for url, why in skipped_urls:
                    logger.warning(f"  SKIP {url}: {why}")

        finally:
            try:
                await context.close()
            except Exception:
                pass

    df = results_to_dataframe(all_results)
    if not df.empty:
        save_dataframe(df, final_path)
        logger.info(f"Scraping Threads selesai. Total: {len(df)} baris. File: {final_path}")

    else:
        logger.warning("Tidak ada data yang terkumpul dari sesi ini.")
    return df


# ---------------------------------------------------------------------------
# Entrypoint CLI
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    import asyncio
    keywords = sys.argv[1:] if len(sys.argv) > 1 else []
    if not keywords:
        print("Penggunaan: python threads_scraper.py <keyword1> [keyword2 ...]")
        sys.exit(1)
    df_result = asyncio.run(run_threads_scraper(keywords=keywords))
    print(f"\nHasil: {len(df_result)} baris terkumpul.")
