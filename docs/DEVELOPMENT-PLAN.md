# ArkVel — Development plan

> Open-source platform for animal adoption and management, designed for the
> **European** context (municipal/private pounds, shelters, associations, nonprofits).
> Inspired by [Baja Pet Rescue](https://github.com/kasugaijin/baja-pet-rescue) (Ruby on Rails),
> re-engineered on a Laravel + Filament stack.

Living document. Version 1 — 2026-09-26.

> **Status note (2026-11).** This is the original roadmap. The platform has since reached a
> **feature-complete beta**: Phases 0–4 (foundations, core domain, public catalog + adopter
> portal + applications, fostering/appointments/advanced medical, communication/notifications/
> donations) plus the public UI rebuild (UI-0…UI-8, see [FRONTEND-PLAN.md](FRONTEND-PLAN.md))
> are shipped. The roadmap below is preserved as originally written; later phases (5–8) may be
> partially delivered ahead of this document.

---

## 1. Goal and principles

**Mission.** Take ArkVel from a scaffold to a **production-ready platform** for managing
the complete lifecycle of an animal in a pound/shelter and the adoption/fostering process,
usable by multiple organizations (multi-tenant) across several European countries.

**Guiding principles.**
1. **Official stack.** Laravel 13 + Filament 5 + the Spatie/official ecosystem. No
   unmaintained packages; a preference for first-party solutions.
2. **Native multi-tenancy.** Each organization (pound/association/nonprofit) is an isolated tenant.
3. **Privacy by design (GDPR).** The adopter's personal data is a first-class citizen:
   consent, minimization, retention, the right to be forgotten.
4. **Multilingual.** IT/EN as the base, an architecture ready for ES/FR/DE.
5. **Explicit domain.** State machines for the adoption workflow, typed enums,
   Actions as testable units of business logic.
6. **Test-first on the domain.** Pest for unit/feature; the adoption workflow is 100% covered.

---

## 2. Technology stack

### Core
| Component | Choice | Notes |
|---|---|---|
| Framework | **Laravel 13.x** | Latest major |
| Admin/UI | **Filament 5.x** | Multiple panels + multi-tenancy |
| PHP | **8.4** (min 8.3) | |
| Database | **PostgreSQL 16** | JSONB, full-text, geospatial (PostGIS optional) |
| Cache/Queue | **Redis** | + Laravel Horizon |
| Search | **Meilisearch** (via Laravel Scout) | Animal catalog with filters/facets |
| Realtime | **Laravel Reverb** | Live notifications in the dashboard |
| Media storage | **European S3-compatible** (Scaleway/OVH/Hetzner) | EU data residency |
| Public frontend | **Livewire 3 + Blade + Tailwind** | Catalog and adopter portal |

### Official / ecosystem packages
- `spatie/laravel-permission` — roles and permissions (team-scoped per tenant)
- `spatie/laravel-medialibrary` (+ Filament plugin) — animal photos/documents
- `spatie/laravel-translatable` (+ Filament plugin) — multilingual content
- `spatie/laravel-activitylog` — audit trail
- `spatie/laravel-model-states` — adoption/fostering state machine
- `spatie/laravel-data` — DTOs for Actions and APIs
- `spatie/laravel-backup` + `spatie/laravel-health` — production operability
- `laravel/scout` + Meilisearch — search
- `laravel/cashier-stripe` — donations/fees (SEPA, SCA/PSD2)
- `laravel/sanctum` — API tokens for future apps/integrations
- `laravel/socialite` — social login (optional)
- `laravel/horizon`, `laravel/reverb`, `laravel/pulse`, `laravel/telescope` (dev)
- Dev: `pestphp/pest`, `larastan/larastan`, `laravel/pint`, `rector/rector`

---

## 3. Software architecture

### 3.1 Filament panels (3 panels + public site)

```
┌──────────────────────────────────────────────────────────────┐
│  Public site (Livewire)        → animal catalog, org pages     │
│    /                              search, animal detail         │
├──────────────────────────────────────────────────────────────┤
│  Adopter panel       /account  → profile, applications, favs    │
│                                   appointments, messages        │
├──────────────────────────────────────────────────────────────┤
│  Organization panel  /manage   → MULTI-TENANT (per org)         │
│                                   animals, applications, fosters,│
│                                   medical, staff, content        │
├──────────────────────────────────────────────────────────────┤
│  Admin panel         /admin    → platform super-admin:          │
│                                   org verification, global users,│
│                                   moderation, settings           │
└──────────────────────────────────────────────────────────────┘
```

**Tenancy.** The Organization panel uses Filament's native multi-tenancy, with
`Organization` as the tenant. A staff user can belong to multiple organizations (pivot
`organization_user`) with a per-organization role. `spatie/laravel-permission` in
"teams" mode maps permissions onto the current tenant.

### 3.2 Code structure (modular monolith)

```
app/
├── Enums/                 # Species, Sex, Size, AnimalStatus, ApplicationStatus...
├── Models/                # Eloquent
├── States/                # Adoption/ApplicationState (spatie/model-states)
│   └── Application/        #   Submitted, UnderReview, Interview, Approved, ...
├── Actions/               # Atomic, testable business logic
│   ├── Applications/       #   SubmitApplication, AdvanceApplication, ...
│   ├── Adoptions/          #   FinalizeAdoption, GenerateContract, ...
│   └── Fostering/
├── Policies/              # Authorization
├── Data/                  # DTOs (spatie/laravel-data)
├── Filament/
│   ├── Admin/             # Resources/Pages/Widgets of the admin panel
│   ├── Shelter/           # Resources/Pages/Widgets of the organization panel (tenant)
│   └── Adopter/           # Adopter panel
├── Livewire/              # Public site components
├── Notifications/         # Email/DB/broadcast
├── Support/               # Helpers, cross-cutting services
└── Providers/Filament/    # AdminPanelProvider, ShelterPanelProvider, AdopterPanelProvider
```

---

## 4. Data model (adapted to Europe)

Relationship legend: `1─*` one-to-many, `*─*` many-to-many.

### Main entities

**organizations** (pound/shelter/association/nonprofit) — *tenant*
- `name`, `slug`, `type` (enum: `canile_comunale`, `canile_privato`, `rifugio`,
  `associazione`, `societa`), `description` (translatable)
- EU legal data: `legal_name`, `vat_number` (VAT), `tax_code` (tax code),
  `registration_number`
- Address: `country` (ISO-3166), `region`, `province`, `city`, `postal_code`,
  `address_line`, `latitude`, `longitude`
- Contacts: `email`, `phone`, `website`, social
- `logo`, `cover`, `status` (enum: `pending`, `verified`, `suspended`), `verified_at`
- `1─*` animals, staff, applications, adoptions, appointments, donations

**users** — a single auth identity (no role column: role via spatie/permission)
- `name`, `email`, `password`, `locale`, `phone`, `email_verified_at`, optional 2FA
- `*─*` organizations (pivot `organization_user` with `role`, `verified`, `invited_at`)
- `1─1` adopter_profile

**animals** (dog/cat/other) — replaces `dogs`
- `organization_id`, `name`, `slug`, `species` (enum), `breed`, `sex` (enum),
  `birthdate` / `estimated_age`, `age_unit`, `size` (enum), `weight`, `color`
- Health: `microchip_number`, `sterilized`, `vaccinated`, `dewormed`,
  `fiv_felv`/`tests`, `health_notes`, `special_needs`
- Behavior: `temperament`, `energy_level`, `good_with_kids`,
  `good_with_dogs`, `good_with_cats`, `training_level`, `description` (translatable)
- Management: `status` (enum: `draft`, `available`, `reserved`, `adoption_pending`,
  `adopted`, `fostered`, `medical_hold`, `not_available`), `intake_date`,
  `intake_source`, `application_paused` + `pause_reason`, `featured`
- Media (medialibrary): photos (max N), documents
- `1─*` applications, medical_records, appointments; `1─1` adoption

**adopter_profiles** — screening questionnaire (GDPR-aware)
- `user_id`, contacts, EU address, `housing_type`, `has_garden`/`fenced`,
  `rent_or_own`, `landlord_allows_pets`, `adults_in_home`, `kids_in_home`,
  `other_pets`, `experience`, `hours_alone_weekday/weekend`, `lifestyle`,
  `ideal_animal`, `contingency_plan`, `previous_surrender`, `referral_source`
- GDPR consents: `consent_data_processing`, `consent_marketing`, `consented_at`
- `1─1` user

**adoption_applications** — application for an animal
- `animal_id`, `user_id` (adopter), `organization_id`, `assigned_to` (staff)
- `status` (state machine, see §5), `staff_notes`, `submitted_at`,
  `decided_at`, `decision_reason`
- unique constraint `(animal_id, user_id)` to avoid duplicates
- `*─1` animal, user

**adoptions** — finalized adoption
- `animal_id`, `user_id`, `organization_id`, `application_id`, `adopted_at`,
  `fee`, `currency` (EUR), `contract_path`, `follow_up_status`
- `1─1` animal

**foster_placements** + **foster_applications** — fostering (relevant in the EU)
- Analogous to adoptions but temporary: `start_date`, `end_date`, `type`
  (`pre_adoption`, `medical`, `emergency`, `long_term`)

**appointments** — visits/meetings with the animal
- `animal_id`, `user_id`, `organization_id`, `scheduled_at`, `type`
  (`visit`, `interview`, `home_check`), `status`, `notes`

**medical_records** — medical chart
- `animal_id`, `type` (`vaccination`, `treatment`, `surgery`, `checkup`,
  `deworming`), `date`, `vet_name`, `description`, `next_due_at`, attachments

**message_threads** / **messages** — organization ↔ adopter communication
- thread linked to an application/animal; in-app + email notifications

**donations**
- `organization_id`, `donor_user_id?`, `amount`, `currency` (EUR),
  `type` (`one_off`, `recurring`), `provider_ref` (Stripe), `status`

**favorites** — adopter wishlist (`user_id`, `animal_id`)

**content_pages** — mini-CMS for the organization's public page (translatable)

**Cross-cutting:** `activity_log` (audit), `notifications`, `media`,
`roles`/`permissions`/`model_has_roles` (spatie), `settings` (spatie-settings).

### Europe vs Baja adaptations
- Multi-species (not just dogs) and real multi-organization (Baja assumes 1 rescue).
- EU legal fields (VAT, tax code), EU addresses/regions, EUR currency.
- **Microchip** and (roadmap) integration with the national dog registry / EU pet passport.
- GDPR: explicit consents, retention, the right to be forgotten, approximate geolocation on public maps.
- **Fostering** as a first-class citizen (widely used in Italy/EU).

---

## 5. Adoption workflow (state machine)

Implemented with `spatie/laravel-model-states` on `adoption_applications.status`.

```
                 ┌──────────────┐
                 │  Submitted   │  (adopter submits; a complete profile is required)
                 └──────┬───────┘
                        ▼
                 ┌──────────────┐      withdraw ┌───────────┐
                 │ UnderReview  │──────────────▶│ Withdrawn │ (adopter)
                 └──────┬───────┘               └───────────┘
             ┌──────────┼───────────┐
             ▼          ▼           ▼
      ┌────────────┐ ┌──────────┐ ┌──────────┐
      │ Interview  │ │ Rejected │ │ Waitlist │
      │ /HomeCheck │ └──────────┘ └──────────┘
      └─────┬──────┘
            ▼
      ┌────────────┐        ┌────────────────┐
      │  Approved  │───────▶│ AdoptionPending│ (animal reserved)
      └────────────┘        └───────┬────────┘
                                    ▼
                            ┌───────────────┐
                            │   Completed   │ (creates Adoption + contract)
                            └───────────────┘
```

**Main rules/guards:**
- `Submitted` requires a complete `adopter_profile` + GDPR consents.
- Constraint: an animal can have only one application in `AdoptionPending`/`Completed`.
- Transitions allowed only to verified staff of the owning organization (Policy).
- Every transition → activity log + notification to the adopter.
- `Completed` is terminal: creates `Adoption`, updates `animal.status = adopted`,
  generates the contract PDF, sends email, closes the animal's other applications.

An analogous (reduced) workflow for **fostering**.

---

## 6. Roles and permissions

`spatie/laravel-permission` in **teams** mode (team = organization).

| Role | Scope | Main capabilities |
|---|---|---|
| **Super Admin** | Platform | Organization verification, global user management, settings, moderation |
| **Org Owner** | Organization | Everything about the organization + staff and billing management |
| **Org Staff** | Organization | Animals, applications, fosters, medical, appointments, messages |
| **Org Volunteer** | Organization | Read-only + notes (limited permissions) |
| **Adopter** | Themselves | Profile, applications, favorites, appointments, messages |

- Staff invited via email; `verified` before being able to operate (as in Baja, but
  the *organization's* verification is done by the Super Admin, the *staff's* by the Org Owner).
- Authorization applied via a **Policy** on every model + automatic tenant scoping.

---

## 7. Phased roadmap

Each phase is a releasable, tested increment. The estimates are indicative
(part-time, single-developer work).

### Phase 0 — Foundations (rebuild) · ~1 week
- Fresh Laravel 13 + Filament 5 install, PHP 8.4.
- Updated Docker Compose: PHP-FPM, Nginx, PostgreSQL, Redis, Meilisearch, Mailpit.
- Quality toolchain: Pint, Larastan (max level), Rector, Pest, GitHub Actions CI.
- Base conventions: Enums, the §3.2 folder structure, a documented `.env`.
- **Deliverable:** an app that runs in Docker, a green CI pipeline.

### Phase 1 — Core domain + Organization panel · ~2 weeks
- Migrations: organizations, users, memberships, animals, medical_records.
- Filament multi-tenancy + spatie/permission (teams).
- Auth (register/login/verify/reset, optional 2FA), seeded roles.
- Organization panel: Animals CRUD (with medialibrary + translatable), medical chart.
- Realistic factories + seeders (Italian Faker).
- **Deliverable:** staff manage their organization's animals end-to-end.

### Phase 2 — Public catalog + adopter portal + applications · ~3 weeks
- Public Livewire site: home, search/filters (Scout+Meilisearch), animal page,
  organization page, favorites.
- Adopter panel: profile/GDPR questionnaire, application submission, application status.
- Applications state machine (§5) + Actions + Policy + email/DB notifications.
- Organization panel: application management (kanban/table by status, notes, assignment).
- **Deliverable:** complete adoption flow submitted→completed with a contract PDF.

### Phase 3 — Fostering, appointments, advanced medical · ~2 weeks
- Fostering with its own workflow.
- Appointments/visits with a calendar and reminders.
- Medical scheduling (vaccines/boosters) with notifications.
- **Deliverable:** day-to-day operational management of the shelter.

### Phase 4 — Communication, notifications, donations · ~2 weeks
- Organization↔adopter messaging (threads) + realtime notifications (Reverb).
- Donations with Cashier/Stripe (SEPA, one-off + recurring).
- In-app notification center + branded transactional email.
- **Deliverable:** engagement and fundraising.

### Phase 5 — GDPR, i18n, advanced search · ~2 weeks
- Consent management, personal data export, deletion/anonymization,
  automatic retention (scheduled jobs), cookie consent, privacy policy.
- Complete IT/EN translations (interface + content), ready for ES/FR/DE.
- Search facets, saved searches, new-animal alerts.
- **Deliverable:** compliant and multi-country ready.

### Phase 6 — Platform governance + analytics · ~1-2 weeks
- Admin panel: organization verification/suspension, moderation, global users, settings.
- Dashboard/KPIs (adoptions, average times, conversion rate) with Filament widgets.
- Exportable reports (CSV/PDF).
- **Deliverable:** operability of the platform by the operator.

### Phase 7 — Hardening & production deploy · ~1-2 weeks
- Test coverage target (domain 100%, critical features), clean Larastan.
- Security: rate limiting, policy review, security headers, dependency audit.
- Observability: Pulse, health checks, Sentry/logs, automatic backups (spatie-backup).
- Deploy: Forge/Ploi or Kubernetes; Horizon + Reverb + scheduler as services;
  EU S3 storage; SSL; CI/CD with zero-downtime deploys.
- **Deliverable:** ArkVel in production.

### Phase 8 — Extensions (post-MVP)
- Public API (Sanctum) + possible mobile app.
- Integration with the national dog registry / microchip / EU pet passport.
- Import/export to other shelter management systems; aggregated adoption portals.
- Electronic contract signing; QR code on the animal page; PWA.

---

## 8. Testing, quality, CI/CD

- **Pest**: unit (Enums, States, Actions), feature (Filament/Livewire flows),
  architecture (Pest arch) to enforce the §3.2 conventions.
- **Larastan** at max level, **Pint** (Laravel preset), **Rector** for upgrades.
- **GitHub Actions**: lint → static analysis → test (Postgres+Redis services) →
  asset build → (deploy on tag).
- **Coverage**: adoption/fostering domain 100%; a regression on every bug.

---

## 9. Deployment & operations

- **EU infra** for data residency (Hetzner/Scaleway/OVH).
- Services: app (PHP-FPM+Nginx), PostgreSQL, Redis, Meilisearch, Reverb, Horizon,
  scheduler, EU S3-compatible storage.
- **Daily encrypted backups** (spatie-backup) + restore verification.
- **Health checks** (spatie-health) + alerting; **Pulse** for performance.
- Secrets via env/secret manager; migrations on deploy; `optimize` in the build.

---

## 10. Open decisions to confirm

1. **Adopter portal**: a dedicated Filament panel (faster) vs a custom Livewire
   frontend (more UX freedom). *Proposal: Filament for the dashboard,
   Livewire for the public catalog.*
2. **Supported species**: dogs/cats only at MVP or generic from the start?
   *Proposal: a generic enum, UI focused on dogs/cats initially.*
3. **Donation payments**: Stripe from the start or defer to Phase 4? *Proposal: Phase 4.*
4. **Pilot country/countries**: Italy only at first (simplifies legal/registry fields)
   or multi-country from the start? *Proposal: Italy as the pilot, a multi-country architecture.*
5. **Reuse vs clean rebuild**: confirmed a rebuild from scratch (a new skeleton),
   reusing this document and the Baja domain as a reference.
