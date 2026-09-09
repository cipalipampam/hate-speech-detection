"""
Router API Autentikasi & Sesi (/api/v1/auth).

Endpoints:
    GET  /api/v1/auth/status
         Memeriksa status sesi X dan Threads (valid/invalid) dari session_manager.
         Tidak membuka browser; hanya memeriksa filesystem profil.

    POST /api/v1/auth/login-trigger/{platform}
         Memicu proses login interaktif Playwright GUI untuk memperbarui sesi.
         Proses ini berjalan di background (non-blocking).
         platform: 'x' atau 'threads'
"""

import asyncio
import logging
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
    summary="Trigger Login Interaktif",
    description=(
        "Memicu proses login interaktif Playwright GUI untuk platform tertentu. "
        "Proses ini berjalan di **background** — response langsung dikembalikan (202 Accepted). "
        "Browser akan terbuka di server (membutuhkan environment GUI). "
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
    if platform == "x":
        background_tasks.add_task(_run_login_x)
        msg = (
            "Proses login X (Twitter) sedang diinisiasi di background. "
            "Browser GUI akan terbuka. Selesaikan login manual di browser, "
            "lalu cek status sesi di GET /api/v1/auth/status."
        )
    else:
        background_tasks.add_task(_run_login_threads)
        msg = (
            "Proses login Threads (Meta) sedang diinisiasi di background. "
            "Browser GUI akan terbuka. Selesaikan login manual di browser, "
            "lalu cek status sesi di GET /api/v1/auth/status."
        )

    logger.info(f"Login trigger diterima untuk platform: {platform}")

    return LoginTriggerResponse(
        platform=platform,
        status="initiated",
        message=msg,
    )
