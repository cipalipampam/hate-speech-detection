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
from pathlib import Path
from datetime import datetime

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


def is_session_valid(platform: str) -> dict:
    """
    Memeriksa validitas sesi login untuk platform tertentu berdasarkan
    keberadaan dan isi folder persistent profile browser.

    Validasi sederhana ini memeriksa:
    - Keberadaan folder profil.
    - Folder tidak kosong (menandakan browser pernah menyimpan data sesi).

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
    last_modified = None

    if exists:
        try:
            # Cek isi folder
            children = list(p.iterdir())
            has_data = len(children) > 0
            if has_data:
                # Ambil waktu modifikasi file terbaru di dalam folder
                mod_times = []
                for child in children:
                    try:
                        mod_times.append(child.stat().st_mtime)
                    except Exception:
                        pass
                if mod_times:
                    ts = max(mod_times)
                    last_modified = datetime.fromtimestamp(ts).strftime("%Y-%m-%dT%H:%M:%S")
        except Exception as e:
            logger.debug(f"Gagal membaca direktori profil {platform}: {e}")

    is_valid = exists and has_data

    if is_valid:
        msg = f"Sesi {platform.upper()} ditemukan dan masih aktif."
    else:
        msg = LOGIN_INSTRUCTIONS.get(key, f"Sesi {platform.upper()} tidak ditemukan.")

    return {
        "platform"      : platform.upper(),
        "profile_dir"   : p.name,          # Hanya nama folder, bukan path absolut
        "exists"        : exists,
        "is_valid"      : is_valid,
        "last_modified" : last_modified,
        "message"       : msg,
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
