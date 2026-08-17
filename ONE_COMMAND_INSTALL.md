# One-command installation

This repository builds the Laravel application and the native Node.js SMPP gateway. PostgreSQL, Redis, RabbitMQ, the gateway, Laravel, and the Redis queue worker are started by one command.

```bash
cd smpp-laravel12
chmod +x install-one-command.sh
./install-one-command.sh
```

The script creates `.env` from `.env.example`, builds the application images, waits for the native gateway health endpoint, starts the Laravel and queue-worker services, generates the Laravel application key, runs migrations, and caches the Laravel configuration. Before live SMS traffic, set `SMPP_GATEWAY_TOKEN`, provider credentials, mail settings, and alert settings in `.env`.

| Service | Local endpoint |
|---|---|
| Laravel dashboard | `http://localhost:8080` |
| Native gateway health/API | `http://localhost:3001` |
| Customer SMPP bind | `localhost:2775` |
| RabbitMQ management | `http://localhost:15672` |

Use `docker compose logs -f gateway` to inspect provider binds, routing, submissions, DLRs, and reconnect events. The complete production deployment procedure is in `docs/native-gateway-deployment.md`.
