# REVE Native SMPP Platform Deployment

This guide deploys the Laravel 12 admin panel, native Node.js SMPP gateway, PostgreSQL, Redis, and RabbitMQ with Docker Compose. Jasmin is not used.

## 1. Prepare the server

Use a Linux server with Docker Engine and the Compose plugin installed. Open only the required ports in the firewall. Port `2775/tcp` is the customer SMPP bind port, port `8080/tcp` is the Laravel panel, port `3001/tcp` is the internal gateway API/live event service, and port `15672/tcp` is the RabbitMQ administration page. In production, restrict ports `3001` and `15672` to an internal administrator network rather than exposing them publicly.

```bash
sudo apt-get update
sudo apt-get install -y git ca-certificates curl
sudo apt-get install -y docker.io docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

Log out and back in after adding the user to the Docker group.

## 2. Clone and configure the repository

```bash
git clone https://github.com/ekycsoftcents-hash/smpp-laravel12.git
cd smpp-laravel12
cp .env.example .env
```

Set a strong Laravel application key and production values. The gateway must receive the same `APP_KEY` as Laravel because provider and customer SMPP passwords are encrypted by Laravel.

```bash
sed -i "s/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/" .env
docker compose run --rm app php artisan key:generate --force
```

Set provider credentials, mail, Telegram, database secrets, and the public `APP_URL` in `.env`. Keep `SMPP_GATEWAY_URL=http://gateway:3001` inside Docker. The gateway service must not be pointed at `localhost` from another container.

## 3. Build and start the services

```bash
docker compose build --pull
docker compose up -d postgres redis rabbit-mq gateway
```

Wait for dependencies to become healthy and then start Laravel and the queue worker.

```bash
docker compose ps
docker compose up -d app queue-worker
```

Run the database migrations and optimize Laravel caches.

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

## 4. Verify the gateway

Check the gateway health endpoint from the host and from inside the application container.

```bash
curl -fsS http://127.0.0.1:3001/health
curl -fsS http://127.0.0.1:3001/ready
docker compose exec app php artisan tinker --execute="dump(Http::get(config('smpp.gateway.url').'/health')->json());"
```

Check the customer SMPP listener. A test client should connect to the server's public IP on port `2775` using a customer account created in the Laravel panel.

```bash
nc -vz 127.0.0.1 2775
docker compose logs --tail=100 gateway
```

The gateway returns `BOUND` provider states only after provider credentials are valid and the upstream SMPP provider accepts the bind. A gateway process can be healthy while every provider is down; use `/health`, provider status, and the live dashboard together.

## 5. Verify Laravel, queue, and billing

```bash
curl -fsS http://127.0.0.1:8080/health
docker compose exec app php artisan queue:failed
docker compose logs --tail=100 queue-worker
docker compose exec app php artisan billing:reconcile --help
```

Submit one controlled test SMS from a test client. Confirm that the message moves through `ACCEPTED`, `SUBMITTED`, and a DLR state, and that the `billing_events` uniqueness rule prevents duplicate charges during retry or repeated DLR delivery.

## 6. Monitor live events

The native gateway exposes Server-Sent Events at `/live/events`. The Laravel monitoring page consumes this stream through the `live-traffic` Blade component. For a direct smoke test, use a browser or:

```bash
curl -N http://127.0.0.1:3001/live/events
```

The stream emits `client.bind`, `client.unbind`, `provider.bind`, `provider.down`, `provider.submit`, `message.submitted`, `message.dlr`, and `message.failed` events.

## 7. Backups and recovery

Back up PostgreSQL before upgrades and before changing routing or billing schema.

```bash
mkdir -p backups

docker compose exec -T postgres pg_dump -U smpp -d smpp | gzip > backups/smpp-$(date +%F-%H%M).sql.gz
docker compose exec redis redis-cli BGSAVE
```

To restore a database backup during a controlled maintenance window:

```bash
gunzip -c backups/smpp-YYYY-MM-DD-HHMM.sql.gz | docker compose exec -T postgres psql -U smpp -d smpp
```

Do not delete the PostgreSQL or Redis volumes during a normal application upgrade. Removing volumes destroys billing, users, invoices, routing, and SMS history.

## 8. Upgrade and rollback

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
docker compose build --pull
docker compose up -d

docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
```

If the new gateway build fails its health check, keep the previous image available, restore the database backup if a migration was applied, and roll back to the previous Git commit before restarting the stack. Never run two gateway containers on the same public SMPP port unless an external load balancer and sticky session strategy are configured.

## 9. Production hardening

Use TLS and an authenticated reverse proxy for the Laravel panel and gateway HTTP API. Do not expose the gateway `/api/v1/messages` endpoint publicly without an API key, IP allowlist, request signature, and rate limit. Store provider and customer passwords only through Laravel encryption, keep `APP_KEY` secret, rotate credentials during maintenance, and monitor failed binds, provider error rate, queue backlog, and low-balance alerts.
