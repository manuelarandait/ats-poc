---
name: ddd-hexagonal-cqrs
description: Architecture rules for this project (DDD bounded contexts, hexagonal layers, CQRS buses, domain & integration events, async flow). Use when creating any new class to decide its layer, name and dependencies, or when designing a new use case or event flow.
---

# DDD + Hexagonal + CQRS

## Bounded contexts

| Context | Owns | Talks to others |
|---------|------|-----------------|
| `Recruitment` | `JobOffer` catalog, `JobApplication` aggregate (candidate data, CV text, status, AI results once received) | Publishes `JobApplicationSubmitted`; reacts to Screening's result |
| `Screening` | Analysing a CV against a job offer (summary + relevance score) through an `AI` port | Reacts to `JobApplicationSubmitted`; publishes its result |
| `Shared` | Shared kernel only: bus interfaces, `AggregateRoot`, base value objects (`Uuid`), clock | Used by everyone, depends on nobody |

Contexts **never import each other's classes** (Deptrac enforces it). They communicate only through messages.

## Layers inside each context

```
Domain          → depends on: Shared\Domain only. Pure PHP, no Symfony, no Doctrine.
Application     → depends on: its own Domain, Shared\Domain.
Infrastructure  → depends on: anything (own Domain/Application, Shared, vendor).
```

- **Domain**: aggregates, entities, value objects, domain events, repository *interfaces* (ports), domain services, domain exceptions.
- **Application**: one folder per use case. `SubmitJobApplication/SubmitJobApplicationCommand.php` + `SubmitJobApplicationHandler.php`; queries return read DTOs, never entities.
- **Infrastructure**: adapters. Driving (HTTP controllers, console commands, message consumers) and driven (Doctrine repositories, AI mock, Messenger bus adapters).

## Naming

- Commands: imperative (`SubmitJobApplication`). Events: past tense (`JobApplicationSubmitted`). Queries: `Find…` / `Search…` / `List…`.
- One handler per command/query, `…Handler`, with `__invoke`. Event subscribers: `<Action>On<Event>` (e.g. `ScreenCvOnJobApplicationSubmitted`).
- Repository ports: `JobApplicationRepository` (interface, Domain) → `DoctrineJobApplicationRepository` (Infrastructure). In-memory versions live in `tests/`.

## CQRS rules

- **Commands** change state and return nothing (void). The id is generated before dispatch (UUID v7) so the caller already knows it.
- **Queries** return data and never change state. They may bypass the domain model and read with DBAL for efficiency.
- Command bus runs inside a DB transaction (`doctrine_transaction` middleware).

## Events

- Aggregates *record* domain events (`$this->record(new JobApplicationSubmitted(...))`); they don't dispatch them.
- The repository/handler pulls them (`pullDomainEvents()`) and publishes them on the event bus **after the transaction commits**, so consumers never see uncommitted data.
- Events are immutable, carry primitives + ids (serializable), and include `occurredOn`.
- Anything crossing a context boundary goes through RabbitMQ (`async` transport); consumers must be **idempotent** (at-least-once delivery).

## Flow of the main use case

```
HTTP POST /apply → SubmitJobApplication (command bus, sync, tx)
  → JobApplication::submit() records JobApplicationSubmitted
  → persisted, event published after commit → RabbitMQ
Worker: Screening reacts → CvAnalyzer (mock LLM) → publishes result
Worker: Recruitment reacts → JobApplication::attachScreening(summary, score)
UI polls/reads the query side → shows summary + score
```

## Checklist for a new class

1. Which context? Which layer? Does its folder already exist?
2. Does it depend only on what its layer allows?
3. Is it `final` (and `readonly` if a VO/DTO/message)?
4. Is there a unit test (Domain/Application) or integration test (Infrastructure)?
5. Does it change a documented decision? → update `docs/PLAN.md` decision log.
