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
| 2026-09-24 | GitHub Copilot | Feature — Deteksi Mode GUI Login (noVNC vs Native) | Instruksi login scraper tidak lagi diasumsikan selalu noVNC. `AuthService.get_login_environment()` mengembalikan `{runtime, gui_mode, novnc_url, message}` dengan urutan keputusan: env `LOGIN_GUI_MODE` (di-set eksplisit `novnc` oleh `docker-compose.yml`, plus `NOVNC_PORT`/`NOVNC_PUBLIC_URL`) → `win32` = `native` → ada `DISPLAY`/`WAYLAND_DISPLAY` = `novnc` bila `/.dockerenv` ada, selain itu `native` → tidak ada display = `unavailable`. `check_display_available()` kini hanya menurunkan jawabannya dari method tersebut. `AllSessionsStatusResponse` menambah field `login_environment` (schema baru `LoginEnvironmentInfo`, ikut di-export di barrel `app/schemas/__init__.py`) sehingga frontend tahu tanpa menebak. Pesan `POST /auth/login-trigger/{platform}` kini kondisional: URL noVNC saat mode `novnc`, "jendela browser terbuka di desktop" saat `native`, dan 503 dengan saran yang sesuai runtime ("restart container" hanya bila benar-benar Docker) saat `unavailable`. Verifikasi lokal: `py_compile` 4 file bersih; probe 4 skenario → AUTO = `local/native`, forced `novnc` = URL terbentuk, forced `native`, forced `unavailable` → `check_display_available()=False`; payload `/auth/status` memuat `login_environment`. |
| 2026-09-24 | GitHub Copilot | Paket A — Pemisahan Teks Mentah vs Teks Bersih di Ekspor | `execute_preprocessing()` kini memanggil `transform_dataframe(..., overwrite_content=False)`: kolom `content` (teks mentah) TIDAK lagi ditimpa hasil preprocessing dan teks bersih masuk ke kolom baru `clean_text`; `to_csv()` otomatis memuat kedua kolom sehingga frontend dapat menyimpan `raw_content` (mentah) dan `clean_content` (bersih) secara terpisah. Agar akurasi model tidak berubah, `execute_classification()` mengirim `text_column="clean_text"` (fallback `content` bila kolom tidak ada). Sebelumnya teks mentah hilang dari ekspor sehingga UI menampilkan teks bersih sebagai “Konten Asli”. **Verifikasi baru**: `tests/check_pipeline_text_columns.py` (predictor di-stub — tidak memuat model 499MB) membuktikan content utuh, `clean_text` benar-benar bersih (URL/emoji/@mention/hashtag dibuang, case folding jalan), model menerima teks bersih, dan header CSV memuat kedua kolom; dijalankan **merah dulu** (5 kegagalan) lalu hijau via `python -m tests.check_pipeline_text_columns`. `main_cli.py:604` sengaja tidak diubah karena outputnya memang file `*_preprocessed.csv`. |
| 2026-09-25 | GitHub Copilot | Paket G-3 & G-4 — Penyeragaman Dokumentasi `src/` & Entrypoint | Lanjutan paket G. **(G-3)** `src/`: docstring modul menjadi 1 baris di `classification/model_loader.py` (35→1), `classification/predictor.py` (35→1), `preprocessing/cleaner.py` (33→1), `preprocessing/normalizer.py` (38→1), `preprocessing/pipeline.py` (38→1), `auth/session_manager.py` (16→1), `auth/login_x.py` (26→1), `auth/login_threads.py` (29→1), `pipeline/end_to_end_pipeline.py` (21→1), `utils/async_compat.py` (11→1). Seluruh docstring fungsi/method dipangkas menjadi 1 baris (`ClassificationHead`, `IndoBERTHierarchicalClassifier.forward`, `ModelLoader`, `HateSpeechPredictor` + 4 method, `clean_text`/`case_folding`/`remove_duplicates`, `load_slang_dictionary`/`SlangNormalizer.__init__`/`normalize`, `preprocess_text` + 4 method pipeline, 4 fungsi `session_manager`, `setup_x_login`, `_verify_login_on_threads`/`setup_threads_login`, 9 method `EndToEndPipeline` + 2 pembungkus sync/async, `ensure_proactor_loop`). **(G-4)** Entrypoint: `main_api.py` docstring modul 22→1, `lifespan` 9→1, `root` 2→1; `main_cli.py` docstring modul 13→1, `print_scraper_failure` 5→1, `_input_keywords_and_urls` 5→1. **Verifikasi**: `compileall` OK · `import main_api` OK (8 endpoint) · `import main_cli` OK · `unittest` **31 test hijau** · pemindaian AST atas 25 file `app/`+`configs/`+`src/`+entrypoint (183 fungsi/kelas) memastikan seluruh docstring modul 1 baris dan hanya 14 docstring fungsi yang > 1 baris — semuanya pengecualian yang disengaja berisi alasan teknis mahal (mis. `_evict_if_needed`, `_build_statistics`, `validate_platform_sessions`, `_slow_scan_comments`, `deep_crawl_post`). **G-5 (frontend)** dikerjakan pada `frontend/REFACTORING_CHECKLIST.md`. **Paket G SELESAI SELURUHNYA (G-1…G-5).** |
| 2026-09-25 | GitHub Copilot | Paket G-1 & G-2 — Penyeragaman Dokumentasi Kode | Gaya dokumentasi diseragamkan atas permintaan pemilik proyek: **satu keterangan pendek per modul/fungsi/method**; blok `Args:`/`Returns:`/`Raises:` dan narasi historis dihapus; banner seksi bergaya `# ----------` + judul pendek tetap dipertahankan. **(G-1)** `src/scraping/`: `threads_scraper.py` docstring modul 29→1 baris dan ±20 docstring fungsi dipangkas (mis. `deep_crawl_post` 30→6, `collect_post_urls` 12→1, `run_threads_scraper` 17→1, `_verify_post_identity` 13→1); `x_scraper.py` 4 docstring panjang dipangkas; `base_scraper.py` diberi docstring modul 1 baris + seluruh docstring menjadi 1 baris. Pengetahuan debugging yang bernilai (bounce 302 ke beranda, syarat identitas halaman, cookie harus berlaku untuk host threads.com) tetap disimpan **1 baris** masing-masing. **(G-2)** `app/routers/*`, `app/schemas/*`, `app/services/*`, dan `configs/config.py`: seluruh docstring modul menjadi 1 baris (`api_pipeline` 15→1, `classification_schema` 14→1, `auth_schema` 7→1, `job_service` 6→1, `pipeline_service` 6→1, dst.) dan docstring panjang dipangkas. **Verifikasi tiap batch**: `compileall` OK · `import main_api` OK (8 endpoint) · `import main_cli` OK · suite `unittest` **31 test hijau** · pencarian AST memastikan docstring modul ≤ 1 baris dan tidak ada sisa docstring panjang selain 3 pengecualian yang disengaja (berisi alasan teknis 3-4 baris: `get_login_environment`, `_evict_if_needed`, `_build_statistics`). **Catatan operasional**: `app/services/scraper_service.py` dihapus ulang karena muncul kembali di working tree (tab editor yang masih terbuka ikut ter-save saat file sudah dihapus) — file itu memang rusak sebab mengimpor `app.schemas.scraper_schema` yang sudah dihapus (`ModuleNotFoundError` terbukti saat diimpor). **Sisa paket G**: G-3 `src/` lainnya (model_loader, predictor, preprocessing, auth, end_to_end_pipeline, async_compat), G-4 entrypoint (`main_api.py`, `main_cli.py`), G-5 frontend (controllers/services/requests). |
| 2026-09-25 | GitHub Copilot | Paket F4 Tahap 2 — Penghapusan Endpoint Tanpa Konsumen | Sesuai keputusan pemilik proyek ("hapus misal tidak dipakai"): permukaan REST API dipangkas dari 15 menjadi **8 endpoint**. Dihapus: `GET /api/v1/classify/info`, `POST /api/v1/classify/batch`, `POST /api/v1/preprocess/single\|batch`, `POST /api/v1/scrape/run`, `GET /api/v1/scrape/status/{job_id}`, dan `GET /api/v1/pipeline/exports` (daftar berkas). **Bukti sebelum menghapus**: pemindaian seluruh repo menunjukkan nol pemanggil di luar backend (frontend Laravel hanya memakai 7 endpoint, `main_cli.py` tidak memakai HTTP sama sekali), seluruh file kandidat ter-track git (dapat dipulihkan), dan **`docker-compose.yml:92` memakai `curl -f http://localhost:8080/` sebagai healthcheck → endpoint root `/` DIPERTAHANKAN** meski sempat masuk daftar kandidat. **File yang dihapus**: `app/routers/api_scraper.py`, `app/routers/api_preprocessing.py`, `app/services/scraper_service.py`, `app/schemas/scraper_schema.py`, `app/schemas/preprocessing_schema.py` + pembersihan barrel `app/routers/__init__.py`, `app/services/__init__.py`, `main_api.py`, serta schema `ModelInfoResponse`/`ClassifyBatchRequest`/`ClassifyBatchResponse`/`ExportFileItem`/`ExportListResponse` yang hanya melayani endpoint tersebut. Perbaikan F1.2 (NaN → teks kosong) dan sisi `ScraperService` pada F4.1 ikut tidak relevan karena kodenya dihapus — test terkait disesuaikan/dihapus, bukan dibiarkan menggantung. **Verifikasi**: suite `unittest` **31 test hijau** (0 gagal / 0 error); `compileall` bersih; `import main_api` OK dengan **8 endpoint**; `import main_cli` OK; guard `check_pipeline_text_columns` LULUS; pemindaian ulang memastikan nol file & nol referensi tersisa; Pylance 12 file bersih. **Dokumentasi**: tabel endpoint `backend/README.md` diperbaiki (peta struktur direktori juga keliru menyebut `pipeline_schema.py`, `settings.py`, `browser_context.py` yang tidak pernah ada) dan `README.md` root yang masih mencatat 4 endpoint hantu; test `tests/test_paket_f4.py` kini menjaga agar endpoint hantu tidak muncul kembali di kedua README. **Catatan**: angka milestone "14 endpoint" pada Tahap 5 di atas digantikan 8 endpoint. |
| 2026-09-25 | GitHub Copilot | Paket F4 Tahap 1 — Kebijakan Sesi Tunggal & Perbaikan Dokumentasi Endpoint | **(F4.1) Duplikasi kebijakan validasi sesi dihapus**: aturan "platform mana yang wajib sesi valid" dulu tersimpan dua kali (`ScraperService.validate_session_for_platform` untuk `/api/v1/scrape/run` dan `EndToEndPipeline.validate_sessions` untuk pipeline). Kini ada satu fungsi bersama `validate_platform_sessions()` di `src/pipeline/end_to_end_pipeline.py`; kedua pemakai mendelegasikan sehingga aturan & pesannya tidak bisa menyimpang lagi. Pesannya seragam dan actionable (menyebut `POST /api/v1/auth/login-trigger/...`) untuk kedua jalur. **(F4.2) Kerja sia-sia dihapus**: `validate_sessions()` tidak lagi memanggil `get_all_sessions_status()` (scan filesystem dua profil setiap eksekusi pipeline) dan berhenti mengembalikan key `statuses` yang tidak pernah dibaca pemakainya — nilai balik kini hanya `{"valid", "errors"}`. **(F4.3) Dokumentasi endpoint diperbaiki**: bagian "Daftar REST API Endpoints" di `backend/README.md` mencatat endpoint **hantu** yang tidak ada di kode (`/api/v1/preprocess/clean`, `/api/v1/scrape/x`, `/api/v1/scrape/threads`, `/api/v1/scrape/status/{task_id}`, `/api/v1/auth/sessions`, `/api/v1/auth/login-browser`) sekaligus melewatkan 6 endpoint nyata. Diganti tabel akurat berisi seluruh 15 endpoint dengan kolom **Konsumen** (Frontend Laravel / ⚠️ belum ada) plus catatan bahwa `main_cli.py` tidak memakai HTTP sama sekali. **Verifikasi**: test baru `tests/test_paket_f4.py` (7 test) dijalankan **MERAH DULU** (7 gagal) lalu hijau; suite total **35 test hijau**; `compileall` bersih; `import main_api` OK (15 endpoint); kecocokan README vs OpenAPI diperiksa otomatis (0 endpoint nyata yang belum terdokumentasi, 0 endpoint hantu). **Keputusan tertunda**: penghapusan fisik endpoint tanpa konsumen (bertanda ⚠️ di tabel README) menunggu persetujuan pemilik proyek. |
| 2026-09-25 | GitHub Copilot | Paket F3 — Konsolidasi Duplikasi & Konsistensi CLI/API | **(F3.1) Duplikasi 100% dihapus**: `is_system_text` dan `_keyword_matches_text` yang identik di `x_scraper.py` & `threads_scraper.py` dipindah ke `src/scraping/base_scraper.py` sebagai `is_system_text(content, phrases)` + `keyword_matches_text(text, keywords)`; tiap scraper mempertahankan wrapper satu-argumen (`is_system_text(content)`) supaya call site lama tidak perlu diubah. **(F3.2) Browser factory tunggal**: `_create_browser()` + `STEALTH_ARGS` + `STEALTH_SCRIPT` yang terduplikasi di `login_x.py` & `login_threads.py` (94,4% identik, beda hanya formatting) dihapus — keduanya kini memakai `create_browser()` dari `base_scraper.py`. **(F3.3) SoC preprocessing**: `ClassificationService` menambah API publik `preprocess_text()`/`preprocess_texts()`; `api_preprocessing.py` tidak lagi mengakses atribut privat `_preprocessor` dan endpoint batch memakai satu pass. **(F3.4) Konsistensi kolom teks CLI vs API**: resolver baru `resolve_text_column(df)` di `end_to_end_pipeline.py` dipakai bersama oleh `execute_classification()` dan menu klasifikasi CLI — sebelumnya CLI memilih `content` (mentah) sedangkan API memilih `clean_text` (bersih), sehingga berkas & keyword yang sama bisa menghasilkan label/confidence berbeda; menu preprocessing CLI kini menyatakan `overwrite_content=True` secara eksplisit (tidak lagi bergantung nilai default). **(F3.5) Dead config dihapus**: `SCRAPER_CONFIG["headless"]` yang tidak pernah dibaca siapa pun, disertai komentar bahwa headless dikendalikan per-request sedangkan proses login selalu GUI. **Verifikasi**: suite `unittest` **28 test hijau** (F1 12 + F2 5 + F3 11) — F3 dijalankan **MERAH DULU** (9 dari 11 gagal) lalu hijau; `compileall` bersih; `import main_api` OK dengan 15 endpoint; `import main_cli` OK; guard `check_pipeline_text_columns` LULUS; uji identitas objek fungsi membuktikan X & Threads benar-benar memakai implementasi yang sama; pemindaian ulang memastikan nol sisa definisi duplikat. **Catatan**: alur login riil (Chromium + sesi tersimpan) belum dijalankan pada langkah ini — perbedaan implementasi lama hanya formatting dan konsolidasinya diverifikasi lewat uji identitas + import. |
| 2026-09-25 | GitHub Copilot | Pembersihan Barrel Schema (Dead Re-export) | Sesuai keputusan pemilik proyek: barrel `app/schemas/__init__.py` yang mengekspor 26 nama lewat `__all__` **dihapus** karena dead code — pencarian menunjukkan 0 kemunculan `from app.schemas import ...` di seluruh backend, semua pemakai mengimpor langsung dari modul subdomainnya (mis. `from app.schemas.scraper_schema import ScrapeRequest`). Isi file kini hanya catatan arsitektur + `__all__: list[str] = []`. Verifikasi: `compileall` bersih, `import main_api` OK (15 endpoint), suite 28 test hijau. |
| 2026-09-25 | GitHub Copilot | Paket F2 — Pembersihan Dead Code & Hygiene | **Symbol mati yang dihapus** (semuanya diverifikasi hanya punya satu kemunculan = definisinya, bukan asumsi): `AuthService.check_display_available()` + import `Tuple` (`get_login_environment()` sudah menjadi sumber kebenaran mode GUI); `_cookie_hosts_for()` di `src/auth/login_threads.py` (fungsi `_host_is_allowed()` TETAP dipertahankan karena masih dipakai di baris 259 & 282); `import json` tak terpakai di blok `__main__` `session_manager.py`; `LOGGED_IN_SELECTORS` yang diimpor di dalam `run_x_scraper` tetapi tidak dipakai (verifikasi sesi X memakai cookie `auth_token`/`twid`); `Union` di `end_to_end_pipeline.py`; `SCRAPER_CONFIG` di `login_threads.py` & `login_x.py`. **Routers**: `import logging` + `logger = logging.getLogger(__name__)` yang tidak pernah dipakai dihapus dari `api_scraper.py` dan `api_pipeline.py`. **main_cli.py**: `Confirm`, `ModelLoader`, `STORAGE_DIR` (import mati), import ganda (`from configs.config import MODEL_DIR`, `import os`, `import pandas as pd`) dikonsolidasikan ke header, variabel perantara `show_prog` di-inline, dan komentar usang "Placeholder Menu Modul Lain (Akan dihubungkan saat modul dibuat)" diganti header yang sesuai isinya. **api_auth.py**: signature `trigger_login()` dirapikan menjadi `background_tasks: BackgroundTasks` di posisi pertama (tanpa default dummy `BackgroundTasks()`) — perilaku identik, gaya kanonik FastAPI. **Verifikasi**: suite `unittest` **17 test hijau** (F1 12 + F2 5); `compileall` seluruh file `.py` bersih; `import main_api` OK dengan **15 endpoint OpenAPI**; `import main_cli` OK; guard `tests.check_pipeline_text_columns` LULUS; pemindaian ulang memastikan nol jejak simbol yang dihapus. Test baru `tests/test_paket_f2.py` mengunci inventaris endpoint (kontrak dengan `FastAPIClientService.php`) dan mencegah `check_display_available` dihidupkan kembali. **Catatan temuan**: `/docs`, `/redoc`, `/openapi.json` tidak muncul di `app.openapi()["paths"]` (bukan operasi API) sehingga diverifikasi terpisah lewat `app.routes`. **Belum diputuskan (menunggu pemilik proyek)**: barrel `app/schemas/__init__.py` (26 nama + `__all__`) tidak diimpor siapa pun — opsi: dipakai di seluruh import (sesuai rencana Tahap 1.6) atau dihapus. |
| 2026-09-25 | GitHub Copilot | Paket F1 — Keamanan Data & Ketahanan Service Layer | **(F1.1) Eviction JobManager** (`app/services/job_service.py`): eviction kini HANYA membuang job berstatus `success`/`error`; job `queued`/`running` tidak pernah dibuang lagi (fallback FIFO lama bisa menghapus job yang masih berjalan → endpoint status 404 → frontend menandai analisis FAILED permanen padahal worker masih bekerja). Bila seluruh slot terisi job aktif, kapasitas dibiarkan terlampaui sementara + log warning. **(F1.2) NaN → teks kosong** (`app/services/scraper_service.py`): `str(row.get(...))` yang mengubah NaN menjadi literal `"nan"` (karena `bool(nan) is True`) diganti helper `_cell_to_text()`, dan serialisasi dipindah ke `_serialize_dataframe()` agar bisa diuji. **(F1.3) Statistik pipeline toleran data parsial** (`app/services/pipeline_service.py`): akses langsung `v["count"]`/`v["hate_pct"]` diganti `_build_statistics()` berbasis `.get()`; `non_hate_speech` & `hate_pct` yang tidak dikirim backend diturunkan dari `total`/`hate_speech` sehingga invarian hate+non_hate=total tetap terjaga. Sebelumnya `KeyError` tertangkap `except` generik → pipeline yang SUKSES dilaporkan gagal dengan pesan "error tidak terduga". **(F1.4) Cabang mati dihapus**: fallback `except AttributeError` pada `get_safe_export_path()` (tidak mungkin dieksekusi di Python ≥3.9 dan logikanya lebih lemah dari `is_relative_to`). **(F1.5) Pydantic v2**: 6 blok `class Config` → `model_config = ConfigDict(json_schema_extra=...)` (classification_schema ×3, preprocessing_schema ×2, scraper_schema ×1) — menghapus `PydanticDeprecatedSince20` yang akan breaking di Pydantic v3. **Verifikasi**: suite baru `tests/test_paket_f1.py` (stdlib `unittest`, 12 test) dijalankan **MERAH DULU** (4 gagal: job aktif terbuang, NaN, statistik, deprecation) lalu **HIJAU 12/12**; `python -m compileall` bersih; `import main_api` sukses dengan OpenAPI **15 path** lengkap; guard lama `python -m tests.check_pipeline_text_columns` tetap LULUS. Catatan: `pytest` tidak terpasang di environment proyek → test memakai `unittest` bawaan (`python -m unittest tests.test_paket_f1`). |
| 2026-09-23 | GitHub Copilot | Bug Fix — Stage 2 Hijack ke Beranda | URL hasil Stage 1 tidak ter-crawl; browser terlihat "tertarik" ke beranda lalu deep crawl berjalan di beranda. **Bukti**: `threads_scraper_result.csv` memuat item dari puluhan akun tak terkait dengan `source` = URL post yang diminta, dan satu `user_id` muncul di dua `source` berbeda (feed yang sama dipanen 2x). **Root Cause kode**: guard identitas di `deep_crawl_post` hanya dievaluasi SEKALI tepat setelah `domcontentloaded`; Threads membounce permalink ke beranda SETELAH hydrate → guard lolos → `_slow_scan_comments` men-scan DOM beranda sedangkan `source` diisi `requested_url`. **Perbaikan** (`threads_scraper.py`): (1) `_verify_post_identity()` + `_JS_CHECK_POST_IDENTITY` diverifikasi 3 titik (setelah load, setelah hydrate 3s, dan berkala setiap `IDENTITY_CHECK_EVERY` step di dalam scan); (2) `Stage2IdentityLost` membatalkan seluruh data bila identitas hilang (anti salah-label); (3) `_JS_EXTRACT_COMMENTS` mengembalikan `owner_post_id`; `_collect_js_items` menolak item "Original Post" milik post lain; (4) `deep_crawl_post` mengembalikan `Stage2Outcome(data, skipped, reason)` dan memakai `_open_post_in_app()` (router SPA) sebagai strategi navigasi utama — urutan akhir (in-app dulu, `page.goto` fallback) ditetapkan setelah verdict diagnostik H2 pada entri berikutnya; (5) `ThreadsScrapeAborted` → gagal keras bila sesi tidak aktif atau SEMUA URL di-skip (sebelumnya "sukses 0 baris"); (6) `_check_logged_in_live()` + `_looks_like_login_wall()` menggantikan cek nama cookie; (7) RotatingFileHandler `logs/threads_scraper.log`; (8) `_dump_stage1_urls()` menulis `storage/exports/threads_stage1_urls.json`. **Sesi** (`session_manager.py`): `AUTH_COOKIE_HOSTS` — cookie auth wajib berlaku untuk host yang di-scrape (menangkap `sessionid` milik `.threads.net`/`.instagram.com`); `_has_auth_cookies` mengembalikan detail host. (`login_threads.py`): `LOGIN_URL` pindah ke `threads.com` + `_verify_login_on_threads()` memverifikasi login live di host tersebut. **Verifikasi**: uji guard identitas 4/4 skenario lulus; `py_compile` bersih; JS dirender & lolos `node --check`. **Perlu dijalankan lokal**: login ulang Threads pada host `threads.com`. |
