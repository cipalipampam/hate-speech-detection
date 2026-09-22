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
| **Tahap 1** | Perbaikan Bug Kritis & Penguatan Service Layer (`app/Services/`) | 4 Task | `[ ] Belum` |
| **Tahap 2** | Standardisasi & Konsolidasi Form Request Layer (`app/Http/Requests/`) | 6 Task | `[ ] Belum` |
| **Tahap 3** | Perampingan Controller Layer (`app/Http/Controllers/`) | 5 Task | `[ ] Belum` |
| **Tahap 4** | Standardisasi Penamaan File & Pembersihan Dead Code | 3 Task | `[ ] Belum` |
| **Tahap 5** | Verifikasi Menyeluruh, Authorization Audit & Smoke Testing | 5 Task | `[ ] Belum` |

---

## 📋 Detail Checklist Tahapan

### TAHAP 1: Perbaikan Bug Kritis & Penguatan Service Layer (`app/Services/`)
> **Fokus**: Memperbaiki bug path Docker pada impor CSV, mengisolasi logika ekspor CSV dari controller, dan melengkapi service yang belum ada.

- [ ] **1.1. Perbaikan Bug Kritis Docker Path pada `AnalysisImportService`**
  - [ ] Hapus ketergantungan pada path relatif `base_path('../backend/storage/exports/' . $filename)`.
  - [ ] Tambahkan method di `FastAPIClientService` untuk mengunduh stream CSV dari backend via HTTP endpoint: `GET /api/v1/pipeline/exports/{filename}`.
  - [ ] Update `AnalysisImportService` agar membaca konten CSV via HTTP client (dengan fallback filesystem lokal untuk development non-Docker).
- [ ] **1.2. Refaktorisasi & Sentralisasi Query di `UserManagementService`**
  - [ ] Pindahkan 35 baris logika filtering dan query user dari `UserManagementController@index` ke `UserManagementService@getFilteredUsers()`.
  - [ ] Pindahkan logika penghitungan metrik role (`admin`, `analyst`, `viewer`) dan user aktif ke `UserManagementService@getUserMetrics()`.
  - [ ] Pastikan method `toggleActiveStatus()` digunakan untuk pergantian status user.
- [ ] **1.3. Pembuatan `ProfileService` (`app/Services/ProfileService.php`)**
  - [ ] Buat service baru `ProfileService` untuk memisahkan update profil dan ganti password dari `ProfileController`.
  - [ ] Implementasikan method `updateProfileInfo(User $user, array $data): bool`.
  - [ ] Implementasikan method `updatePassword(User $user, string $newPassword): bool`.
- [ ] **1.4. Pemindahan Logika Ekspor CSV ke `AnalysisService`**
  - [ ] Pindahkan 60 baris generator streaming CSV (`StreamedResponse`, escaping formula injection `=+-@`, header CSV) dari `AnalysisController@exportCsv` ke `AnalysisService@streamExportCsv(Analysis $analysis)`.

🏁 **CHECKPOINT GATE 1:**
- Jalankan test impor data CSV: pastikan data berhasil diimpor ke database tanpa perlu bergantung pada folder host backend.
- Pastikan ekspor CSV berjalan normal dengan pemanggilan service.

---

### TAHAP 2: Standardisasi & Konsolidasi Form Request Layer (`app/Http/Requests/`)
> **Fokus**: Mengaktifkan Form Request yang sempat terbengkalai, memastikan otorisasi dan pesan kustom Indonesia seragam, dan menghapus dead code.

- [ ] **2.1. Konsolidasi `SinglePredictRequest` (`app/Http/Requests/Prediction/`)**
  - [ ] Periksa kembali aturan `min:3`, `max:2000`, pesan error kustom, dan otorisasi `$this->user()->can('test-single-prediction')`.
  - [ ] Pastikan siap di-inject ke `PredictController@classify`.
- [ ] **2.2. Konsolidasi `FilterAnalysisPostRequest` (`app/Http/Requests/Analysis/`)**
  - [ ] Periksa aturan validasi query string: `platform`, `label_lvl1`, `label_lvl2`, `search`, `per_page`.
  - [ ] Pastikan siap di-inject ke `AnalysisController@show`.
- [ ] **2.3. Pembuatan `FilterAnalysisRequest` untuk Halaman Index Analisis**
  - [ ] Buat `app/Http/Requests/Analysis/FilterAnalysisRequest.php` untuk memvalidasi query parameter pada `analyses.index` (`search`, `platform:all,x,threads,both`, `status:all,queued,running,completed,failed`).
- [ ] **2.4. Sinkronisasi `StoreUserRequest` & `UpdateUserRequest` (`app/Http/Requests/User/`)**
  - [ ] Selaraskan validasi role: gunakan `Rule::in(['admin', 'analyst', 'viewer'])`.
  - [ ] Pastikan otorisasi `$this->user()->can('manage-users')` aktif dan pesan kustom bahasa Indonesia lengkap.
- [ ] **2.5. Pemisahan Form Request Profil (`app/Http/Requests/User/`)**
  - [ ] Sesuaikan `UpdateProfileRequest.php` untuk validasi `name` dan `email` (dengan `Rule::unique('users')->ignore($userId)`).
  - [ ] Buat `UpdatePasswordRequest.php` untuk validasi `current_password` dan `new_password` (dengan aturan `Password::min(8)->mixedCase()->numbers()` atau standar aplikasi).
- [ ] **2.6. Pembersihan Dead Code**
  - [ ] Hapus `app/Http/Requests/Auth/RegisterRequest.php` (karena registrasi publik ditiadakan dan tidak ada rute register).

🏁 **CHECKPOINT GATE 2:**
- Uji validasi Form Request: pastikan request yang tidak valid otomatis ditolak dengan pesan kustom bahasa Indonesia yang ramah, dan user tanpa izin dicegat sebelum masuk ke Controller.

---

### TAHAP 3: Perampingan Controller Layer (`app/Http/Controllers/`)
> **Fokus**: Mengubah seluruh controller menjadi Thin Controller (hanya memanggil Form Request dan Service).

- [ ] **3.1. Refaktorisasi `PredictController.php`**
  - [ ] Ganti `Request $request` pada method `classify()` dengan `SinglePredictRequest $request`.
  - [ ] Hapus manual `$request->validate([...])`.
  - [ ] Controller hanya memanggil `$this->predictService->predict($request->validated('text'))`.
- [ ] **3.2. Refaktorisasi `AnalysisController.php`**
  - [ ] Di `index()`: Inject `FilterAnalysisRequest $request`.
  - [ ] Di `show()`: Inject `FilterAnalysisPostRequest $request` menggantikan `Request $request`.
  - [ ] Di `exportCsv()`: Ganti implementasi inline dengan delegasi ke `$this->analysisService->streamExportCsv($analysis)`.
- [ ] **3.3. Refaktorisasi `Admin/UserManagementController.php`**
  - [ ] Di `index()`: Ganti 35 baris query manual dengan pemanggilan `$this->userService->getFilteredUsers($request)` dan `$this->userService->getUserMetrics()`.
  - [ ] Di `store()`: Inject `StoreUserRequest $request`, hapus `$request->validate(...)`.
  - [ ] Di `update()`: Inject `UpdateUserRequest $request`, hapus `$request->validate(...)`.
  - [ ] Di `toggleStatus()`: Gunakan `$this->userService->toggleActiveStatus($user)`.
- [ ] **3.4. Refaktorisasi `ProfileController.php`**
  - [ ] Inject `ProfileService $profileService` pada constructor.
  - [ ] Di `updateInfo()`: Inject `UpdateProfileRequest $request`, hapus inline validation, panggil `$this->profileService->updateProfileInfo(...)`.
  - [ ] Di `updatePassword()`: Inject `UpdatePasswordRequest $request`, hapus inline validation, panggil `$this->profileService->updatePassword(...)`.
- [ ] **3.5. Audit `ScraperMonitorController.php`, `DashboardController.php`, & `AuthController.php`**
  - [ ] Pastikan ketiga controller ini tetap bersih, terjaga dari logic leak, dan konsisten.

🏁 **CHECKPOINT GATE 3:**
- Verifikasi ketebalan Controller: seluruh method controller berukuran < 25 baris kode.
- Pastikan tidak ada satupun pemanggilan `$request->validate()` manual di controller.

---

### TAHAP 4: Standardisasi Penamaan File & Pembersihan Kode
> **Fokus**: Menyesuaikan penamaan konvensi Laravel dan membersihkan unused imports.

- [ ] **4.1. Evaluasi Penamaan Controller & Request**
  - [ ] Pertahankan nama `PredictController` / buat alias jika diperlukan agar tidak memutus view reference di Blade / Route.
  - [ ] Pastikan seluruh namespace dan folder request rapi dan terdokumentasi.
- [ ] **4.2. Pembersihan Unused Imports & PHP Linter**
  - [ ] Hapus `use` statements yang tidak lagi digunakan di seluruh controller dan service.
  - [ ] Jalankan pengecekan sintaks PHP (`php -l`).

🏁 **CHECKPOINT GATE 4:**
- Jalankan `php artisan route:list`: pastikan tidak ada route yang error atau controller method yang broken.

---

### TAHAP 5: Verifikasi Menyeluruh, Authorization Audit & Smoke Testing
> **Fokus**: Menjamin kestabilan aplikasi, tidak ada regresi tampilan UI, dan alur end-to-end berjalan mulus.

- [ ] **5.1. Uji Validasi Form & Error Display di Blade**
  - [ ] Uji input teks kosong pada Live Classifier → muncul error alert.
  - [ ] Uji input email duplikat pada User Management → muncul error validation.
  - [ ] Uji password tidak cocok pada Profile → muncul error validation.
- [ ] **5.2. Uji Otorisasi Hak Akses (Spatie Roles & Permissions)**
  - [ ] Admin: bisa mengakses user management dan seluruh fitur.
  - [ ] Analyst: bisa membuat dan melihat analisis, dilarang mengakses user management.
  - [ ] Viewer: hanya bisa melihat dashboard dan hasil analisis.
- [ ] **5.3. Uji Alur Analisis & Ekspor CSV**
  - [ ] Buat analisis baru → polling AJAX status berjalan lancar.
  - [ ] Klik unduh CSV → file terunduh dengan format header lengkap dan karakter aman.
- [ ] **5.4. Uji Monitor Scraper & Telemetri**
  - [ ] Periksa badge status online FastAPI di navbar header.
  - [ ] Periksa modal trigger login X dan Threads.

🏁 **CHECKPOINT FINAL:**
- Seluruh checklist `[x]` terisi lengkap.
- Frontend dan Backend selaras, bersih, dan siap untuk pengembangan lanjutan atau pengujian skripsi.

---

## 📌 Catatan Riwayat Perubahan Frontend (Changelog Kolaborasi)

| Tanggal | Pelaksana | Modul / Tahap | Catatan / Hasil Verifikasi |
| :---: | :---: | :---: | :--- |
| *Inisialisasi* | Antigravity AI | Setup Roadmap Frontend | Dokumen checklist dibuat untuk panduan refaktorisasi arsitektur frontend Laravel. |

