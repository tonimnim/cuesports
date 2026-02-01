# ===========================================
# CueSports Africa - Makefile
# ===========================================

.PHONY: help install dev up down build rebuild logs shell migrate seed test horizon pulse fresh

# Default target
help:
	@echo "CueSports Africa - Available Commands"
	@echo "======================================"
	@echo ""
	@echo "Setup:"
	@echo "  make install      - Install dependencies and setup project"
	@echo "  make fresh        - Fresh install (reset everything)"
	@echo ""
	@echo "Development:"
	@echo "  make dev          - Start development environment"
	@echo "  make up           - Start all Docker services"
	@echo "  make down         - Stop all Docker services"
	@echo "  make build        - Build Docker images"
	@echo "  make rebuild      - Rebuild Docker images (no cache)"
	@echo ""
	@echo "Database:"
	@echo "  make migrate      - Run database migrations"
	@echo "  make seed         - Run database seeders"
	@echo "  make fresh-db     - Fresh migration with seeders"
	@echo ""
	@echo "Services:"
	@echo "  make horizon      - View Horizon status"
	@echo "  make pulse        - View Pulse dashboard"
	@echo ""
	@echo "Utilities:"
	@echo "  make logs         - View all service logs"
	@echo "  make logs-api     - View API logs"
	@echo "  make shell        - Open shell in API container"
	@echo "  make test         - Run tests"
	@echo "  make lint         - Run code linters"
	@echo "  make cache        - Clear and rebuild caches"

# ===========================================
# Setup Commands
# ===========================================

install:
	@echo "Installing CueSports Africa..."
	@cp -n .env.docker .env 2>/dev/null || true
	docker compose build
	docker compose up -d postgres redis
	@echo "Waiting for database..."
	@sleep 5
	docker compose up -d
	docker compose exec api composer install
	docker compose exec api php artisan key:generate
	docker compose exec api php artisan migrate --seed
	docker compose exec api php artisan passport:install
	@echo ""
	@echo "✓ Installation complete!"
	@echo "  API:      http://localhost:8000"
	@echo "  Frontend: http://localhost:3000"
	@echo "  Horizon:  http://localhost:8000/horizon"
	@echo "  Pulse:    http://localhost:8000/pulse"
	@echo "  Mailpit:  http://localhost:8025"

fresh:
	@echo "Fresh installation..."
	docker compose down -v
	@rm -f .env
	@make install

# ===========================================
# Development Commands
# ===========================================

dev:
	docker compose --profile development up

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

rebuild:
	docker compose build --no-cache

restart:
	docker compose restart

# ===========================================
# Database Commands
# ===========================================

migrate:
	docker compose exec api php artisan migrate

seed:
	docker compose exec api php artisan db:seed

fresh-db:
	docker compose exec api php artisan migrate:fresh --seed

# ===========================================
# Service Commands
# ===========================================

horizon:
	docker compose exec api php artisan horizon:status

horizon-pause:
	docker compose exec api php artisan horizon:pause

horizon-continue:
	docker compose exec api php artisan horizon:continue

pulse:
	@echo "Pulse Dashboard: http://localhost:8000/pulse"

# ===========================================
# Utility Commands
# ===========================================

logs:
	docker compose logs -f

logs-api:
	docker compose logs -f api

logs-horizon:
	docker compose logs -f horizon

logs-frontend:
	docker compose logs -f frontend

shell:
	docker compose exec api sh

shell-postgres:
	docker compose exec postgres psql -U cuesports -d cuesports

shell-redis:
	docker compose exec redis redis-cli

test:
	docker compose exec api php artisan test

test-coverage:
	docker compose exec api php artisan test --coverage

lint:
	docker compose exec api ./vendor/bin/pint

cache:
	docker compose exec api php artisan optimize:clear
	docker compose exec api php artisan config:cache
	docker compose exec api php artisan route:cache
	docker compose exec api php artisan view:cache

tinker:
	docker compose exec api php artisan tinker

# ===========================================
# Production Commands
# ===========================================

prod-build:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml build

prod-up:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile production up -d

prod-down:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down

# ===========================================
# Frontend Commands
# ===========================================

frontend-build:
	docker compose exec frontend npm run build

frontend-lint:
	docker compose exec frontend npm run lint

frontend-shell:
	docker compose exec frontend sh
