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
  echo "Created .env with container-internal defaults. Add provider credentials before live SMS traffic."
fi

# Build the Laravel image and the Jasmin image from ./jasmin/Dockerfile.compose,
# then start PostgreSQL, Redis, RabbitMQ, Jasmin, Laravel and the queue worker.
docker compose build --pull
docker compose up -d

# Wait for Jasmin's native HTTP API and jcli to become available.
for attempt in $(seq 1 60); do
  if docker compose exec -T jasmin python -c "import socket; s=socket.create_connection(('127.0.0.1', 1401), 2); s.close()" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" = "60" ]; then
    echo "Jasmin did not become ready. Check: docker compose logs jasmin" >&2
    exit 1
  fi
  sleep 2
done

for attempt in $(seq 1 30); do
  if docker compose exec -T jasmin python -c "import socket; s=socket.create_connection(('127.0.0.1', 8990), 2); s.close()" >/dev/null 2>&1; then
    break
  fi
  if [ "$attempt" = "30" ]; then
    echo "Jasmin jcli did not become ready. Check: docker compose logs jasmin" >&2
    exit 1
  fi
  sleep 2
done

docker compose exec -T jasmin python /provision-default.py

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
