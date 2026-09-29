# Architecture

This document explains **how the code is organised and why**, compared with the classic Symfony layout (`Controller/`, `Entity/`, `Repository/`, `Service/`). It describes concepts, not implementation details.

## The idea in one sentence

In a classic Symfony app **the framework sits in the centre** and business rules are spread inside it (ORM attributes on entities, services using the `EntityManager`, validation attributes on entities). Here it is inverted: **business rules sit in the centre, in plain PHP, and Symfony, Doctrine and RabbitMQ are plugs at the edge**.

The rule that holds everything together: **dependencies only point inwards**. The domain doesn't know Symfony exists; Symfony knows the domain. This is verified automatically by Deptrac (`make deptrac`), so it is a guarantee, not a convention.

```
┌──────────────────── Infrastructure ────────────────────┐
│  HTTP controllers, Doctrine, RabbitMQ, Twig, AI mock    │
│    ┌─────────────── Application ───────────────┐        │
│    │  Use cases: SubmitJobApplication, …        │        │
│    │    ┌───────────── Domain ─────────────┐    │        │
│    │    │  JobApplication, Email, rules …  │    │        │
│    │    └──────────────────────────────────┘    │        │
│    └────────────────────────────────────────────┘        │
└──────────────────────────────────────────────────────────┘
                 dependencies point → inwards
```

## Folder layout

```
src/
├── Recruitment/          bounded context: job offers and applications
│   ├── Domain/           WHAT the business is and its rules (plain PHP)
│   ├── Application/      WHAT can be DONE with it (use cases)
│   └── Infrastructure/   HOW it connects to the world (Symfony, Doctrine, HTTP…)
├── Screening/            bounded context: AI enrichment of CVs
└── Shared/               the minimum common to all (AggregateRoot, Uuid, bus interfaces)
```

### Domain — the core

Business rules and nothing else: an email must be valid, an application can't jump from `received` to `hired`, a repeated AI result is ignored. No `Symfony\…` or `Doctrine\…` imports.

*Why:* rules are the most valuable and longest-living part of the code. In plain PHP they are tested in milliseconds without booting a kernel or a database, and framework upgrades don't touch them (this project moved from Symfony 7.4 to 8.1 without changing a single domain line).

### Application — the use cases

Each thing the system can do is a pair of classes: a command/query (input data) and its handler (orchestration). Handlers contain no business rules: they load or create aggregates, call their behaviour, save them and publish their events.

*Why:* like classic `Service` classes, but one responsibility each and named after the business. Reading the folder tells you what the application does. Handlers implement our own interfaces, not Messenger's, so use cases don't depend on the framework either.

### Infrastructure — the adapters

Everything technology-specific: HTTP controllers, Doctrine repositories and XML mapping, message consumers, the AI mock, fixtures, templates.

*Why:* replacing PostgreSQL, RabbitMQ or the AI provider only touches this folder.

## Classic Symfony → this project

| Classic Symfony | Here | What changes |
|---|---|---|
| `Entity/JobApplication.php` with `#[ORM\Column]` | `Domain/…/JobApplication.php` + XML mapping in `Infrastructure/Persistence/Doctrine/Mapping` | Still Doctrine — the metadata just moves out of the class, so the entity stays clean. |
| `Repository/…Repository extends ServiceEntityRepository` | Interface in `Domain` + `Doctrine…Repository` in `Infrastructure` | The domain declares *what* it needs (a **port**); infrastructure decides *how* (an **adapter**) — hence "ports & adapters". |
| `#[Assert\Email]` on the entity | `Email::fromString()` value object (plus form/DTO validation at the edge) | The rule travels with the data: an invalid `Email` can't exist anywhere. |
| `$entity->setStatus('hired')` | `$application->changeStatus(JobApplicationStatus::Hired, $now)` | No setters: only business-named methods that protect the rules. State is readable (`public private(set)`) but not writable from outside. |
| `Service/ApplicationService.php` | `Application/SubmitJobApplication/…Handler.php` | One use case per class. |
| `Controller/` | `Infrastructure/Http/` | Translates HTTP → command/query, nothing else. |
| API Platform State Processor / Provider | Command handler / Query handler | Same idea: API Platform processors and providers already are adapters around a use case. |

## CQRS and events

- **Commands** change state and return nothing; **queries** return data and change nothing. They travel on separate buses because their semantics differ (writes run inside a transaction; reads can skip the domain model and read straight from the database for efficiency).
- **Aggregates record domain events** (`JobApplicationSubmitted`) instead of calling other services. Events are published after the transaction commits, so no one reacts to data that was rolled back.

## Why two bounded contexts

`Recruitment` (applications, hiring pipeline) and `Screening` (analysing a CV with AI) speak different languages and change for different reasons. If one imported the other's classes they would become a single coupled block. They **only communicate through events over RabbitMQ**, and Deptrac fails if either imports the other.

```
Recruitment                     RabbitMQ                    Screening
JobApplicationSubmitted  ──────────────────────────────▶  analyse CV (AI mock)
attach summary + score   ◀──────────────────────────────  screening result
```

## Enforced, not just drawn

| Rule | Checked by |
|---|---|
| Domain has no framework/ORM dependency | Deptrac |
| Application has no framework dependency | Deptrac |
| Contexts never import each other | Deptrac |
| Types are sound | PHPStan (level max) |
| Business rules behave as specified | Unit tests (no kernel, no DB) |
| Adapters work with real PostgreSQL / Messenger | Integration tests |

## Trade-offs

Hexagonal architecture is not free: more files, more indirection, some mapping between layers. For a plain CRUD, a classic Symfony + API Platform approach is more productive. It pays off when business rules are rich, the code must live for years, or — as here — asynchronous flows between separate parts of the domain need clear boundaries.
