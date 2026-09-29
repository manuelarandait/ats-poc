# ATS PoC — guide for Claude

Technical-exercise PoC: a micro Application Tracking System (DDD + Hexagonal + CQRS/events, async mocked-AI enrichment, Twig UI). The full brief and the iteration plan live in [docs/PLAN.md](docs/PLAN.md).

## Working agreement (most important rule)

- The author must be able to **defend every decision in an interview**. Never take a design or implementation decision silently: propose options with trade-offs + a recommendation, and wait for the author's answer.
- Work **one iteration at a time** (see the plan). Stop for review at the end of each iteration; one commit per iteration (or per coherent step inside it).
- Record every agreed decision in the *Decision log* of `docs/PLAN.md`, with its reason.
- Talk to the author in Spanish. Code, commits and docs are in English.

## Commands (everything runs in Docker)

```bash
make init              # build + start + composer install + DB + fixtures + worker
make test              # unit + integration
make qa                # php-cs-fixer (dry-run) + phpstan + deptrac
make logs              # follow the async worker
```

Never run PHP/Composer on the host except `composer require` (the platform is pinned to PHP 8.3 in composer.json).

## Architecture map

```
src/
  Recruitment/      bounded context: job offers & applications (owner of JobApplication)
    Domain/         aggregates, value objects, domain events, repository ports — pure PHP
    Application/    commands, queries, handlers, event subscribers (use cases)
    Infrastructure/ Doctrine repos + XML mapping, HTTP controllers, fixtures, messenger wiring
  Screening/        bounded context: AI enrichment (summary + relevance score)
  Shared/           tiny shared kernel: bus interfaces, AggregateRoot, base VOs, clock
```

Layer rules are enforced by Deptrac (`deptrac.yaml`). Details and conventions: skills `ddd-hexagonal-cqrs`, `php-symfony-standards`, `testing`; UI rules in `.claude/rules/ui-design.md`.
