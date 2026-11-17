# Changelog

All notable changes to this project are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0-beta] - 2026-11-22

First public beta. ArkVel is a multi-tenant, open-source adoption & management
platform for animal shelters, rescues and associations.

### Added

- **Public site**: adoption catalogue with search (Meilisearch), filters and map;
  animal pages with gallery; organisation directory & profiles; public adopter
  login/registration; privacy & terms pages; i18n (it/en); WCAG 2.2 AA.
- **Adopter portal** (`/account`): GDPR profile & consents, favourites, adoption
  and fostering applications, appointments, real-time messages, donations with
  history, notification centre, self-service account deletion (anonymisation).
- **Organisation management** (`/manage`): animals & medical records; adoption and
  fostering workflows (state machines) with PDF contracts; appointments with an
  in-app calendar (drag-and-drop) and bidirectional Google/CalDAV sync + iCal feed;
  medical scheduling with automated reminders; conversations; donations ledger.
- **Platform admin** (`/admin`): encrypted, config-gated settings for Google
  Calendar and Stripe credentials.
- **Platform**: multi-tenant isolation, team-scoped roles & permissions, real-time
  messaging & notifications (Reverb), branded transactional emails, activity log,
  donations via Stripe (one-off & recurring), Dockerised & self-hostable.

[Unreleased]: https://github.com/SirAlyon/arkVel/compare/v0.1.0-beta...HEAD
[0.1.0-beta]: https://github.com/SirAlyon/arkVel/releases/tag/v0.1.0-beta
