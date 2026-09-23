"""
Session Manager Service.

Bertanggung jawab memeriksa ketersediaan dan validitas sesi login browser
untuk platform X (Twitter) dan Threads / Instagram — TANPA membuka browser penuh.

Fungsi Utama:
    - check_profile_exists(platform)   -> bool  : Cek apakah folder profil ada dan berisi data.
    - is_session_valid(platform)       -> bool  : Validasi apakah sesi masih aktif berdasarkan
                                                  keberadaan & isi folder profil.
    - get_all_sessions_status()        -> dict  : Ringkasan status kedua platform sekaligus.

Prinsip:
    Modul ini hanya memeriksa filesystem — tidak pernah membuka browser,
    tidak pernah membuat folder baru, dan tidak pernah melakukan login otomatis.
    Jika sesi tidak ditemukan / tidak valid, modul memberikan status + pesan
    instruksi agar user menjalankan login_x.py atau login_threads.py.
"""

import logging
import os
import shutil
import sqlite3
import tempfile
from pathlib import Path
from datetime import datetime
from time import time

# Import path konfigurasi terpusat
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[2]))  # tambah root backend ke path

from configs.config import X_PROFILE_DIR, THREADS_PROFILE_DIR

logger = logging.getLogger("session_manager")
if not logger.handlers:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")


# ---------------------------------------------------------------------------
# Konstanta
# ---------------------------------------------------------------------------

PLATFORM_PROFILES = {
    "x"       : X_PROFILE_DIR,
    "threads" : THREADS_PROFILE_DIR,
}

LOGIN_INSTRUCTIONS = {
    "x": "Sesi X (Twitter) belum ditemukan atau sudah kedaluwarsa.",
    "threads": "Sesi Threads belum ditemukan atau sudah kedaluwarsa.",
}

AUTH_COOKIE_NAMES = {
    "x": ("auth_token", "twid"),
    "threads": ("sessionid", "ds_user_id"),
}

# Host domain yang sah untuk cookie autentikasi tiap platform.
#
# Penting: cookie autentikasi harus benar-benar berlaku untuk host yang di-scrape.
# Cookie `sessionid` milik `.threads.net` atau `.instagram.com` TIDAK dikirim ke
# `threads.com`, sehingga sesi tampak valid padahal halaman di-scrape sebagai
# tamu — inilah yang membuat Threads membounce permalink ke beranda.
AUTH_COOKIE_HOSTS = {
    "x": ("x.com", "twitter.com"),
    "threads": ("threads.com",),
}

CHROMIUM_EPOCH_OFFSET = 11644473600


# ---------------------------------------------------------------------------
# Fungsi Utama
# ---------------------------------------------------------------------------

def check_profile_exists(platform: str) -> bool:
    """
    Memeriksa apakah direktori persistent profile browser untuk platform
    tertentu sudah ada dan berisi data (tidak kosong).

    Args:
        platform (str): "x" atau "threads" (case-insensitive).

    Returns:
        bool: True jika folder profil ada dan memiliki isi, False jika belum ada / kosong.
    """
    key = platform.lower().strip()
    profile_path = PLATFORM_PROFILES.get(key)
    if not profile_path:
        logger.warning(f"Platform tidak dikenal: '{platform}'. Gunakan 'x' atau 'threads'.")
        return False

    p = Path(profile_path)
    if not p.exists() or not p.is_dir():
        return False
    try:
        # Cek apakah direktori memiliki setidaknya 1 file/subfolder
        return any(p.iterdir())
    except Exception:
        return False


def _has_auth_cookies(profile_path: Path, platform: str) -> dict:
    """
    Periksa cookie autentikasi Chromium tanpa membuka browser.

    Selain nama + masa berlaku, cookie juga harus berlaku untuk host domain yang
    sah bagi platform (`AUTH_COOKIE_HOSTS`). Pemeriksaan host ini penting karena
    profil bisa saja punya `sessionid` untuk domain lain yang tidak dipakai scraper.

    Returns:
        dict: {
            "valid"        : bool,
            "matched_names": set[str],  # nama cookie yang lolos semua syarat
            "matched_hosts": set[str],  # host_key yang lolos
            "seen_hosts"   : set[str],  # semua host_key dari cookie auth apa pun
        }
    """
    required_names = set(AUTH_COOKIE_NAMES[platform])
    allowed_hosts  = AUTH_COOKIE_HOSTS.get(platform, ())
    result = {"valid": False, "matched_names": set(), "matched_hosts": set(), "seen_hosts": set()}

    # Cek lokasi standar database cookie Chromium terlebih dahulu (hindari glob rekursif ribuan file di Docker mount)
    candidate_dbs = [
        profile_path / "Default" / "Cookies",
        profile_path / "Default" / "Network" / "Cookies",
        profile_path / "Network" / "Cookies",
        profile_path / "Cookies",
    ]
    cookie_databases = [db for db in candidate_dbs if db.is_file()]
    if not cookie_databases:
        cookie_databases = list(profile_path.glob("**/Cookies"))

    for cookie_database in cookie_databases:
        temporary_database = None
        try:
            # Chromium may keep this database locked while a scraper is running.
            file_descriptor, temporary_path = tempfile.mkstemp(suffix=".sqlite")
            os.close(file_descriptor)
            temporary_database = Path(temporary_path)
            shutil.copy2(cookie_database, temporary_database)

            connection = sqlite3.connect(temporary_database)
            try:
                rows = connection.execute(
                    "SELECT name, host_key, expires_utc, length(encrypted_value) "
                    "FROM cookies WHERE name IN (?, ?)",
                    tuple(required_names),
                ).fetchall()
            finally:
                connection.close()

            for name, host_key, expires_utc, value_length in rows:
                host = (host_key or "").lower()
                if host:
                    result["seen_hosts"].add(host)

                if value_length <= 0:
                    continue
                if expires_utc and expires_utc / 1_000_000 - CHROMIUM_EPOCH_OFFSET <= time():
                    continue
                if allowed_hosts and not any(allowed in host for allowed in allowed_hosts):
                    continue

                result["matched_names"].add(name)
                result["matched_hosts"].add(host)

            if required_names.issubset(result["matched_names"]):
                result["valid"] = True
                return result
        except (OSError, sqlite3.Error) as error:
            logger.debug("Gagal membaca database cookie %s: %s", cookie_database, error)
        finally:
            if temporary_database:
                temporary_database.unlink(missing_ok=True)

    return result

def is_session_valid(platform: str) -> dict:
    """
    Memeriksa validitas sesi login untuk platform tertentu berdasarkan
    keberadaan dan isi folder persistent profile browser.

    Validasi ini memeriksa keberadaan cookie autentikasi wajib di database
    Chromium. Keberadaan folder saja tidak cukup karena Chromium membuat
    artefak profil sebelum user berhasil login.

    Catatan: Validasi mendalam (apakah cookie belum expired di server)
    hanya bisa dilakukan saat browser dibuka oleh scraper via is_logged_in().

    Args:
        platform (str): "x" atau "threads".

    Returns:
        dict: {
            "platform": str,
            "profile_dir": str,
            "exists": bool,
            "is_valid": bool,
            "last_modified": str | None,
            "message": str,
        }
    """
    key = platform.lower().strip()
    profile_path = PLATFORM_PROFILES.get(key)

    if not profile_path:
        return {
            "platform"      : platform,
            "profile_dir"   : None,
            "exists"        : False,
            "is_valid"      : False,
            "last_modified" : None,
            "message"       : f"Platform '{platform}' tidak dikenal. Gunakan 'x' atau 'threads'.",
        }

    p = Path(profile_path)
    exists = p.exists() and p.is_dir()
    has_data = False
    cookie_check = {"valid": False, "matched_names": set(), "matched_hosts": set(), "seen_hosts": set()}
    last_modified = None

    if exists:
        try:
            # Cek isi folder untuk informasi diagnostik dan waktu perubahan.
            children = list(p.iterdir())
            has_data = len(children) > 0
            if has_data:
                # Cek mtime dari file cookie atau folder profil langsung (hindari rglob ribuan file cache di Docker)
                cookie_candidates = [
                    p / "Default" / "Cookies",
                    p / "Default" / "Network" / "Cookies",
                    p / "Network" / "Cookies",
                    p / "Cookies",
                ]
                target_stat = next((c for c in cookie_candidates if c.is_file()), p)
                try:
                    ts = target_stat.stat().st_mtime
                    last_modified = datetime.fromtimestamp(ts).strftime("%Y-%m-%dT%H:%M:%S")
                except Exception:
                    pass
                cookie_check = _has_auth_cookies(p, key)
        except Exception as e:
            logger.debug(f"Gagal membaca direktori profil {platform}: {e}")

    has_auth_cookies = bool(cookie_check["valid"])
    is_valid = exists and has_data and has_auth_cookies

    # Bedakan penyebab kegagalan agar tidak selalu jatuh ke pesan "belum login".
    if is_valid:
        msg = f"Sesi {platform.upper()} ditemukan dan masih aktif."
    elif exists and has_data and cookie_check["seen_hosts"] and not has_auth_cookies:
        allowed = ", ".join(AUTH_COOKIE_HOSTS.get(key, ()))
        msg = (
            f"Cookie autentikasi {platform.upper()} ada, tetapi TIDAK berlaku untuk host "
            f"{allowed}. Host yang ditemukan: {', '.join(sorted(cookie_check['seen_hosts']))}. "
            "Login ulang pada host yang benar lalu ulangi scraping."
        )
    else:
        msg = LOGIN_INSTRUCTIONS.get(key, f"Sesi {platform.upper()} tidak ditemukan.")

    return {
        "platform"      : platform.upper(),
        "profile_dir"   : p.name,          # Hanya nama folder, bukan path absolut
        "exists"        : exists,
        "is_valid"      : is_valid,
        "last_modified" : last_modified,
        "message"       : msg,
        "auth_hosts"    : sorted(cookie_check["matched_hosts"]),
        "warning_hosts" : sorted(cookie_check["seen_hosts"] - cookie_check["matched_hosts"]),
    }


def get_all_sessions_status() -> dict:
    """
    Mengembalikan status sesi untuk semua platform (X dan Threads) sekaligus.

    Returns:
        dict: {
            "x"       : { ... is_session_valid result ... },
            "threads" : { ... is_session_valid result ... },
        }
    """
    return {
        "x"       : is_session_valid("x"),
        "threads" : is_session_valid("threads"),
    }


# ---------------------------------------------------------------------------
# CLI standalone — jalankan langsung untuk cek status sesi
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    import json

    print("\n=== CEK STATUS SESI LOGIN ===\n")
    status = get_all_sessions_status()

    for platform, info in status.items():
        print(f"Platform : {info['platform']}")
        print(f"  Profile Dir   : {info['profile_dir']}")
        print(f"  Exists        : {info['exists']}")
        print(f"  Valid         : {info['is_valid']}")
        print(f"  Last Modified : {info['last_modified'] or '-'}")
        print(f"  Status        : {info['message']}")
        print()
