# ATS PoC

Minimal Application Tracking System: candidates apply to a job pasting their CV as text, and an asynchronous (mocked) AI process enriches each application with a summary and a relevance score.

## Requirements

- Docker + Docker Compose
- GNU Make

## Run

```bash
make init
```

- App: http://localhost:8080
- RabbitMQ management: http://localhost:15672 (guest / guest)

## Test

```bash
make test
```

Run `make` to list all available commands.
