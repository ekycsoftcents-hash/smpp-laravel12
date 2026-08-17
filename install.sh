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

docker compose build --pull
docker compose up -d postgres redis rabbit-mq gateway
for attempt in $(seq 1 60); do
  if curl -fsS http://127.0.0.1:3001/health >/dev/null 2>&1; then break; fi
  if [ "$attempt" = "60" ]; then echo "Native gateway did not become ready. Check: docker compose logs gateway" >&2; exit 1; fi
  sleep 2
done
docker compose up -d app queue-worker
docker compose run --rm app php artisan key:generate --force
docker compose run --rm app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache

echo
echo "Installation complete."
echo "Laravel dashboard: http://localhost:8080"
echo "Native gateway API:  http://localhost:3001"
echo "Customer SMPP:       localhost:2775"
echo "RabbitMQ UI:       http://localhost:15672"
echo
docker compose ps
