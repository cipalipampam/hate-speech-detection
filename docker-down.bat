@echo off
REM ==============================================================================
REM docker-down.bat — Hentikan HateSense ID Lab Docker Environment
REM ==============================================================================

echo [HateSense ID Lab] Menghentikan seluruh container ...
docker compose down

echo [HateSense ID Lab] Semua container telah dihentikan.
pause
