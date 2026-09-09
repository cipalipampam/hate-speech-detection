# IndoBERT Saved Models
Direktori ini digunakan untuk menyimpan bobot model IndoBERT (`best_model.pt`, ~499MB).

File bobot model tidak di-commit ke Git karena batasan ukuran file 100MB di GitHub.

## Cara Mendapatkan Model:
1. **Otomatis via Docker:**
   Saat `docker compose up -d` dijalankan, container backend otomatis mengunduh bobot model dari GitHub Releases jika file belum ada di host.
2. **Manual:**
   Unduh file `best_model.pt` dari tab **Releases** di repositori GitHub, lalu letakkan di dalam folder ini (`backend/saved_models/best_model.pt`).
