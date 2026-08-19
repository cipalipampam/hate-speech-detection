# 🚀 Backend AI & Data Pipeline (IndoBERT Sentiment Analysis)

Sistem backend modular untuk analisis sentimen opini publik pada platform **X (Twitter)** dan **Threads** menggunakan model **Fine-Tuned IndoBERT**.

---

## 📂 Struktur Direktori

```text
apps/backend/
├── configs/          # Konfigurasi & .env
├── storage/          # Sesi login browser, kamusalay, dan hasil export
├── saved_models/     # Model IndoBERT hasil fine-tuning
├── src/              # Logika Bisnis (Auth, Scraping, Preprocessing, IndoBERT, Pipeline)
├── app/              # FastAPI Routers & Schemas (Untuk integrasi ke Laravel)
├── main_cli.py       # Entrypoint Terminal (Fokus utama saat ini)
├── main_api.py       # Entrypoint REST API Server (FastAPI)
└── requirements.txt  # Python Dependencies
```

---

## 💻 Cara Menjalankan

### 1. Mode Terminal (CLI)
Untuk menguji dan menjalankan pipeline secara interaktif dari console/terminal:
```bash
cd apps/backend
python main_cli.py
```

### 2. Mode REST API Server (FastAPI)
Untuk menjalankan API server agar siap diakses oleh Frontend Laravel:
```bash
cd apps/backend
uvicorn main_api:app --reload --host 0.0.0.0 --port 8000
```
Dokumentasi interaktif (Swagger UI): `http://localhost:8000/docs`
