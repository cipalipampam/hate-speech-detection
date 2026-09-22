"""
Router API Autentikasi & Sesi (/api/v1/auth).

Endpoints:
    GET  /api/v1/auth/status
         Memeriksa status sesi X dan Threads (valid/invalid).
         Hanya memeriksa filesystem profil melalui AuthService.

    POST /api/v1/auth/login-trigger/{platform}
         Memicu proses login interaktif Playwright GUI untuk memperbarui sesi.
         Proses berjalan di background (non-blocking).
"""

import logging
from typing import Literal

from fastapi import APIRouter, BackgroundTasks, HTTPException, Path, status

from app.schemas.auth_schema import (
    AllSessionsStatusResponse,
    LoginTriggerResponse,
)
from app.services.auth_service import auth_service

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/auth", tags=["Autentikasi & Sesi"])


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
        "Tidak membuka browser; didelegasikan ke AuthService untuk memeriksa kondisi filesystem."
    ),
)
def get_session_status() -> AllSessionsStatusResponse:
    """Kembalikan status sesi X dan Threads via AuthService."""
    return auth_service.get_all_sessions_status()


@router.post(
    "/login-trigger/{platform}",
    response_model=LoginTriggerResponse,
    status_code=status.HTTP_202_ACCEPTED,
    summary="Trigger Login Interaktif via noVNC",
    description=(
        "Memicu proses login interaktif Playwright GUI untuk platform tertentu. "
        "Proses berjalan di background via BackgroundTasks. "
        "Akses http://localhost:6080 di browser Windows untuk melihat GUI browser."
    ),
)
async def trigger_login(
    platform: Literal["x", "threads"] = Path(
        ...,
        description="Platform yang akan di-login: 'x' atau 'threads'.",
    ),
    background_tasks: BackgroundTasks = BackgroundTasks(),
) -> LoginTriggerResponse:
    """Inisiasi background login task untuk platform yang dipilih."""
    display_ok, display_msg = auth_service.check_display_available()
    if not display_ok:
        platform_name = "X (Twitter)" if platform == "x" else "Threads"
        logger.warning(f"Login trigger gagal — display tidak tersedia: {display_msg}")
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=(
                f"Layanan virtual display belum siap untuk platform {platform_name}. "
                f"Detail: {display_msg}. Coba restart container backend."
            ),
        )

    # Delegasikan task worker ke AuthService
    background_tasks.add_task(auth_service.run_login_task, platform)

    platform_title = "X (Twitter)" if platform == "x" else "Threads (Meta)"
    msg = (
        f"Proses login {platform_title} sedang diinisiasi di background. "
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
