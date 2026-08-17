# One-command installation

This package builds Jasmin directly from the supplied `jasmin/` source archive and builds Laravel from the same project directory. PostgreSQL, Redis, RabbitMQ, Jasmin, Laravel, and the Redis queue worker are started by one command.

```bash
cd smpp-laravel12
chmod +x install-one-command.sh
./install-one-command.sh
```

The script creates `.env`, builds both application images, starts the services, generates the Laravel application key, and runs migrations. Before live SMS traffic, set `JASMIN_USERNAME` and `JASMIN_PASSWORD` in `.env` and provision a Jasmin user, SMPP provider connector, and route through jcli. The source archive is Jasmin 0.12, so this stack does not use the previous `jookies/jasmin:0.11` image.

| Service | Local endpoint |
|---|---|
| Laravel dashboard | `http://localhost:8080` |
| Jasmin native HTTP API | `http://localhost:1401` |
| Jasmin customer SMPP | `localhost:2775` |
| Jasmin jcli | `localhost:8990` |
| RabbitMQ management | `http://localhost:15672` |
