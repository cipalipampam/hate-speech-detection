# Roadmap & Checklist Refaktorisasi Backend FastAPI
**Sistem Analisis Ujaran Kebencian (IndoBERT Multi-Head Hierarchical Classifier)**

Dokumen ini adalah panduan kerja dan *checkpoint tracker* untuk refaktorisasi arsitektur backend dari pola monolitik/fat-controller menuju arsitektur bertingkat bersih (**Controller → Schema/Request Form → Service Layer → Core Engine**).

---

## 🎯 Prinsip & Standar Arsitektur

1. **Separation of Concerns (SoC)**:
   - **Controller (`app/routers/`)**: Hanya menangani HTTP I/O, parsing parameter, memanggil Service, dan mengembalikan HTTP Response. Dilarang menampung state/variabel global atau logika komputasi.
   - **Request Form / Schema (`app/schemas/`)**: Menampung DTO (Data Transfer Object) dan validasi input/output deklaratif via Pydantic v2.
   - **Service Layer (`app/services/`)**: Menampung seluruh *Business Logic*, orkestrasi task, *in-memory job store*, konkurensi/antrean (*locks*), dan manipulasi data.
   - **Core Engine (`src/`)**: Menampung modul komputasi murni (PyTorch Model, Playwright Scraper Core, Regex Normalizer).
2. **Standardisasi Penamaan File**:
   - Menghapus prefiks redundan `api_` pada router (`api_auth.py` → `auth.py`).
   - Menghapus sufiks repetitif `_schema.py` pada schema (`scraper_schema.py` → `scraper.py`).
   - Memisahkan schema pipeline menjadi file tersendiri (`pipeline.py`).
3. **100% Kontrak API Kompatibel**:
   - Seluruh URL endpoint (`/api/v1/...`) dan struktur JSON request/response **tidak boleh berubah** agar Frontend Laravel (`FastAPIClientService.php`) tidak mengalami *breaking change*.
4. **Aturan Kolaborasi & Checkpoint**:
   - Tiap tahap dibagi menjadi sub-tugas kecil.
   - Update checklist `[ ]` menjadi `[x]` segera setelah sub-tugas selesai diverifikasi.
   - Setiap tahap memiliki **Checkpoint Gate** yang harus lolos uji sebelum melangkah ke tahap berikutnya guna mencegah konflik dan regresi kode.

---

## 🗺️ Matriks Status & Tahapan Pekerjaan

| Tahap | Deskripsi Modul | Estimasi Sub-tugas | Status |
| :---: | :--- | :---: | :---: |
| **Tahap 1** | Standarisasi Request Form & Schema Layer (`app/schemas/`) | 6 Task | `[x] Selesai` |
| **Tahap 2** | Pembangunan Application Service Layer (`app/services/`) | 6 Task | `[x] Selesai` |
| **Tahap 3** | Refaktorisasi Controller Layer (`app/routers/`) | 6 Task | `[x] Selesai` |
| **Tahap 4** | Integrasi Entrypoint & Cleanup Legacy Code (`main_api.py`) | 3 Task | `[x] Selesai` |
| **Tahap 5** | Verifikasi Komprehensif, Stress Test & Concurrency Audit | 5 Task | `[x] Selesai` |

---

## 📋 Detail Checklist Tahapan

### TAHAP 1: Pembentukan Request Form / Schema Layer (`app/schemas/`)
> **Fokus**: Membersihkan DTO, memisahkan domain yang tercampur, memindahkan validasi manual dari router ke Pydantic, dan menerapkan standar Pydantic v2.

- [x] **1.1. Ekstraksi Schema Autentikasi (`app/schemas/auth_schema.py`)**
  - [x] Pindahkan `SessionStatusItem`, `AllSessionsStatusResponse`, dan `LoginTriggerResponse` dari `app/routers/api_auth.py` ke `app/schemas/auth_schema.py`.
  - [x] Router `api_auth.py` kini mengimpor schema dari package `app.schemas`.
- [x] **1.2. Penambahan Validator Keyword Pipeline (`app/schemas/classification_schema.py`)**
  - [x] Tambahkan `@field_validator("keywords")` pada `PipelineRunRequest` untuk memvalidasi string kosong/hanya spasi `["   "]`.
- [x] **1.3. Standarisasi Schema Klasifikasi (`app/schemas/classification_schema.py`)**
  - [x] Tambahkan deklarasi batas `max_length=500` pada `ClassifyBatchRequest.texts` sehingga validasi batch 500 teks dilakukan otomatis oleh Pydantic.
- [x] **1.4. Standarisasi Schema Preprocessing (`app/schemas/preprocessing_schema.py`)**
  - [x] Tambahkan deklarasi batas `max_length=1000` pada `PreprocessBatchRequest.texts`.
- [x] **1.5. Standarisasi Schema Scraper (`app/schemas/scraper_schema.py`)**
  - [x] Tambahkan `@field_validator("keywords")` pada `ScrapeRequest` agar menolak list yang hanya berisi spasi kosong.
- [x] **1.6. Barrel Re-export (`app/schemas/__init__.py`)**
  - [x] Ekspor seluruh 25 schema publik di `__init__.py` dengan `__all__` agar import clean dan terpusat.

🏁 **CHECKPOINT GATE 1: [LOLOS / PASSED]**
- Validasi panjang batch (>500 & >1000) dan keyword kosong/spasi berhasil memicu `ValidationError` (HTTP 422).
- Seluruh 25 skema ter-import dengan sukses via `app.schemas`.

---

### TAHAP 2: Pembentukan Application Service Layer (`app/services/`)
> **Fokus**: Mengisolasi *Business Logic*, mengelola *In-Memory Job Store*, menangani *Concurrency Locks*, dan mencegah *Event Loop Blocking*.

- [x] **2.1. Implementasi State & Lock Manager (`app/services/job_service.py`)**
  - [x] Buat class `JobManager` terpusat untuk menyimpan status job scraping dan pipeline.
  - [x] Terapkan batas maksimal riwayat job (LRU / FIFO eviction) untuk mencegah **Memory Leak**.
  - [x] Buat `set` task references untuk mencegah **Garbage Collection** tak terduga pada background task asyncio.
  - [x] Implementasikan `asyncio.Lock()` untuk platform X (`x_lock`) dan Threads (`threads_lock`) guna **mencegah Playwright browser profile collision crash** (`SingletonLock`).
- [x] **2.2. Implementasi Service Autentikasi (`app/services/auth_service.py`)**
  - [x] Pindahkan fungsi `_check_display_available()` dan worker background login dari router ke service.
  - [x] Sediakan method `get_all_sessions_status()` dan `run_login_task(platform)`.
- [x] **2.3. Implementasi Service Scraper (`app/services/scraper_service.py`)**
  - [x] Pindahkan fungsi worker `_execute_scrape_worker` dari router ke service.
  - [x] Kelola pembuatan config scraper, eksekusi scraper ber-lock, penggabungan dataframe, deduplikasi pandas, dan update status job di `JobManager`.
- [x] **2.4. Implementasi Service Klasifikasi (`app/services/classification_service.py`)**
  - [x] Pindahkan logika orkestrasi preprocessing teks sebelum inferensi.
  - [x] Pindahkan kalkulasi statistik persentase hate speech dari router ke service method `classify_batch_texts()`.
- [x] **2.5. Implementasi Service Pipeline & Ekspor (`app/services/pipeline_service.py`)**
  - [x] Pindahkan worker pipeline dari router ke service dengan isolasi status tracking di `JobManager`.
  - [x] Bungkus eksekusi tahap komputasi berat (preprocessing & klasifikasi) dengan `asyncio.to_thread()` di `end_to_end_pipeline.py` agar **tidak memblokir Event Loop utama FastAPI**.
  - [x] Implementasikan pembacaan file ekspor dan **sanitasi path aman** menggunakan `pathlib.Path.is_relative_to()` untuk mencegah *Path Traversal Vulnerability*.
- [x] **2.6. Barrel Re-export (`app/services/__init__.py`)**
  - [x] Ekspor seluruh service instance terpusat di `__init__.py` (`job_manager`, `auth_service`, `scraper_service`, `classification_service`, `pipeline_service`).

🏁 **CHECKPOINT GATE 2: [LOLOS / PASSED]**
- Uji service layer terisolasi: JobManager create/update/eviction status transisi normal.
- Uji concurrency lock: Lock platform X, Threads, dan Both berhasil diakuisisi & dilepas secara tertib.
- Uji path traversal: Percobaan akses path berbahaya `../../etc/passwd` berhasil dicegat (`None`).

---

### TAHAP 3: Perombakan Controller Layer (`app/routers/`)
> **Fokus**: Menjadikan router sebagai pure HTTP controller tipis (Thin Controller) yang bebas dari logic internal.

- [x] **3.1. Refaktorisasi Router Auth (`app/routers/api_auth.py`)**
  - [x] Hapus inline schema dan fungsi helper internal (`_run_login_x`, `_run_login_threads`, `_check_display_available`).
  - [x] Perbaiki bug parameter: ubah `background_tasks: BackgroundTasks = None` menjadi `background_tasks: BackgroundTasks`.
  - [x] Delegasikan seluruh logika ke `AuthService`.
- [x] **3.2. Refaktorisasi Router Scraper (`app/routers/api_scraper.py`)**
  - [x] Hapus variabel `_job_store` dan fungsi worker `_run_scrape_job` dari router.
  - [x] Endpoint `POST /run` dan `GET /status/{job_id}` murni memanggil `ScraperService`.
- [x] **3.3. Refaktorisasi Router Klasifikasi (`app/routers/api_classification.py`)**
  - [x] Hapus inisialisasi `_preprocess_pipeline = PreprocessingPipeline()` dari modul router.
  - [x] Hapus manual length check `len(body.texts) > 500` — sudah ditangani Pydantic di Tahap 1.
  - [x] Delegasikan inferensi single dan batch ke `ClassificationService`.
- [x] **3.4. Refaktorisasi Router Preprocessing (`app/routers/api_preprocessing.py`)**
  - [x] Hapus inisialisasi `_pipeline = PreprocessingPipeline()` — reuse `classification_service._preprocessor`.
  - [x] Hapus manual length check `len(body.texts) > 1000` — sudah ditangani Pydantic di Tahap 1.
- [x] **3.5. Refaktorisasi Router Pipeline (`app/routers/api_pipeline.py`)**
  - [x] Hapus variabel `_job_store` dan worker `_run_pipeline_job` dari router.
  - [x] Delegasikan `run`, `status`, `exports`, dan `download` ke `PipelineService`.
  - [x] Path traversal protection dipindahkan ke `PipelineService.get_safe_export_path()`.
- [x] **3.6. Barrel Re-export & Router Aggregation (`app/routers/__init__.py`)**
  - [x] Daftarkan semua router di `__init__.py` dengan `__all__` (`auth_router`, `scraper_router`, dst).

🏁 **CHECKPOINT GATE 3: [LOLOS / PASSED]**
- Verifikasi OpenAPI schema: **14 endpoint** terdaftar sempurna tanpa regresi (termasuk semua prefix `/api/v1/...`).
- Seluruh router tidak lagi mengimpor `pandas` atau mengelola variabel dictionary state global.
- Ukuran file router turun signifikan: `api_auth.py` (192→87 baris), `api_scraper.py` (228→82 baris), `api_pipeline.py` (307→108 baris).

---

### TAHAP 4: Integrasi Entrypoint & Cleanup Legacy (`main_api.py`)
> **Fokus**: Menghubungkan seluruh arsitektur baru ke server utama FastAPI dan menghapus file legacy dengan aman.

- [x] **4.1. Update Router Ingestion di `main_api.py`**
  - [x] Import semua router dari barrel `app.routers`. Nama file router tetap `api_*.py`, sesuai keputusan untuk tidak melakukan rename massal.
- [x] **4.2. Injeksi Predictor ke Service Layer**
  - [x] Predictor yang dimuat pada startup diinjeksi eksplisit ke `ClassificationService` melalui `set_predictor()`. Router klasifikasi dan penggunaan service standalone memakai instance bersama ini tanpa double load; `PipelineService` mempertahankan injeksi predictor melalui parameter yang telah ada.
- [x] **4.3. Pembersihan File Legacy (`Safe Deletion`)**
  - [x] Tidak ada file legacy untuk dihapus karena nama `api_*.py` dan `*_schema.py` dipertahankan; tidak diperlukan re-export kompatibilitas tambahan.

🏁 **CHECKPOINT GATE 4: [LOLOS / PASSED]**
- Jalankan pemeriksaan import: `python -c "import main_api; print('Server ready!')"`.
- Pastikan tidak ada `ModuleNotFoundError` atau broken imports.

---

### TAHAP 5: Verifikasi Menyeluruh & Testing Kompatibilitas
> **Fokus**: Menjamin kestabilan server, tidak ada regresi, dan 100% kompatibel dengan Laravel Frontend.

- [x] **5.1. Swagger UI & OpenAPI Contract Test**
  - [x] Verifikasi schema OpenAPI JSON di `/openapi.json`.
  - [x] Pastikan seluruh tag, summary, dan response type muncul presisi di Swagger UI (`/docs`).
- [x] **5.2. Functional Endpoint Smoke Test**
  - [x] `GET /api/v1/health` → `200 OK`
  - [x] `GET /api/v1/auth/status` → `200 OK`
  - [x] `POST /api/v1/preprocess/single` & `batch` → `200 OK`
  - [x] `POST /api/v1/classify/single` & `batch` → `200 OK`
  - [x] `GET /api/v1/pipeline/exports` → `200 OK`
- [x] **5.3. Uji Validasi Input (Negative Tests)**
  - [x] Request batch dengan 501 item → harus return `422 Unprocessable Entity`.
  - [x] Request scrape dengan keyword `["   "]` → harus return `422 Unprocessable Entity`.
  - [x] Download file dengan payload `../../etc/passwd` → harus return `404/400 Path Invalid`.
- [x] **5.4. Uji Concurrency Lock Playwright**
  - [x] Dua `POST /api/v1/scrape/run` simultan dengan worker scraper terstub mengantre secara serial. Uji browser Playwright riil perlu dijalankan di lingkungan lokal.
- [x] **5.5. Uji Non-blocking Event Loop**
  - [x] Saat batch klasifikasi tiruan berjalan selama 250 ms, request `/api/v1/health` merespons dalam 6.15 ms.

🏁 **CHECKPOINT FINAL: [LOLOS / PASSED]**
- Seluruh checklist tercentang `[x]`.
- Verifikasi kode-level dan kontrak API selesai. Jalankan satu uji scraping Playwright dengan sesi riil di lingkungan lokal sebelum rilis produksi.

---

## 📌 Catatan Riwayat Perubahan (Changelog Kolaborasi)

| Tanggal | Pelaksana | Tahap / Modul | Catatan / Hasil Verifikasi |
| :---: | :--- :| :---: | :--- |
| *Inisialisasi* | Antigravity AI | Setup Roadmap | Dokumen checklist dibuat untuk panduan refaktorisasi. |
| 2026-09-22 | Antigravity AI | Tahap 1 (Schemas) | Ekstraksi auth_schema.py, validasi deklaratif Pydantic (max batch & non-empty keywords), re-export 25 schema di app.schemas. Pass Gate 1. |
| 2026-09-22 | Antigravity AI | Tahap 2 (Services) | Pembuatan job_service, auth_service, scraper_service, classification_service, pipeline_service, non-blocking ML inference (asyncio.to_thread), dan path traversal protection. Pass Gate 2. |
| 2026-09-22 | Antigravity AI | Tahap 3 (Routers) | Refaktorisasi 5 router menjadi Thin Controller: delegasi ke service layer, hapus _job_store & worker dari router, hapus validasi manual redundan. Perbaiki bug BackgroundTasks=None. 14 endpoint OpenAPI terverifikasi. Pass Gate 3. |
| 2026-09-22 | Codex | Tahap 4 (Entrypoint) | Menggunakan barrel `app.routers`; predictor lifespan diinjeksi eksplisit ke `ClassificationService` dan dibersihkan saat startup gagal/shutdown. Nama file modul dipertahankan, sehingga tidak ada legacy file untuk dihapus. |
| 2026-09-22 | Codex | Tahap 5 (Verification) | OpenAPI, Swagger, smoke test, validasi negatif, dua request scrape simultan dengan worker terstub, dan audit responsivitas event loop lulus. Uji Playwright dengan browser/sesi riil perlu dijalankan lokal sebelum rilis produksi. |
| 2026-09-22 | Antigravity AI | Bug Fix — Scraper Scroll Stage 1 | **Root Cause**: `STALE_LIMIT=8` terlalu kecil + `_keyword_matches_text` terlalu ketat (kartu kosong di-skip → stale count cepat naik → Stage 1 berhenti prematur → langsung masuk reverse sweep). **Perbaikan** di `threads_scraper.py` & `x_scraper.py`: (1) `STALE_LIMIT` 8→20, `SCROLL_STEP_PX` 600→850; (2) teks kosong dianggap relevan (kartu media dari search resmi); (3) tambah nudge scroll setiap kelipatan 3/4/8/14 stale count; (4) `_JS_EXTRACT_SEARCH_CARDS` diganti `closest('div[data-pressable-container="true"], article')` (Threads); (5) divider check threshold `step>=5`→`step>=10`; (6) tambah parameter `status_callback: Optional[Callable[[str], None]]` di semua fungsi publik + diteruskan ke `end_to_end_pipeline.py` & `scraper_service.py`. |
| 2026-09-22 | Antigravity AI | Code Review — Import Fix | Tambah `from typing import Callable, Optional` di `threads_scraper.py` & `x_scraper.py` — import hilang setelah penambahan `status_callback` type hint. Kedua file compile dengan `py_compile` code 0. |
| 2026-09-23 | GitHub Copilot | Verifikasi Repro — Stage 2 LULUS | Repro Stage 1+Stage 2 (keyword "korupsi", max_links=3, scroll=8) ke path output terpisah. **Hasil**: Stage 1 menemukan 3 URL dari 11 post terdeteksi; Stage 2 membuka ketiganya **via navigasi in-app pada percobaan pertama** (log: 3× `Navigasi in-app (router SPA Threads)...` → 3× `✓ N item terkumpul`) tanpa satu pun `melewati URL` atau fallback `goto`. **Distribusi**: `@aryandaharryy/post/Ddn6-qRI6-y` 5 baris, `@ipangwidjaja/post/Ddn7im8EXX0` 2 baris, `@ardhavia11/post/Ddn7mtfEusZ` 1 baris — masing-masing **tepat 1 "Original Post"**. **Isi**: seluruh baris relevan topik korupsi (mis. "Rakyat disuruh bayar pajak hanya untuk menggaji pejabat lali sisanya y di korupsi"). **5/5 kriteria LULUS** pada pemeriksa hasil repro, termasuk K4 "tidak ada `user_id` di >1 source" — kriteria yang **GAGAL pada CSV lama dengan 31 pelanggaran** (`poetra_1010`→2, `tribunnews`→3). Ditambah pembersihan noise UI `No replies yet` / `Belum ada balasan` dari `NOISE_EXACT` karena sebelumnya menempel di akhir `content`. |
| 2026-09-23 | GitHub Copilot | Diagnostik Stage 2 — VERDICT H2 | Diagnostik read-only pada permalink `@sindonews/post/DdnLWz0H3W9` memakai browser GUI dan profil sesi riil. **Hasil**: cookie `sessionid` `domain=.threads.com`; beranda `create=True profile=True` (LOGIN) → **H1 (sesi) GUGUR**. **Test A** `goto` permalink `.com`: HTTP **302** → `https://www.threads.com/?injected_media_ids=["3992209540662654397"]` → `pathHasPostId=False`, DOM = feed beranda (post dibuka DI DALAM feed). **Test B** `.net`: 301 → 302 → beranda (sama). **Test C** navigasi in-app (inject anchor + klik): `pathHasPostId=True`, `time[datetime]=2`, DOM = halaman post ("Thread · 2.3K views · sindonews") → **BERHASIL**. **Test D** `context.request.get(permalink)`: 200, body 277 KB tetapi tanpa id post/`<time>` (shell SPA). **Kesimpulan**: Threads membalas 302 untuk SETIAP navigasi top-level permalink; hanya navigasi in-app yang memuat halaman post. **Tindak lanjut**: urutan strategi `deep_crawl_post` dibalik menjadi **in-app lebih dulu** (`attempt ganjil`) dengan `page.goto` sebagai fallback (`attempt genap`), plus `_click_in_app_anchor()` terpisah dan percobaan klik dari beranda bila klik dari halaman saat ini gagal. Script diagnostik: verdict otomatis (H1/H2/H3/OK) + baca DB cookie sebelum browser dibuka (menghindari WinError 32). Uji guard tetap 4/4 lulus. |
| 2026-09-23 | GitHub Copilot | Hardening — Divider Guard di Extractor | `_JS_EXTRACT_COMMENTS` kini menolak semua container yang berada SETELAH elemen pembatas akhir thread (`_THREAD_DIVIDER_TEXTS`: "Related threads", "Postingan terkait", dll.) memakai `compareDocumentPosition`. Sebelumnya pembatas hanya dipakai untuk menghentikan scroll (`_check_thread_divider`), sedangkan ekstraksi JS tetap membaca seluruh dokumen — termasuk blok rekomendasi di bawahnya. Daftar teks pembatas kini satu sumber (`_THREAD_DIVIDER_TEXTS`) yang dipakai kedua fungsi. JS diverifikasi dengan `node --check`. |
| 2026-09-23 | GitHub Copilot | Bug Fix — Stage 2 Hijack ke Beranda | URL hasil Stage 1 tidak ter-crawl; browser terlihat "tertarik" ke beranda lalu deep crawl berjalan di beranda. **Bukti**: `threads_scraper_result.csv` memuat item dari puluhan akun tak terkait dengan `source` = URL post yang diminta, dan satu `user_id` muncul di dua `source` berbeda (feed yang sama dipanen 2x). **Root Cause kode**: guard identitas di `deep_crawl_post` hanya dievaluasi SEKALI tepat setelah `domcontentloaded`; Threads membounce permalink ke beranda SETELAH hydrate → guard lolos → `_slow_scan_comments` men-scan DOM beranda sedangkan `source` diisi `requested_url`. **Perbaikan** (`threads_scraper.py`): (1) `_verify_post_identity()` + `_JS_CHECK_POST_IDENTITY` diverifikasi 3 titik (setelah load, setelah hydrate 3s, dan berkala setiap `IDENTITY_CHECK_EVERY` step di dalam scan); (2) `Stage2IdentityLost` membatalkan seluruh data bila identitas hilang (anti salah-label); (3) `_JS_EXTRACT_COMMENTS` mengembalikan `owner_post_id`; `_collect_js_items` menolak item "Original Post" milik post lain; (4) `deep_crawl_post` mengembalikan `Stage2Outcome(data, skipped, reason)` dan memakai `_open_post_in_app()` (router SPA) sebagai strategi navigasi utama — urutan akhir (in-app dulu, `page.goto` fallback) ditetapkan setelah verdict diagnostik H2 pada entri berikutnya; (5) `ThreadsScrapeAborted` → gagal keras bila sesi tidak aktif atau SEMUA URL di-skip (sebelumnya "sukses 0 baris"); (6) `_check_logged_in_live()` + `_looks_like_login_wall()` menggantikan cek nama cookie; (7) RotatingFileHandler `logs/threads_scraper.log`; (8) `_dump_stage1_urls()` menulis `storage/exports/threads_stage1_urls.json`. **Sesi** (`session_manager.py`): `AUTH_COOKIE_HOSTS` — cookie auth wajib berlaku untuk host yang di-scrape (menangkap `sessionid` milik `.threads.net`/`.instagram.com`); `_has_auth_cookies` mengembalikan detail host. (`login_threads.py`): `LOGIN_URL` pindah ke `threads.com` + `_verify_login_on_threads()` memverifikasi login live di host tersebut. **Verifikasi**: uji guard identitas 4/4 skenario lulus; `py_compile` bersih; JS dirender & lolos `node --check`. **Perlu dijalankan lokal**: login ulang Threads pada host `threads.com`. |
