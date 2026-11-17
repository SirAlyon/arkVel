# Security Policy

## Supported versions

ArkVel is in **beta**. Security fixes are applied to the `main` branch; there is
no long-term support for older snapshots yet.

| Version | Supported |
| ------- | --------- |
| `main` (beta) | ✅ |
| older        | ❌ |

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report vulnerabilities privately through one of these channels:

1. **GitHub Private Vulnerability Reporting** — preferred.
   Go to the repository's **Security** tab → **Report a vulnerability**.
   _(Maintainers: enable this under Settings → Security → "Private vulnerability reporting".)_
2. **Email** — **security@kiba.studio** _(replace with your preferred contact)_.

Please include:

- a description of the issue and its potential impact;
- steps to reproduce (a proof of concept if possible);
- affected routes/components and any relevant configuration.

We aim to acknowledge reports within a few business days and to keep you updated
on the fix. Responsible disclosure is appreciated: give us a reasonable window
to release a fix before any public disclosure.

## Scope & notes

ArkVel handles personal data (adopters, organisations) and payments (Stripe,
behind a config-gated integration). Areas of particular interest:

- tenant isolation (no cross-organisation data leakage);
- authorization/policies and the permission model;
- the Stripe webhook (signature verification) and donation flows;
- GDPR features (consent, account anonymisation);
- any path that renders user-provided content.

Secrets are never committed: configuration lives in `.env` / platform settings
(encrypted at rest). If you find a secret in the git history, report it privately.
