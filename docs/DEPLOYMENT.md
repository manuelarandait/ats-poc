# Deploying to production

🇪🇸 [Versión en español](DEPLOYMENT.es.md)

**The Docker setup in this repository is for local development**: the code is mounted as a volume, Symfony runs in the `dev` environment and there is a demo recruiter account. This page lists what a production deployment would need. None of it is required to run or evaluate the PoC.

## Runtime pieces

| Piece | Role | Scaling |
|---|---|---|
| **app** | HTTP: pages, commands and queries | Stateless once sessions and rate-limit counters are shared (see below): add instances behind a load balancer |
| **worker** | `messenger:consume async`: the AI enrichment and the events between contexts | One or more processes; add more when the queue grows |
| **PostgreSQL 16** | Application data and the failure transport | Needs the `pg_trgm` extension (the migrations create it; a managed database must allow it) |
| **RabbitMQ** | Events between bounded contexts | A managed broker or a cluster |

The app and the worker run the **same image** with a different command.

## Build

A production image differs from the development one in that it:

- **copies the code in** instead of mounting it, and installs dependencies with `composer install --no-dev --optimize-autoloader`;
- runs with `APP_ENV=prod` and `APP_DEBUG=0`, with the cache warmed up at build time (`cache:warmup`);
- **compiles the assets**: `tailwind:build --minify` and `asset-map:compile`, so they are served as static files.

## Configuration and secrets

| Variable | Production value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `prod` / `0` |
| `APP_SECRET` | A long random value, kept as a secret |
| `DATABASE_URL` | The production database, credentials as a secret |
| `MESSENGER_TRANSPORT_DSN` | The production broker, with its own user (not `guest`) |
| `SYMFONY_TRUSTED_PROXIES` | The load balancer's address range, so the client IP (used by the rate limiter) and HTTPS are detected correctly |
| `APPLY_RATE_LIMIT` | Abuse threshold for the apply form |

Secrets come from the platform (environment, secret manager) or Symfony's secrets vault, never from a committed `.env` file.

## Release steps

On every deploy:

1. **Build and push** the image.
2. **Run the migrations** before the new code receives traffic: `bin/console doctrine:migrations:migrate --no-interaction`. They must stay compatible with the version still running during the rollout.
3. **Prepare the broker** on the first deploy, or when transports change: `bin/console messenger:setup-transports`.
4. **Roll out** the app instances.
5. **Restart the workers** so they run the new code: `bin/console messenger:stop-workers` makes each one finish its current message and exit, and the process manager starts it again.

## Workers

- Run them under a process manager (systemd, Supervisor, Kubernetes) that restarts them when they exit.
- Recycle them regularly with `--time-limit` and `--memory-limit`, as the development setup already does with `--time-limit=3600`.
- **Watch the failure transport**: a message there means an analysis failed after its retries. Alert when it isn't empty; inspect with `messenger:failed:show` and replay with `messenger:failed:retry`.

## Running several app instances

- **Sessions** are stored in files on each instance by default: move them to a shared store (Redis or the database), or recruiters would be logged out when the load balancer switches instance.
- **Rate-limit counters** live in the app cache: point the `cache.rate_limiter` pool to Redis so every instance shares them.
- **Health check**: `/health` answers `{"status":"ok"}` for the load balancer. It only checks that PHP answers, not the database or the broker.
- **Logs** go to the container output (stderr), ready for the platform's log collector.

## Before going live

These are product gaps rather than deployment steps; they are explained in [Architecture → Trade-offs and next steps](ARCHITECTURE.md#trade-offs-and-next-steps):

- **A real user store** (users table or SSO) instead of the in-memory demo account, and remove the demo credentials shown on the login page.
- **A real LLM adapter** implementing `CvAnalyzer`, with its API key as a secret.
- **A transactional outbox**, so no event is lost if the broker is down right after a commit.
- **HTTPS** (FrankenPHP can obtain certificates automatically, or the load balancer terminates it) and **database backups**, which also cover the failure transport.
