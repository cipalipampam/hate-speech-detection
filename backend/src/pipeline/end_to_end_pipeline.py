"""Orkestrator end-to-end: validasi sesi → scraping → preprocessing → klasifikasi → statistik → CSV."""

import asyncio
import logging
import re
from datetime import datetime
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional, Tuple

import pandas as pd  

from configs.config import EXPORTS_DIR
from src.auth.session_manager import is_session_valid
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


def resolve_text_column(df: pd.DataFrame) -> str:
    """Pilih kolom teks untuk inferensi: 'clean_text' bila ada, jika tidak 'content'.

    Dipakai bersama oleh pipeline API dan menu klasifikasi CLI agar label/confidence keduanya identik.
    """
    return "clean_text" if "clean_text" in df.columns else "content"


def validate_platform_sessions(platform: str) -> Tuple[bool, List[str]]:
    """Satu-satunya sumber kebijakan validasi sesi per platform ('x' / 'threads' / 'both').

    Kembalikan (valid, errors); pesan error menyebut endpoint `login-trigger` karena tampil di UI.
    """
    plat = platform.lower().strip()
    errors: List[str] = []

    if plat in ("x", "both"):
        if not is_session_valid("x").get("is_valid", False):
            errors.append(
                "Sesi X (Twitter) tidak aktif. Login terlebih dahulu via "
                "POST /api/v1/auth/login-trigger/x"
            )

    if plat in ("threads", "both"):
        if not is_session_valid("threads").get("is_valid", False):
            errors.append(
                "Sesi Threads tidak aktif. Login terlebih dahulu via "
                "POST /api/v1/auth/login-trigger/threads"
            )

    return len(errors) == 0, errors


# ---------------------------------------------------------------------------
# Kelas EndToEndPipeline
# ---------------------------------------------------------------------------

class EndToEndPipeline:
    """Orchestrator alur analisis end-to-end (async)."""

    def __init__(self, predictor: Optional[HateSpeechPredictor] = None):
        self.prep_pipeline = PreprocessingPipeline()
        self._predictor = predictor  # Jika diinjeksi, gunakan instance yang ada (mencegah re-load model)
        # Menampung kegagalan scraping agar tidak hilang sebagai "sukses 0 baris".
        self.scraping_errors: List[str] = []

    def _record_scraping_error(self, platform_label: str, error: Exception) -> None:
        """Catat kegagalan scraping satu platform beserta alasannya."""
        reason = getattr(error, "reason", None) or str(error)
        detail = getattr(error, "detail", "") or ""
        message = f"Scraping {platform_label} gagal: {reason}"
        if detail:
            message = f"{message}\n{detail}"
        self.scraping_errors.append(message)
        logger.error(message)

    @property
    def predictor(self) -> HateSpeechPredictor:
        """Lazy loader untuk HateSpeechPredictor jika belum diinjeksi."""
        if self._predictor is None:
            self._predictor = HateSpeechPredictor()
        return self._predictor

    # ── Tahap 1: Validasi Sesi ────────────────────────────────────────────

    def validate_sessions(self, platform: str) -> Dict[str, Any]:
        """Delegasi ke validate_platform_sessions(); kembalikan {"valid": bool, "errors": list[str]}."""
        valid, errors = validate_platform_sessions(platform)
        return {"valid": valid, "errors": errors}

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
        """Jalankan scraper sesuai platform; kembalikan DataFrame mentah gabungan yang sudah dideduplikasi."""
        platform = platform.lower().strip()
        frames = []
        self.scraping_errors = []

        if status_callback:
            status_callback(f"Memulai scraping pada platform [{platform.upper()}] dengan mode '{search_mode}'...")

        x_config = XScrapeConfig(
            search_mode=search_mode,
            max_links=max_links,
            search_scroll=max_scroll_steps,
            headless=headless,
        )
        threads_config = ThreadsScrapeConfig(
            search_mode=search_mode,
            max_links=max_links,
            search_scroll=max_scroll_steps,
            headless=headless,
        )

        if platform == "both":
            if status_callback:
                status_callback("Meluncurkan scraping paralel: X & Threads...")

            task_x = run_x_scraper(keywords=keywords, direct_urls=direct_urls, config=x_config, status_callback=status_callback)
            task_t = run_threads_scraper(keywords=keywords, direct_urls=direct_urls, config=threads_config, status_callback=status_callback)
            res_x, res_t = await asyncio.gather(task_x, task_t, return_exceptions=True)

            if isinstance(res_x, Exception):
                self._record_scraping_error("X", res_x)
            elif isinstance(res_x, pd.DataFrame) and not res_x.empty:
                frames.append(res_x)

            if isinstance(res_t, Exception):
                self._record_scraping_error("Threads", res_t)
            elif isinstance(res_t, pd.DataFrame) and not res_t.empty:
                frames.append(res_t)

        elif platform == "x":
            if status_callback:
                status_callback("Meluncurkan scraping X (Twitter)...")
            try:
                res_x = await run_x_scraper(keywords=keywords, direct_urls=direct_urls, config=x_config, status_callback=status_callback)
                if isinstance(res_x, pd.DataFrame) and not res_x.empty:
                    frames.append(res_x)
            except Exception as error:
                self._record_scraping_error("X", error)

        elif platform == "threads":
            if status_callback:
                status_callback("Meluncurkan scraping Threads...")
            try:
                res_t = await run_threads_scraper(keywords=keywords, direct_urls=direct_urls, config=threads_config, status_callback=status_callback)
                if isinstance(res_t, pd.DataFrame) and not res_t.empty:
                    frames.append(res_t)
            except Exception as error:
                # Termasuk ThreadsScrapeAborted (sesi tidak aktif / semua URL di-skip).
                self._record_scraping_error("Threads", error)

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
        """Tambah kolom 'clean_text' TANPA menimpa kolom 'content' (teks mentah).

        Teks mentah wajib ikut ke file CSV & kolom `analysis_classifications.raw_content` agar auditabel.
        """
        if df.empty:
            return df

        if status_callback:
            status_callback(f"Menjalankan Preprocessing Pipeline pada {len(df):,} data teks...")

        df_clean = self.prep_pipeline.transform_dataframe(
            df,
            text_column="content",
            overwrite_content=False,
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
        """Jalankan inferensi IndoBERT (Level 1 & Level 2) pada kolom hasil resolve_text_column()."""
        if df.empty:
            return df

        if status_callback:
            status_callback(f"Menjalankan Klasifikasi IndoBERT Hierarkis pada {len(df):,} baris...")

        # Model IndoBERT harus menerima teks BERSIH (URL/emoji/@mention sudah dibuang),
        # jadi kolom 'clean_text' yang dipakai — resolusinya sama dengan yang dipakai CLI.
        text_column = resolve_text_column(df)

        df_classified = self.predictor.predict_dataframe(
            df,
            text_column=text_column,
            batch_size=batch_size,
            show_progress=True,
        )
        return df_classified

    # ── Tahap 5: Perhitungan Statistik ───────────────────────────────────

    def compute_statistics(self, df: pd.DataFrame) -> Dict[str, Any]:
        """Hitung ringkasan statistik: total, hate/non-hate, breakdown Level 2 & platform, rata-rata confidence."""
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
        """Simpan DataFrame hasil analisis ke CSV (utf-8-sig) di storage/exports/."""
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
        """Jalankan seluruh alur end-to-end; kembalikan dict status + statistik + path ekspor."""
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
            # Bedakan "memang tidak ada hasil" dari "scraping gagal" agar bug tidak
            # terlihat sebagai sukses tanpa data.
            if self.scraping_errors:
                first_line = self.scraping_errors[0].splitlines()[0]
                return {
                    "status": "error",
                    "stage": "scraping",
                    "message": first_line,
                    "errors": self.scraping_errors,
                    "total_data": 0,
                }
            return {
                "status": "warning",
                "stage": "scraping",
                "message": "Tidak ada data postingan yang berhasil dikumpulkan dari media sosial.",
                "total_data": 0,
            }

        # 3. Preprocessing (non-blocking ke event loop asyncio)
        if status_callback:
            status_callback(f"[Langkah 3/5] Membersihkan dan menormalisasi teks ({len(df_raw)} baris)...")
        df_clean = await asyncio.to_thread(
            self.execute_preprocessing,
            df_raw,
            status_callback,
        )

        # 4. Klasifikasi IndoBERT (non-blocking ke event loop asyncio)
        if status_callback:
            status_callback("[Langkah 4/5] Melakukan inferensi model IndoBERT Multi-Head...")
        df_classified = await asyncio.to_thread(
            self.execute_classification,
            df_clean,
            32,
            status_callback,
        )

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
    """Pembungkus async: buat EndToEndPipeline lalu jalankan run()."""
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
    """Pembungkus sinkron untuk CLI: asyncio.run(run_end_to_end_pipeline(...))."""
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
