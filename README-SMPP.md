# SMPP Control Plane — Laravel 12 + Native Node.js Gateway

This project separates business operations from SMPP transport. **Laravel 12** owns users, client SMPP accounts, rates, routing rules, billing, invoices, reports, and permanent PostgreSQL records. **Native Node.js** owns customer/provider SMPP binds, submit_sm, deliver_sm, provider selection, retry, failover, and live events. Redis provides queue and Pub/Sub transport, while RabbitMQ remains available for auxiliary platform integrations.

## Local startup

```bash
cp .env.example .env
# Set APP_KEY, SMPP_GATEWAY_TOKEN, provider credentials, mail and alert settings
composer install
npm install
php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

The dashboard is available at `http://localhost:8080`. Native gateway health is available at `http://localhost:3001/health`, customer SMPP binds listen on `localhost:2775`, and RabbitMQ management is available at `http://localhost:15672`. The queue consumer runs as a separate `queue-worker` service and consumes the `sms-submit` queue.

## SMS submission flow

`POST /api/v1/sms/send` validates `from`, `to`, `content`, and an optional `idempotency_key`. It creates the `sms_messages` row and dispatches `App\\Jobs\\SendSmsToGateway` to Redis. The job calls `App\\Services\\Gateway\\NativeSmppGatewayClient`, which sends an authenticated request to the Node.js gateway at `/api/v1/messages`. Node.js selects a provider using country, prefix, sender, priority, and health rules, sends `submit_sm`, and returns the provider message ID. The job records the provider correlation and posts submission billing.

The provider sends delivery receipts through `deliver_sm` to Node.js. The gateway maps `DELIVRD`, `UNDELIV`, `REJECTD`, and `EXPIRED` to platform statuses, updates the shared PostgreSQL `sms_messages` row, publishes a Redis event, and leaves Laravel's billing ledger to apply its duplicate-safe DLR transition.

## Queue behavior

The worker uses Redis with `php artisan queue:work redis --queue=sms-submit,default --sleep=1 --tries=8 --timeout=90`. The job uses exponential-style backoff values of 5, 15, 45, 120, 300, 600, and 900 seconds. A final failure marks the SMS `FAILED` and stores the exception in metadata. Existing terminal statuses and repeated idempotency keys are ignored.

## Live traffic

The Node.js gateway publishes events such as `client.bind`, `provider.bind`, `provider.down`, `message.submitted`, `message.dlr`, and `message.failed` to `/live/events` as Server-Sent Events. The Laravel Blade monitoring page includes an Alpine component and a Vite-powered React component at `resources/js/components/LiveTrafficMonitor.jsx`.

## Production hardening

Use TLS behind a reverse proxy, keep `SMPP_GATEWAY_TOKEN` and `APP_KEY` secret, apply IP allowlists and rate limits to the gateway API, encrypt provider credentials, back up PostgreSQL and Redis, ship structured logs, and load-test against provider test accounts before enabling international traffic.

See `docs/laravel-node-sync.md` for the database/API contract and `docs/native-gateway-deployment.md` for Docker deployment and recovery.
