"""
End-to-End Analysis Pipeline Orchestrator — Modul 5.

Deskripsi:
    Modul orkestrator terpadu yang mengeksekusi seluruh rantai proses secara otomatis:
    
    1. Validasi Sesi Autentikasi (X / Threads / Both)
         ↓
    2. Eksekusi Mesin Scraping 2-Stage (Search Discovery + Deep Crawl)
         ↓
    3. Eksekusi Preprocessing Pipeline (Regex Cleaning, Case Folding, Kamusalay Normalization)
         ↓
    4. Eksekusi Inferensi Model IndoBERT (Hierarchical Level 1 & Level 2 Prediction)
         ↓
    5. Agregasi Statistik & Rekapitulasi Metrik Sentimen
         ↓
    6. Ekspor Hasil Analisis Final ke CSV di `storage/exports/`

Fungsi Publik:
    - run_end_to_end_pipeline()  : Fungsi orkestrator asinkron (async).
    - run_pipeline_sync()        : Wrapper sinkron (sync) untuk CLI atau skrip reguler.
    - EndToEndPipeline           : Kelas orkestrator berorientasi objek.
"""

import asyncio
import logging
import re
from datetime import datetime
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional, Union

import pandas as pd

from configs.config import EXPORTS_DIR
from src.auth.session_manager import is_session_valid, get_all_sessions_status
from src.scraping.x_scraper import run_x_scraper, XScrapeConfig
from src.scraping.threads_scraper import run_threads_scraper, ThreadsScrapeConfig
from src.preprocessing.pipeline import PreprocessingPipeline
from src.classification.predictor import HateSpeechPredictor

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Helper Format Slug
# ---------------------------------------------------------------------------

def _create_filename_slug(keywords: List[str] = None, platform: str = "both") -> str:
    """Membuat nama file unik berbasis timestamp dan keyword."""
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    if keywords:
        raw_kw = "_".join(keywords[:2])
        clean_kw = re.sub(r"[^\w\-]", "_", raw_kw)[:25].strip("_")
        return f"analysis_{platform}_{clean_kw}_{ts}"
    return f"analysis_{platform}_{ts}"


# ---------------------------------------------------------------------------
# Kelas EndToEndPipeline
# ---------------------------------------------------------------------------

class EndToEndPipeline:
    """
    Kelas Orchestrator untuk menjalankan alur analisis end-to-end secara utuh.
    """

    def __init__(self):
        self.prep_pipeline = PreprocessingPipeline()
        self._predictor = None  # Lazy-loaded saat tahap klasifikasi

    @property
    def predictor(self) -> HateSpeechPredictor:
        """Lazy loader untuk HateSpeechPredictor agar tidak memakan RAM sebelum waktunya."""
        if self._predictor is None:
            self._predictor = HateSpeechPredictor()
        return self._predictor

    # ── Tahap 1: Validasi Sesi ────────────────────────────────────────────

    def validate_sessions(self, platform: str) -> Dict[str, Any]:
        """
        Memeriksa ketersediaan dan keabsahan sesi login media sosial.

        Args:
            platform (str): 'x', 'threads', atau 'both'.

        Returns:
            dict: Status validasi sesi.
        """
        platform = platform.lower().strip()
        errors = []
        statuses = get_all_sessions_status()

        if platform in ("x", "both"):
            x_valid = is_session_valid("x")
            if not x_valid:
                errors.append("Sesi X (Twitter) tidak aktif atau profil belum login.")

        if platform in ("threads", "both"):
            t_valid = is_session_valid("threads")
            if not t_valid:
                errors.append("Sesi Threads tidak aktif atau profil belum login.")

        return {
            "valid": len(errors) == 0,
            "errors": errors,
            "statuses": statuses,
        }

    # ── Tahap 2: Eksekusi Scraping ────────────────────────────────────────

    async def execute_scraping(
        self,
        keywords: Optional[List[str]] = None,
        direct_urls: Optional[List[str]] = None,
        platform: str = "both",
        search_mode: str = "latest",
        max_links: int = 100,
        max_scroll_steps: int = 500,
        headless: bool = False,
        status_callback: Optional[Callable[[str], None]] = None,
    ) -> pd.DataFrame:
        """
        Menjalankan scraper sesuai platform yang dipilih.

        Returns:
            pd.DataFrame: DataFrame mentah berkolom [platform, source, user_id, type, date, content].
        """
        platform = platform.lower().strip()
        frames = []

        if status_callback:
            status_callback(f"Memulai scraping pada platform [{platform.upper()}] dengan mode '{search_mode}'...")

        x_config = XScrapeConfig(
            search_mode=search_mode,
            max_links=max_links,
            scan_max_steps=max_scroll_steps,
            headless=headless,
        )
        threads_config = ThreadsScrapeConfig(
            search_mode=search_mode,
            max_links=max_links,
            scan_max_steps=max_scroll_steps,
            headless=headless,
        )

        if platform == "both":
            if status_callback:
                status_callback("Meluncurkan scraping paralel: X & Threads...")

            task_x = run_x_scraper(keywords=keywords, direct_urls=direct_urls, config=x_config)
            task_t = run_threads_scraper(keywords=keywords, direct_urls=direct_urls, config=threads_config)
            res_x, res_t = await asyncio.gather(task_x, task_t, return_exceptions=True)

            if isinstance(res_x, Exception):
                logger.error(f"Error scraping X: {res_x}")
            elif isinstance(res_x, pd.DataFrame) and not res_x.empty:
                frames.append(res_x)

            if isinstance(res_t, Exception):
                logger.error(f"Error scraping Threads: {res_t}")
            elif isinstance(res_t, pd.DataFrame) and not res_t.empty:
                frames.append(res_t)

        elif platform == "x":
            if status_callback:
                status_callback("Meluncurkan scraping X (Twitter)...")
            res_x = await run_x_scraper(keywords=keywords, direct_urls=direct_urls, config=x_config)
            if isinstance(res_x, pd.DataFrame) and not res_x.empty:
                frames.append(res_x)

        elif platform == "threads":
            if status_callback:
                status_callback("Meluncurkan scraping Threads...")
            res_t = await run_threads_scraper(keywords=keywords, direct_urls=direct_urls, config=threads_config)
            if isinstance(res_t, pd.DataFrame) and not res_t.empty:
                frames.append(res_t)

        if not frames:
            return pd.DataFrame(columns=["platform", "source", "user_id", "type", "date", "content"])

        df_merged = pd.concat(frames, ignore_index=True)
        # Deduplikasi berdasarkan source + content
        if "content" in df_merged.columns and "source" in df_merged.columns:
            df_merged = df_merged.drop_duplicates(subset=["source", "content"]).reset_index(drop=True)

        return df_merged

    # ── Tahap 3: Preprocessing Teks ───────────────────────────────────────

    def execute_preprocessing(
        self,
        df: pd.DataFrame,
        status_callback: Optional[Callable[[str], None]] = None,
    ) -> pd.DataFrame:
        """
        Menjalankan pembersihan dan normalisasi teks secara in-place pada kolom 'content'.
        """
        if df.empty:
            return df

        if status_callback:
            status_callback(f"Menjalankan Preprocessing Pipeline pada {len(df):,} data teks...")

        df_clean = self.prep_pipeline.transform_dataframe(
            df,
            text_column="content",
            show_progress=True,
        )
        return df_clean

    # ── Tahap 4: Klasifikasi IndoBERT ─────────────────────────────────────

    def execute_classification(
        self,
        df: pd.DataFrame,
        batch_size: int = 32,
        status_callback: Optional[Callable[[str], None]] = None,
    ) -> pd.DataFrame:
        """
        Menjalankan inferensi model IndoBERT (Level 1 & Level 2).
        """
        if df.empty:
            return df

        if status_callback:
            status_callback(f"Menjalankan Klasifikasi IndoBERT Hierarkis pada {len(df):,} baris...")

        df_classified = self.predictor.predict_dataframe(
            df,
            text_column="content",
            batch_size=batch_size,
            show_progress=True,
        )
        return df_classified

    # ── Tahap 5: Perhitungan Statistik ───────────────────────────────────

    def compute_statistics(self, df: pd.DataFrame) -> Dict[str, Any]:
        """
        Menghitung ringkasan statistik lengkap untuk dashboard dan laporan skripsi.
        """
        if df.empty or "label_lvl1" not in df.columns:
            return {
                "total_data": 0,
                "hate_speech_count": 0,
                "hate_speech_pct": 0.0,
                "non_hate_speech_count": 0,
                "non_hate_speech_pct": 0.0,
                "level2_breakdown": {},
                "platform_breakdown": {},
                "avg_confidence_lvl1": 0.0,
                "avg_confidence_lvl2": 0.0,
            }

        total = len(df)
        hate_count = int(sum(df["label_lvl1"] == "hate_speech"))
        nonhate_count = total - hate_count

        # Breakdown Level 2
        lvl2_counts = df["label_lvl2"].value_counts().to_dict()
        lvl2_breakdown = {}
        for cat, cnt in lvl2_counts.items():
            lvl2_breakdown[cat] = {
                "count": int(cnt),
                "percentage": round(float(cnt / total * 100), 2),
            }

        # Breakdown Platform
        plat_counts = df["platform"].value_counts().to_dict()
        plat_breakdown = {}
        for p, cnt in plat_counts.items():
            sub_df = df[df["platform"] == p]
            p_hate = int(sum(sub_df["label_lvl1"] == "hate_speech"))
            plat_breakdown[p] = {
                "total": int(cnt),
                "hate_speech": p_hate,
                "non_hate_speech": int(cnt - p_hate),
                "hate_pct": round(float(p_hate / cnt * 100), 2) if cnt > 0 else 0.0,
            }

        avg_conf1 = float(df["confidence_lvl1"].mean()) if "confidence_lvl1" in df.columns else 0.0
        avg_conf2 = float(df["confidence_lvl2"].mean()) if "confidence_lvl2" in df.columns else 0.0

        return {
            "total_data": total,
            "hate_speech_count": hate_count,
            "hate_speech_pct": round(float(hate_count / total * 100), 2),
            "non_hate_speech_count": nonhate_count,
            "non_hate_speech_pct": round(float(nonhate_count / total * 100), 2),
            "level2_breakdown": lvl2_breakdown,
            "platform_breakdown": plat_breakdown,
            "avg_confidence_lvl1": round(avg_conf1 * 100, 2),
            "avg_confidence_lvl2": round(avg_conf2 * 100, 2),
        }

    # ── Tahap 6: Ekspor CSV ───────────────────────────────────────────────

    def export_results(
        self,
        df: pd.DataFrame,
        filename: Optional[str] = None,
        keywords: Optional[List[str]] = None,
        platform: str = "both",
    ) -> Path:
        """
        Menyimpan DataFrame hasil analisis ke file CSV di storage/exports/.
        """
        if filename is None:
            filename = f"{_create_filename_slug(keywords, platform)}.csv"
        elif not filename.endswith(".csv"):
            filename = f"{filename}.csv"

        out_path = EXPORTS_DIR / filename
        df.to_csv(out_path, index=False, encoding="utf-8-sig")
        logger.info(f"Hasil analisis berhasil diekspor ke: {out_path}")
        return out_path

    # ── Eksekutor Utama (Async) ───────────────────────────────────────────

    async def run(
        self,
        keywords: Optional[List[str]] = None,
        direct_urls: Optional[List[str]] = None,
        platform: str = "both",
        search_mode: str = "latest",
        max_links: int = 50,
        max_scroll_steps: int = 300,
        headless: bool = False,
        export_csv: bool = True,
        output_filename: Optional[str] = None,
        status_callback: Optional[Callable[[str], None]] = None,
    ) -> Dict[str, Any]:
        """
        Menjalankan seluruh alur end-to-end secara asinkron.
        """
        t_start = datetime.now()

        # 1. Validasi Sesi
        if status_callback:
            status_callback("[Langkah 1/5] Memeriksa validitas sesi media sosial...")
        auth_check = self.validate_sessions(platform)
        if not auth_check["valid"]:
            return {
                "status": "error",
                "stage": "auth_validation",
                "message": "Sesi media sosial tidak valid.",
                "errors": auth_check["errors"],
            }

        # 2. Scraping
        if status_callback:
            status_callback(f"[Langkah 2/5] Memulai scraping data media sosial ({platform.upper()})...")
        df_raw = await self.execute_scraping(
            keywords=keywords,
            direct_urls=direct_urls,
            platform=platform,
            search_mode=search_mode,
            max_links=max_links,
            max_scroll_steps=max_scroll_steps,
            headless=headless,
            status_callback=status_callback,
        )

        if df_raw.empty:
            return {
                "status": "warning",
                "stage": "scraping",
                "message": "Tidak ada data postingan yang berhasil dikumpulkan dari media sosial.",
                "total_data": 0,
            }

        # 3. Preprocessing
        if status_callback:
            status_callback(f"[Langkah 3/5] Membersihkan dan menormalisasi teks ({len(df_raw)} baris)...")
        df_clean = self.execute_preprocessing(df_raw, status_callback=status_callback)

        # 4. Klasifikasi IndoBERT
        if status_callback:
            status_callback(f"[Langkah 4/5] Melakukan inferensi model IndoBERT Multi-Head...")
        df_classified = self.execute_classification(df_clean, status_callback=status_callback)

        # 5. Statistik & Ekspor
        if status_callback:
            status_callback("[Langkah 5/5] Menghitung rekapitulasi statistik & mengekspor data...")
        stats = self.compute_statistics(df_classified)

        exported_path = None
        if export_csv:
            exported_path = self.export_results(
                df_classified,
                filename=output_filename,
                keywords=keywords,
                platform=platform,
            )

        t_elapsed = (datetime.now() - t_start).total_seconds()

        return {
            "status": "success",
            "platform": platform,
            "keywords": keywords or [],
            "direct_urls": direct_urls or [],
            "search_mode": search_mode,
            "total_data": len(df_classified),
            "statistics": stats,
            "exported_file": str(exported_path) if exported_path else None,
            "elapsed_seconds": round(t_elapsed, 2),
            "dataframe": df_classified,
        }


# ---------------------------------------------------------------------------
# Fungsi Interface Eksternal
# ---------------------------------------------------------------------------

async def run_end_to_end_pipeline(
    keywords: Optional[List[str]] = None,
    direct_urls: Optional[List[str]] = None,
    platform: str = "both",
    search_mode: str = "latest",
    max_links: int = 50,
    max_scroll_steps: int = 300,
    headless: bool = False,
    export_csv: bool = True,
    output_filename: Optional[str] = None,
    status_callback: Optional[Callable[[str], None]] = None,
) -> Dict[str, Any]:
    """
    Fungsi utilitas pembungkus untuk menjalankan pipeline end-to-end (async).
    """
    pipeline = EndToEndPipeline()
    return await pipeline.run(
        keywords=keywords,
        direct_urls=direct_urls,
        platform=platform,
        search_mode=search_mode,
        max_links=max_links,
        max_scroll_steps=max_scroll_steps,
        headless=headless,
        export_csv=export_csv,
        output_filename=output_filename,
        status_callback=status_callback,
    )


def run_pipeline_sync(
    keywords: Optional[List[str]] = None,
    direct_urls: Optional[List[str]] = None,
    platform: str = "both",
    search_mode: str = "latest",
    max_links: int = 50,
    max_scroll_steps: int = 300,
    headless: bool = False,
    export_csv: bool = True,
    output_filename: Optional[str] = None,
    status_callback: Optional[Callable[[str], None]] = None,
) -> Dict[str, Any]:
    """
    Fungsi utilitas pembungkus untuk menjalankan pipeline end-to-end secara sinkron (sync).
    Cocok untuk CLI runner atau script standar.
    """
    return asyncio.run(
        run_end_to_end_pipeline(
            keywords=keywords,
            direct_urls=direct_urls,
            platform=platform,
            search_mode=search_mode,
            max_links=max_links,
            max_scroll_steps=max_scroll_steps,
            headless=headless,
            export_csv=export_csv,
            output_filename=output_filename,
            status_callback=status_callback,
        )
    )
