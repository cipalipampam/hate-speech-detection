"""
Router API Autentikasi & Sesi (/api/v1/auth).

Endpoints:
    GET  /api/v1/auth/status
         Memeriksa status sesi X dan Threads (valid/invalid) dari session_manager.
         Tidak membuka browser; hanya memeriksa filesystem profil.

    POST /api/v1/auth/login-trigger/{platform}
         Memicu proses login interaktif Playwright GUI untuk memperbarui sesi.
         Proses ini berjalan di background (non-blocking).
         Browser akan terbuka di virtual display Xvfb di container Docker;
         akses via noVNC di http://localhost:6080 untuk berinteraksi dengan browser.
         platform: 'x' atau 'threads'
"""

import asyncio
import logging
import os
import sys
from typing import Any, Dict, Literal

from fastapi import APIRouter, BackgroundTasks, HTTPException, Path, status
from pydantic import BaseModel

from src.auth.session_manager import get_all_sessions_status, is_session_valid
from src.auth.login_x import setup_x_login
from src.auth.login_threads import setup_threads_login
from configs.config import X_PROFILE_DIR, THREADS_PROFILE_DIR

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/auth", tags=["Autentikasi & Sesi"])


# ---------------------------------------------------------------------------
# Response Schemas (inline — sederhana, tidak perlu file schema terpisah)
# ---------------------------------------------------------------------------

class SessionStatusItem(BaseModel):
    """Status sesi satu platform."""
    platform: str
    is_valid: bool
    profile_path: str
    message: str


class AllSessionsStatusResponse(BaseModel):
    """Status semua sesi (X & Threads)."""
    x: SessionStatusItem
    threads: SessionStatusItem
    all_valid: bool


class LoginTriggerResponse(BaseModel):
    """Response saat login trigger berhasil diinisiasi."""
    platform: str
    status: str
    message: str


# ---------------------------------------------------------------------------
# Background login task helpers
# ---------------------------------------------------------------------------

async def _run_login_x():
    """Menjalankan proses login X di background."""
    try:
        logger.info("Background Task: Memulai proses login X...")
        await setup_x_login(str(X_PROFILE_DIR))
        logger.info("Background Task: Proses login X selesai.")
    except Exception as e:
        logger.error(f"Background Task: Error saat login X: {e}")


async def _run_login_threads():
    """Menjalankan proses login Threads di background."""
    try:
        logger.info("Background Task: Memulai proses login Threads...")
        await setup_threads_login(str(THREADS_PROFILE_DIR))
        logger.info("Background Task: Proses login Threads selesai.")
    except Exception as e:
        logger.error(f"Background Task: Error saat login Threads: {e}")


def _check_display_available() -> tuple[bool, str]:
    """
    Periksa apakah display server tersedia untuk membuka browser GUI.

    Mengembalikan (is_available, reason_message).

    Sekarang container backend dilengkapi Xvfb sehingga $DISPLAY=:99 selalu
    ter-set oleh entrypoint.sh. Pengecekan ini sebagai safety net jika
    entrypoint tidak berjalan normal.
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


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@router.get(
    "/status",
    response_model=AllSessionsStatusResponse,
    summary="Cek Status Semua Sesi Login",
    description=(
        "Memeriksa ketersediaan dan keabsahan sesi login browser persisten "
        "untuk platform X (Twitter) dan Threads. "
        "Tidak membuka browser; hanya memeriksa kondisi folder profil di filesystem."
    ),
)
def get_session_status() -> AllSessionsStatusResponse:
    """Kembalikan status sesi X dan Threads."""
    statuses = get_all_sessions_status()

    x_info = statuses.get("x", {})
    t_info = statuses.get("threads", {})

    x_item = SessionStatusItem(
        platform="X (Twitter)",
        is_valid=x_info.get("is_valid", False),
        profile_path=X_PROFILE_DIR.name,   # Hanya nama folder, bukan path absolut
        message=x_info.get("message", "Status tidak diketahui."),
    )
    t_item = SessionStatusItem(
        platform="Threads (Meta)",
        is_valid=t_info.get("is_valid", False),
        profile_path=THREADS_PROFILE_DIR.name,  # Hanya nama folder, bukan path absolut
        message=t_info.get("message", "Status tidak diketahui."),
    )

    return AllSessionsStatusResponse(
        x=x_item,
        threads=t_item,
        all_valid=x_item.is_valid and t_item.is_valid,
    )


@router.post(
    "/login-trigger/{platform}",
    response_model=LoginTriggerResponse,
    status_code=status.HTTP_202_ACCEPTED,
    summary="Trigger Login Interaktif via noVNC",
    description=(
        "Memicu proses login interaktif Playwright GUI untuk platform tertentu. "
        "Proses ini berjalan di **background** — response langsung dikembalikan (202 Accepted). "
        "Browser akan terbuka di virtual display Xvfb di dalam container Docker. "
        "Akses **http://localhost:6080** di browser Windows Anda untuk melihat dan "
        "berinteraksi dengan browser yang sedang login. "
        "Gunakan endpoint GET /status untuk mengecek apakah sesi sudah aktif setelah login."
    ),
)
async def trigger_login(
    platform: Literal["x", "threads"] = Path(
        ...,
        description="Platform yang akan di-login: 'x' atau 'threads'.",
    ),
    background_tasks: BackgroundTasks = None,
) -> LoginTriggerResponse:
    """Inisiasi background login task untuk platform yang dipilih."""
    # Safety check: Xvfb harus berjalan (entrypoint.sh set $DISPLAY=:99)
    display_ok, display_msg = _check_display_available()
    if not display_ok:
        platform_name = "X (Twitter)" if platform == "x" else "Threads"
        logger.warning(f"Login trigger gagal — display tidak tersedia: {display_msg}")
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=(
                f"Layanan virtual display belum siap untuk platform {platform_name}. "
                f"Detail: {display_msg}. "
                f"Coba restart container backend dan tunggu beberapa detik sebelum mencoba lagi."
            ),
        )

    if platform == "x":
        background_tasks.add_task(_run_login_x)
        msg = (
            "Proses login X (Twitter) sedang diinisiasi di background. "
            "Browser GUI terbuka di virtual display container. "
            "Buka http://localhost:6080 di browser Windows Anda untuk melihat dan menyelesaikan login. "
            "Setelah selesai, cek status sesi di GET /api/v1/auth/status."
        )
    else:
        background_tasks.add_task(_run_login_threads)
        msg = (
            "Proses login Threads (Meta) sedang diinisiasi di background. "
            "Browser GUI terbuka di virtual display container. "
            "Buka http://localhost:6080 di browser Windows Anda untuk melihat dan menyelesaikan login. "
            "Setelah selesai, cek status sesi di GET /api/v1/auth/status."
        )

    logger.info(f"Login trigger diterima untuk platform: {platform}")

    return LoginTriggerResponse(
        platform=platform,
        status="initiated",
        message=msg,
    )
