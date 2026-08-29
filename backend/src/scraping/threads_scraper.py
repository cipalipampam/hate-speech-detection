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

    collect_post_urls(page, keyword, config, all_keywords) -> list[str]
        Stage 1: Kumpulkan URL dari hasil pencarian Threads.

    deep_crawl_post(page, link, config) -> list[dict]
        Stage 2: Crawl mendalam satu postingan + semua replynya.

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
import re
import sys
import urllib.parse
from dataclasses import dataclass, field
from pathlib import Path

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


# ---------------------------------------------------------------------------
# Path Default Output
# ---------------------------------------------------------------------------

DEFAULT_CHECKPOINT = str(EXPORTS_DIR / "threads_checkpoint.csv")
DEFAULT_OUTPUT     = str(EXPORTS_DIR / "threads_scraper_result.csv")


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
    search_scroll     : int   = 300          # Max rounds scroll di halaman pencarian
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

_JS_EXTRACT_COMMENTS = f"""
() => {{
    const SYSTEM_EXACT = new Set({_SYSTEM_EXACT_JS});
    const TIME_RE = /^\\d+[smhdw]$/i;
    const DATE_RE = /^(\\d{{1,2}}[\\/\\.-]\\d{{1,2}}[\\/\\.-]\\d{{2,4}}|\\d{{4}}[\\/\\.-]\\d{{1,2}}[\\/\\.-]\\d{{1,2}}|\\d{{1,2}}\\s+[a-z]{{3,9}}(\\s+\\d{{2,4}})?|[a-z]{{3,9}}\\s+\\d{{1,2}}(\\s+\\d{{2,4}})?)$/i;
    const COUNT_RE = /^\\d+([.,]\\d+)?[KMB]?$/i;
    const ACTION_NOISE_RE = /^(top|view activity|lihat aktivitas|reply to.*|balas ke.*|balas kepada.*|activity|share|bagikan|teratas)$/i;
    const NOISE = new Set({_NOISE_JS});

    const allPC = document.querySelectorAll('div[data-pressable-container="true"], article');
    const results = [];
    const seen = new Set();

    for (const el of allPC) {{
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
        const url = window.location.href;
        const postIdMatch = url.match(/\\/post\\/([^/?#]+)/);
        const postId = postIdMatch ? postIdMatch[1] : '';
        if (postId) {{
            const timeAnchor = timeEl.closest('a[href]');
            if (timeAnchor && (timeAnchor.getAttribute('href') || '').includes(postId)) type = 'Original Post';
        }}

        results.push({{ user_id: username, date: dateVal, content: content, type: type }});
    }}
    return results;
}}
"""

_JS_EXTRACT_SEARCH_CARDS = """
() => {
    const links = document.querySelectorAll('a[href*="/post/"]');
    const results = [];
    const seenUrls = new Set();

    for (const link of links) {
        let url = link.href || '';
        url = url.split('?')[0].split('#')[0].replace(/\\/$/, '');
        if (!url || seenUrls.has(url) || url.includes('/media')) continue;

        let card = link;
        while (card.parentElement && card.parentElement.tagName !== 'BODY' && card.parentElement.getAttribute('role') !== 'main') {
            const parent = card.parentElement;
            const parentPostLinks = parent.querySelectorAll('a[href*="/post/"]');
            const postIds = new Set();
            for (const pl of parentPostLinks) {
                const pUrl = (pl.href || '').split('?')[0].split('#')[0].replace(/\\/$/, '');
                if (pUrl && !pUrl.includes('/media')) postIds.add(pUrl);
            }
            if (postIds.size > 1) break;
            card = parent;
        }

        const dirAutos = card.querySelectorAll('[dir="auto"]');
        const tokens = [];
        for (const da of dirAutos) {
            const t = (da.textContent || '').trim();
            if (t) tokens.push(t);
        }
        const fullText = tokens.length > 0 ? tokens.join(' ') : (card.textContent || '').trim();

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
        return await page.evaluate("""() => {
            const targets = ['related threads', 'postingan terkait', 'suggested threads',
                             'postingan yang disarankan', 'more threads', 'postingan lainnya',
                             'thread lainnya', 'rekomendasi'];
            const elements = document.querySelectorAll('h2, h3, h4, [role="heading"], div[dir="auto"], span[dir="auto"]');
            for (const el of elements) {
                const txt = (el.textContent || '').trim().toLowerCase();
                if (txt.length > 0 && txt.length < 40) {
                    for (const t of targets) {
                        if (txt === t || txt.startsWith(t)) {
                            const rect = el.getBoundingClientRect();
                            if (rect.top > 0 && rect.top < window.innerHeight + 300) return true;
                        }
                    }
                }
            }
            return false;
        }""")
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


async def _collect_js_items(page: Page, source_link: str, collected: dict) -> int:
    """Eksekusi JS extractor dan masukkan hasilnya ke dict 'collected' tanpa duplikasi."""
    count_before = len(collected)
    try:
        items = await page.evaluate(_JS_EXTRACT_COMMENTS)
        for item in items:
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
    return len(collected) - count_before


# ---------------------------------------------------------------------------
# Stage 2: Slow Scan (Micro-Step Forward + Reverse Sweep)
# ---------------------------------------------------------------------------

async def _slow_scan_comments(page: Page, source_link: str, config: ThreadsScrapeConfig) -> list[dict]:
    """
    Mengekstrak konten thread + semua reply dari halaman postingan Threads yang sudah dibuka.
    Menggunakan micro-step scroll ke bawah (forward scan) + sweep ke atas (reverse scan)
    untuk memastikan semua thread/reply ter-render dan ter-ekstrasi.
    """
    CONFIRM_ROUNDS  = 5
    LOG_EVERY       = 20
    EXPAND_EVERY    = 2
    REVERSE_STEP_PX = 250

    collected: dict[tuple, dict] = {}
    confirm_streak = 0
    step = 0
    last_log_count = 0

    for step in range(config.scan_max_steps):
        try:
            has_divider = await _check_thread_divider(page)
            if has_divider and step >= 5:
                await _collect_js_items(page, source_link, collected)
                logger.info(f"Thread divider terdeteksi pada step {step + 1}. Berhenti forward scan.")
                break

            got_new = False
            if step % config.scan_parse_every == 0:
                new_added = await _collect_js_items(page, source_link, collected)
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
            nudge_added = await _collect_js_items(page, source_link, collected)
            if nudge_added > 0:
                confirm_streak = 0

        except Exception as e:
            logger.warning(f"Forward scan berhenti pada step {step}: {e}")
            break

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
            await _collect_js_items(page, source_link, collected)
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
        await _collect_js_items(page, source_link, collected)
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
    text_lower    = text.lower()
    text_no_space = text_lower.replace(" ", "")
    for kw in keywords:
        clean          = kw.lstrip('#').lower()
        clean_no_space = clean.replace(" ", "")
        if clean in text_lower:
            return True
        if clean_no_space and clean_no_space in text_no_space:
            return True
    return False


# ---------------------------------------------------------------------------
# Stage 1: Search Discovery
# ---------------------------------------------------------------------------

async def collect_post_urls(
    page: Page, keyword: str, config: ThreadsScrapeConfig, all_keywords: list[str] = None,
) -> list[str]:
    """
    Stage 1 — Search Discovery:
    Membuka halaman pencarian Threads dan mengumpulkan URL postingan yang relevan
    dengan keyword menggunakan smart scroll dan pre-filtering.

    Args:
        page        : Halaman Playwright yang sudah aktif.
        keyword     : Kata kunci utama pencarian.
        config      : Konfigurasi scraper.
        all_keywords: Daftar semua keyword untuk filtering (opsional).

    Returns:
        list[str]: URL-URL postingan yang relevan (sudah di-deduplikasi).
    """
    match_keywords = all_keywords or [keyword]
    encoded_query  = urllib.parse.quote(keyword)
    search_url     = f"https://www.threads.net/search?q={encoded_query}&serp_type=default"

    is_top_mode = config.search_mode.lower() == "top"
    mode_label = "TOP / Terpopuler" if is_top_mode else "LATEST / Terbaru"

    logger.info(f"--- Stage 1: Mencari URL ({mode_label}) untuk keyword '{keyword}' ---")
    seen_urls: set[str] = set()
    ordered_urls: list[str] = []
    skipped_count = 0

    SCROLL_STEP_PX = 600
    STALE_LIMIT    = 8

    try:
        await page.goto(search_url, wait_until="domcontentloaded", timeout=config.goto_timeout_ms)
        await random_delay((2.5, 4.0))

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
            await page.evaluate(f"window.scrollBy({{ top: {SCROLL_STEP_PX}, behavior: 'smooth' }})")
            await random_delay(config.delay_range)

            cards = await page.evaluate(_JS_EXTRACT_SEARCH_CARDS)
            new_kept = 0
            new_skipped = 0
            for card in cards:
                url = card["url"]
                if url in seen_urls:
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
                logger.info(f"  Scroll #{scroll_round}: +{new_kept} URL relevan (total: {len(ordered_urls)}, dilewati: {skipped_count})")
            elif new_skipped > 0:
                stale_count = 0
            else:
                stale_count += 1

            if len(ordered_urls) >= config.max_links:
                logger.info(f"  Kuota {config.max_links} URL tercapai pada scroll #{scroll_round}.")
                break
            if stale_count >= STALE_LIMIT:
                logger.info(f"  Tidak ada post baru selama {STALE_LIMIT} scroll berturut-turut. Berhenti.")
                break

        ordered_urls = ordered_urls[:config.max_links]
        logger.info(f"Stage 1 selesai: {len(ordered_urls)} URL relevan dari {len(seen_urls)} total.")

    except Exception as e:
        logger.warning(f"Stage 1 gagal untuk keyword '{keyword}': {e}")

    return ordered_urls


# ---------------------------------------------------------------------------
# Stage 2: Deep Crawl
# ---------------------------------------------------------------------------

async def deep_crawl_post(
    page: Page, link: str, config: ThreadsScrapeConfig, max_retries: int = 2,
) -> list[dict]:
    """
    Stage 2 — Deep Crawl:
    Buka satu URL postingan Threads dan ekstrak konten thread asli + semua reply
    menggunakan slow scan.

    Args:
        page       : Halaman Playwright yang sudah aktif.
        link       : URL postingan Threads yang akan di-crawl.
        config     : Konfigurasi scraper.
        max_retries: Jumlah retry jika terjadi error.

    Returns:
        list[dict]: Data postingan dan reply.
    """
    logger.info(f"Deep Crawling : {link}")
    for attempt in range(1, max_retries + 2):
        try:
            await page.goto(link, wait_until="domcontentloaded", timeout=config.goto_timeout_ms)
            await random_delay(config.delay_range)
            results = await asyncio.wait_for(
                _slow_scan_comments(page, link, config),
                timeout=config.per_post_timeout_s,
            )
            return results
        except asyncio.TimeoutError:
            logger.warning(f"Timeout pada {link}. Lanjut ke postingan berikutnya.")
            return []
        except Exception as e:
            if attempt <= max_retries:
                wait_s = 2 ** attempt
                logger.warning(f"Error pada {link} (attempt {attempt}/{max_retries + 1}): {e}. Retry dalam {wait_s}s...")
                await asyncio.sleep(wait_s)
            else:
                logger.warning(f"Gagal setelah {max_retries + 1} attempt pada {link}. Di-skip.")
                return []


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

    Returns:
        pd.DataFrame: Seluruh data yang terkumpul (baris = 1 thread/reply).
    """
    import pandas as pd  # import lokal agar aman

    config      = config or ThreadsScrapeConfig()
    keywords    = keywords or []
    direct_urls = direct_urls or []

    # Validasi sesi
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
            # Validasi sesi aktif di browser (cek cookie)
            cookies     = await context.cookies()
            has_session = any(c.get("name") == "sessionid"  and bool(c.get("value")) for c in cookies)
            has_ds_user = any(c.get("name") == "ds_user_id" and bool(c.get("value")) for c in cookies)
            if not (has_session and has_ds_user):
                logger.warning("Cookie sessionid/ds_user_id tidak ditemukan. Periksa status login Threads.")

            # Standby di home
            try:
                await page.goto("https://www.threads.net/", timeout=60_000)
            except Exception:
                pass

            session_urls: list[str] = []

            # Direct URLs
            for u in direct_urls:
                if u not in global_seen_urls:
                    global_seen_urls.add(u)
                    session_urls.append(u)

            # Stage 1 — Keyword Search
            for kw in keywords:
                urls = await collect_post_urls(page, kw, config, all_keywords=keywords)
                for u in urls:
                    if u not in global_seen_urls:
                        global_seen_urls.add(u)
                        session_urls.append(u)

            logger.info(f"Total URL untuk di-crawl: {len(session_urls)}")

            if not session_urls:
                logger.warning("Tidak ada URL yang ditemukan. Coba periksa keyword atau sesi login.")
                return pd.DataFrame()

            # Stage 2 — Deep Crawl
            for idx, link in enumerate(session_urls, start=1):
                logger.info(f"[{idx}/{len(session_urls)}] {link}")
                post_data = await deep_crawl_post(page, link, config)
                if post_data:
                    all_results.extend(post_data)
                    append_checkpoint(post_data, checkpoint_path)
                await random_delay(config.delay_range)

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
