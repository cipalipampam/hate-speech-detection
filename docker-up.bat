@echo off
REM ==============================================================================
REM docker-up.bat — Jalankan HateSense ID Lab Docker Environment
REM ==============================================================================

echo [HateSense ID Lab] Memeriksa file .env ...
if not exist .env (
    if exist .env.example (
        echo [HateSense ID Lab] .env tidak ditemukan. Menyalin dari .env.example ...
        copy .env.example .env >nul
    )
)

echo [HateSense ID Lab] Membangun dan menjalankan container Docker ...
docker compose up -d --build

echo.
echo ==============================================================================
echo [HateSense ID Lab] Container berhasil dijalankan di background!
echo.
echo   - Frontend Laravel : http://localhost:8000
echo   - FastAPI Docs     : http://localhost:8080/docs
echo   - Status Container : docker compose ps
echo   - Pantau Log       : docker compose logs -f
echo ==============================================================================
pause
