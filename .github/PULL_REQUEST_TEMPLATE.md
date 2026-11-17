<!-- Thanks for contributing to ArkVel! Please fill in the sections below. -->

## What does this PR do?

<!-- A short description of the change and the motivation. Link any related issue. -->

Closes #

## Type of change

- [ ] 🐛 Bug fix
- [ ] ✨ Feature
- [ ] ♻️ Refactor
- [ ] 📝 Docs
- [ ] ✅ Tests
- [ ] 🔧 Chore / tooling

## Checklist

- [ ] My branch is **not** `main` and the commits follow [Conventional Commits](https://www.conventionalcommits.org/)
- [ ] New/changed behaviour is covered by **tests** (Pest)
- [ ] The **quality gate is green** in the container:
      `./vendor/bin/pint` · `./vendor/bin/phpstan analyse` · `./vendor/bin/pest`
- [ ] `declare(strict_types=1)` and full typing; domain logic lives in Actions, not controllers/models
- [ ] No secrets committed; user-facing strings are translated (it/en)
- [ ] Docs updated if needed (`docs/`, README)

## Notes for reviewers

<!-- Anything reviewers should pay special attention to (tenancy, security, migrations…). -->
