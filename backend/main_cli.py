"""
CLI Entrypoint (Terminal Interactive Runner).

Program terminal interaktif modular untuk menjalankan dan menguji
seluruh komponen backend:
  1. Manajemen Autentikasi & Sesi (X & Threads)
  2. Scraping Data (X & Threads)
  3. Preprocessing & Normalisasi Teks
  4. Inferensi Sentimen IndoBERT
  5. End-to-End Analysis Pipeline

Penggunaan:
    cd apps/backend
    python main_cli.py
"""

import asyncio
import os
import re
import sys
from pathlib import Path
import pandas as pd

# Pastikan direktori backend masuk ke sys.path dan konsol Windows mendukung UTF-8
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")

BACKEND_DIR = Path(__file__).resolve().parent
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

# Library Rich untuk antarmuka terminal modern
try:
    from rich.console import Console
    from rich.panel import Panel
    from rich.table import Table
    from rich.prompt import Prompt, Confirm
    from rich.text import Text
    from rich import box
    HAS_RICH = True
except ImportError:
    HAS_RICH = False

# Import modul auth backend
from src.auth import (
    setup_x_login,
    setup_threads_login,
    get_all_sessions_status,
    is_session_valid,
)
from configs.config import (
    X_PROFILE_DIR,
    THREADS_PROFILE_DIR,
    STORAGE_DIR,
    EXPORTS_DIR,
)

# Import modul scraping backend
from src.scraping import (
    run_x_scraper,
    run_threads_scraper,
    XScrapeConfig,
    ThreadsScrapeConfig,
)

# Import modul preprocessing backend
from src.preprocessing import PreprocessingPipeline, clean_text, case_folding
from configs.config import MODEL_DIR

console = Console() if HAS_RICH else None


# ---------------------------------------------------------------------------
# Tampilan Header & Banner
# ---------------------------------------------------------------------------

def display_banner():
    """Menampilkan banner judul utama aplikasi."""
    if HAS_RICH:
        banner_text = Text()
        banner_text.append("🏛️  SISTEM ANALISIS SENTIMEN OPINI PUBLIK\n", style="bold cyan")
        banner_text.append("Platform: X (Twitter) & Threads (Meta) | Model: Fine-Tuned IndoBERT\n", style="bold white")
        banner_text.append("Arsitektur: Modular Service-Oriented (Backend CLI & API Ready)", style="dim white")
        
        panel = Panel(
            banner_text,
            box=box.DOUBLE_EDGE,
            border_style="cyan",
            padding=(1, 2)
        )
        console.print(panel)
    else:
        print("=" * 70)
        print(" SISTEM ANALISIS SENTIMEN OPINI PUBLIK (X & THREADS)")
        print(" Model: Fine-Tuned IndoBERT | Backend CLI Runner")
        print("=" * 70)


def print_info(msg: str):
    if HAS_RICH:
        console.print(f"[bold blue]ℹ INFO:[/bold blue] {msg}")
    else:
        print(f"[INFO] {msg}")


def print_success(msg: str):
    if HAS_RICH:
        console.print(f"[bold green]✔ SUKSES:[/bold green] {msg}")
    else:
        print(f"[SUKSES] {msg}")


def print_warning(msg: str):
    if HAS_RICH:
        console.print(f"[bold yellow]⚠ PERINGATAN:[/bold yellow] {msg}")
    else:
        print(f"[PERINGATAN] {msg}")


def print_error(msg: str):
    if HAS_RICH:
        console.print(f"[bold red]✖ ERROR:[/bold red] {msg}")
    else:
        print(f"[ERROR] {msg}")


# ---------------------------------------------------------------------------
# Fitur Modul 1: Manajemen Autentikasi & Sesi
# ---------------------------------------------------------------------------

def show_auth_status_table():
    """Menampilkan tabel status sesi login untuk kedua platform."""
    statuses = get_all_sessions_status()
    
    if HAS_RICH:
        table = Table(
            title="📊 Status Sesi Login Browser (Persistent Context)",
            box=box.ROUNDED,
            header_style="bold magenta",
            show_lines=True
        )
        table.add_column("Platform", style="bold cyan", width=12)
        table.add_column("Status Sesi", justify="center", width=14)
        table.add_column("Folder Profil", style="dim", width=42)
        table.add_column("Terakhir Diperbarui", justify="center", width=20)
        table.add_column("Keterangan", width=25)

        for key, info in statuses.items():
            platform_name = info["platform"]
            is_valid = info["is_valid"]
            
            if is_valid:
                status_badge = "[bold green]● AKTIF[/bold green]"
                desc = "[green]Siap untuk scraping[/green]"
            else:
                status_badge = "[bold red]○ BELUM ADA / EXPIRED[/bold red]"
                desc = "[yellow]Perlu setup login[/yellow]"
                
            table.add_row(
                platform_name,
                status_badge,
                str(info["profile_dir"]),
                str(info["last_modified"] or "-"),
                desc
            )
        console.print(table)
    else:
        print("\n=== STATUS SESI LOGIN BROWSER ===")
        for key, info in statuses.items():
            print(f"Platform: {info['platform']} | Valid: {info['is_valid']} | Profile: {info['profile_dir']}")
        print("=" * 40)


def handle_auth_menu():
    """Sub-menu interaktif untuk manajemen autentikasi sesi."""
    while True:
        if HAS_RICH:
            console.print("\n[bold cyan]─── [MENU 1] MANAJEMEN AUTENTIKASI & SESI ───[/bold cyan]")
            console.print("[1] 🔍 Cek Status Semua Sesi Login")
            console.print("[2] 🔑 Login / Refresh Sesi X (Twitter)")
            console.print("[3] 🔑 Login / Refresh Sesi Threads (Instagram)")
            console.print("[0] ⬅  Kembali ke Menu Utama")
            choice = Prompt.ask("\nPilih menu auth", choices=["1", "2", "3", "0"], default="1")
        else:
            print("\n--- MENU 1: MANAJEMEN AUTENTIKASI ---")
            print("1. Cek Status Semua Sesi Login")
            print("2. Login / Refresh Sesi X (Twitter)")
            print("3. Login / Refresh Sesi Threads (Instagram)")
            print("0. Kembali ke Menu Utama")
            choice = input("\nPilih menu [1/2/3/0]: ").strip()

        if choice == "1":
            show_auth_status_table()
        elif choice == "2":
            print_info(f"Mempersiapkan browser untuk login X (Twitter)...")
            print_info(f"Target profile: {X_PROFILE_DIR}")
            try:
                success = asyncio.run(setup_x_login(str(X_PROFILE_DIR)))
                if success:
                    print_success("Sesi login X berhasil diperbarui dan disimpan!")
                else:
                    print_warning("Proses login X belum berhasil diselesaikan.")
            except Exception as e:
                print_error(f"Terjadi kesalahan saat membuka browser X: {e}")
        elif choice == "3":
            print_info(f"Mempersiapkan browser untuk login Threads...")
            print_info(f"Target profile: {THREADS_PROFILE_DIR}")
            try:
                success = asyncio.run(setup_threads_login(str(THREADS_PROFILE_DIR)))
                if success:
                    print_success("Sesi login Threads berhasil diperbarui dan disimpan!")
                else:
                    print_warning("Proses login Threads belum berhasil diselesaikan.")
            except Exception as e:
                print_error(f"Terjadi kesalahan saat membuka browser Threads: {e}")
        elif choice == "0":
            break


# ---------------------------------------------------------------------------
# Placeholder Menu Modul Lain (Akan dihubungkan saat modul dibuat)
# ---------------------------------------------------------------------------

def _input_keywords_and_urls() -> tuple[list[str], list[str]]:
    """
    Helper untuk meminta input keyword dan/atau URL dari user secara interaktif.
    Mendukung input multi-nilai dipisah koma.

    Returns:
        tuple: (keywords: list[str], direct_urls: list[str])
    """
    if HAS_RICH:
        console.print("\n[dim]Ketik keyword atau URL. Pisahkan dengan koma untuk beberapa nilai.[/dim]")
        console.print("[dim]Contoh keyword : RKUHAP, #TolakRKUHAP[/dim]")
        console.print("[dim]Contoh URL     : https://x.com/user/status/123, https://x.com/user/status/456[/dim]")
        raw = Prompt.ask("[bold cyan]Input keyword / URL[/bold cyan]", default="")
    else:
        print("\nKetik keyword atau URL (pisahkan dengan koma):")
        raw = input("Input: ").strip()

    if not raw.strip():
        return [], []

    parts    = [p.strip() for p in raw.split(",") if p.strip()]
    urls     = [p for p in parts if re.match(r'https?://', p)]
    keywords = [p for p in parts if not re.match(r'https?://', p)]
    return keywords, urls


CURRENT_SEARCH_MODE = "latest"  # "latest" (Terbaru/Recent) atau "top" (Terpopuler/Top)


def handle_scraping_menu():
    """Sub-menu interaktif untuk Modul 2: Scraping Data (X & Threads)."""
    global CURRENT_SEARCH_MODE
    while True:
        mode_label = "TERBARU (LATEST / RECENT) ⚡" if CURRENT_SEARCH_MODE == "latest" else "TERPOPULER (TOP) 🔥"
        mode_color = "bold green" if CURRENT_SEARCH_MODE == "latest" else "bold yellow"

        if HAS_RICH:
            console.print("\n[bold cyan]─── [MENU 2] SCRAPING DATA MEDIA SOSIAL ───[/bold cyan]")
            # Tampilkan status sesi singkat & mode aktif
            for key, s in get_all_sessions_status().items():
                badge = "[green]●[/green]" if s["is_valid"] else "[red]○[/red]"
                console.print(f"  {badge} Sesi {s['platform']}: {'AKTIF' if s['is_valid'] else 'Belum Ada/Expired'}")
            console.print(f"  🎯 Mode Pencarian: [{mode_color}]{mode_label}[/{mode_color}]")
            console.print()
            console.print("[1] 🐦 Scraping X")
            console.print("[2] 🧵 Scraping Threads")
            console.print("[3] 🔄 Scraping Kedua Platform Sekuensial")
            console.print("[4] ⚡ Scraping Kedua Platform Paralel")
            console.print(f"[5] ⚙️  Ubah Mode Pencarian (Saat ini: {CURRENT_SEARCH_MODE.upper()})")
            console.print("[0] ⬅  Kembali ke Menu Utama")
            choice = Prompt.ask("\nPilih menu scraping", choices=["1", "2", "3", "4", "5", "0"], default="1")
        else:
            print("\n--- MENU 2: SCRAPING DATA ---")
            print(f"Mode Pencarian Aktif: {CURRENT_SEARCH_MODE.upper()}")
            print("1. Scraping X (Twitter)")
            print("2. Scraping Threads (Meta)")
            print("3. Scraping Kedua Platform (Sekuensial / Bergantian)")
            print("4. Scraping Kedua Platform (Paralel / Bersamaan)")
            print(f"5. Ubah Mode Pencarian (Saat ini: {CURRENT_SEARCH_MODE.upper()})")
            print("0. Kembali ke Menu Utama")
            choice = input("\nPilih menu [1/2/3/4/5/0]: ").strip()

        if choice == "0":
            break

        if choice == "5":
            if HAS_RICH:
                console.print("\n[bold cyan]─── PILIH MODE PENCARIAN TAHAP 1 ───[/bold cyan]")
                console.print("[1] ⚡ [bold green]Terbaru (Latest / Recent)[/bold green] — Mengambil cuitan/postingan kronologis paling baru")
                console.print("[2] 🔥 [bold yellow]Terpopuler (Top)[/bold yellow] — Mengambil cuitan/postingan dengan interaksi/engagements tertinggi")
                sub_choice = Prompt.ask("\nPilih mode", choices=["1", "2"], default="1")
            else:
                print("\nPilih Mode Pencarian:")
                print("1. Terbaru (Latest / Recent)")
                print("2. Terpopuler (Top)")
                sub_choice = input("Pilihan [1/2]: ").strip()

            if sub_choice == "1":
                CURRENT_SEARCH_MODE = "latest"
                print_success("Mode pencarian diubah menjadi: TERBARU (LATEST / RECENT)")
            elif sub_choice == "2":
                CURRENT_SEARCH_MODE = "top"
                print_success("Mode pencarian diubah menjadi: TERPOPULER (TOP)")
            continue

        if choice == "1":
            x_status = is_session_valid("x")
            if not x_status["is_valid"]:
                print_warning("Sesi X belum ada atau expired. Silakan login dulu via Menu 1 → [2].")
                continue

            if HAS_RICH:
                console.print(f"\n[bold cyan]─── SCRAPING X (TWITTER) [{mode_label}] ───[/bold cyan]")
            keywords, urls = _input_keywords_and_urls()
            if not keywords and not urls:
                print_warning("Tidak ada keyword/URL yang dimasukkan. Operasi dibatalkan.")
            else:
                try:
                    print_info(f"Memulai scraping X ({CURRENT_SEARCH_MODE.upper()}) — Keyword: {keywords} | URL langsung: {len(urls)}")
                    df = asyncio.run(run_x_scraper(
                        keywords=keywords,
                        direct_urls=urls,
                        config=XScrapeConfig(search_mode=CURRENT_SEARCH_MODE),
                    ))
                    if not df.empty:
                        print_success(f"Scraping X selesai! {len(df)} baris data terkumpul.")
                        print_info(f"Tersimpan di: {EXPORTS_DIR / 'x_scraper_result.csv'}")
                    else:
                        print_warning("Tidak ada data yang terkumpul. Periksa keyword atau sesi login.")
                except Exception as e:
                    print_error(f"Scraping X gagal: {e}")

            input("\nTekan Enter untuk kembali ke menu scraping...")

        elif choice == "2":
            t_status = is_session_valid("threads")
            if not t_status["is_valid"]:
                print_warning("Sesi Threads belum ada atau expired. Silakan login dulu via Menu 1 → [3].")
                continue

            if HAS_RICH:
                console.print(f"\n[bold cyan]─── SCRAPING THREADS (META) [{mode_label}] ───[/bold cyan]")
            keywords, urls = _input_keywords_and_urls()
            if not keywords and not urls:
                print_warning("Tidak ada keyword/URL yang dimasukkan. Operasi dibatalkan.")
            else:
                try:
                    print_info(f"Memulai scraping Threads ({CURRENT_SEARCH_MODE.upper()}) — Keyword: {keywords} | URL langsung: {len(urls)}")
                    df = asyncio.run(run_threads_scraper(
                        keywords=keywords,
                        direct_urls=urls,
                        config=ThreadsScrapeConfig(search_mode=CURRENT_SEARCH_MODE),
                    ))
                    if not df.empty:
                        print_success(f"Scraping Threads selesai! {len(df)} baris data terkumpul.")
                        print_info(f"Tersimpan di: {EXPORTS_DIR / 'threads_scraper_result.csv'}")
                    else:
                        print_warning("Tidak ada data yang terkumpul. Periksa keyword atau sesi login.")
                except Exception as e:
                    print_error(f"Scraping Threads gagal: {e}")

            input("\nTekan Enter untuk kembali ke menu scraping...")

        elif choice == "3":
            # Validasi kedua sesi sebelum mulai
            x_status = is_session_valid("x")
            t_status = is_session_valid("threads")

            if not x_status["is_valid"] and not t_status["is_valid"]:
                print_error("Kedua sesi (X & Threads) belum ada / expired. Silakan login terlebih dahulu via Menu 1.")
                continue

            if HAS_RICH:
                console.print(f"\n[bold cyan]─── SCRAPING DUAL PLATFORM SEKUENSIAL [{mode_label}] ───[/bold cyan]")
            keywords, urls = _input_keywords_and_urls()
            if not keywords and not urls:
                print_warning("Tidak ada keyword/URL yang dimasukkan. Operasi dibatalkan.")
                continue

            x_count = 0
            threads_count = 0

            # 1. Eksekusi Scraping X
            if x_status["is_valid"]:
                try:
                    print_info(f"\n[1/2] Memulai scraping X (Twitter) [{CURRENT_SEARCH_MODE.upper()}]...")
                    df_x = asyncio.run(run_x_scraper(
                        keywords=keywords,
                        direct_urls=urls,
                        config=XScrapeConfig(search_mode=CURRENT_SEARCH_MODE),
                    ))
                    x_count = len(df_x) if not df_x.empty else 0
                    if x_count > 0:
                        print_success(f"Scraping X selesai! {x_count} baris data terkumpul.")
                    else:
                        print_warning("Tidak ada data X yang terkumpul.")
                except Exception as e:
                    print_error(f"Scraping X gagal: {e}")
            else:
                print_warning("Sesi X tidak aktif, melewati scraping X.")

            # 2. Eksekusi Scraping Threads (Otomatis menggunakan keyword yang sama)
            if t_status["is_valid"]:
                try:
                    print_info(f"\n[2/2] Memulai scraping Threads (Meta) [{CURRENT_SEARCH_MODE.upper()}]...")
                    df_t = asyncio.run(run_threads_scraper(
                        keywords=keywords,
                        direct_urls=urls,
                        config=ThreadsScrapeConfig(search_mode=CURRENT_SEARCH_MODE),
                    ))
                    threads_count = len(df_t) if not df_t.empty else 0
                    if threads_count > 0:
                        print_success(f"Scraping Threads selesai! {threads_count} baris data terkumpul.")
                    else:
                        print_warning("Tidak ada data Threads yang terkumpul.")
                except Exception as e:
                    print_error(f"Scraping Threads gagal: {e}")
            else:
                print_warning("Sesi Threads tidak aktif, melewati scraping Threads.")

            # Ringkasan Akhir
            if HAS_RICH:
                summary_table = Table(title=f"\n📊 Ringkasan Hasil Scraping Sekuensial [{CURRENT_SEARCH_MODE.upper()}]", box=box.ROUNDED)
                summary_table.add_column("Platform", style="bold cyan")
                summary_table.add_column("Jumlah Data", justify="right", style="bold green")
                summary_table.add_column("Lokasi File", style="dim")
                summary_table.add_row("X (Twitter)", f"{x_count} baris", str(EXPORTS_DIR / "x_scraper_result.csv"))
                summary_table.add_row("Threads (Meta)", f"{threads_count} baris", str(EXPORTS_DIR / "threads_scraper_result.csv"))
                summary_table.add_row("TOTAL", f"{x_count + threads_count} baris", "-")
                console.print(summary_table)
            else:
                print(f"\n=== RINGKASAN SCRAPING ({CURRENT_SEARCH_MODE.upper()}) ===")
                print(f"X (Twitter)    : {x_count} baris")
                print(f"Threads (Meta) : {threads_count} baris")
                print(f"Total          : {x_count + threads_count} baris")

            input("\nTekan Enter untuk kembali ke menu scraping...")

        elif choice == "4":
            # Validasi kedua sesi sebelum mulai
            x_status = is_session_valid("x")
            t_status = is_session_valid("threads")

            if not x_status["is_valid"] or not t_status["is_valid"]:
                if not x_status["is_valid"]:
                    print_warning("Sesi X belum ada/expired.")
                if not t_status["is_valid"]:
                    print_warning("Sesi Threads belum ada/expired.")
                print_error("Kedua sesi (X & Threads) harus aktif untuk menjalankan mode paralel.")
                continue

            if HAS_RICH:
                console.print(f"\n[bold cyan]─── SCRAPING DUAL PLATFORM PARALEL ⚡ [{mode_label}] ───[/bold cyan]")
                console.print("[dim]Kedua browser akan berjalan bersamaan untuk mempercepat proses.[/dim]")
            keywords, urls = _input_keywords_and_urls()
            if not keywords and not urls:
                print_warning("Tidak ada keyword/URL yang dimasukkan. Operasi dibatalkan.")
                continue

            x_count = 0
            threads_count = 0

            async def _run_both_parallel():
                print_info(f"Meluncurkan scraping X & Threads [{CURRENT_SEARCH_MODE.upper()}] secara bersamaan...")
                task_x = run_x_scraper(keywords=keywords, direct_urls=urls, config=XScrapeConfig(search_mode=CURRENT_SEARCH_MODE))
                task_t = run_threads_scraper(keywords=keywords, direct_urls=urls, config=ThreadsScrapeConfig(search_mode=CURRENT_SEARCH_MODE))
                return await asyncio.gather(task_x, task_t, return_exceptions=True)

            try:
                res_x, res_t = asyncio.run(_run_both_parallel())

                # Evaluasi hasil X
                if isinstance(res_x, Exception):
                    print_error(f"Scraping X error: {res_x}")
                elif not res_x.empty:
                    x_count = len(res_x)
                    print_success(f"Scraping X selesai: {x_count} baris.")
                else:
                    print_warning("Tidak ada data X yang terkumpul.")

                # Evaluasi hasil Threads
                if isinstance(res_t, Exception):
                    print_error(f"Scraping Threads error: {res_t}")
                elif not res_t.empty:
                    threads_count = len(res_t)
                    print_success(f"Scraping Threads selesai: {threads_count} baris.")
                else:
                    print_warning("Tidak ada data Threads yang terkumpul.")

            except Exception as e:
                print_error(f"Terjadi kegagalan pada scraping paralel: {e}")

            # Ringkasan Akhir
            if HAS_RICH:
                summary_table = Table(title=f"\n📊 Ringkasan Hasil Scraping Paralel ⚡ [{CURRENT_SEARCH_MODE.upper()}]", box=box.ROUNDED)
                summary_table.add_column("Platform", style="bold cyan")
                summary_table.add_column("Jumlah Data", justify="right", style="bold green")
                summary_table.add_column("Lokasi File", style="dim")
                summary_table.add_row("X (Twitter)", f"{x_count} baris", str(EXPORTS_DIR / "x_scraper_result.csv"))
                summary_table.add_row("Threads (Meta)", f"{threads_count} baris", str(EXPORTS_DIR / "threads_scraper_result.csv"))
                summary_table.add_row("TOTAL", f"{x_count + threads_count} baris", "-")
                console.print(summary_table)
            else:
                print(f"\n=== RINGKASAN SCRAPING PARALEL ({CURRENT_SEARCH_MODE.upper()}) ===")
                print(f"X (Twitter)    : {x_count} baris")
                print(f"Threads (Meta) : {threads_count} baris")
                print(f"Total          : {x_count + threads_count} baris")

            input("\nTekan Enter untuk kembali ke menu scraping...")


def handle_preprocessing_menu():
    """Sub-menu interaktif Modul 3: Preprocessing & Normalisasi Teks."""
    while True:
        if HAS_RICH:
            console.print("\n[bold cyan]─── [MENU 3] PREPROCESSING & NORMALISASI TEKS ───[/bold cyan]")
            console.print("[dim]Pipeline: Deduplikasi → Regex Cleaning → Case Folding → Kamusalay Normalizer[/dim]")
            console.print()
            console.print("[1] 📄 Preprocess File CSV Hasil Scraping")
            console.print("[2] ⌨️  Preprocess Teks Langsung (Demo / Uji Coba)")
            console.print("[0] ⬅  Kembali ke Menu Utama")
            choice = Prompt.ask("\nPilih menu preprocessing", choices=["1", "2", "0"], default="1")
        else:
            print("\n--- MENU 3: PREPROCESSING TEKS ---")
            print("1. Preprocess File CSV Hasil Scraping")
            print("2. Preprocess Teks Langsung (Demo)")
            print("0. Kembali ke Menu Utama")
            choice = input("\nPilih menu [1/2/0]: ").strip()

        if choice == "0":
            break

        if choice == "1":
            # ─── Preprocess CSV ───
            if HAS_RICH:
                console.print("\n[bold cyan]─── PREPROCESS FILE CSV SCRAPING ───[/bold cyan]")

            # Cari file CSV yang ada di EXPORTS_DIR
            csv_files = sorted(EXPORTS_DIR.glob("*.csv"))
            if not csv_files:
                print_warning(f"Tidak ada file CSV di direktori export: {EXPORTS_DIR}")
                input("\nTekan Enter untuk kembali...")
                continue

            if HAS_RICH:
                console.print("\nFile CSV yang tersedia:")
                for i, f in enumerate(csv_files, start=1):
                    import os
                    size_kb = os.path.getsize(f) // 1024
                    console.print(f"  [{i}] {f.name} ({size_kb} KB)")
                idx_str = Prompt.ask(
                    "\nPilih nomor file",
                    choices=[str(i) for i in range(1, len(csv_files) + 1)],
                    default="1",
                )
            else:
                print("\nFile CSV yang tersedia:")
                for i, f in enumerate(csv_files, start=1):
                    print(f"  {i}. {f.name}")
                idx_str = input("Pilih nomor file: ").strip()

            try:
                selected_file = csv_files[int(idx_str) - 1]
            except (ValueError, IndexError):
                print_warning("Nomor file tidak valid.")
                continue

            # Load dan preprocess
            try:
                import pandas as pd
                print_info(f"Memuat file: {selected_file.name}...")
                df_raw = pd.read_csv(selected_file, encoding="utf-8-sig")
                print_info(f"Loaded {len(df_raw):,} baris dari '{selected_file.name}'.")

                if df_raw.empty:
                    print_warning("File CSV kosong!")
                    input("\nTekan Enter untuk kembali...")
                    continue

                print_info("Menginisialisasi pipeline (memuat kamus slang kamusalay)...")
                pipeline = PreprocessingPipeline()

                show_prog = True
                df_clean = pipeline.transform_dataframe(df_raw, text_column="content", show_progress=show_prog)

                # Simpan output
                stem = selected_file.stem.replace("_result", "").replace("_scraper", "")
                out_filename = f"{stem}_preprocessed.csv"
                out_path = EXPORTS_DIR / out_filename
                df_clean.to_csv(out_path, index=False, encoding="utf-8-sig")

                print_success(f"Preprocessing selesai! {len(df_clean):,} baris bersih.")
                print_info(f"File output tersimpan: {out_path}")

                # Tampilkan sample 3 baris
                if HAS_RICH:
                    sample_table = Table(title="Contoh Hasil Preprocessing (3 Baris Pertama)", box=box.ROUNDED)
                    sample_table.add_column("#", style="dim", width=3)
                    sample_table.add_column("platform", style="cyan", width=10)
                    sample_table.add_column("user_id", style="yellow", width=15)
                    sample_table.add_column("content (hasil preprocessing)", style="bold green", max_width=60)
                    for i, row in df_clean.head(3).iterrows():
                        sample_table.add_row(
                            str(i + 1),
                            str(row.get("platform", "")),
                            str(row.get("user_id", "")),
                            str(row.get("content", ""))[:120],
                        )
                    console.print(sample_table)
                    console.print(f"[dim]Struktur Kolom ({len(df_clean.columns)} kolom): {list(df_clean.columns)}[/dim]")
                else:
                    print("\n--- Contoh Hasil (3 baris pertama) ---")
                    for i, row in df_clean.head(3).iterrows():
                        print(f"  [{row.get('platform')}] @{row.get('user_id')}: {str(row.get('content', ''))[:100]}")
                    print(f"\nStruktur Kolom: {list(df_clean.columns)}")

            except Exception as e:
                print_error(f"Preprocessing gagal: {e}")
                import traceback; traceback.print_exc()

            input("\nTekan Enter untuk kembali ke menu preprocessing...")

        elif choice == "2":
            # ─── Demo teks langsung ───
            if HAS_RICH:
                console.print("\n[bold cyan]─── DEMO PREPROCESSING TEKS LANGSUNG ───[/bold cyan]")
                console.print("[dim]Ketik teks bebas untuk melihat hasil cleaning + normalisasi slang.[/dim]")

            print_info("Memuat pipeline (kamus slang)...")
            try:
                pipeline = PreprocessingPipeline()
            except Exception as e:
                print_error(f"Gagal memuat pipeline: {e}")
                input("\nTekan Enter untuk kembali...")
                continue

            while True:
                if HAS_RICH:
                    raw = Prompt.ask("\n[cyan]Masukkan teks[/cyan] (kosongkan untuk kembali)")
                else:
                    raw = input("\nMasukkan teks (kosong = kembali): ")

                if not raw.strip():
                    break

                step1 = clean_text(raw)
                step2 = case_folding(step1)
                step3 = pipeline.transform_text(raw)

                if HAS_RICH:
                    console.print(f"  [dim]RAW      :[/dim] {raw}")
                    console.print(f"  [yellow]CLEANED  :[/yellow] {step1}")
                    console.print(f"  [yellow]LOWERCASE:[/yellow] {step2}")
                    console.print(f"  [bold green]NORMALIZED:[/bold green] {step3}")
                else:
                    print(f"  RAW       : {raw}")
                    print(f"  CLEANED   : {step1}")
                    print(f"  LOWERCASE : {step2}")
                    print(f"  NORMALIZED: {step3}")


def handle_classification_menu():
    """Sub-menu interaktif Modul 4: Inferensi & Uji Model IndoBERT."""
    while True:
        if HAS_RICH:
            console.print("\n[bold magenta]─── [MENU 4] INFERENSI & KLASIFIKASI INDOBERT ───[/bold magenta]")
            console.print("[dim]Model: IndoBERT Multi-Head Hierarchical Classifier (Level 1 & Level 2)[/dim]")
            console.print()
            console.print("[1] ⌨️  Uji Coba Prediksi Teks Tunggal (Interaktif)")
            console.print("[2] 📄 Klasifikasi File CSV Hasil Preprocessing")
            console.print("[3] ℹ️  Informasi & Status Model")
            console.print("[0] ⬅  Kembali ke Menu Utama")
            choice = Prompt.ask("\nPilih menu klasifikasi", choices=["1", "2", "3", "0"], default="1")
        else:
            print("\n--- MENU 4: KLASIFIKASI INDOBERT ---")
            print("1. Uji Coba Prediksi Teks Tunggal")
            print("2. Klasifikasi File CSV Hasil Preprocessing")
            print("3. Informasi & Status Model")
            print("0. Kembali ke Menu Utama")
            choice = input("\nPilih menu [1/2/3/0]: ").strip()

        if choice == "0":
            break

        # ─── Cek Ketersediaan Model ───
        weights_file = MODEL_DIR / "best_model.pt"
        if not weights_file.exists():
            if HAS_RICH:
                panel = Panel(
                    f"[bold red]⚠️ File Bobot Model Tidak Ditemukan![/bold red]\n\n"
                    f"File [yellow]best_model.pt[/yellow] belum ada di direktori:\n"
                    f"[cyan]{MODEL_DIR}[/cyan]\n\n"
                    f"[bold white]Cara Memasang Model dari Google Colab:[/bold white]\n"
                    f"1. Download file [bold green]hasil_training_indobert.zip[/bold green] dari Colab.\n"
                    f"2. Ekstrak isinya ([yellow]best_model.pt[/yellow], [yellow]label_mapping.json[/yellow], [yellow]model_config.json[/yellow]).\n"
                    f"3. Pindahkan file-file tersebut ke folder di atas.\n\n"
                    f"[dim]Setelah file ditaruh, menu ini akan otomatis mendeteksi dan siap dipakai.[/dim]",
                    title="[bold red]Model Belum Tersedia[/bold red]",
                    box=box.ROUNDED,
                    border_style="red"
                )
                console.print(panel)
            else:
                print(f"\n[WARNING] File bobot 'best_model.pt' belum ada di: {MODEL_DIR}")
                print("Silakan ekstrak hasil training Colab ke folder tersebut.")

            input("\nTekan Enter untuk kembali...")
            continue

        # Inisialisasi Predictor (Lazy-loaded singleton: hanya dimuat saat menu 4 dibuka)
        try:
            print_info("Memuat model IndoBERT ke memori...")
            from src.classification import HateSpeechPredictor, ModelLoader
            predictor = HateSpeechPredictor()
        except Exception as e:
            print_error(f"Gagal memuat model: {e}")
            import traceback; traceback.print_exc()
            input("\nTekan Enter untuk kembali...")
            continue

        if choice == "1":
            # ─── Uji Prediksi Teks Tunggal ───
            if HAS_RICH:
                console.print("\n[bold magenta]─── UJI PREDIKSI TEKS TUNGGAL ───[/bold magenta]")
                console.print("[dim]Ketik kalimat opini/komentar untuk melihat prediksi Level 1 & Level 2.[/dim]")

            # Pipeline preprocessing untuk membersihkan teks sebelum masuk tokenizer
            prep_pipeline = PreprocessingPipeline()

            while True:
                if HAS_RICH:
                    raw_input_text = Prompt.ask("\n[cyan]Masukkan teks[/cyan] (kosongkan untuk kembali)")
                else:
                    raw_input_text = input("\nMasukkan teks (kosong = kembali): ")

                if not raw_input_text.strip():
                    break

                # Preprocessing teks terlebih dahulu
                clean_input_text = prep_pipeline.transform_text(raw_input_text)
                if not clean_input_text.strip():
                    print_warning("Teks kosong setelah dibersihkan.")
                    continue

                # Jalankan prediksi
                res = predictor.predict_text(clean_input_text)

                lvl1_label = res["level1"]["label"]
                lvl1_conf  = res["level1"]["confidence"]
                lvl2_label = res["level2"]["label"]
                lvl2_conf  = res["level2"]["confidence"]
                is_hate    = res["is_hate_speech"]

                if HAS_RICH:
                    status_style = "bold red" if is_hate else "bold green"
                    status_text  = "⚠️ TERDETEKSI UJARAN KEBENCIAN" if is_hate else "✅ NON-HATE SPEECH (AMAN)"

                    result_table = Table(title="Hasil Klasifikasi IndoBERT", box=box.ROUNDED)
                    result_table.add_column("Tingkat", style="bold cyan", width=15)
                    result_table.add_column("Label Prediksi", style="bold white", width=25)
                    result_table.add_column("Confidence Score", style="bold yellow", width=20)

                    result_table.add_row(
                        "Level 1",
                        f"[{'red' if is_hate else 'green'}]{lvl1_label.upper()}[/{'red' if is_hate else 'green'}]",
                        f"{lvl1_conf * 100:.2f}%"
                    )
                    result_table.add_row(
                        "Level 2",
                        f"[yellow]{lvl2_label}[/yellow]",
                        f"{lvl2_conf * 100:.2f}%"
                    )
                    console.print(result_table)
                    console.print(f"Status Keseluruhan: [{status_style}]{status_text}[/{status_style}]")
                    console.print(f"[dim]Teks Asli     : {raw_input_text}[/dim]")
                    console.print(f"[dim]Teks Bersih   : {clean_input_text}[/dim]")
                else:
                    print("\n--- Hasil Klasifikasi ---")
                    print(f"Teks Bersih : {clean_input_text}")
                    print(f"Level 1     : {lvl1_label.upper()} ({lvl1_conf * 100:.2f}%)")
                    print(f"Level 2     : {lvl2_label} ({lvl2_conf * 100:.2f}%)")
                    print(f"Status      : {'⚠️ UJARAN KEBENCIAN' if is_hate else '✅ NON-HATE SPEECH'}")

        elif choice == "2":
            # ─── Klasifikasi File CSV ───
            if HAS_RICH:
                console.print("\n[bold magenta]─── KLASIFIKASI FILE CSV HASIL PREPROCESSING ───[/bold magenta]")

            # Cari file CSV yang ada di EXPORTS_DIR
            csv_files = sorted(EXPORTS_DIR.glob("*.csv"))
            if not csv_files:
                print_warning(f"Tidak ada file CSV di direktori: {EXPORTS_DIR}")
                input("\nTekan Enter untuk kembali...")
                continue

            if HAS_RICH:
                console.print("\nFile CSV yang tersedia:")
                for i, f in enumerate(csv_files, start=1):
                    import os
                    size_kb = os.path.getsize(f) // 1024
                    tag = " [bold green](Preprocessed)[/bold green]" if "_preprocessed" in f.name else ""
                    console.print(f"  [{i}] {f.name}{tag} ({size_kb} KB)")
                idx_str = Prompt.ask(
                    "\nPilih nomor file yang akan diklasifikasikan",
                    choices=[str(i) for i in range(1, len(csv_files) + 1)],
                    default="1",
                )
            else:
                print("\nFile CSV yang tersedia:")
                for i, f in enumerate(csv_files, start=1):
                    print(f"  {i}. {f.name}")
                idx_str = input("Pilih nomor file: ").strip()

            try:
                selected_file = csv_files[int(idx_str) - 1]
            except (ValueError, IndexError):
                print_warning("Nomor file tidak valid.")
                continue

            try:
                import pandas as pd
                print_info(f"Memuat file: {selected_file.name}...")
                df_input = pd.read_csv(selected_file, encoding="utf-8-sig")
                print_info(f"Loaded {len(df_input):,} baris dari '{selected_file.name}'.")

                if df_input.empty:
                    print_warning("File CSV kosong!")
                    input("\nTekan Enter untuk kembali...")
                    continue

                # Tentukan kolom teks yang akan diprediksi
                text_col = "content"
                if "content" not in df_input.columns and "clean_text" in df_input.columns:
                    text_col = "clean_text"
                elif text_col not in df_input.columns:
                    print_error(f"Kolom teks ('content' / 'clean_text') tidak ditemukan di file. Kolom: {list(df_input.columns)}")
                    input("\nTekan Enter untuk kembali...")
                    continue

                # Jalankan klasifikasi batch
                df_classified = predictor.predict_dataframe(
                    df_input,
                    text_column=text_col,
                    batch_size=32,
                    show_progress=True,
                )

                # Simpan output
                stem = selected_file.stem.replace("_preprocessed", "").replace("_result", "").replace("_scraper", "")
                out_filename = f"{stem}_classified.csv"
                out_path = EXPORTS_DIR / out_filename
                df_classified.to_csv(out_path, index=False, encoding="utf-8-sig")

                print_success(f"Klasifikasi selesai! {len(df_classified):,} baris berhasil diproses.")
                print_info(f"File output tersimpan: {out_path}")

                # Ringkasan Statistik
                total_rows = len(df_classified)
                hate_total = sum(df_classified["label_lvl1"] == "hate_speech")
                nonhate_total = total_rows - hate_total

                if HAS_RICH:
                    stat_table = Table(title=f"Ringkasan Statistik Sentimen ({selected_file.name})", box=box.ROUNDED)
                    stat_table.add_column("Kategori Level 1", style="bold cyan", width=25)
                    stat_table.add_column("Jumlah Data", style="bold white", width=15)
                    stat_table.add_column("Persentase", style="bold yellow", width=15)

                    stat_table.add_row("⚠️ Hate Speech", f"{hate_total:,}", f"{hate_total/total_rows*100:.2f}%")
                    stat_table.add_row("✅ Non-Hate Speech", f"{nonhate_total:,}", f"{nonhate_total/total_rows*100:.2f}%")
                    stat_table.add_row("[bold]TOTAL[/bold]", f"[bold]{total_rows:,}[/bold]", "[bold]100.00%[/bold]")
                    console.print(stat_table)

                    # Tabel Sampel Hasil
                    sample_table = Table(title="Contoh Hasil Klasifikasi (3 Baris Pertama)", box=box.ROUNDED)
                    sample_table.add_column("#", style="dim", width=3)
                    sample_table.add_column("user_id", style="yellow", width=12)
                    sample_table.add_column("content", style="white", max_width=40)
                    sample_table.add_column("Level 1", style="bold", width=15)
                    sample_table.add_column("Level 2", style="cyan", width=20)
                    sample_table.add_column("Conf Lvl1", style="green", width=10)

                    for i, row in df_classified.head(3).iterrows():
                        is_h = row.get("label_lvl1") == "hate_speech"
                        lvl1_display = f"[{'red' if is_h else 'green'}]{row.get('label_lvl1', '')}[/{'red' if is_h else 'green'}]"
                        sample_table.add_row(
                            str(i + 1),
                            str(row.get("user_id", "")),
                            str(row.get("content", ""))[:80],
                            lvl1_display,
                            str(row.get("label_lvl2", "")),
                            f"{float(row.get('confidence_lvl1', 0)) * 100:.1f}%",
                        )
                    console.print(sample_table)
                else:
                    print(f"\n--- Ringkasan Statistik ---")
                    print(f"Hate Speech     : {hate_total:,} ({hate_total/total_rows*100:.2f}%)")
                    print(f"Non-Hate Speech : {nonhate_total:,} ({nonhate_total/total_rows*100:.2f}%)")

            except Exception as e:
                print_error(f"Klasifikasi gagal: {e}")
                import traceback; traceback.print_exc()

            input("\nTekan Enter untuk kembali ke menu klasifikasi...")

        elif choice == "3":
            # ─── Informasi & Status Model ───
            if HAS_RICH:
                info_table = Table(title="Informasi & Metadata Model IndoBERT", box=box.ROUNDED)
                info_table.add_column("Parameter", style="bold cyan", width=25)
                info_table.add_column("Nilai / Keterangan", style="white", width=50)

                info_table.add_row("Base Pretrained Model", predictor.loader.pretrained_model)
                info_table.add_row("Perangkat Eksekusi", f"[bold green]{predictor.device.upper()}[/bold green]")
                info_table.add_row("Max Sequence Length", f"{predictor.max_length} tokens")
                info_table.add_row("Kelas Level 1 (2)", ", ".join(predictor.classes_lvl1))
                info_table.add_row("Kelas Level 2 (6)", ", ".join(predictor.classes_lvl2))
                info_table.add_row("Folder Penyimpanan", str(MODEL_DIR))
                info_table.add_row("File Bobot", str(weights_file.name))

                console.print(info_table)
            else:
                print("\n--- Informasi Model IndoBERT ---")
                print(f"Base Model : {predictor.loader.pretrained_model}")
                print(f"Device     : {predictor.device.upper()}")
                print(f"Max Length : {predictor.max_length}")
                print(f"Kelas Lvl1 : {predictor.classes_lvl1}")
                print(f"Kelas Lvl2 : {predictor.classes_lvl2}")

            input("\nTekan Enter untuk kembali...")


def handle_pipeline_menu():
    """Sub-menu interaktif Modul 5: Eksekusi Analisis Penuh (End-to-End Orchestrator)."""
    while True:
        if HAS_RICH:
            console.print("\n[bold magenta]─── [MENU 5] EKSEKUSI ANALISIS PENUH (END-TO-END) ───[/bold magenta]")
            console.print("[dim]Alur Otomatis Penuh: Validasi Sesi → Scraping → Preprocessing → IndoBERT → Statistik & Export CSV[/dim]")
            console.print()
            console.print("[1] 🚀 Jalankan Analisis Lengkap (X & Threads - Paralel ⚡)")
            console.print("[2] 🐦 Jalankan Analisis Khusus X (Twitter)")
            console.print("[3] 🧵 Jalankan Analisis Khusus Threads (Meta)")
            console.print("[0] ⬅  Kembali ke Menu Utama")
            choice = Prompt.ask("\nPilih opsi pipeline", choices=["1", "2", "3", "0"], default="1")
        else:
            print("\n--- MENU 5: ANALISIS PENUH END-TO-END ---")
            print("1. Jalankan Analisis Lengkap (X & Threads)")
            print("2. Jalankan Analisis Khusus X")
            print("3. Jalankan Analisis Khusus Threads")
            print("0. Kembali ke Menu Utama")
            choice = input("\nPilih opsi [1/2/3/0]: ").strip()

        if choice == "0":
            break

        platform_map = {
            "1": "both",
            "2": "x",
            "3": "threads",
        }
        selected_platform = platform_map.get(choice, "both")

        # ─── 1. Cek Ketersediaan Model ───
        weights_file = MODEL_DIR / "best_model.pt"
        if not weights_file.exists():
            print_error(f"Model IndoBERT ('best_model.pt') belum ditemukan di: {MODEL_DIR}")
            print_warning("Harap pastikan file bobot hasil training sudah diekstrak sebelum menjalankan pipeline end-to-end.")
            input("\nTekan Enter untuk kembali...")
            continue

        # ─── 2. Input Parameter Analisis ───
        if HAS_RICH:
            console.print(f"\n[bold cyan]─── PARAMETER ANALISIS END-TO-END [{selected_platform.upper()}] ───[/bold cyan]")
        
        keywords, urls = _input_keywords_and_urls()
        if not keywords and not urls:
            print_warning("Tidak ada keyword atau URL yang dimasukkan. Analisis dibatalkan.")
            continue

        # Konfigurasi Tambahan
        if HAS_RICH:
            limit_str = Prompt.ask("Batas link/postingan yang di-scan per platform", default="30")
            steps_str = Prompt.ask("Maksimal scroll scan per postingan (kedalaman komentar)", default="200")
            headless_choice = Prompt.ask("Jalankan browser di latar belakang (Headless)?", choices=["y", "n"], default="n")
        else:
            limit_str = input("Batas link/postingan yang di-scan (default: 30): ").strip() or "30"
            steps_str = input("Maksimal scroll scan per postingan (default: 200): ").strip() or "200"
            headless_choice = input("Jalankan browser headless? [y/n] (default: n): ").strip().lower() or "n"

        max_links = int(limit_str) if limit_str.isdigit() else 30
        max_scroll_steps = int(steps_str) if steps_str.isdigit() else 200
        headless = (headless_choice == "y")

        # ─── 3. Eksekusi Pipeline ───
        if HAS_RICH:
            console.print(f"\n[bold green]▶ MEMULAI PIPELINE END-TO-END [{CURRENT_SEARCH_MODE.upper()}]...[/bold green]")
        else:
            print(f"\n>>> MEMULAI PIPELINE END-TO-END ({CURRENT_SEARCH_MODE.upper()})...")

        def _cli_status_callback(msg: str):
            if HAS_RICH:
                console.print(f"  [cyan]ℹ[/cyan] {msg}")
            else:
                print(f"  [INFO] {msg}")

        try:
            from src.pipeline import run_pipeline_sync
            
            result = run_pipeline_sync(
                keywords=keywords,
                direct_urls=urls,
                platform=selected_platform,
                search_mode=CURRENT_SEARCH_MODE,
                max_links=max_links,
                max_scroll_steps=max_scroll_steps,
                headless=headless,
                export_csv=True,
                status_callback=_cli_status_callback,
            )

            # Evaluasi Hasil
            if result.get("status") == "error":
                print_error(f"Pipeline gagal pada tahap {result.get('stage')}: {result.get('message')}")
                if result.get("errors"):
                    for err in result["errors"]:
                        print_warning(f"  - {err}")
                input("\nTekan Enter untuk kembali...")
                continue

            if result.get("status") == "warning":
                print_warning(f"Peringatan: {result.get('message')}")
                input("\nTekan Enter untuk kembali...")
                continue

            stats = result.get("statistics", {})
            total_data = result.get("total_data", 0)
            elapsed = result.get("elapsed_seconds", 0)
            exported_file = result.get("exported_file", "")
            df_final = result.get("dataframe", pd.DataFrame())

            print_success(f"\n🎉 ANALISIS SELESAI DALAM {elapsed:.1f} DETIK! ({total_data:,} data terproses)")
            print_info(f"📁 File CSV Lengkap Disimpan: {exported_file}")

            # ─── Tampilan Laporan Statistik ───
            if HAS_RICH:
                # 1. Tabel Ringkasan Utama (Level 1)
                main_table = Table(title="📊 Ringkasan Deteksi Ujaran Kebencian (Level 1)", box=box.ROUNDED)
                main_table.add_column("Klasifikasi Sentimen", style="bold cyan", width=28)
                main_table.add_column("Jumlah Data", justify="right", style="bold white", width=16)
                main_table.add_column("Persentase", justify="right", style="bold yellow", width=16)

                hate_cnt = stats.get("hate_speech_count", 0)
                hate_pct = stats.get("hate_speech_pct", 0.0)
                nonhate_cnt = stats.get("non_hate_speech_count", 0)
                nonhate_pct = stats.get("non_hate_speech_pct", 0.0)

                main_table.add_row("⚠️ Ujaran Kebencian (Hate Speech)", f"{hate_cnt:,}", f"{hate_pct:.2f}%")
                main_table.add_row("✅ Opini Netral/Aman (Non-Hate)", f"{nonhate_cnt:,}", f"{nonhate_pct:.2f}%")
                main_table.add_row("[bold]TOTAL OPINI TERANALISIS[/bold]", f"[bold]{total_data:,}[/bold]", "[bold]100.00%[/bold]")
                console.print(main_table)

                # 2. Tabel Breakdown Sub-Kategori (Level 2)
                lvl2_table = Table(title="🏷️ Distribusi Sub-Kategori Ujaran Kebencian (Level 2)", box=box.ROUNDED)
                lvl2_table.add_column("Sub-Kategori", style="bold magenta", width=28)
                lvl2_table.add_column("Jumlah Data", justify="right", style="bold white", width=16)
                lvl2_table.add_column("Proporsi", justify="right", style="bold yellow", width=16)

                lvl2_data = stats.get("level2_breakdown", {})
                for cat_name, cat_info in lvl2_data.items():
                    lvl2_table.add_row(
                        cat_name.replace("_", " ").title(),
                        f"{cat_info['count']:,}",
                        f"{cat_info['percentage']:.2f}%"
                    )
                console.print(lvl2_table)

                # 3. Tabel Perbandingan Platform (jika multi-platform)
                plat_data = stats.get("platform_breakdown", {})
                if len(plat_data) > 1:
                    plat_table = Table(title="🌐 Perbandingan Sentimen Antar Platform", box=box.ROUNDED)
                    plat_table.add_column("Platform", style="bold cyan", width=18)
                    plat_table.add_column("Total Data", justify="right", width=14)
                    plat_table.add_column("Hate Speech", justify="right", style="red", width=14)
                    plat_table.add_column("Non-Hate", justify="right", style="green", width=14)
                    plat_table.add_column("% Hate Speech", justify="right", style="bold yellow", width=16)

                    for p_name, p_info in plat_data.items():
                        plat_table.add_row(
                            p_name,
                            f"{p_info['total']:,}",
                            f"{p_info['hate_speech']:,}",
                            f"{p_info['non_hate_speech']:,}",
                            f"{p_info['hate_pct']:.2f}%"
                        )
                    console.print(plat_table)

                # 4. Tabel Sampel Hasil
                if not df_final.empty:
                    sample_table = Table(title="🔍 Sampel Data Hasil Analisis (3 Baris Teratas)", box=box.ROUNDED)
                    sample_table.add_column("Platform", style="dim", width=10)
                    sample_table.add_column("User", style="yellow", width=12)
                    sample_table.add_column("Isi Teks (Content)", style="white", max_width=45)
                    sample_table.add_column("Level 1", style="bold", width=14)
                    sample_table.add_column("Level 2", style="cyan", width=22)

                    for _, row in df_final.head(3).iterrows():
                        is_h = row.get("label_lvl1") == "hate_speech"
                        lvl1_txt = f"[{'red' if is_h else 'green'}]{str(row.get('label_lvl1','')).upper()}[/{'red' if is_h else 'green'}]"
                        sample_table.add_row(
                            str(row.get("platform", "")),
                            str(row.get("user_id", "")),
                            str(row.get("content", ""))[:70] + "...",
                            lvl1_txt,
                            str(row.get("label_lvl2", "")),
                        )
                    console.print(sample_table)

            else:
                print(f"\n=== HASIL ANALISIS ({CURRENT_SEARCH_MODE.upper()}) ===")
                print(f"Total Opini Teranalisis : {total_data:,}")
                print(f"Hate Speech             : {stats.get('hate_speech_count', 0):,} ({stats.get('hate_speech_pct', 0.0):.2f}%)")
                print(f"Non-Hate Speech         : {stats.get('non_hate_speech_count', 0):,} ({stats.get('non_hate_speech_pct', 0.0):.2f}%)")
                print(f"File Hasil              : {exported_file}")

        except Exception as e:
            print_error(f"Terjadi kesalahan saat menjalankan pipeline end-to-end: {e}")
            import traceback; traceback.print_exc()

        input("\nTekan Enter untuk kembali ke menu pipeline...")


# ---------------------------------------------------------------------------
# Loop Utama Program (Main Interactive Loop)
# ---------------------------------------------------------------------------

def main():
    """Fungsi entrypoint utama CLI runner."""
    while True:
        if HAS_RICH:
            console.clear()
        else:
            os.system('cls' if os.name == 'nt' else 'clear')

        display_banner()
        
        # Tampilkan status ringkas sesi auth di beranda CLI
        print_info("Status Sesi Saat Ini:")
        statuses = get_all_sessions_status()
        for key, s in statuses.items():
            status_text = "[green]AKTIF[/green]" if s["is_valid"] else "[red]BELUM ADA / EXPIRED[/red]"
            if HAS_RICH:
                console.print(f"  • [bold]{s['platform']}[/bold]: {status_text}")
            else:
                print(f"  - {s['platform']}: {'AKTIF' if s['is_valid'] else 'BELUM ADA / EXPIRED'}")

        if HAS_RICH:
            menu_table = Table(box=box.SIMPLE, show_header=False, padding=(0, 1))
            menu_table.add_column("No", style="bold cyan", width=4)
            menu_table.add_column("Menu", style="bold white")
            menu_table.add_column("Status", style="dim")

            menu_table.add_row("[1]", "🔑  Manajemen Autentikasi & Sesi", "[green]Siap Pakai[/green]")
            menu_table.add_row("[2]", "🌐  Scraping Data Media Sosial (X / Threads)", "[green]Siap Pakai[/green]")
            menu_table.add_row("[3]", "🧹  Pembersihan & Preprocessing Teks", "[green]Siap Pakai[/green]")
            menu_table.add_row("[4]", "🤖  Inferensi & Uji Model IndoBERT", "[green]Siap Pakai[/green]")
            menu_table.add_row("[5]", "🚀  Eksekusi Analisis Penuh (End-to-End)", "[green]Siap Pakai[/green]")
            menu_table.add_row("[0]", "🚪  Keluar dari Program", "[dim]Exit[/dim]")

            console.print("\n[bold]PILIHAN MENU UTAMA:[/bold]")
            console.print(menu_table)
            choice = Prompt.ask("\n[bold cyan]Masukkan nomor menu[/bold cyan]", choices=["1", "2", "3", "4", "5", "0"], default="1")
        else:
            print("\nPILIHAN MENU UTAMA:")
            print("1. [🔑] Manajemen Autentikasi & Sesi [Siap Pakai]")
            print("2. [🌐] Scraping Data Media Sosial (X / Threads) [Siap Pakai]")
            print("3. [🧹] Pembersihan & Preprocessing Teks [Siap Pakai]")
            print("4. [🤖] Inferensi & Uji Model IndoBERT [Siap Pakai]")
            print("5. [🚀] Eksekusi Analisis Penuh (End-to-End) [Siap Pakai]")
            print("0. [🚪] Keluar")
            choice = input("\nMasukkan nomor menu [1/2/3/4/5/0]: ").strip()

        if choice == "1":
            handle_auth_menu()
        elif choice == "2":
            handle_scraping_menu()
        elif choice == "3":
            handle_preprocessing_menu()
        elif choice == "4":
            handle_classification_menu()
        elif choice == "5":
            handle_pipeline_menu()
        elif choice == "0":
            if HAS_RICH:
                console.print("\n[bold cyan]Terima kasih telah menggunakan sistem backend analisis sentimen. Sampai jumpa![/bold cyan]\n")
            else:
                print("\nTerima kasih telah menggunakan sistem backend analisis sentimen. Sampai jumpa!\n")
            sys.exit(0)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\nProgram dihentikan oleh pengguna (Ctrl+C). Sampai jumpa!")
        sys.exit(0)
