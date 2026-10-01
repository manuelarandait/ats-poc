# Work plan

Each iteration ends with green `make test` + `make qa`, a review with the author and a commit.

## Iterations

- [x] **0. Skeleton** — Docker (FrankenPHP, worker, Postgres, RabbitMQ), `make init`, three Messenger buses, Doctrine XML mapping, test suites.
- [x] **1. Standards & tooling** — CLAUDE.md, skills, rules, this plan; PHPStan (max), PHP-CS-Fixer, Deptrac, `make qa`; dama/doctrine-test-bundle, Foundry, DoctrineFixturesBundle.
- [x] **2. Recruitment domain** — `JobOffer` catalog, `JobApplication` aggregate, value objects, statuses, domain events. Unit tests only (no framework).
- [x] **CI** — GitHub Actions runs `make init`, `make qa` and `make test` on every PR and on `main`.
- [x] **3. Submit use case (command side)** — `SubmitJobApplication` command + handler, Doctrine repository, migrations, job-offer fixtures, domain events published after commit. Integration tests.
- [x] **4. Screening context (async enrichment)** — reacts to `JobApplicationSubmitted` via RabbitMQ, `CvAnalyzer` port + mocked LLM adapter, emits the result back; Recruitment attaches summary + score. Idempotency, retries, failure path. Tests.
- [x] **5. Read side (query side)** — list (newest first, filters by status/position, search by name/email) and detail queries, read models / DTOs. Integration tests.
- [x] **6. UI** — Apply, Applications (real-time filtering with Stimulus), Detail; Tailwind. Functional tests for the main flows.
- [ ] **7. Docs & polish** — README (run, test, architecture & event-flow overview), final review against acceptance criteria.

## Open questions (to decide in their iteration)


## Decision log

| # | Decision | Why |
|---|----------|-----|
| 1 | Symfony 8.1 / PHP 8.4 (upgraded from 7.4 LTS in iteration 1) | Author's daily stack; Messenger maps naturally to buses. Greenfield PoC → current stable over LTS; PHP 8.4 asymmetric visibility (`public private(set)`) fits aggregates/VOs. Trade-off: 8.1 maintained until 01/2027 — a long-lived product would pick 7.4 LTS or plan the 8.4 LTS upgrade. |
| 2 | RabbitMQ + dedicated worker container | Real messaging, closest to production; showcases async processing. |
| 3 | Everything in Docker, `make init` | Reviewer only needs Docker + Make. |
| 4 | Three buses (command / query / event) | Different semantics: transactional writes, side-effect-free reads, events with 0..n listeners. |
| 5 | Doctrine XML mapping in Infrastructure | Domain stays free of ORM/framework code. |
| 6 | Aggregate named `JobApplication` | Avoids clashing with the hexagonal *Application* layer. |
| 7 | Two bounded contexts: Recruitment + Screening, talking via events | Makes domain boundaries and event flow explicit (evaluation criteria). |
| 8 | Fixed job-offer catalog loaded with DoctrineFixturesBundle | Gives the AI mock something to score against; author's usual tooling. |
| 9 | PHPStan max + PHP-CS-Fixer + Deptrac (`make qa`) | Quality and architecture rules verified automatically, not just claimed. |
| 10 | AssetMapper + Stimulus + Tailwind (no Node) | Idiomatic Symfony, modern look, zero JS build pipeline. |
| 11 | PHPUnit + dama/doctrine-test-bundle + Foundry | Fast isolated DB tests; Foundry factories go through named constructors to respect the domain. |
| 12 | Plan, CLAUDE.md, skills and rules versioned in the repo | Transparent, disciplined AI-assisted workflow. |
| 13 | Test methods in snake_case (`test_it_rejects_invalid_email`) | Reads as a sentence in PHPUnit output; tests document behaviour. |
| 14 | Conventional Commits | Readable history by type (feat/fix/test/docs/chore/refactor). |
| 15 | Application layer framework-free: handlers implement own marker interfaces, tagged via `_instanceof` | Use cases don't know Messenger exists; framework swap wouldn't touch them. Enforced by Deptrac. Trade-off: less idiomatic than `#[AsMessageHandler]`, a bit more wiring. |
| 16 | Keep Symfony's official `AGENTS.md`, project skills take precedence on conflicts | Shows knowledge of the official guide and deliberate, justified deviations (AbstractController, attributes in domain). |
| 17 | Hiring pipeline `received → in_review → interviewing → hired`, `rejected` from any non-final status; rules live in the enum | Gives the status filter real meaning and a real invariant to protect; small `ChangeJobApplicationStatus` use case later. |
| 18 | Email mandatory, phone optional (normalised to `+digits`) | Email is the channel an ATS always needs; normalised phone is comparable and searchable. |
| 19 | Id value objects validate the UUID format in the domain; UUID v7 generated in Infrastructure before dispatch | Domain stays dependency-free; the caller knows the id (commands return nothing). |
| 20 | PHP 8.4 asymmetric visibility (`public private(set)`) instead of getters | State readable without boilerplate, mutable only through behaviour methods that enforce rules. |
| 21 | Position = reference to a `JobOffer` (by id) from the fixed catalog | Filtering by position = filtering by offer; the offer description is what the AI scores against. |
| 22 | AI result as `AiScreening` VO (summary + 0–100 score) plus explicit `ScreeningStatus` (`pending/completed/failed`), independent from the hiring status | UI can show "analysing…" / "failed" states; hiring and screening evolve separately. |
| 23 | Screening methods are idempotent: repeated result ignored, late failure never overrides a result, failed can still complete | RabbitMQ delivers at least once; retries must be safe. |
| 24 | Aggregates record events with primitives only; `JobApplicationSubmitted` carries ids for now | Serializable through the broker; whether it should carry the CV (event-carried state transfer) is decided in iteration 4. |
| 25 | CI on GitHub Actions reusing the Makefile inside Docker | Same commands locally and in CI, no drift; reviewers see a green check on every PR without cloning. Trade-off: slower than native `setup-php` (image build). |
| 26 | PostgreSQL stays (vs SQLite / in-memory) | App and worker are separate processes that must share state; persistence is a requirement; the list needs indexed filtering/search. Swappable thanks to the repository ports. |
| 27 | Events published after commit with Messenger's `dispatch_after_current_bus` (not a transactional outbox) | Native, no extra code; nobody sees rolled-back data. Trade-off: if RabbitMQ is down right after the commit the event is lost — production would use an outbox (store events in the same transaction, relay them). |
| 28 | Single-value VOs mapped with custom DBAL types; `Candidate` and `AiScreening` as embeddables (`candidate_*`, `ai_*` columns); a `postLoad` listener turns an all-NULL `AiScreening` into `null` | Domain untouched by persistence; score is a real indexed/sortable column for the list. Doctrine can't express nullable embeddables, so the workaround lives in Infrastructure. |
| 29 | No foreign key `job_application.job_offer_id → job_offer` | Aggregates reference each other by id (no ORM association); integrity guaranteed by the handler (`JobOfferNotFound`). A hand-written FK would be dropped by the next migrations diff. |
| 30 | Fixtures built through domain behaviour (submit, changeStatus, completeScreening), stable ids, recorded events discarded | Seeds can't create impossible states; demo data isn't re-screened. |
| 31 | `ChangeJobApplicationStatus` use case built now (UI in iteration 6) | Completes the command side; may be adjusted when the UI needs it. |
| 32 | Fat `JobApplicationSubmitted` (event-carried state transfer): `submit()` receives the `JobOffer`, the event carries CV + position title/description | Screening needs no call back to Recruitment; the contexts stay decoupled in time and code. Trade-off: bigger messages, CV text travels through the broker. |
| 33 | JSON wire format; the event name is the contract; each consumer owns its own class to read it (`app.event_consumers` map); consumer-driven contract tests | No context imports another's classes (Deptrac-verified); contract drift fails a test instead of production. Trade-off: some duplication of small event classes. |
| 34 | Only cross-context events are routed to RabbitMQ; the rest stay in-process (supersedes "all events async") | Routing config documents the integration flow; no useless messages on the broker. |
| 35 | The result travels back as Screening events (`cv_screened`, `cv_screening_failed`); Recruitment subscribers translate them into its own commands | Screening doesn't know Recruitment exists; the write goes through the command bus (transaction, rules, events) like any other. |
| 36 | Screening is stateless (domain service behind a `CvAnalyzer` port, no persistence) | The state that matters (result, status) belongs to the application in Recruitment; nothing to duplicate. |
| 37 | Retries by Messenger (3 retries, exponential 1 s ×2); when exhausted, a worker listener publishes `CvScreeningFailed`; the original message is kept in the Doctrine failure transport | Transient AI failures self-heal; permanent ones become a business fact instead of an eternal "pending", and can still be replayed (`messenger:failed:retry`). |
| 38 | Mock LLM: deterministic, explainable score (80 % skill coverage + 20 % seniority) and summary; latency and random failure rate via env; a CV marker forces a failure | Brief forbids real LLM calls; deterministic output is testable; latency/failures exercise the async UX and retry path. Real LLM = new `CvAnalyzer` adapter. |
| 39 | Async tests run a real Messenger `Worker` over the in-memory transport with serialization on (no back-off delay in test) | Tests exercise the same JSON contract, retry and failure listeners as production, in milliseconds. |
| 40 | All Symfony packages aligned on 8.1 + `symfony/doctrine-messenger` | Fixes an incomplete upgrade (some packages were still 7.4) and a failure transport that could not work without its bridge — both found by running the real stack. |
| 41 | Read side = plain SQL (DBAL) over the write tables straight into DTOs, behind read-model ports in Application (no projection table) | CQRS at code level: reads never load aggregates or the unit of work; strong consistency, no extra moving parts. A projection table is the next step if reads and writes ever need to scale apart. |
| 42 | Typed query bus: `Query<TResponse>` generics, `ask()` returns the declared DTO | Controllers get typed results with PHPStan max, no casts. |
| 43 | Search = case-insensitive "contains" (`ILIKE`) on name or email, backed by `pg_trgm` GIN indexes; user wildcards escaped | Fast substring search as data grows (verified with `EXPLAIN`: BitmapOr over both indexes). Not accent-insensitive (would need `unaccent`). |
| 44 | Btree indexes declared in the XML mapping; trigram indexes created in the migration and declared to Doctrine by a `postGenerateSchema` listener | Doctrine mapping can't express GIN/opclasses; without the listener every `migrations:diff` would propose dropping them. |
| 45 | Offset pagination (20 per page, max 100) with total count; tie-break on `id` (UUID v7, time-ordered) | Simple, fits filters and a page UI. Keyset pagination would be the choice for very large tables. |
| 46 | Detail DTO exposes `nextStatuses()` computed from the domain enum | The UI only offers transitions the domain accepts; the rule isn't duplicated. |
| 47 | Foundry factories build aggregates through `submit()` + behaviour (`inStatus()`, `screened()`) | Tests seed realistic data fast without bypassing domain invariants. |
| 48 | Keep Doctrine mapping in XML (reviewed: author usually prefers YAML) | Doctrine ORM 3.0 removed the YAML drivers; XML is the remaining option that keeps the domain free of ORM attributes, with XSD validation and IDE completion. Attributes would be simpler but couple the domain to Doctrine. |
| 49 | Apply flow: `/jobs` catalog → `/jobs/{id}` (description + form) → confirmation page (Post/Redirect/Get) | Matches the brief ("a page with a job description where a candidate submits"); the position is implicit; reloading never resubmits. |
| 50 | Real-time filtering with Turbo Frames + a tiny Stimulus debounce; frame requests render only the results partial; the URL follows the filters | Server-rendered HTML from one Twig template (no duplicated rendering in JS); shareable URLs and Back button work; still a plain GET form without JS. |
| 51 | "Pending → done" by polling: the server renders a poll marker inside the frame only while an analysis is pending | No extra infrastructure (vs Mercure); polling stops by itself when the HTML no longer contains the marker. |
| 52 | English UI with dark mode: `.dark` class strategy, OS preference by default, toggle remembered in `localStorage`, applied before paint | Brief is in English; dark mode requested by the author; no theme flash on load. |
| 53 | Symfony Form bound to a request DTO validated at the edge; domain rejections mapped back to their field; stateless CSRF for forms, session CSRF token for the status change | Friendly field errors, while the domain still validates everything (defence in depth). |
| 54 | Invokable controllers (one per action), no `AbstractController`, talking only to the command/query buses | Controllers stay thin adapters: HTTP ⇄ command/query. |
| 55 | Tailwind v4 through the standalone binary (symfonycasts/tailwind-bundle), component classes with `@apply`, built by `make init` | Modern styling without Node; one definition per component (buttons, cards, inputs), dark variants included. |
| 56 | Presentational pieces as anonymous Twig Components (`<twig:StatusBadge :status="…" />`) instead of Twig macros | Component-style, Vue-like syntax (`:prop`, attribute pass-through) that reads better in templates; template-only, no PHP classes needed. Class-based components, if ever needed, would live in `Shared/Infrastructure/Twig/Component`. |
