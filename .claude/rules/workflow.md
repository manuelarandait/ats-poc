# Workflow rules

- **Ask before deciding.** For every non-trivial choice (naming, modelling, library, trade-off) present options + recommendation to the author and wait. Trivial, reversible details inside an agreed design don't need a question.
- Follow `docs/PLAN.md`: one iteration at a time, stop for review at the end. Don't start the next iteration without the author's go-ahead.
- Before writing code, load the relevant skill: `ddd-hexagonal-cqrs` (where/what), `php-symfony-standards` (how), `testing` (tests).
- Tests are written together with the code of each iteration, not afterwards. Definition of done: `make test` and `make qa` green.
- Commits: Conventional Commits (`feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `chore:`, optional scope e.g. `feat(recruitment):`), English, imperative, subject ≤ 72 chars + short body explaining *why*. Never commit with failing tests or QA.
- After each agreed decision, add a row to the *Decision log* in `docs/PLAN.md`.
- At the end of each iteration, summarise for the author (in Spanish): what was built, decisions taken and **arguments to defend them in the interview**, plus the questions for the next iteration.
