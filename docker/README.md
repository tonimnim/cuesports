# CueSports Africa - Docker Setup

Complete Docker configuration for running CueSports Africa with Laravel Octane, Horizon, and all optimizations.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         NGINX (Production)                       │
│                    Port 80/443 - Reverse Proxy                   │
└─────────────────────────────────────────────────────────────────┘
                                  │
         ┌────────────────────────┼────────────────────────┐
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Next.js       │    │  Laravel API    │    │   WebSocket     │
│   Frontend      │    │  (Octane/FPH)   │    │   (Pusher)      │
│   Port 3000     │    │   Port 8000     │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                    ┌───────────┴───────────┐
                    │                       │
                    ▼                       ▼
         ┌─────────────────┐     ┌─────────────────┐
         │   PostgreSQL    │     │     Redis       │
         │   Port 5432     │     │   Port 6379     │
         └─────────────────┘     └─────────────────┘
                                        │
                         ┌──────────────┴──────────────┐
                         │                             │
                         ▼                             ▼
              ┌─────────────────┐          ┌─────────────────┐
              │    Horizon      │          │    Scheduler    │
              │  Queue Worker   │          │   (Cron Jobs)   │
              └─────────────────┘          └─────────────────┘
```

## Services

| Service | Description | Port |
|---------|-------------|------|
| `api` | Laravel API with Octane (FrankenPHP) | 8000 |
| `frontend` | Next.js Frontend | 3000 |
| `horizon` | Laravel Horizon Queue Worker | - |
| `scheduler` | Laravel Task Scheduler | - |
| `postgres` | PostgreSQL 16 Database | 5432 |
| `redis` | Redis 7 Cache & Queue | 6379 |
| `nginx` | Nginx Reverse Proxy (Production) | 80/443 |
| `mailpit` | Email Testing (Development) | 1025/8025 |
| `minio` | S3-Compatible Storage (Development) | 9000/9001 |

## Quick Start

### Prerequisites

- Docker Desktop 4.0+
- Docker Compose 2.0+
- Make (optional, for convenience commands)

### Installation

```bash
# Clone the repository
git clone https://github.com/your-org/cuesports.git
cd cuesports

# Copy environment file
cp .env.docker .env

# Start all services
docker compose up -d

# Install dependencies and setup
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
docker compose exec api php artisan passport:install

# Or use Make (if available)
make install
```

### Access Points

| Service | URL |
|---------|-----|
| API | http://localhost:8000 |
| Frontend | http://localhost:3000 |
| Horizon Dashboard | http://localhost:8000/horizon |
| Pulse Dashboard | http://localhost:8000/pulse |
| Mailpit UI | http://localhost:8025 |
| MinIO Console | http://localhost:9001 |

## Common Commands

### Using Make

```bash
# Start development environment
make dev

# Start all services
make up

# Stop all services
make down

# View logs
make logs

# Run migrations
make migrate

# Fresh database with seeders
make fresh-db

# Run tests
make test

# Open shell in API container
make shell

# Clear and rebuild caches
make cache
```

### Using Docker Compose

```bash
# Start services
docker compose up -d

# Stop services
docker compose down

# View logs
docker compose logs -f

# Execute commands in API container
docker compose exec api php artisan migrate
docker compose exec api php artisan tinker

# Rebuild images
docker compose build --no-cache
```

## Development Workflow

### API Development

The Laravel API runs with Octane which watches for file changes in development:

```bash
# View API logs
docker compose logs -f api

# Restart API after major changes
docker compose restart api

# Run artisan commands
docker compose exec api php artisan make:model MyModel -m
docker compose exec api php artisan test
```

### Frontend Development

The Next.js frontend hot-reloads automatically:

```bash
# View frontend logs
docker compose logs -f frontend

# Run npm commands
docker compose exec frontend npm install some-package
docker compose exec frontend npm run lint
```

### Database

```bash
# Access PostgreSQL CLI
docker compose exec postgres psql -U cuesports -d cuesports

# Run migrations
docker compose exec api php artisan migrate

# Fresh migration with seed
docker compose exec api php artisan migrate:fresh --seed

# Create new migration
docker compose exec api php artisan make:migration create_some_table
```

### Queue / Horizon

```bash
# View Horizon logs
docker compose logs -f horizon

# Check Horizon status
docker compose exec api php artisan horizon:status

# Pause/Continue processing
docker compose exec api php artisan horizon:pause
docker compose exec api php artisan horizon:continue

# Restart Horizon (after code changes)
docker compose restart horizon
```

## Production Deployment

### Build Production Images

```bash
# Build with production target
docker compose build --target production

# Or use Make
make prod-build
```

### Production Docker Compose

Create a `docker-compose.prod.yml` override:

```yaml
services:
  api:
    build:
      target: production
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '1'
          memory: 1G

  frontend:
    build:
      target: production
```

### Deploy

```bash
# Start with production profile
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Or use Make
make prod-up
```

## Performance Optimizations

### Laravel Octane (FrankenPHP)

- High-performance PHP application server
- Persistent workers (no per-request bootstrapping)
- Built-in HTTP/2 and HTTP/3 support
- Automatic HTTPS with Let's Encrypt (production)

### Redis Configuration

- Used for cache, sessions, and queues
- Configured with 256MB memory limit
- LRU eviction policy

### PostgreSQL Tuning

The `init.sql` configures PostgreSQL for optimal performance:
- Shared buffers: 256MB
- Effective cache size: 768MB
- WAL optimization for writes

### OPcache (Production)

- JIT compilation enabled
- 256MB memory
- No timestamp validation (faster)

## Troubleshooting

### Common Issues

**Container won't start:**
```bash
# Check logs
docker compose logs api

# Rebuild from scratch
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

**Database connection refused:**
```bash
# Wait for PostgreSQL to be ready
docker compose up -d postgres
sleep 10
docker compose up -d
```

**Permission issues:**
```bash
# Fix storage permissions
docker compose exec api chmod -R 777 storage bootstrap/cache
```

**Out of memory:**
```bash
# Increase Docker memory limit in Docker Desktop settings
# Or reduce worker count in docker-compose.yml
```

### Reset Everything

```bash
# Stop and remove all containers, volumes, and networks
docker compose down -v --remove-orphans

# Remove all Docker images for this project
docker rmi $(docker images 'cuesports*' -q)

# Fresh start
make fresh
```

## Directory Structure

```
docker/
├── Dockerfile              # Main Laravel Dockerfile
├── README.md               # This file
├── cron/
│   └── laravel-cron        # Cron job configuration
├── nginx/
│   ├── nginx.conf          # Main Nginx config
│   ├── conf.d/
│   │   ├── default.conf    # Server blocks
│   │   └── locations.conf  # Location rules
│   └── ssl/                # SSL certificates (production)
├── php/
│   ├── php-development.ini # PHP dev settings
│   └── php-production.ini  # PHP prod settings
├── postgres/
│   └── init.sql            # Database initialization
└── supervisor/
    └── supervisord.conf    # Supervisor configuration
```

## Environment Variables

See `.env.example` for all available configuration options.

Key Docker-specific variables:
- `APP_PORT` - API port (default: 8000)
- `FRONTEND_PORT` - Frontend port (default: 3000)
- `FORWARD_DB_PORT` - PostgreSQL external port (default: 5432)
- `FORWARD_REDIS_PORT` - Redis external port (default: 6379)
