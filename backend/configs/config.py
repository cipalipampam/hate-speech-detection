"""
Konfigurasi Terpusat Sistem Backend.

Menyimpan seluruh path direktori, konstanta runtime, dan parameter default
untuk modul auth, scraping, preprocessing, dan inferensi IndoBERT.
"""

from pathlib import Path

# ---------------------------------------------------------------------------
# Direktori Root
# ---------------------------------------------------------------------------

# Direktori root backend (d:/skripsi/Project/apps/backend)
BACKEND_DIR = Path(__file__).resolve().parent.parent

# Direktori storage runtime
STORAGE_DIR     = BACKEND_DIR / "storage"
SESSIONS_DIR    = STORAGE_DIR / "sessions"
EXPORTS_DIR     = STORAGE_DIR / "exports"
DICT_DIR        = STORAGE_DIR / "dictionaries"

# Direktori model IndoBERT
MODEL_DIR       = BACKEND_DIR / "saved_models" / "indobert_sentiment"

# ---------------------------------------------------------------------------
# Path Sesi Login (Persistent Browser Profile)
# ---------------------------------------------------------------------------

# Direktori User Data Directory browser untuk X (Twitter)
X_PROFILE_DIR       = SESSIONS_DIR / "x_profile"

# Direktori User Data Directory browser untuk Threads / Instagram
THREADS_PROFILE_DIR = SESSIONS_DIR / "threads_profile"

# ---------------------------------------------------------------------------
# Path File Pendukung
# ---------------------------------------------------------------------------

# Kamus normalisasi kata tidak baku (slang/typo) ke kata baku bahasa Indonesia
KAMUSALAY_PATH = DICT_DIR / "kamusalay.csv"

# ---------------------------------------------------------------------------
# Konfigurasi Scraper
# ---------------------------------------------------------------------------

SCRAPER_CONFIG = {
    "headless"          : False,            # False = buka browser GUI (wajib untuk login)
    "search_mode"       : "latest",         # "latest" (Terbaru/Recent) atau "top" (Terpopuler/Default)
    "goto_timeout_ms"   : 60_000,           # Timeout navigasi halaman (ms)
    "delay_range"       : (2.0, 4.0),       # Range random delay antar aksi (detik)
    "max_links"         : 500,              # Batas maks URL postingan per keyword
    "scan_step_px"      : 250,             # Langkah scroll micro-step (px)
    "scan_delay_range"  : (0.15, 0.30),    # Delay antar langkah scroll (detik)
    "scan_max_steps"    : 3000,            # Maks langkah scroll per postingan
}

# ---------------------------------------------------------------------------
# Konfigurasi Model IndoBERT
# ---------------------------------------------------------------------------

MODEL_CONFIG = {
    "max_seq_len"   : 128,              # Panjang token maksimum untuk tokenizer
    "batch_size"    : 32,              # Jumlah sampel per batch inferensi
    "device"        : "cpu",           # "cuda" jika ada GPU, "cpu" untuk CPU
    "label_mapping" : {
        0: "Negatif",
        1: "Netral",
        2: "Positif",
    },
}

# ---------------------------------------------------------------------------
# Pastikan direktori penting sudah ada saat modul diimpor
# ---------------------------------------------------------------------------

for _dir in (SESSIONS_DIR, EXPORTS_DIR, DICT_DIR, X_PROFILE_DIR.parent, THREADS_PROFILE_DIR.parent):
    _dir.mkdir(parents=True, exist_ok=True)
