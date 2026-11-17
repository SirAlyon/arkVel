# Contributing to ArkVel

Thank you for your interest in contributing to **ArkVel** — an open-source
adoption & management platform for animal shelters, rescues and associations. 🐾

Whether it's a bug report, a feature idea, documentation, or code, contributions
are welcome. Please also read our [Code of Conduct](CODE_OF_CONDUCT.md).

> 🔒 Found a security issue? **Do not open a public issue** — see [SECURITY.md](SECURITY.md).

---

## Ways to contribute

- **Report a bug** or **request a feature** via the issue templates.
- **Improve the docs** in [`docs/`](docs/) or the README.
- **Submit code** via a pull request (see the workflow below).

## Development setup

Everything runs in Docker (PHP 8.4, Composer and Node live in the containers — no
local PHP/Node required).

```bash
git clone https://github.com/SirAlyon/arkVel.git && cd arkVel
docker compose up -d

docker compose exec php composer install
docker compose exec php cp .env.example .env
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed          # demo data (dev only)
docker compose exec php sh -lc "npm ci && npm run build"
```

App: http://localhost:8090 · Mailpit: http://localhost:8025 · Meilisearch: http://localhost:7700

## Project standards

ArkVel aims for a clean, well-tested codebase. In short:

- **SOLID & modern OOP**: `declare(strict_types=1)` everywhere, full typing, no
  business logic in models. A use case = one invokable **Action**; controllers,
  Filament pages and Livewire components stay thin.
- **State machines** for lifecycles (adoption, fostering); **policies** for
  authorization; **multi-tenancy** — never leak data across organisations.
- **GDPR by design**, accessibility (WCAG 2.2 AA), and i18n (Italian base, English
  fallback) — no hard-coded user-facing strings.
- The detailed conventions live in [`CLAUDE.md`](CLAUDE.md) and
  [`laravel/CLAUDE.md`](laravel/CLAUDE.md).

## Tests & quality gate

Every change ships with its tests. Before opening a PR, all three must be green
(run inside the container):

```bash
docker compose exec php ./vendor/bin/pint            # code style (PSR-12)
docker compose exec php ./vendor/bin/phpstan analyse # static analysis (level 6)
docker compose exec php ./vendor/bin/pest            # tests (unit + feature + browser)
```

CI runs the same checks on every pull request.

## Pull request workflow

1. **Never commit to `main`.** Create a branch: `feat/…`, `fix/…`, `docs/…`, `refactor/…`.
2. Use **[Conventional Commits](https://www.conventionalcommits.org/)**
   (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`).
3. Keep each PR focused; include tests and update docs where relevant.
4. Make sure the **quality gate is green**, then open the PR and fill in the template.
5. A maintainer will review it. Thanks for helping animals find homes! 🐶🐱

## License

By contributing, you agree that your contributions are licensed under the
[AGPL-3.0](LICENSE), the same license that covers the project.
