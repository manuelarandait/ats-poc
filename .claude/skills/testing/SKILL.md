---
name: testing
description: Testing strategy and conventions for this project (unit vs integration, Object Mothers, in-memory repositories, Foundry, dama transactions, Messenger in-memory transport). Use when writing or reviewing any test.
---

# Testing

## Pyramid

| Suite | Location | What | Rules |
|-------|----------|------|-------|
| Unit | `tests/Unit/<Context>/…` | Domain (aggregates, VOs) and Application handlers | No kernel, no DB, no Symfony. Handlers get in-memory repos and fake buses. Milliseconds. |
| Integration | `tests/Integration/<Context>/…` | Adapters: Doctrine repos, DBAL queries, Messenger wiring, mock AI adapter | `KernelTestCase`; real Postgres (`app_test`), each test rolled back by dama/doctrine-test-bundle. |
| Functional | `tests/Integration/…/Http` | HTTP flows end to end (submit, list/filter/search, detail) | `WebTestCase`; async transport is `in-memory://`, assert messages were queued, then consume them explicitly to test the async path. |

Mirror the `src/` tree in `tests/`.

## Conventions

- Test class `final`, named `<Subject>Test`; methods in snake_case starting with `test_`: `test_it_rejects_an_invalid_email()`. They must read as a sentence in the PHPUnit output.
- Arrange / Act / Assert blocks separated by a blank line. One behaviour per test.
- Build domain objects with **Object Mothers** (`JobApplicationMother::submitted()`, `EmailMother::random()`) in `tests/<Context>/Domain/…Mother.php`. Never repeat raw constructor calls across tests.
- **Foundry** only for persisted fixtures in integration/functional tests. Factories must instantiate through the aggregate's named constructor (`instantiateWith(...)`) so invariants and recorded events are respected — no property hydration.
- Test behaviour, not implementation: assert on state and recorded/published events, not on which private method was called. Mock only ports you own (e.g. `CvAnalyzer`), never Doctrine or Symfony internals.
- Time is controlled with `MockClock`; ids are fixed in tests that assert on them.
- Each acceptance criterion from the brief must map to at least one test:
  - submission creates a record with `appliedAt` + default status
  - enrichment adds summary + score (happy path **and** failure/retry path)
  - list is newest first; filters by status and position; search by name/email
  - detail exposes all data including enrichment outputs

## Commands

```bash
make test               # everything
make test-unit
make test-integration
```
