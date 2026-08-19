"""
Modul Scraping Data.

Ekspor publik:
    X (Twitter):
        - run_x_scraper()         : Pipeline scraping X lengkap (Stage 1 + Stage 2).
        - XScrapeConfig           : Kelas konfigurasi scraper X.

    Threads (Meta):
        - run_threads_scraper()   : Pipeline scraping Threads lengkap (Stage 1 + Stage 2).
        - ThreadsScrapeConfig     : Kelas konfigurasi scraper Threads.

    Utilitas Bersama (dari base_scraper):
        - results_to_dataframe()  : Konversi list dict ke DataFrame deduplikasi.
        - save_dataframe()        : Simpan DataFrame ke CSV.
        - append_checkpoint()     : Append inkremental ke CSV checkpoint.
        - check_profile_exists()  : Cek ketersediaan direktori profil sesi.
"""

from .x_scraper import run_x_scraper, XScrapeConfig
from .threads_scraper import run_threads_scraper, ThreadsScrapeConfig
from .base_scraper import (
    results_to_dataframe,
    save_dataframe,
    append_checkpoint,
    check_profile_exists,
)

__all__ = [
    # X
    "run_x_scraper",
    "XScrapeConfig",
    # Threads
    "run_threads_scraper",
    "ThreadsScrapeConfig",
    # Utilitas
    "results_to_dataframe",
    "save_dataframe",
    "append_checkpoint",
    "check_profile_exists",
]
