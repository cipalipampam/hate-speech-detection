"""
Job Service & State Management — Application Service Layer.

Mengelola:
1. In-Memory Job Store terpusat dengan batas kapasitas (mencegah memory leak).
2. Strong References pada asyncio.Task (mencegah garbage collection mid-execution).
3. Concurrency Lock per platform (mencegah Playwright crash akibat SingletonLock browser profile).
"""

import asyncio
import logging
from collections import OrderedDict
from contextlib import asynccontextmanager
from datetime import datetime
from typing import Any, Dict, Optional, Set

logger = logging.getLogger(__name__)


class JobManager:
    """
    Manajer state job asinkron terpusat untuk Scraping dan Pipeline.

    Fitur:
    - FIFO / Capacity Eviction: Maksimum `max_jobs` job disimpan di memori.
    - Concurrency Control: Lock asinkron per platform (X & Threads) agar Playwright
      tidak membuka folder browser profile yang sama secara bersamaan.
    - Task Lifetime Management: Mencegah asyncio background task di-garbage collect.
    """

    def __init__(self, max_jobs: int = 150):
        self._max_jobs = max_jobs
        self._jobs: OrderedDict[str, Dict[str, Any]] = OrderedDict()
        self._active_tasks: Set[asyncio.Task] = set()

        # Platform-specific async locks untuk akses browser Chromium persisten
        self._lock_x = asyncio.Lock()
        self._lock_threads = asyncio.Lock()

    # ── Job Store Operations ───────────────────────────────────────────────

    def create_job(self, job_id: str, job_type: str, metadata: Dict[str, Any]) -> Dict[str, Any]:
        """Mendaftarkan job baru dengan status awal 'queued'."""
        self._evict_if_needed()

        job_record = {
            "job_id": job_id,
            "job_type": job_type,
            "status": "queued",
            "message": "Job dibuat dan menunggu antrean eksekusi.",
            "created_at": datetime.now().isoformat(),
            "updated_at": datetime.now().isoformat(),
            "elapsed_seconds": None,
            "error_detail": None,
            **metadata,
        }
        self._jobs[job_id] = job_record
        return job_record

    def get_job(self, job_id: str) -> Optional[Dict[str, Any]]:
        """Mengambil data job berdasarkan job_id."""
        return self._jobs.get(job_id)

    def update_job(self, job_id: str, **kwargs) -> Optional[Dict[str, Any]]:
        """Memperbarui field tertentu pada job record."""
        if job_id not in self._jobs:
            logger.warning(f"Percobaan memperbarui job yang tidak ada: {job_id}")
            return None

        self._jobs[job_id].update(kwargs)
        self._jobs[job_id]["updated_at"] = datetime.now().isoformat()
        return self._jobs[job_id]

    def _evict_if_needed(self):
        """Hapus job tertua yang sudah selesai jika kapasitas terlampaui (cegah memory leak)."""
        if len(self._jobs) < self._max_jobs:
            return

        # Prioritaskan hapus job yang sudah 'success' atau 'error'
        keys_to_remove = []
        for jid, data in self._jobs.items():
            if data.get("status") in ("success", "error"):
                keys_to_remove.append(jid)
                if len(self._jobs) - len(keys_to_remove) < self._max_jobs:
                    break

        # Fallback jika semua job masih running/queued, hapus tertua (FIFO)
        if not keys_to_remove and self._jobs:
            keys_to_remove.append(next(iter(self._jobs)))

        for k in keys_to_remove:
            self._jobs.pop(k, None)
            logger.debug(f"JobManager: Job {k} dievilsi dari memori untuk menjaga kapasitas.")

    # ── Task Lifetime Tracking ─────────────────────────────────────────────

    def track_task(self, task: asyncio.Task):
        """
        Menyimpan strong reference ke asyncio.Task agar tidak di-garbage collect
        di tengah eksekusi oleh Python runtime.
        """
        self._active_tasks.add(task)
        task.add_done_callback(self._active_tasks.discard)

    # ── Concurrency Lock Manager ───────────────────────────────────────────

    @asynccontextmanager
    async def platform_lock(self, platform: str):
        """
        Async context manager untuk mengamankan akses ke profil browser Playwright.
        Mencegah error 'user data directory is already in use'.
        """
        plat = platform.lower().strip()
        acquired_locks = []

        try:
            if plat in ("x", "both"):
                logger.info(f"Mengantre lock platform [X]...")
                await self._lock_x.acquire()
                acquired_locks.append(self._lock_x)
                logger.info(f"Lock platform [X] berhasil diperoleh.")

            if plat in ("threads", "both"):
                logger.info(f"Mengantre lock platform [THREADS]...")
                await self._lock_threads.acquire()
                acquired_locks.append(self._lock_threads)
                logger.info(f"Lock platform [THREADS] berhasil diperoleh.")

            yield

        finally:
            for lock in reversed(acquired_locks):
                if lock.locked():
                    lock.release()
            logger.info(f"Seluruh lock browser untuk platform [{plat}] telah dilepaskan.")


# Singleton instance terpusat
job_manager = JobManager()

