# ArkVel — Agent guide and development standards

Open-source platform for animal adoption and management (municipal pounds, shelters,
associations, nonprofits) across Europe. Stack: **Laravel 13 + Filament 5 + PHP 8.4 + PostgreSQL 16**.

- **Plan & roadmap:** [docs/DEVELOPMENT-PLAN.md](docs/DEVELOPMENT-PLAN.md) — read it before working on a phase.
- **Application code:** lives in [laravel/](laravel/). The Docker infrastructure is in the repo root.
- Laravel/Filament-specific rules: [laravel/CLAUDE.md](laravel/CLAUDE.md).

---

## 1. How to run commands (ALWAYS via Docker)

The host PHP is 8.2 (too old). **Every** PHP/artisan/composer/tool command must be
run inside the `php` container (PHP 8.4):

```sh
docker compose exec -T php php artisan <cmd>
docker compose exec -T php composer <cmd>
docker compose exec -T php ./vendor/bin/pint
docker compose exec -T php ./vendor/bin/phpstan analyse --no-progress
docker compose exec -T php ./vendor/bin/pest
docker compose --profile testing run --rm browser-tests   # browser tests (Pest 4 + Playwright)
```

Browser tests (`laravel/tests/Browser`) run only in the `browser-tests` service (Debian image:
Playwright doesn't support the Alpine of the `php` container) and are excluded from the standard run.

If the stack isn't up: `docker compose up -d`. App at http://localhost:8090,
Mailpit at http://localhost:8025, Meilisearch at http://localhost:7700.

---

## 2. Quality gate — NON-negotiable

Before considering any code change **done**, all three must pass:

```sh
docker compose exec -T php ./vendor/bin/pint            # style (PSR-12, Laravel preset)
docker compose exec -T php ./vendor/bin/phpstan analyse # static analysis (Larastan level 6+)
docker compose exec -T php ./vendor/bin/pest            # tests
```

Shortcut: use the `/quality-gate` skill. CI ([.github/workflows/ci.yml](.github/workflows/ci.yml))
runs the same checks: if they fail locally, they'll fail in CI.

---

## 3. Testing policy — tests after EVERY feature

**Every new or changed feature must ship with its tests, in the same change.**
Code without tests is not considered complete.

- **Framework:** Pest 4. Files in `laravel/tests/{Unit,Feature}`.
- **Expected coverage:** domain (`app/Actions`, `app/States`, `app/Enums`) → **100%**.
  Critical flows (adoption, fostering, permissions, tenancy) → covered by feature tests.
- **Unit** tests for Actions/States/DTO/Enum (pure logic, without the DB where possible).
- **Feature** tests for HTTP/Filament flows: use `RefreshDatabase`, the model **factories**
  and the Filament/Livewire testing helpers.
- A fixed bug = a regression test that reproduces it.
- Write the expectation first (or at least alongside), not "later, if there's time left".

---

## 4. Design principles (SOLID + modern OOP)

We apply SOLID pragmatically, with idiomatic Laravel/PHP 8.4 OOP.

- **S — Single Responsibility.** One use case = one invokable **Action** class
  (`app/Actions/...`, a single `__invoke()`/`handle()`). Filament Controllers, Pages and Resources
  stay *thin*: they validate/authorize and delegate to the Action.
- **O — Open/Closed.** Extend by adding new Actions/States/Policies, not by bloating
  existing classes. Lifecycle states are added as new state classes.
- **L — Liskov.** Implementations of a contract are interchangeable; no
  overrides that break the base type's expectations.
- **I — Interface Segregation.** Small, focused contracts (e.g. `GeneratesContract`,
  `SendsNotification`), not catch-all interfaces.
- **D — Dependency Inversion.** Depend on interfaces for services with multiple
  implementations (payments, notifications, geocoding, storage); do the binding in the
  Service Providers. Inject via the constructor, **no `new`ing of dependencies** and no global state.

### OOP / PHP 8.4
- `declare(strict_types=1);` in **all** PHP files.
- Type **everything**: properties, parameters, return types. No `mixed` if avoidable.
- `readonly` for value objects/DTOs; promoted constructors; `enum` for every fixed
  set of values (implement `HasLabel`/`HasColor` for use in Filament).
- Prefer immutability; avoid setters that mutate shared state.
- No business logic in Models: Models hold relationships, casts, scopes,
  accessors/mutators. Logic lives in Actions/Services. Models stay "thin".
- Typed DTOs with `spatie/laravel-data` to pass data to Actions.
- Lifecycle (adoption/fostering) via `spatie/laravel-model-states`: **never** mutate
  a state column by hand, bypassing the state machine.

---

## 5. Cross-cutting domain rules

- **Authorization:** every Model has a **Policy**. Permissions go through
  `spatie/laravel-permission` in *teams* mode (team = organization/tenant).
- **Multi-tenancy:** organization data is isolated per tenant (Filament tenancy).
  Every query over organization resources must be tenant-scoped: never leak cross-tenant.
- **Privacy/GDPR by design:** minimize personal data, record consents,
  honor retention and the right to be forgotten (see plan §5/§Phase 5). Auditing goes through
  `spatie/laravel-activitylog`.
- **i18n:** translated UI strings (IT base, EN fallback); multilingual content via
  `spatie/laravel-translatable`. No hard-coded user-facing strings.
- **Validation:** always validate input (Filament schema / Form Request / `laravel-data`
  rules). Never trust incoming data.

---

## 6. Filament 5 conventions

- One **Resource** per Model; use the native components (form/table/infolist/schemas v5).
- Reuse form components by extracting them; don't duplicate schemas between create/edit.
- Use **RelationManagers** for relationships, **Widgets** for KPIs/dashboards.
- Domain actions in Resources → delegate to the **Action** classes, they contain no logic.
- Respect Policies: Resources must honor authorization and tenant scoping.
- When unsure about the v5 API, **check the docs** (`https://filamentphp.com/docs/5.x/...`)
  or Laravel Boost if installed — don't improvise method signatures.

---

## 7. Git & workflow

- **Never commit to `main`.** One branch per phase/feature (`rebuild/...`, `feat/...`, `fix/...`).
- **Conventional Commits** (`feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`).
- One commit = one coherent change, with its tests, quality gate green.
- Don't commit `.env`, `vendor/`, generated assets, or logs.

---

## 8. AI workflow (agents & skills)

Orchestration is done by **this main session**: it analyzes, plans and delegates to
specialized sub-agents. There is no (nor any need for) a separate "orchestrator agent".

> The AI tooling (specialized sub-agent definitions and project skills such as `/new-feature`
> and `/quality-gate`) lives **outside the repository** as local-only tooling — it is not checked
> in. The workflow it supports is described below so the standards stay reproducible without it.

The specialized sub-agents handle the recurring kinds of work and apply the standards in this file:

- **feature-builder** — implements a vertical feature end-to-end to these standards (with tests + quality gate).
- **filament-builder** — builds idiomatic Filament 5 Resources/Pages/Widgets.
- **test-author** — writes/extends Pest tests and runs the suite.
- **standards-reviewer** — read-only review of the diff against the project's SOLID/OOP standards.
- **ui-builder** — builds the public Livewire UI (`x-ui.*` components, pages) per
  [docs/FRONTEND-PLAN.md](docs/FRONTEND-PLAN.md). Doesn't touch Filament.
- **design-reviewer** — read-only visual and accessibility review (multi-viewport screenshots, WCAG 2.2 AA).

Supporting workflows:

- **new-feature** — full workflow: understand → design → implement → test → review → quality gate.
- **quality-gate** — runs pint + phpstan + pest and reports the outcome.
- **ui-review** — visual/accessibility review of the public pages touched (via `design-reviewer`).

**MCP** (`.mcp.json` in the repo root): `laravel-boost` (per-version docs, DB schema, browser logs),
`playwright` (navigation and screenshots), `chrome-devtools` (performance, console, network).

For large, parallelizable work you can use workflow orchestration (fanning out
multiple agents); do this only on explicit request.
