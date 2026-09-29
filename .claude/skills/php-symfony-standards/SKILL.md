---
name: php-symfony-standards
description: PHP 8.3 and Symfony 7.4 coding standards for this project. Use when writing or reviewing any PHP class, Symfony config, controller, console command or Doctrine code.
---

# PHP & Symfony standards

## PHP

- `declare(strict_types=1);` in every file. PSR-4 under `App\`.
- Classes are `final` by default; value objects and DTOs are `final readonly`. Only open a class when there is a real extension point.
- Constructor property promotion; `private` by default. No setters on domain objects: behaviour methods with intention-revealing names (`markAsScreened()`, not `setScore()`).
- Named constructors for domain objects (`JobApplication::submit(...)`, `Email::fromString(...)`); keep `__construct` private when it has invariants.
- Native types everywhere (params, returns, properties). Use PHPDoc only for what types can't say (`list<Foo>`, `array{id: string}`, `non-empty-string`) — it must pass PHPStan level max.
- Backed `enum`s for closed sets (statuses). Put small behaviour on the enum (`canTransitionTo()`).
- Fail fast with specific exceptions (`InvalidEmail extends DomainException`), never generic `\Exception`. Exceptions are named after the problem, no `Exception` suffix needed in the domain.
- No `null` for "missing but expected" collections — use empty arrays. `null` only when absence is a real domain state (e.g. `?AiScore` before screening).
- Time is injected (`ClockInterface` from `symfony/clock`); never `new \DateTimeImmutable()` inside domain or handlers. Use `DateTimeImmutable` only.
- Early returns, no `else` after `return`, small methods. No static helpers/singletons.

## Symfony

- Configuration via attributes for wiring (`#[AsMessageHandler]`, `#[Route]`, `#[AsCommand]`) — but **only in Infrastructure/Application**, never in Domain.
- Controllers are thin, invokable (`__invoke`), one action per class, in `Infrastructure/Http`. They translate HTTP ⇄ command/query and nothing else: no Doctrine, no business rules.
- Input validation at the edge with Symfony Validator on a request DTO (`#[MapRequestPayload]` / form DTO); domain invariants are validated again inside value objects (defence in depth, the domain never trusts the edge).
- Dispatch through our own bus interfaces (`CommandBus`, `QueryBus`, `EventBus` in `Shared/Domain/Bus`), implemented by Messenger adapters in `Shared/Infrastructure`. Application code never depends on `MessageBusInterface` directly.
- Autowiring + autoconfigure; bind interfaces to implementations in `config/services.yaml` only when autowiring can't resolve them.
- Doctrine: XML mapping in `<Context>/Infrastructure/Persistence/Doctrine/Mapping`, custom DBAL types for value objects, migrations generated with `doctrine:migrations:diff` and reviewed by hand. Never `doctrine:schema:update --force`.
- Environment config through env vars (`.env` has safe dev defaults only). No secrets committed.
- Logging through PSR-3 `LoggerInterface` with context arrays, never string concatenation.

## Tooling (must pass before committing)

- `make cs` — PHP-CS-Fixer with `@Symfony` + `@Symfony:risky` rule sets.
- `make stan` — PHPStan level max with the Symfony and Doctrine extensions.
- `make deptrac` — layer and context boundaries (see skill `ddd-hexagonal-cqrs`).
- `make qa` runs all three.
 