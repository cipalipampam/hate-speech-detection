"""
Modul Autentikasi & Manajemen Sesi.

Ekspor publik:
    - setup_x_login()           : Buka browser GUI untuk login X (Twitter).
    - setup_threads_login()     : Buka browser GUI untuk login Threads.
    - check_profile_exists()    : Cek apakah folder profil sesi sudah ada.
    - is_session_valid()        : Cek validitas sesi berdasarkan isi folder profil.
    - get_all_sessions_status() : Status sesi kedua platform sekaligus.
"""

from .login_x        import setup_x_login
from .login_threads  import setup_threads_login
from .session_manager import (
    check_profile_exists,
    is_session_valid,
    get_all_sessions_status,
)

__all__ = [
    "setup_x_login",
    "setup_threads_login",
    "check_profile_exists",
    "is_session_valid",
    "get_all_sessions_status",
]
