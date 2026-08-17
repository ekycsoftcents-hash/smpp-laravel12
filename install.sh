#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required. Install Docker Engine and Docker Compose plugin first." >&2
  exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose plugin is required: docker compose version was not available." >&2
  exit 1
fi

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Created .env from .env.example. Set JASMIN_USERNAME/JASMIN_PASSWORD before live SMS traffic."
fi

# Build the Laravel image and the Jasmin image from ./jasmin/Dockerfile.compose,
# then start PostgreSQL, Redis, RabbitMQ, Jasmin, Laravel and the queue worker.
docker compose build --pull
docker compose up -d

# Generate the Laravel key and apply all database migrations in the running app image.
docker compose run --rm app php artisan key:generate --force
docker compose run --rm app php artisan migrate --force

echo
echo "Installation complete."
echo "Laravel dashboard: http://localhost:8080"
echo "Jasmin HTTP API:  http://localhost:1401"
echo "Customer SMPP:    localhost:2775"
echo "Jasmin jcli:       localhost:8990"
echo "RabbitMQ UI:       http://localhost:15672"
echo
docker compose ps
