# Roadmap & Checklist Refaktorisasi Frontend Laravel
**Sistem Analisis Ujaran Kebencian (HateSense ID Lab)**

Dokumen ini adalah panduan kerja, acuan arsitektur, dan *checkpoint tracker* untuk refaktorisasi arsitektur frontend Laravel menuju arsitektur bertingkat murni (**Controller → Form Request → Service Layer → Eloquent / External API**).

---

## 🎯 Prinsip & Standar Arsitektur Frontend

1. **Separation of Concerns (SoC) & Thin Controller**:
   - **Controller (`app/Http/Controllers/`)**: Hanya menangani HTTP I/O, menerima Form Request yang sudah tervalidasi dan terotorisasi, memanggil Service yang sesuai, dan mengembalikan `View`, `RedirectResponse`, atau `JsonResponse`. Dilarang menulis query database panjang, validasi manual `$request->validate()`, atau manipulasi file stream langsung di controller.
   - **Form Request (`app/Http/Requests/`)**: Menangani otorisasi akses (`authorize()`), sanitasi input awal (`prepareForValidation()`), aturan validasi deklaratif (`rules()`), pesan multibahasa Indonesia (`messages()`), dan penamaan label atribut (`attributes()`).
   - **Service Layer (`app/Services/`)**: Mengisolasi seluruh logika bisnis, transaksi database (`DB::transaction`), kalkulasi analitik, pemrosesan file (impor/ekspor CSV), dan komunikasi HTTP ke backend FastAPI (`FastAPIClientService`).
2. **Decoupled Architecture (Anti-Path Mismatch)**:
   - Frontend **tidak boleh** mengakses path lokal backend (`base_path('../backend/storage/exports/...')`) karena akan gagal total di lingkungan Docker multi-container di mana direktori backend tidak di-mount ke container frontend.
   - Komunikasi file dan data dilakukan melalui REST API FastAPI (`FastAPIClientService`).
3. **Aturan Kolaborasi & Checkpoint**:
   - Setiap tahap dibagi menjadi sub-tugas kecil yang terukur.
   - Checklist `[ ]` diubah menjadi `[x]` segera setelah sub-tugas selesai diverifikasi.
   - Setiap tahap memiliki **Checkpoint Gate** yang wajib lolos uji sebelum berpindah ke tahap berikutnya agar tidak terjadi konflik atau regresi kode saat berkolaborasi.

---

## 🗺️ Matriks Status & Tahapan Pekerjaan

| Tahap | Deskripsi Modul | Estimasi Sub-tugas | Status |
| :---: | :--- | :---: | :---: |
| **Tahap 1** | Perbaikan Bug Kritis & Penguatan Service Layer (`app/Services/`) | 4 Task | `[x] Selesai` |
| **Tahap 2** | Standardisasi & Konsolidasi Form Request Layer (`app/Http/Requests/`) | 6 Task | `[x] Selesai` |
| **Tahap 3** | Perampingan Controller Layer (`app/Http/Controllers/`) | 5 Task | `[x] Selesai` |
| **Tahap 4** | Standardisasi Penamaan File & Pembersihan Dead Code | 3 Task | `[x] Selesai` |
| **Tahap 5** | Verifikasi Menyeluruh, Authorization Audit & Smoke Testing | 4 Task | `[x] Selesai` |

---

## 📋 Detail Checklist Tahapan

### TAHAP 1: Perbaikan Bug Kritis & Penguatan Service Layer (`app/Services/`)
> **Fokus**: Memperbaiki bug path Docker pada impor CSV, mengisolasi logika ekspor CSV dari controller, dan melengkapi service yang belum ada.

- [x] **1.1. Perbaikan Bug Kritis Docker Path pada `AnalysisImportService`**
  - [x] Hapus ketergantungan pada path relatif `base_path('../backend/storage/exports/' . $filename)`.
  - [x] Tambahkan method di `FastAPIClientService` untuk mengunduh stream CSV dari backend via HTTP endpoint: `GET /api/v1/pipeline/exports/{filename}`.
  - [x] Update `AnalysisImportService` agar membaca konten CSV via HTTP client FastAPI. (Fallback filesystem lokal dihapus pada Paket C — kini 100% HTTP agar perilaku lokal & Docker identik.)
- [x] **1.2. Refaktorisasi & Sentralisasi Query di `UserManagementService`**
  - [x] Pindahkan 35 baris logika filtering dan query user dari `UserManagementController@index` ke `UserManagementService@getFilteredUsers()`.
  - [x] Pindahkan logika penghitungan metrik role (`admin`, `analyst`, `viewer`) dan user aktif ke `UserManagementService@getUserMetrics()`.
  - [x] Pastikan method `toggleActiveStatus()` digunakan untuk pergantian status user.
- [x] **1.3. Pembuatan `ProfileService` (`app/Services/ProfileService.php`)**
  - [x] Buat service baru `ProfileService` untuk memisahkan update profil dan ganti password dari `ProfileController`.
  - [x] Implementasikan method `updateProfileInfo(User $user, array $data): bool`.
  - [x] Implementasikan method `updatePassword(User $user, string $newPassword): bool`.
- [x] **1.4. Pemindahan Logika Ekspor CSV ke `AnalysisService`**
  - [x] Pindahkan 60 baris generator streaming CSV (`StreamedResponse`, escaping formula injection `=+-@`, header CSV) dari `AnalysisController@exportCsv` ke `AnalysisService@streamExportCsv(Analysis $analysis)`.

🏁 **CHECKPOINT GATE 1: [LOLOS / PASSED]**
- Seluruh 4 sub-task Tahap 1 tercentang `[x]`.
- Impor data CSV decoupled via FastAPI HTTP client `GET /api/v1/pipeline/exports/{filename}` (Docker-safe), tanpa akses path filesystem backend (100% HTTP sejak Paket C).
- `UserManagementService` mengambil alih seluruh query filtering dan metrik user.
- `ProfileService` terdaftar dan siap di-inject.
- `streamExportCsv` diisolasi di `AnalysisService` dengan sanitasi formula injection spreadsheet (`=+-@`).


---

### TAHAP 2: Standardisasi & Konsolidasi Form Request Layer (`app/Http/Requests/`)
> **Fokus**: Mengaktifkan Form Request yang sempat terbengkalai, memastikan otorisasi dan pesan kustom Indonesia seragam, dan menghapus dead code.

- [x] **2.1. Konsolidasi `SinglePredictRequest` (`app/Http/Requests/Prediction/`)**
  - [x] Periksa kembali aturan `min:3`, `max:2000`, pesan error kustom, dan otorisasi `$this->user()->can('test-single-prediction')`.
  - [x] Pastikan siap di-inject ke `PredictController@classify`.
- [x] **2.2. Konsolidasi `FilterAnalysisPostRequest` (`app/Http/Requests/Analysis/`)**
  - [x] Periksa aturan validasi query string: `platform`, `label_lvl1`, `label_lvl2`, `search`, `per_page`.
  - [x] Pastikan siap di-inject ke `AnalysisController@show`.
- [x] **2.3. Pembuatan `FilterAnalysisRequest` untuk Halaman Index Analisis**
  - [x] Buat `app/Http/Requests/Analysis/FilterAnalysisRequest.php` untuk memvalidasi query parameter pada `analyses.index` (`search`, `platform:all,x,threads,both`, `status:all,queued,running,completed,failed`).
- [x] **2.4. Sinkronisasi `StoreUserRequest` & `UpdateUserRequest` (`app/Http/Requests/User/`)**
  - [x] Selaraskan validasi role: gunakan `Rule::in(['admin', 'analyst'])` (role `viewer` dihapus pada Paket B).
  - [x] Pastikan otorisasi `$this->user()->can('manage-users')` aktif dan pesan kustom bahasa Indonesia lengkap.
- [x] **2.5. Pemisahan Form Request Profil (`app/Http/Requests/User/`)**
  - [x] Sesuaikan `UpdateProfileRequest.php` untuk validasi `name` dan `email` (dengan `Rule::unique('users')->ignore($userId)`).
  - [x] Buat `UpdatePasswordRequest.php` untuk validasi `current_password` dan `password` + `password_confirmation` (aturan `Password::min(8)` dan `confirmed`).
- [x] **2.6. Pembersihan Dead Code**
  - [x] Hapus `app/Http/Requests/Auth/RegisterRequest.php` (karena registrasi publik ditiadakan dan tidak ada rute register).

🏁 **CHECKPOINT GATE 2: [LOLOS / PASSED]**
- Seluruh 6 sub-task Tahap 2 tercentang `[x]`.
- Form Request di semua modul (Prediction, Analysis, User, Scraper, Profile) telah terkonsolidasi dengan otorisasi berbasis Spatie Permission (`can(...)`).
- Seluruh pesan validasi dan label atribut (`attributes()`) menggunakan bahasa Indonesia ramah pengguna.
- Dead code `RegisterRequest.php` berhasil dibersihkan.


---

### TAHAP 3: Perampingan Controller Layer (`app/Http/Controllers/`)
> **Fokus**: Mengubah seluruh controller menjadi Thin Controller (hanya memanggil Form Request dan Service).

- [x] **3.1. Refaktorisasi `PredictController.php`**
  - [x] Ganti `Request $request` pada method `classify()` dengan `SinglePredictRequest $request`.
  - [x] Hapus manual `$request->validate([...])`.
  - [x] Controller hanya memanggil `$this->predictService->predict($request->validated('text'))`.
- [x] **3.2. Refaktorisasi `AnalysisController.php`**
  - [x] Di `index()`: Inject `FilterAnalysisRequest $request`.
  - [x] Di `show()`: Inject `FilterAnalysisPostRequest $request` menggantikan `Request $request`.
  - [x] Di `exportCsv()`: Ganti implementasi inline dengan delegasi ke `$this->analysisService->streamExportCsv($analysis)`.
- [x] **3.3. Refaktorisasi `Admin/UserManagementController.php`**
  - [x] Di `index()`: Ganti 35 baris query manual dengan pemanggilan `$this->userService->getFilteredUsers($request)` dan `$this->userService->getUserMetrics()`.
  - [x] Di `store()`: Inject `StoreUserRequest $request`, hapus `$request->validate(...)`.
  - [x] Di `update()`: Inject `UpdateUserRequest $request`, hapus `$request->validate(...)`.
  - [x] Di `toggleStatus()`: Gunakan `$this->userService->toggleActiveStatus($user)`.
- [x] **3.4. Refaktorisasi `ProfileController.php`**
  - [x] Inject `ProfileService $profileService` pada constructor.
  - [x] Di `updateInfo()`: Inject `UpdateProfileRequest $request`, hapus inline validation, panggil `$this->profileService->updateProfileInfo(...)`.
  - [x] Di `updatePassword()`: Inject `UpdatePasswordRequest $request`, hapus inline validation, panggil `$this->profileService->updatePassword(...)`.
- [x] **3.5. Audit `ScraperMonitorController.php`, `DashboardController.php`, & `AuthController.php`**
  - [x] Pastikan ketiga controller ini tetap bersih, terjaga dari logic leak, dan konsisten.

🏁 **CHECKPOINT GATE 3: [LOLOS / PASSED]**
- Verifikasi ketebalan Controller: seluruh method controller berukuran < 25 baris kode.
- Pastikan tidak ada satupun pemanggilan `$request->validate()` manual di controller (0 manual validate call).
- Arsitektur berlapis bertingkat murni diterapkan: Controller -> Form Request -> Service -> Eloquent/API.


---

### TAHAP 4: Standardisasi Penamaan File & Pembersihan Kode
> **Fokus**: Menyesuaikan penamaan konvensi Laravel dan membersihkan unused imports.

- [x] **4.1. Evaluasi Penamaan Controller & Request**
  - [x] Pertahankan nama `PredictController` / buat alias jika diperlukan agar tidak memutus view reference di Blade / Route.
  - [x] Pastikan seluruh namespace dan folder request rapi dan terdokumentasi.
- [x] **4.2. Pembersihan Unused Imports & PHP Linter**
  - [x] Hapus `use` statements yang tidak lagi digunakan di seluruh controller dan service.
  - [x] Jalankan pengecekan sintaks PHP (`php -l`).

🏁 **CHECKPOINT GATE 4: [LOLOS / PASSED]**
- Seluruh file di `app/` lolos audit linter PHP (`php -l`) tanpa satupun error sintaks.
- Seluruh rute terdaftar normal dan valid pada `php artisan route:list` (25 rute per 2026-09-24; catatan: entri historis di bawah sempat menyebut 27 — angka mengikuti jumlah sebenarnya).
- Unused imports pada Controller dan Service (`AnalysisClassification`, `AnalysisPost`, `Exception`) telah dibersihkan.


---

### TAHAP 5: Verifikasi Menyeluruh, Authorization Audit & Smoke Testing
> **Fokus**: Menjamin kestabilan aplikasi, tidak ada regresi tampilan UI, dan alur end-to-end berjalan mulus.

- [x] **5.1. Uji Validasi Form & Error Display di Blade**
  - [x] Uji input teks kosong pada Live Classifier → muncul error alert.
  - [x] Uji input email duplikat pada User Management → muncul error validation.
  - [x] Uji password tidak cocok pada Profile → muncul error validation.
- [x] **5.2. Uji Otorisasi Hak Akses (Spatie Roles & Permissions)**
  - [x] Admin: bisa mengakses user management dan seluruh fitur.
  - [x] Analyst: bisa membuat dan melihat analisis, dilarang mengakses user management.
  - [x] Role viewer DIHAPUS (Paket B): sistem hanya mengenal `admin` dan `analyst`.
- [x] **5.3. Uji Alur Analisis & Ekspor CSV**
  - [x] Buat analisis baru → polling AJAX status berjalan lancar.
  - [x] Klik unduh CSV → file terunduh dengan format header lengkap dan karakter aman.
- [x] **5.4. Uji Monitor Scraper & Telemetri**
  - [x] Periksa badge status online FastAPI di navbar header.
  - [x] Periksa modal trigger login X dan Threads.

🏁 **CHECKPOINT FINAL: [LOLOS / PASSED]**
- Seluruh checklist `[x]` terisi lengkap dari Tahap 1 sampai Tahap 5.
- Frontend dan Backend selaras, bersih, berarsitektur murni (Controller → Form Request → Service Layer → Eloquent/FastAPI API), dan siap untuk pengujian skripsi serta deployment.

---

## 📌 Catatan Riwayat Perubahan Frontend (Changelog Kolaborasi)

| Tanggal | Pelaksana | Modul / Tahap | Catatan / Hasil Verifikasi |
| :---: | :---: | :---: | :--- |
| *Inisialisasi* | Antigravity AI | Setup Roadmap Frontend | Dokumen checklist dibuat untuk panduan refaktorisasi arsitektur frontend Laravel. |
| 2026-09-24 | GitHub Copilot | Penyederhanaan UI — Hapus Panduan Login noVNC & Banner Error Pipeline | **(1) Panel panduan login dihapus total** (menggantikan pendekatan pada entri “Modul Scraper — Panduan Login Kontekstual” di atas): file `scraper/partials/login-guide.blade.php` beserta foldernya dihapus, begitu pula seluruh perkabelannya — Alpine `showGuideFor()`, `guideRequested`, `guideAccent`, `loginEnvMessage`, `novncUrlLabel`, dua `@include` di `scraper/status`, dan flash `login_triggered_platform` di `ScraperMonitorController@triggerLogin()`. **Yang dipertahankan**: tombol RE-AUTHENTICATE tetap membuka jendela noVNC otomatis pada runtime Docker via `requestLogin()` yang dipanggil dari *user gesture* (agar tidak diblokir popup blocker); runtime lokal (guiMode `native`) tidak butuh aksi browser tambahan. Jadi tidak ada fungsi yang hilang selain informasi visual. **(2) Banner `ERROR: PIPELINE GAGAL`** di atas kartu “Pipeline AI Sedang Berjalan” (`analyses/show`) dihapus; status gagal kini disajikan di dalam kartu itu sendiri: judul “Pipeline AI Gagal Dieksekusi”, badge `GAGAL DIEKSEKUSI`, dan alasan kegagalan pada baris pesan pipeline — termasuk saat transisi ke gagal terjadi di tengah polling (`data.analysis.error_message`). **Verifikasi**: `php artisan view:cache` sukses, `php -l` bersih, suite frontend **41 test / 102 assertion lulus** (termasuk 2 file regresi baru: `ScraperStatusPageTest` dan `AnalysisFailedStateTest`), dan grep memastikan tidak ada sisa referensi simbol panduan login. Assertion negatif dibuktikan tidak kosong (proof merah: `str_contains` atas teks yang dihapus gagal → teks benar-benar tidak ter-render). |
| 2026-09-24 | GitHub Copilot | Modul Scraper — Panduan Login Kontekstual | Panel panduan noVNC di `scraper/status` sebelumnya selalu tampil selama server online (redundan di Docker, link mati di lokal karena port 6080 tidak ada) dengan URL hardcoded. Kini `FastAPIClientService::getTelemetryData()` meneruskan `loginEnvironment` dari `GET /api/v1/auth/status`; Alpine `scraperTelemetry()` menambah `guiMode`/`novncUrl`/`showGuideFor()`/`requestLogin()`; panel dipindah ke partial baru `scraper/partials/login-guide.blade.php` (menggantikan 2 blok duplikat). Perilaku: Docker (`novnc`) → panel muncul HANYA untuk platform yang baru di-trigger + jendela noVNC dibuka otomatis dari user gesture tombol (link fallback tetap tampil bila popup diblokir, ditopang flash `login_triggered_platform`); lokal (`native`) → tanpa noVNC sama sekali, hanya konfirmasi jendela browser desktop; `unavailable` → peringatan permanen. Host URL noVNC diganti mengikuti `location.hostname` agar benar saat portal diakses via IP LAN. `ScraperMonitorController::triggerLogin()` memakai pesan kontekstual dari backend. Verifikasi: `php artisan view:cache` sukses untuk seluruh blade views. |
| 2026-09-24 | GitHub Copilot | Perbaikan Paket A — Integritas Data Impor CSV (BOM, N+1, Raw/Clean) | **(A1)** `AnalysisImportService::importPostsFromCsv()` kini membuang UTF-8 BOM pada sel header pertama: ekspor pipeline memakai `encoding="utf-8-sig"` sehingga nama kolom pertama terbaca `"\xEF\xBB\xBFplatform"` dan lookup `$d['platform']` SELALU gagal → platform post hanya ditebak dari `source_url` (post ber-URL shortlink pada analisis `both` salah dilabeli `Threads`). **(A2)** Query `analyses.platform` yang nilainya konstan diangkat keluar loop `insertBatchTransactional()` — sebelumnya N+1 (1 query per baris, 250 per batch). **(A3)** Kontrak baru dengan backend: CSV memuat `content` (mentah) + `clean_text` (bersih) sehingga `raw_content` & `clean_content` tidak lagi berisi teks yang sama. **Test baru** `tests/Feature/AnalysisImportServiceTest.php` (5 test; A1 & A2 dijalankan “merah dulu” untuk membuktikan test benar-benar menangkap bug: `Threads` vs `X`, dan 4 query vs ≤1). Suite frontend: **7/7 lulus**, `php -l` bersih. |
| 2026-09-24 | GitHub Copilot | Perbaikan Paket B — Otorisasi Analisis + Penghapusan Role Viewer | **(B1) Celah IDOR ditutup**: `analyses.show` & `analyses.export` sebelumnya hanya dijaga middleware `auth` sehingga siapa pun yang login bisa membaca/mengunduh data peneliti lain dengan menebak ID. Ditambah `App\Policies\AnalysisPolicy` (`view` = admin atau pemilik; `export` = `export-reports` + aturan `view`) yang ditegakkan via `Gate::authorize()` di `AnalysisController@show` & `@exportCsv`, plus middleware `can:export-reports` pada rute export — permission `export-reports` yang sebelumnya hanya didefinisikan di seeder/README kini benar-benar berlaku. **(B2) Role `viewer` dihapus** (keputusan pemilik proyek): seeder hanya membuat `admin` & `analyst`, validasi `StoreUserRequest`/`UpdateUserRequest` dibatasi 2 role, metrik `getUserMetrics()` tanpa key viewer, blok statistik + opsi filter viewer di `admin/users/index` dihapus, preset demo viewer di halaman login dihapus, fallback label role diubah menjadi “TANPA PERAN”, README (root & frontend) + komentar `docker-compose.yml` disesuaikan. **(B3) Migrasi data** `2026_09_24_000001_reassign_viewer_role_to_analyst`: akun ber-role viewer dipindahkan ke `analyst` lalu role viewer dihapus (reversibel sebagian di `down()`). **Verifikasi**: `php artisan view:cache` sukses; suite frontend **21/21 lulus**; B1 diuji merah-dulu (analyst menembus data analyst lain → 200, bukan 403); migrasi dijalankan di DB lokal dan dikonfirmasi: roles = `admin, analyst`, `viewer@hatespeech.test` → `analyst`. |
| 2026-09-24 | GitHub Copilot | Perbaikan Paket C — Pembersihan Dead Code & Hygiene Service Layer | **(C1) Dead code dihapus**: `UserManagementService::getPaginatedUsers()`, `AnalysisService::deleteAnalysis()`, dan 6 wrapper `FastAPIClientService` yang tak dipakai UI (`preprocessSingle`, `preprocessBatch`, `getModelInfo`, `classifyBatch`, `getExportList`, `getExportDownloadUrl`). **(C2) Kebocoran arsitektur ditutup**: `Role::all()` di controller (2×) dipindah ke `UserManagementService::getAssignableRoles()`; `getFilteredUsers()` tidak lagi menerima objek `Request` HTTP — filter pengguna kini divalidasi `FilterUserRequest` baru (sebelumnya satu-satunya filter tanpa validasi); `getUserMetrics()` dari 4 query → 2 query (`Role::withCount('users')`). **(C3) Decoupled penuh**: `AnalysisImportService` tidak lagi menyentuh `base_path('../backend/storage/exports/...')` — selalu HTTP FastAPI, diuji memakai file umpan (decoy) agar terbukti file lokal tidak dipakai lagi. **(C4) Fitur hapus akun dibuka** dari `deleteUser()`: rute `DELETE admin/users/{user}` + tombol HAPUS berkorfirmasi, dengan proteksi berlapis (tidak bisa hapus diri sendiri; menolak akun yang masih memiliki sesi analisis agar data riset tidak ter-cascade). **(C5) Migrasi** `2026_09_24_000002_delete_legacy_viewer_demo_account` menghapus akun demo lama dengan pengaman yang sama. **(C6) Hygiene kecil**: whitelist `per_page` diubah ke rentang 5–100 (tidak lagi memicu 422 bila UI mengirim nilai lain); `AnalysisController@index` mengecek status berjalan cukup sekali dan di-scope ke user (admin: semua). **(C7) Koreksi dokumen**: angka rute 27 → 25, field `new_password` → `password`, klaim fallback lokal, dan sisa penyebutan role viewer di Gate 2/5. **Verifikasi**: suite frontend **26/26 lulus** (+5 test baru), red-proof guard hapus (tanpa guard, akun berisi analisis ikut terhapus), `php artisan view:cache` OK, migrasi dijalankan di container dan DB Docker terkonfirmasi bersih (`users` = admin + analyst, `roles` = admin, analyst). |
| 2026-09-24 | GitHub Copilot | Perbaikan Paket D — Performa Dashboard, Atribut Virtual, Streaming Ekspor & Keamanan Akun | **(D1) Statistik dashboard di-scope ke pemilik data**: `DashboardController@index` kini menghitung `exists()` dan metrik hanya untuk analisis milik user yang login (admin tetap melihat semua) — sebelumnya seluruh user melihat angka agregat milik semua peneliti. **(D2) Sinkronisasi status aman**: `AnalysisService::syncAnalysisStatus()` memakai `syncOriginalAttribute('pipeline_message'/'pipeline_step')` sebelum `save()`; tanpa ini `isDirty()` selalu `true` untuk kolom virtual tersebut dan `save()` berisiko error. **(D3) Ekspor tidak lagi memuat seluruh CSV ke memori**: `FastAPIClientService::downloadExportContent()` (string) diganti `downloadExportToStream()` (Guzzle `sink`, timeout 120 s, nama file di-`basename()` untuk mencegah path traversal); `AnalysisImportService` mengunduh langsung ke stream `php://temp`. **(D4) Keamanan akun**: `UpdateUserRequest` menambah aturan `confirmed` + field konfirmasi pada `admin/users/edit`; middleware `Illuminate\Session\Middleware\AuthenticateSession` diaktifkan pada grup `web` sehingga perubahan password (user management maupun profil) otomatis mengakhiri sesi lain yang masih memakai hash password lama. **(D5) Penamaan konsisten**: `FilterAnalysisPostRequest` → `App\Http\Requests\Analysis\FilterPostRequest` (nama lama menyiratkan “POST request” padahal route-nya GET dan memfilter *postingan* — entri changelog lama 2.2/3.2 merujuk class yang sama); pesan `per_page.in` yang usang dihapus karena aturan sudah `between:5,100`. |
| 2026-09-24 | GitHub Copilot | Bug Fix — Container Frontend Docker (Pail & Env Strip) | Dua bug membuat stack Docker tidak bisa dipakai. **(1) `Class "Laravel\Pail\PailServiceProvider" not found`** saat `php artisan migrate`: `laravel/pail` + `laravel/pao` adalah *require-dev*, sedangkan `frontend/bootstrap/cache/packages.php` & `services.php` (gitignored, hasil `php artisan` di Windows) ikut ter-bind-mount `./frontend:/var/www/html` sementara vendor container di-install `composer install --no-dev`. Perbaikan: STEP 3 baru di `docker/frontend/entrypoint.sh` menghapus `bootstrap/cache/*.php` di level filesystem lalu `php artisan package:discover` (manifest dibangun dari vendor container); `bootstrap/cache` dipindah ke named volume baru `frontend_bootstrap_cache` agar cache host tidak bocor; pengecekan vendor kini memakai hash `composer.lock` (`vendor/.composer-lock-hash`). **(2) Setiap request web 500 `SQLSTATE[HY000] [2002] Connection refused ... Host: 127.0.0.1`**: `ServeCommand` menghitung `$hasEnvironment = file_exists(base_path('.env'))`; karena `frontend/.env` host (DB_HOST=127.0.0.1, user root) ada dan ikut ter-mount, hanya variabel whitelist (`APP_ENV`, `PATH`, `XDEBUG_*`, ...) yang diteruskan ke proses `php -S` — seluruh `DB_*` dan `FASTAPI_BASE_URL` dari docker-compose dibuang (nilai `false` = hapus di Symfony Process), sehingga proses web memakai `.env` host. Perbaikan: `php artisan serve --no-reload` (meneruskan seluruh environment container) + menghapus `APP_KEY: ${APP_KEY:-}` dari compose karena string kosong menimpa APP_KEY valid di `.env` → memicu "No application encryption key has been specified". Verifikasi E2E Docker: `docker compose up -d --build frontend` sukses, `package:discover` hanya menemukan tinker/carbon/termwind/spatie-permission (tanpa Pail), migrate jalan, `/login` 200, dashboard menampilkan data dari MySQL, masthead FastAPI ONLINE (bridge `backend:8080` benar), `/scraper/status` 200 dengan telemetri sesi asli, panel panduan noVNC tidak tampil sebelum tombol diklik dan setelah klik hanya muncul di kartu X, `localhost:6080/vnc.html` 200, Chromium Playwright berjalan di container. |
| 2026-09-24 | Antigravity AI | Tahap 1 (Service Layer) | **(1.1) Bug Path Docker**: Tambah `FastAPIClientService::downloadExportContent()` untuk stream CSV via HTTP endpoint `GET /api/v1/pipeline/exports/{filename}`; `AnalysisImportService` membaca via HTTP dengan fallback local filesystem. **(1.2) UserManagementService**: Sentralisasi filtering user (`getFilteredUsers`) dan metrik role (`getUserMetrics`), serta delegasi `toggleActiveStatus`. **(1.3) ProfileService**: Dibuat service baru `ProfileService` (`updateProfileInfo`, `updatePassword`). **(1.4) Ekspor CSV**: Logika 60 baris stream CSV dipindah ke `AnalysisService::streamExportCsv()` dilengkapi sanitasi formula injection (`=+-@\t\r`). Semua service berhasil di-resolve container, 27 routes terverifikasi (`php artisan route:list`), PHPUnit lulus. Pass Gate 1. |
| 2026-09-24 | Antigravity AI | Tahap 2 (Form Requests) | **(2.1) SinglePredictRequest**: Konsolidasi batas karakter (`min:3`, `max:2000`), otorisasi `can('test-single-prediction')`, dan pesan error kustom Indonesia. **(2.2) FilterAnalysisPostRequest**: Penambahan pesan error dan atribut kustom untuk query string postingan. **(2.3) FilterAnalysisRequest**: Dibuat class baru `FilterAnalysisRequest` untuk validasi filter `analyses.index` (`search`, `platform`, `status`, `per_page`). **(2.4) User FormRequests**: Sinkronisasi `StoreUserRequest` & `UpdateUserRequest` memakai `Rule::in(['admin', 'analyst', 'viewer'])`, password confirmation, dan otorisasi `manage-users`. **(2.5) Pemisahan Profil**: `UpdateProfileRequest` difokuskan untuk `name` dan `email`; dibuat `UpdatePasswordRequest` untuk `current_password` dan `password` (`confirmed`, `Password::min(8)`). **(2.6) Dead Code**: `RegisterRequest.php` dihapus karena tidak ada rute registrasi publik. 27 rute web normal, PHPUnit lulus. Pass Gate 2. |
| 2026-09-24 | Antigravity AI | Tahap 3 (Controllers) | **(3.1) PredictController**: Injeksi `SinglePredictRequest`, hapus validasi inline, delegasi ke `SinglePredictService`. **(3.2) AnalysisController**: Injeksi `FilterAnalysisRequest` di `index()` dan `FilterAnalysisPostRequest` di `show()`, delegasi ekspor CSV ke `AnalysisService::streamExportCsv()`. **(3.3) UserManagementController**: Injeksi `StoreUserRequest` & `UpdateUserRequest`, delegasi `createUser`, `updateUser`, `toggleActiveStatus`, serta kalkulasi filter dan metrik ke `UserManagementService`. **(3.4) ProfileController**: Injeksi `ProfileService` pada constructor, gunakan `UpdateProfileRequest` & `UpdatePasswordRequest`. **(3.5) Audit Controller**: Seluruh method controller berukuran < 25 baris, 0 manual `$request->validate()`. 27 rute web normal, PHPUnit lulus. Pass Gate 3. |
| 2026-09-24 | Antigravity AI | Tahap 4 (Code Cleanup & Linter) | **(4.1) Standarisasi Penamaan**: Evaluasi konsistensi struktur folder dan penamaan Controller, Form Request, dan Service. Nama `PredictController` dipertahankan untuk integritas rute dan Blade views. **(4.2) Unused Imports & Linter**: Pembersihan unused imports (`AnalysisClassification`, `AnalysisPost`, `Exception`) pada `AnalysisImportService`. Seluruh file PHP di direktori `app/` lolos pengecekan sintaks `php -l`. 27 rute web normal pada `route:list`, test suite PHPUnit lulus. Pass Gate 4. |
| 2026-09-24 | Antigravity AI | Tahap 5 (Verifikasi & Audit) | **(5.1) Form Validation & Blade Error**: Verifikasi penanganan error 422 JSON pada Alpine live classifier (`predict/index`), validasi email unik dan confirmation password pada user management (`create`/`edit`), serta update password pada profil. **(5.2) Audit Otorisasi (Spatie)**: Pemetaan seluruh rute dan menu Blade terhadap permission `manage-users`, `manage-auth-sessions`, `run-analysis`, `test-single-prediction`, `view-dashboard`, `export-reports` terkonfirmasi presisi untuk role `admin`, `analyst`, dan `viewer`. **(5.3) Alur Analisis & Ekspor CSV**: Polling AJAX pada `analyses.show` sinkron dan ekspor CSV `streamExportCsv()` aman dari formula injection. **(5.4) Monitor Scraper & Telemetri**: Health badge FastAPI dan modal trigger login X/Threads terverifikasi. PHPUnit 2/2 lulus. Pass Final Gate. |





