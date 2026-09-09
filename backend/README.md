<div align="center">

# 🧠 HateSense ID Lab — Backend AI Engine & REST API
### Core Intelligence Service: IndoBERT Classifier, NLP Preprocessing, & Playwright Scraper

[![Python 3.10](https://img.shields.io/badge/Python-3.10-3776AB.svg?logo=python&logoColor=white)](https://www.python.org/)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.111-009688.svg?logo=fastapi&logoColor=white)](https://fastapi.tiangolo.com/)
[![PyTorch](https://img.shields.io/badge/PyTorch-2.2-EE4C2C.svg?logo=pytorch&logoColor=white)](https://pytorch.org/)
[![Transformers](https://img.shields.io/badge/HuggingFace-Transformers-FFD21E.svg?logo=huggingface&logoColor=black)](https://huggingface.co/)
[![Playwright](https://img.shields.io/badge/Playwright-Chromium-2EAD33.svg?logo=playwright&logoColor=white)](https://playwright.dev/python/)

<p align="center">
  Layanan backend berbasis <b>FastAPI</b> dan <b>PyTorch</b> yang menyediakan endpoint RESTful untuk inferensi model <i>fine-tuned</i> <b>IndoBERT</b>, pembersihan dan normalisasi teks bahasa gaul/alay, serta automasi browser <i>headless</i> untuk mengumpulkan data dari platform <b>X (Twitter)</b> dan <b>Threads</b>.
</p>

---
</div>

## 📑 Daftar Isi
1. [Arsitektur & Komponen Utama](#-arsitektur--komponen-utama)
2. [Peta Struktur Direktori](#-peta-struktur-direktori)
3. [Arsitektur Model IndoBERT Hierarki](#-arsitektur-model-indobert-hierarki)
4. [Pipeline Preprocessing & Kamus Alay](#-pipeline-preprocessing--kamus-alay)
5. [Mesin Scraping Playwright](#-mesin-scraping-playwright)
6. [Daftar REST API Endpoints](#-daftar-rest-api-endpoints)
7. [Panduan Instalasi & Menjalankan](#-panduan-instalasi--menjalankan)
8. [Panduan Pemeliharaan & Troubleshooting (Maintenance Guide)](#-panduan-pemeliharaan--troubleshooting-maintenance-guide)

---

## 🏛️ Arsitektur & Komponen Utama

Layanan backend dirancang secara modular dan asinkron untuk menangani komputasi berat (deep learning dan web scraping) tanpa memblokir request HTTP:

1. **Lifespan Model Caching**:
   Model IndoBERT (~499 MB) dimuat **1 kali saat server boot** ke dalam memori (`request.app.state.predictor`). Request inferensi selanjutnya langsung membaca objek di memori dengan latensi rendah (~20-50ms per kalimat di CPU).
2. **Dual-Head Hierarchical Classifier**:
   Memanfaatkan *IndoBERT Base* sebagai shared encoder feature extractor, yang terhubung ke dua *Linear Classification Head* independen untuk mendeteksi status ujaran kebencian (Level 1) dan sub-kategori spesifik (Level 2).
3. **Mekanisme Gating Konsistensi Hierarki**:
   Jika Level 1 terdeteksi `non_hate_speech`, output Level 2 secara otomatis di-override menjadi `tidak_relevan` untuk mencegah anomali prediksi antar tingkat.
4. **Headless Browser Scraping**:
   Menggunakan **Playwright (Python)** dengan *persistent browser context* untuk melewati proteksi bot, lazy loading komentar, dan rendering client-side di X dan Threads.

---

## 📂 Peta Struktur Direktori

```
backend/
├── app/                                # Layer API Web (FastAPI)
│   ├── routers/                        # Controller REST API per domain
│   │   ├── api_auth.py                 # Endpoint sesi login browser (X & Threads)
│   │   ├── api_classification.py       # Endpoint prediksi tunggal & batch
│   │   ├── api_pipeline.py             # Endpoint orkestrasi end-to-end
│   │   ├── api_preprocessing.py        # Endpoint pembersihan teks
│   │   └── api_scraper.py              # Endpoint background scraper
│   └── schemas/                        # Validasi data request/response (Pydantic)
│       ├── auth_schema.py
│       ├── classification_schema.py
│       ├── pipeline_schema.py
│       ├── preprocessing_schema.py
│       └── scraper_schema.py
│
├── configs/                            # Pengaturan runtime & logging
│   └── settings.py
│
├── saved_models/                       # Artefak Model AI & Laporan Evaluasi
│   ├── best_model.pt                   # Checkpoint PyTorch bobot model (~499 MB)
│   ├── label_mapping.json              # Pemetaan label string ke ID indeks
│   ├── model_config.json               # Hyperparameter arsitektur model
│   └── reports/                        # Laporan metrik F1, Confusion Matrix, kurva loss
│
├── src/                                # Core Engine & Business Logic
│   ├── classification/                 # Engine Klasifikasi PyTorch
│   │   ├── model_loader.py             # Definisi arsitektur PyTorch & model loading
│   │   └── predictor.py                # Wrapper inferensi, tokenizer, & softmax
│   ├── preprocessing/                  # Pipeline Pembersihan Teks
│   │   ├── cleaner.py                  # Regex URLs, mentions, emojis, angka
│   │   ├── normalizer.py               # Kamus substitusi kata alay/slang
│   │   └── pipeline.py                 # Orchestrator preprocessing
│   └── scraping/                       # Engine Scraping Media Sosial
│       ├── browser_context.py          # Session & Playwright lifecycle manager
│       ├── threads_scraper.py          # DOM selector & scroll engine Threads
│       └── x_scraper.py                # DOM selector & scroll engine X (Twitter)
│
├── storage/                            # Penyimpanan Lokal & Runtime State
│   ├── dictionaries/                   # Kamus referensi (kamusalay.csv)
│   ├── exports/                        # File CSV hasil scraping/klasifikasi
│   └── sessions/                       # Browser cookies & local storage
│       ├── threads_profile/
│       └── x_profile/
│
├── main_api.py                         # Entrypoint Server REST API (Uvicorn)
├── main_cli.py                         # Entrypoint CLI interaktif untuk debug
└── requirements.txt                    # Daftar dependensi library Python
```

---

## 🤖 Arsitektur Model IndoBERT Hierarki

* **Pretrained Base**: `indobenchmark/indobert-base-p1`
* **Mekanisme Dual Head**:
  - `encoder`: Shared IndoBERT Transformer layer (768 hidden dimensions).
  - `head_lvl1`: `Linear(768, 2)` $\rightarrow$ Output logits untuk 2 kelas.
  - `head_lvl2`: `Linear(768, 6)` $\rightarrow$ Output logits untuk 6 kelas.

```python
# Definisi arsitektur dual head di src/classification/model_loader.py:
class HierarchicalIndoBERT(nn.Module):
    def __init__(self, pretrained_model, num_classes_lvl1=2, num_classes_lvl2=6, dropout_rate=0.3):
        super().__init__()
        self.encoder = AutoModel.from_pretrained(pretrained_model)
        self.dropout = nn.Dropout(dropout_rate)
        self.classifier_lvl1 = nn.Linear(768, num_classes_lvl1)
        self.classifier_lvl2 = nn.Linear(768, num_classes_lvl2)
```

### Taksonomi Label
* **Level 1 (Deteksi Dasar)**:
  `[0] non_hate_speech`, `[1] hate_speech`
* **Level 2 (Sub-Kategori)**:
  `[0] tidak_relevan`, `[1] delegitimasi_institusi`, `[2] dehumanisasi`, `[3] ajakan_kekerasan`, `[4] hoaks_pemicu_kebencian`, `[5] kutukan_agama_personal`

---

## 🧹 Pipeline Preprocessing & Kamus Alay

Setiap teks masukan dibersihkan melalui tahapan berurutan di `src/preprocessing/`:
1. **Case Folding**: Mengubah semua karakter ke huruf kecil (*lowercase*).
2. **Cleaning Regex**:
   - Menghapus URL (`https?://...`).
   - Menghapus mention akun (`@username`).
   - Menghapus tanda hashtag (`#`).
   - Menghapus emoji, simbol non-ASCII, dan tanda baca berlebih.
   - Menghapus angka dan whitespace berulang.
3. **Slang Normalization (`kamusalay.csv`)**:
   Mencocokkan kata-kata tidak baku dengan korpus kamus alay bahasa Indonesia (terdapat di `storage/dictionaries/kamusalay.csv`) agar bentuk kata menjadi baku sebelum masuk ke tokenizer IndoBERT.

---

## 🕷️ Mesin Scraping Playwright

Scraper berada di `src/scraping/` menggunakan modul **Playwright Python**:
* **Persistent Context**: Sesi login (cookies, state) disimpan di folder `storage/sessions/` sehingga akun tidak perlu login berulang kali.
* **Smart Infinite Scroll**: Menggulir linimasa secara berkala, menunggu elemen baru di-*render*, dan mencegah deteksi bot dengan delay acak (*human-like jitter*).
* **Dukungan Platform**:
  - `x_scraper.py`: Mendukung pencarian kata kunci (*search keyword*) dan URL postingan spesifik.
  - `threads_scraper.py`: Mendukung scraping komentar pada thread dan profil publik.

---

## 🌐 Daftar REST API Endpoints

Server REST API berjalan pada port **8080**. Dokumentasi interaktif Swagger dapat diakses di `http://localhost:8080/docs`.

### 1. Klasifikasi (`/api/v1/classify`)
* `GET /api/v1/classify/info`: Menampilkan status model IndoBERT (device CPU/CUDA, daftar kelas).
* `POST /api/v1/classify/single`: Prediksi klasifikasi untuk 1 teks.
* `POST /api/v1/classify/batch`: Prediksi klasifikasi untuk array teks (maks. 500 baris per request).

### 2. Preprocessing (`/api/v1/preprocess`)
* `POST /api/v1/preprocess/clean`: Menguji hasil pembersihan dan normalisasi teks tanpa inferensi model.

### 3. Media Social Scraping (`/api/v1/scrape`)
* `POST /api/v1/scrape/x`: Memulai background scraping platform X (Twitter).
* `POST /api/v1/scrape/threads`: Memulai background scraping platform Threads.
* `GET /api/v1/scrape/status/{task_id}`: Memeriksa progres scraping (jumlah item terkumpul, persentase).

### 4. Sesi Autentikasi (`/api/v1/auth`)
* `GET /api/v1/auth/sessions`: Memeriksa status login sesi X dan Threads.
* `POST /api/v1/auth/login-browser`: Membuka browser interaktif untuk login manual jika session expired.

### 5. Pipeline (`/api/v1/pipeline`)
* `POST /api/v1/pipeline/run`: Menjalankan alur lengkap: Ambil Data $\rightarrow$ Preprocessing $\rightarrow$ Klasifikasi $\rightarrow$ Export CSV.

---

## 🚀 Panduan Instalasi & Menjalankan

### Melalui Docker (Bagian dari Docker Compose)
Backend otomatis berjalan bersama layanan lain:
```bash
docker compose up -d --build backend
```
Auto-download bobot model `best_model.pt` akan dieksekusi oleh `docker/backend/entrypoint.sh` jika file belum ada di host.

### Menjalankan Mandiri / Lokal (Development Mode)
1. Buat dan aktifkan virtual environment:
   ```bash
   python -m venv venv
   # Windows PowerShell:
   .\venv\Scripts\Activate.ps1
   # Linux/macOS:
   source venv/bin/activate
   ```
2. Pasang dependensi:
   ```bash
   pip install --upgrade pip
   pip install -r requirements.txt
   playwright install chromium
   ```
3. Pastikan file model `saved_models/best_model.pt` sudah tersedia.
4. Jalankan server Uvicorn:
   ```bash
   uvicorn main_api:app --reload --host 0.0.0.0 --port 8080
   ```

---

## 🔧 Panduan Pemeliharaan & Troubleshooting (Maintenance Guide)

### 1. Memperbarui atau Mengganti Bobot Model (`best_model.pt`)
Jika Anda melatih ulang (*retraining*) model dengan dataset baru:
1. Simpan file bobot PyTorch ke `backend/saved_models/best_model.pt`.
2. Jika ada perubahan jumlah kelas atau nama label:
   - Perbarui `saved_models/model_config.json`.
   - Perbarui pemetaan ID di `saved_models/label_mapping.json`.
3. Restart backend container (`docker compose restart backend`) agar model termuat ulang ke memori.

### 2. Memperbarui Selektor DOM Scraper (X / Threads)
Platform media sosial sering memperbarui class HTML dan atribut mereka:
- Buka `src/scraping/x_scraper.py` atau `src/scraping/threads_scraper.py`.
- Sesuaikan selector CSS/XPath pada method pengambil elemen postingan (misal: `article[data-testid="tweet"]` pada X).

### 3. Sesi Login Media Sosial Kedaluwarsa (*Expired Cookies*)
Jika scraper gagal mengambil data karena diminta login:
1. Jalankan `python main_cli.py` di terminal lokal.
2. Pilih menu autentikasi browser untuk membuka browser Chromium interaktif.
3. Login ke akun X/Threads Anda. Profil sesi akan otomatis tersimpan di `storage/sessions/`.

### 4. Menambah Kosakata Bahasa Gaul (*Slang Words*)
Buka `storage/dictionaries/kamusalay.csv`:
- Tambahkan baris baru dengan format: `kata_tidak_baku,kata_baku`.
- Perubahan akan langsung aktif tanpa perlu melatih ulang model AI.
