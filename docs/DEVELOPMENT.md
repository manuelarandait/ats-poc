# Development guide

🇪🇸 [Versión en español](DEVELOPMENT.es.md)

How to work on the project day to day. To get it running for the first time, see the [README](../README.md#quick-start); for how the code is organised, see [Architecture](ARCHITECTURE.md).

Everything runs in Docker: PHP, Composer and the tests never run on the host.

## Everyday commands

| Command | What it does |
|---|---|
| `make init` | First run: build, start, install dependencies, create the databases, load demo data, build the assets |
| `make up` / `make down` | Start / stop the containers |
| `make test` | All tests (`make test-unit`, `make test-integration` for one suite) |
| `make qa` | Coding standard (dry-run), PHPStan level max and Deptrac |
| `make cs-fix` | Fix the coding standard |
| `make db` | Create the databases and run pending migrations, for dev **and** test |
| `make fixtures` | Reload the demo data — **wipes** every application created by hand |
| `make assets` / `make css-watch` | Build the CSS once / on every template change |
| `make logs` | Follow the asynchronous worker |
| `make sh` | Shell into the app container |

Running part of the test suite:

```bash
docker compose exec app php bin/phpunit --filter BrowseJobApplicationsTest
```

## Configuration

Defaults live in `.env`; put local overrides in `.env.local` (git-ignored). The tests use `.env.test`. Variables set by `compose.yaml` are real environment variables and win over any `.env` file.

| Variable | Default | Purpose |
|---|---|---|
| `DATABASE_URL` | PostgreSQL in the `database` container (set by `compose.yaml`) | Application database (tests add the `_test` suffix) |
| `MESSENGER_TRANSPORT_DSN` | RabbitMQ in the `rabbitmq` container (set by `compose.yaml`) | Queue for the events between contexts |
| `MOCK_LLM_LATENCY_MS` | `1500` (`0` in tests) | How long the mocked LLM "thinks", so the UI shows *Analysing…* |
| `MOCK_LLM_FAILURE_RATE` | `0` | Share of mocked LLM calls that fail at random (`0`–`1`), to try the retries |
| `APPLY_RATE_LIMIT` | `5` | Valid applications accepted per IP every 15 minutes |

The web app reads `.env.local` on the next request; **the worker only on restart** (see below).

## Making changes

**Where new code goes.** Decide the bounded context and the layer first: business rules in `Domain`, use cases in `Application` (one folder per command or query), anything that touches Symfony, Doctrine or RabbitMQ in `Infrastructure`. `make qa` fails (Deptrac) if a dependency points the wrong way. The [folder layout](ARCHITECTURE.md#folder-layout) shows where each kind of class lives.

**Changing the database.** Mapping is XML, in `src/*/Infrastructure/Persistence/Doctrine/Mapping`. After changing it:

```bash
docker compose exec app php bin/console doctrine:migrations:diff   # generates migrations/VersionXXXX.php
make db                                                            # applies it to dev and test
```

Review the generated migration before committing it.

**Changing the UI.** Templates are Twig, with reusable pieces as Twig Components in `templates/components/`; behaviour is small Stimulus controllers in `assets/controllers/`. Run `make css-watch` while editing so Tailwind picks up new classes. UI conventions are in [`.claude/rules/ui-design.md`](../.claude/rules/ui-design.md).

## The asynchronous side

The `worker` container runs `messenger:consume async` and handles the AI enrichment.

- **It doesn't reload code.** After changing a subscriber, a handler that runs in the worker, anything in `Screening`, or `.env.local`, restart it: `docker compose restart worker`.
- **Watching it**: `make logs`; queues at http://localhost:15672 (guest / guest).
- **Failures**: a message that keeps failing is retried 3 times (1 s, 2 s, 4 s) and then stored in the failure transport (the `messenger_messages` table):

```bash
docker compose exec app php bin/console messenger:failed:show        # list
docker compose exec app php bin/console messenger:failed:show 1 -vv  # one message, with its exception
docker compose exec app php bin/console messenger:failed:retry       # replay
```

**Trying an outage and its recovery**: set `MOCK_LLM_FAILURE_RATE=1` in `.env.local`, restart the worker and apply; the application ends up *AI unavailable*. Set it back to `0`, restart the worker and run `messenger:failed:retry`: the application gets its summary and score. (A CV containing `[simulate-llm-failure]` always fails, so it is good for the failure path but not for the recovery.)

## Troubleshooting

| Symptom | Fix |
|---|---|
| `make init` fails: port already in use | Something on the host uses 8080, 5433 or 15672. Stop it, or change the published port in `compose.yaml`. |
| An application stays in *Analysing…* | The worker isn't running or is stuck: `docker compose ps`, then `make logs`. |
| A change in the worker's code has no effect | Restart the worker: `docker compose restart worker`. |
| *Too many applications from your network* | The rate limit (5 per 15 min). Raise `APPLY_RATE_LIMIT` in `.env.local`. |
| *Too many failed login attempts* | Login throttling: wait one minute. |
| New Tailwind classes have no style | `make assets` (or keep `make css-watch` running). |
| Tests fail after pulling new commits | A new migration: `make db`. A new dependency: `docker compose exec app composer install`. |
