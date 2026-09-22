"""
Auth Service — Application Service Layer.

Mengelola:
1. Pengecekan ketersediaan Xvfb display server untuk GUI browser.
2. Pengecekan keabsahan profil browser X dan Threads di filesystem.
3. Eksekusi proses background login Playwright.
"""

import logging
import os
import sys
from typing import Tuple

from configs.config import X_PROFILE_DIR, THREADS_PROFILE_DIR
from src.auth.login_threads import setup_threads_login
from src.auth.login_x import setup_x_login
from src.auth.session_manager import get_all_sessions_status
from app.schemas.auth_schema import (
    AllSessionsStatusResponse,
    LoginTriggerResponse,
    SessionStatusItem,
)

logger = logging.getLogger(__name__)


class AuthService:
    """Service untuk urusan autentikasi dan status sesi scraper."""

    @staticmethod
    def check_display_available() -> Tuple[bool, str]:
        """
        Periksa apakah display server tersedia untuk membuka browser GUI Playwright.
        Mengembalikan (is_available, reason_message).
        """
        if sys.platform == "win32":
            return True, "Windows host — GUI tersedia."

        display = os.environ.get("DISPLAY", "").strip()
        wayland = os.environ.get("WAYLAND_DISPLAY", "").strip()

        if display or wayland:
            return True, f"Display tersedia: {display or wayland}"

        return False, (
            "Tidak ada display server yang terdeteksi ($DISPLAY / $WAYLAND_DISPLAY kosong). "
            "Kemungkinan Xvfb belum berjalan. Coba restart container."
        )

    @staticmethod
    def get_all_sessions_status() -> AllSessionsStatusResponse:
        """Mengambil status keabsahan sesi login X dan Threads dari filesystem profil."""
        statuses = get_all_sessions_status()

        x_info = statuses.get("x", {})
        t_info = statuses.get("threads", {})

        x_item = SessionStatusItem(
            platform="X (Twitter)",
            is_valid=x_info.get("is_valid", False),
            profile_path=X_PROFILE_DIR.name,
            message=x_info.get("message", "Status tidak diketahui."),
        )
        t_item = SessionStatusItem(
            platform="Threads (Meta)",
            is_valid=t_info.get("is_valid", False),
            profile_path=THREADS_PROFILE_DIR.name,
            message=t_info.get("message", "Status tidak diketahui."),
        )

        return AllSessionsStatusResponse(
            x=x_item,
            threads=t_item,
            all_valid=x_item.is_valid and t_item.is_valid,
        )

    @staticmethod
    async def run_login_task(platform: str):
        """Menjalankan proses login interaktif Playwright di background."""
        plat = platform.lower().strip()
        try:
            if plat == "x":
                logger.info("AuthService: Memulai background login X...")
                await setup_x_login(str(X_PROFILE_DIR))
                logger.info("AuthService: Background login X selesai.")
            elif plat == "threads":
                logger.info("AuthService: Memulai background login Threads...")
                await setup_threads_login(str(THREADS_PROFILE_DIR))
                logger.info("AuthService: Background login Threads selesai.")
            else:
                logger.warning(f"AuthService: Platform tidak dikenal: {platform}")
        except Exception as e:
            logger.error(f"AuthService: Error saat login {platform}: {e}", exc_info=True)


# Singleton instance
auth_service = AuthService()

