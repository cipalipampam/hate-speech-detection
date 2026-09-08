# ==============================================================================
# Makefile — HateSense ID Lab Docker Helper Commands
# ==============================================================================
.PHONY: help up down restart status logs logs-frontend logs-backend logs-db bash-frontend bash-backend clean test-db

help:
	@echo "Perintah Docker HateSense ID Lab:"
	@echo "  make up              - Build dan jalankan seluruh container di background"
	@echo "  make down            - Hentikan seluruh container"
	@echo "  make restart         - Restart seluruh container"
	@echo "  make status          - Tampilkan status seluruh container & port"
	@echo "  make logs            - Stream logs seluruh service secara real-time"
	@echo "  make logs-frontend   - Stream logs container Frontend (Laravel)"
	@echo "  make logs-backend    - Stream logs container Backend (FastAPI)"
	@echo "  make logs-db         - Stream logs container Database (MySQL)"
	@echo "  make bash-frontend   - Masuk ke terminal container Frontend"
	@echo "  make bash-backend    - Masuk ke terminal container Backend"
	@echo "  make clean           - Hentikan container dan hapus semua volume (RESET DATA)"

up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

status:
	docker compose ps

logs:
	docker compose logs -f

logs-frontend:
	docker compose logs -f frontend

logs-backend:
	docker compose logs -f backend

logs-db:
	docker compose logs -f db

bash-frontend:
	docker compose exec frontend /bin/bash

bash-backend:
	docker compose exec backend /bin/bash

clean:
	docker compose down -v
