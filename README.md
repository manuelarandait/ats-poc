# ATS PoC

Minimal Application Tracking System: candidates apply to a job pasting their CV as text, and an asynchronous (mocked) AI process enriches each application with a summary and a relevance score.

## Documentation

- [Architecture](docs/ARCHITECTURE.md) ([español](docs/ARCHITECTURE.es.md)) — how the code is organised and why (hexagonal vs classic Symfony)
- [Work plan & decision log](docs/PLAN.md)

## Requirements

- Docker + Docker Compose
- GNU Make

## Run

```bash
make init
```

- App: http://localhost:8080
- RabbitMQ management: http://localhost:15672 (guest / guest)
- PostgreSQL: `localhost:5433`, database `app`, user `app` / password `app` (tests use a separate `app_test` database)

## Test

```bash
make test
```

Run `make` to list all available commands.
