# SMPP Control Plane — Laravel 12 + Jasmin 0.11

This project separates SMS transport from business logic: **Jasmin 0.11** sends SMS through SMPP connectors, while **Laravel 12** owns API validation, queue dispatch, routing/billing persistence, DLR state updates and audit data. PostgreSQL stores the records, Redis is the Laravel queue backend, and RabbitMQ is included for Jasmin's broker-oriented gateway environment.

## Local startup

Copy the environment template and set the Jasmin account credentials:

```bash
cp .env.example .env
# set JASMIN_USERNAME and JASMIN_PASSWORD
composer install
php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

The dashboard is available at `http://localhost:8080`. RabbitMQ management is available at `http://localhost:15672` using the credentials in `.env`. The queue consumer runs as a separate `queue-worker` service and consumes the `sms-submit` queue.

## SMS submission flow

`POST /api/v1/sms/send` validates `from`, `to`, `content`, and an optional `idempotency_key`. It creates the `sms_messages` row and dispatches `App\\Jobs\\SendSmsToJasmin` to Redis. The worker calls `App\\Services\\Jasmin\\JasminHttpAdapter`, which sends a form-encoded request to Jasmin `/send` with `username`, `password`, `to`, `from`, `content`, `coding`, `dlr=yes`, `dlr-url`, `dlr-level=2`, and `dlr-method=POST`. Jasmin returns a queued provider message ID; the job stores it in message metadata and marks the message `SUBMITTED`.

Jasmin posts delivery receipts to `POST /api/webhooks/jasmin/dlr`. `SmsController::jasminDlr` maps common Jasmin statuses such as `DELIVRD`, `UNDELIV`, `REJECTD`, and `EXPIRED` into the platform statuses and updates the CDR row. The callback URL must be reachable from the Jasmin container; for local Compose, `http://app/api/webhooks/jasmin/dlr` is the internal service URL.

## Queue behavior

The worker uses Redis with `php artisan queue:work redis --queue=sms-submit,default --sleep=3 --tries=5 --timeout=45 --backoff=5`. The job has backoff values of 5, 15, 45, and 120 seconds. On final failure it marks the SMS `FAILED` and stores the exception in metadata. Existing terminal statuses are ignored, which provides a basic idempotency guard.

## Production hardening

Before live traffic, replace the placeholder Jasmin account with a real Jasmin user and route, add API-key authentication and rate limiting, encrypt provider credentials, sign and verify callbacks, validate a shared DLR secret, implement transactional customer/provider ledger posting, add dead-letter monitoring, and configure a durable external PostgreSQL/Redis/RabbitMQ deployment. The included `jookies/jasmin:0.11` container is a local integration target; provider SMPP connector credentials and routes are intentionally deployment-specific.

References: [Jasmin HTTP API](https://docs.jasminsms.com/en/latest/apis/http/index.html), [Laravel Queues](https://laravel.com/docs/12.x/queues).
