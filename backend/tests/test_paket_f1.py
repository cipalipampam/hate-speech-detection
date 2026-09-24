"""
Test regresi Paket F1 — keamanan data & ketahanan service layer.

Cakupan:
    1. JobManager tidak boleh membuang job yang masih queued/running.
    2. Statistik pipeline yang tidak lengkap tidak boleh menggagalkan seluruh job.
    3. Proteksi path traversal pada unduhan ekspor tetap ketat & tepat.
    4. Schema Pydantic v2 bebas dari `class Config` (deprecated).

Catatan: uji serialisasi NaN (dulu ada di sini) DIHAPUS bersama endpoint `/api/v1/scrape/*`
beserta `ScraperService` — kode bermasalah itu sudah tidak ada, jadi test-nya ikut hilang.

Jalankan:
    cd backend
    python -m unittest tests.test_paket_f1 -v
"""

from __future__ import annotations

import importlib
import sys
import tempfile
import unittest
import warnings
from pathlib import Path
from unittest.mock import patch

BACKEND_DIR = Path(__file__).resolve().parents[1]
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

from app.schemas.classification_schema import ClassifySingleRequest, PipelineRunRequest
import app.services.job_service  # noqa: F401 (memastikan submodul ter-import)
import app.services.pipeline_service  # noqa: F401
from app.services.job_service import JobManager
from app.services.pipeline_service import PipelineService, pipeline_service

# CATATAN: `app/services/__init__.py` mengekspor singleton dengan nama yang SAMA
# dengan nama modulnya (mis. `pipeline_service`), sehingga
# `import app.services.pipeline_service as m` mengembalikan INSTANCE, bukan modul.
# Ambil modul aslinya lewat sys.modules agar patch target tepat sasaran.
pipeline_service_module = sys.modules["app.services.pipeline_service"]


# ---------------------------------------------------------------------------
# 1. JobManager — eviction
# ---------------------------------------------------------------------------

class JobManagerEvictionTest(unittest.TestCase):
    """Job aktif tidak boleh hilang dari memori (penyebab 404 → frontend FAILED)."""

    def test_job_aktif_tidak_pernah_dieviction(self):
        manager = JobManager(max_jobs=3)

        manager.create_job("aktif_1", "pipeline", {})
        manager.update_job("aktif_1", status="running")
        manager.create_job("antre_2", "pipeline", {})   # queued
        manager.create_job("antre_3", "pipeline", {})   # queued

        # Job ke-4 memicu pemeriksaan kapasitas; tidak ada job selesai untuk dibuang.
        manager.create_job("antre_4", "pipeline", {})

        self.assertIsNotNone(
            manager.get_job("aktif_1"),
            "Job yang MASIH running ikut terbuang dari memori (status polling akan 404).",
        )
        for job_id in ("antre_2", "antre_3", "antre_4"):
            self.assertIsNotNone(manager.get_job(job_id), f"{job_id} seharusnya tetap ada.")

    def test_job_selesai_dieviction_lebih_dulu(self):
        manager = JobManager(max_jobs=3)

        manager.create_job("aktif_1", "pipeline", {})
        manager.update_job("aktif_1", status="running")
        manager.create_job("selesai_1", "pipeline", {})
        manager.update_job("selesai_1", status="success")
        manager.create_job("antre_1", "pipeline", {})

        manager.create_job("antre_2", "pipeline", {})   # memicu eviction

        self.assertIsNone(manager.get_job("selesai_1"), "Job selesai tertua harus dibuang lebih dulu.")
        self.assertIsNotNone(manager.get_job("aktif_1"))
        self.assertIsNotNone(manager.get_job("antre_1"))
        self.assertIsNotNone(manager.get_job("antre_2"))

    def test_kapasitas_kembali_terkendali_saat_semua_job_selesai(self):
        manager = JobManager(max_jobs=3)

        for i in range(10):
            manager.create_job(f"job_{i}", "scrape", {})
            manager.update_job(f"job_{i}", status="success")

        self.assertLessEqual(len(manager._jobs), 3, "Kapasitas max_jobs harus tetap ditegakkan.")


# ---------------------------------------------------------------------------
# 3. Statistik pipeline tidak lengkap
# ---------------------------------------------------------------------------

class PipelineStatisticsTest(unittest.IsolatedAsyncioTestCase):
    async def test_statistik_tidak_lengkap_tidak_menggagalkan_job(self):
        class FakePipeline:
            def __init__(self, predictor=None):
                self.predictor = predictor

            async def run(self, **kwargs):
                return {
                    "status": "success",
                    "total_data": 3,
                    "statistics": {
                        "total_data": 3,
                        "hate_speech_count": 1,
                        "hate_speech_pct": 33.33,
                        "non_hate_speech_count": 2,
                        "non_hate_speech_pct": 66.67,
                        # sengaja tidak lengkap: platform_breakdown tanpa non_hate_speech/hate_pct
                        "level2_breakdown": {"tidak_relevan": {"count": 2, "percentage": 66.67}},
                        "platform_breakdown": {"Threads": {"total": 3, "hate_speech": 1}},
                    },
                    "exported_file": str(BACKEND_DIR / "storage" / "exports" / "analysis_threads_uji_20260925_000000.csv"),
                }

        with patch.object(pipeline_service_module, "EndToEndPipeline", FakePipeline):
            request = PipelineRunRequest(
                keywords=["uji statistik"],
                platform="threads",
                max_links=1,
                max_scroll_steps=50,
                headless=True,
            )
            job = await pipeline_service.create_and_start_pipeline_job(request, predictor=object())

            status = None
            for _ in range(200):
                status = pipeline_service.get_job_status(job.job_id)
                if status.status in ("success", "error"):
                    break
                await __import__("asyncio").sleep(0.02)

        self.assertIsNotNone(status)
        self.assertEqual(
            "success",
            status.status,
            f"Job gagal padahal pipeline sukses — pesan: {status.message} | detail: {status.error_detail}",
        )
        self.assertEqual(3, status.statistics.total_data)
        # Field yang tidak dikirim backend diturunkan agar invarian
        # hate_speech + non_hate_speech == total tetap terjaga.
        self.assertEqual(2, status.statistics.platform_breakdown["Threads"].non_hate_speech)
        self.assertAlmostEqual(33.33, status.statistics.platform_breakdown["Threads"].hate_pct, places=2)


# ---------------------------------------------------------------------------
# 4. Proteksi path traversal
# ---------------------------------------------------------------------------

class SafeExportPathTest(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.exports_dir = Path(self._tmp.name)
        (self.exports_dir / "data_uji.csv").write_text("a,b\n1,2\n", encoding="utf-8")
        (self.exports_dir / "sub").mkdir()
        (self.exports_dir / "sub" / "nested.csv").write_text("a\n1\n", encoding="utf-8")

        self._patcher = patch.object(pipeline_service_module, "EXPORTS_DIR", self.exports_dir)
        self._patcher.start()
        self.addCleanup(self._patcher.stop)

    def test_file_valid_di_izinkan(self):
        self.assertIsNotNone(PipelineService.get_safe_export_path("data_uji.csv"))

    def test_subfolder_di_dalam_exports_di_izinkan(self):
        self.assertIsNotNone(PipelineService.get_safe_export_path("sub/nested.csv"))

    def test_path_traversal_ditolak(self):
        for evil in ("../rahasia.csv", "../../Windows/win.ini", "sub/../../rahasia.csv"):
            self.assertIsNone(
                PipelineService.get_safe_export_path(evil),
                f"Path traversal '{evil}' seharusnya ditolak.",
            )

    def test_path_absolut_di_luar_exports_ditolak(self):
        luar = Path(self._tmp.name).parent / "di_luar.csv"
        self.assertIsNone(PipelineService.get_safe_export_path(str(luar)))

    def test_direktori_bukan_file_ditolak(self):
        self.assertIsNone(PipelineService.get_safe_export_path("sub"))


# ---------------------------------------------------------------------------
# 5. Pydantic v2 — tidak boleh pakai `class Config`
# ---------------------------------------------------------------------------

class PydanticV2ConfigTest(unittest.TestCase):
    def test_tidak_ada_deprecation_class_config(self):
        modul = (
            "app.schemas.auth_schema",
            "app.schemas.classification_schema",
            "app.schemas",
        )
        with warnings.catch_warnings(record=True) as caught:
            warnings.simplefilter("always")
            for nama in modul:
                importlib.reload(importlib.import_module(nama))

        pesan = [str(w.message) for w in caught if "class-based" in str(w.message)]
        self.assertEqual([], pesan, f"Masih ada deprecation Pydantic v2: {pesan}")

    def test_contoh_schema_tetap_tersedia(self):
        self.assertIn("example", ClassifySingleRequest.model_json_schema())
        self.assertIn("example", PipelineRunRequest.model_json_schema())


if __name__ == "__main__":
    unittest.main(verbosity=2)
