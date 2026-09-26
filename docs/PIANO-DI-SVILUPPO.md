# ArkVel — Piano di sviluppo

> Piattaforma open-source per l'adozione e la gestione di animali, pensata per il
> contesto **europeo** (canili comunali/privati, rifugi, associazioni, società).
> Ispirata a [Baja Pet Rescue](https://github.com/kasugaijin/baja-pet-rescue) (Ruby on Rails),
> reingegnerizzata su stack Laravel + Filament.

Documento vivo. Versione 1 — 2026-09-26.

---

## 1. Obiettivo e principi

**Missione.** Portare ArkVel da scaffold a **piattaforma production-ready** per la gestione
del ciclo di vita completo dell'animale in canile/rifugio e del processo di adozione/affido,
utilizzabile da più enti (multi-tenant) in più paesi europei.

**Principi guida.**
1. **Stack ufficiale.** Laravel 13 + Filament 5 + ecosistema Spatie/ufficiale. Niente
   pacchetti non mantenuti; preferenza per soluzioni di prima parte.
2. **Multi-tenant nativo.** Ogni ente (canile/associazione/società) è un tenant isolato.
3. **Privacy by design (GDPR).** Il dato personale dell'adottante è cittadino di prima classe:
   consenso, minimizzazione, retention, diritto all'oblio.
4. **Multilingua.** IT/EN come base, architettura pronta per ES/FR/DE.
5. **Dominio esplicito.** Macchine a stati per il workflow di adozione, enum tipizzati,
   azioni (Actions) come unità di business logic testabili.
6. **Test-first sul dominio.** Pest per unit/feature; il workflow di adozione è coperto al 100%.

---

## 2. Stack tecnologico

### Core
| Componente | Scelta | Note |
|---|---|---|
| Framework | **Laravel 13.x** | Ultima major |
| Admin/UI | **Filament 5.x** | Pannelli multipli + multi-tenancy |
| PHP | **8.4** (min 8.3) | |
| Database | **PostgreSQL 16** | JSONB, full-text, geospaziale (PostGIS opzionale) |
| Cache/Queue | **Redis** | + Laravel Horizon |
| Ricerca | **Meilisearch** (via Laravel Scout) | Catalogo animali con filtri/facet |
| Realtime | **Laravel Reverb** | Notifiche live in dashboard |
| Storage media | **S3-compatibile europeo** (Scaleway/OVH/Hetzner) | Data residency UE |
| Frontend pubblico | **Livewire 3 + Blade + Tailwind** | Catalogo e portale adottanti |

### Pacchetti ufficiali / ecosistema
- `spatie/laravel-permission` — ruoli e permessi (team-scoped per tenant)
- `spatie/laravel-medialibrary` (+ plugin Filament) — foto/documenti animali
- `spatie/laravel-translatable` (+ plugin Filament) — contenuti multilingua
- `spatie/laravel-activitylog` — audit trail
- `spatie/laravel-model-states` — macchina a stati adozione/affido
- `spatie/laravel-data` — DTO per Actions e API
- `spatie/laravel-backup` + `spatie/laravel-health` — operatività produzione
- `laravel/scout` + Meilisearch — ricerca
- `laravel/cashier-stripe` — donazioni/quote (SEPA, SCA/PSD2)
- `laravel/sanctum` — API token per app/integrazioni future
- `laravel/socialite` — login social (opzionale)
- `laravel/horizon`, `laravel/reverb`, `laravel/pulse`, `laravel/telescope` (dev)
- Dev: `pestphp/pest`, `larastan/larastan`, `laravel/pint`, `rector/rector`

---

## 3. Architettura software

### 3.1 Pannelli Filament (3 pannelli + sito pubblico)

```
┌──────────────────────────────────────────────────────────────┐
│  Sito pubblico (Livewire)      → catalogo animali, pagine ente │
│    /                              ricerca, dettaglio animale    │
├──────────────────────────────────────────────────────────────┤
│  Pannello Adottante  /account  → profilo, candidature, favoriti│
│                                   appuntamenti, messaggi        │
├──────────────────────────────────────────────────────────────┤
│  Pannello Ente       /manage   → MULTI-TENANT (per organizz.)  │
│                                   animali, candidature, affidi, │
│                                   medico, staff, contenuti      │
├──────────────────────────────────────────────────────────────┤
│  Pannello Admin      /admin    → super-admin di piattaforma:   │
│                                   verifica enti, utenti globali,│
│                                   moderazione, impostazioni     │
└──────────────────────────────────────────────────────────────┘
```

**Tenancy.** Il pannello Ente usa la multi-tenancy nativa di Filament, con
`Organization` come tenant. Un utente staff può appartenere a più enti (pivot
`organization_user`) con ruolo per-ente. `spatie/laravel-permission` in modalità
"teams" mappa i permessi sul tenant corrente.

### 3.2 Struttura del codice (modular monolith)

```
app/
├── Enums/                 # Species, Sex, Size, AnimalStatus, ApplicationStatus...
├── Models/                # Eloquent
├── States/                # Adoption/ApplicationState (spatie/model-states)
│   └── Application/        #   Submitted, UnderReview, Interview, Approved, ...
├── Actions/               # Business logic atomica e testabile
│   ├── Applications/       #   SubmitApplication, AdvanceApplication, ...
│   ├── Adoptions/          #   FinalizeAdoption, GenerateContract, ...
│   └── Fostering/
├── Policies/              # Autorizzazione
├── Data/                  # DTO (spatie/laravel-data)
├── Filament/
│   ├── Admin/             # Resources/Pages/Widgets pannello admin
│   ├── Shelter/           # Resources/Pages/Widgets pannello ente (tenant)
│   └── Adopter/           # Pannello adottante
├── Livewire/              # Componenti sito pubblico
├── Notifications/         # Email/DB/broadcast
├── Support/               # Helper, servizi trasversali
└── Providers/Filament/    # AdminPanelProvider, ShelterPanelProvider, AdopterPanelProvider
```

---

## 4. Modello dati (adattato all'Europa)

Legenda relazioni: `1─*` uno-a-molti, `*─*` molti-a-molti.

### Entità principali

**organizations** (canile/rifugio/associazione/società) — *tenant*
- `name`, `slug`, `type` (enum: `canile_comunale`, `canile_privato`, `rifugio`,
  `associazione`, `societa`), `description` (translatable)
- Dati legali UE: `legal_name`, `vat_number` (P.IVA), `tax_code` (cod. fiscale),
  `registration_number`
- Indirizzo: `country` (ISO-3166), `region`, `province`, `city`, `postal_code`,
  `address_line`, `latitude`, `longitude`
- Contatti: `email`, `phone`, `website`, social
- `logo`, `cover`, `status` (enum: `pending`, `verified`, `suspended`), `verified_at`
- `1─*` animals, staff, applications, adoptions, appointments, donations

**users** — identità auth unica (no colonna ruolo: ruolo via spatie/permission)
- `name`, `email`, `password`, `locale`, `phone`, `email_verified_at`, 2FA opz.
- `*─*` organizations (pivot `organization_user` con `role`, `verified`, `invited_at`)
- `1─1` adopter_profile

**animals** (cane/gatto/altro) — sostituisce `dogs`
- `organization_id`, `name`, `slug`, `species` (enum), `breed`, `sex` (enum),
  `birthdate` / `estimated_age`, `age_unit`, `size` (enum), `weight`, `color`
- Salute: `microchip_number`, `sterilized`, `vaccinated`, `dewormed`,
  `fiv_felv`/`tests`, `health_notes`, `special_needs`
- Comportamento: `temperament`, `energy_level`, `good_with_kids`,
  `good_with_dogs`, `good_with_cats`, `training_level`, `description` (translatable)
- Gestione: `status` (enum: `draft`, `available`, `reserved`, `adoption_pending`,
  `adopted`, `fostered`, `medical_hold`, `not_available`), `intake_date`,
  `intake_source`, `application_paused` + `pause_reason`, `featured`
- Media (medialibrary): foto (max N), documenti
- `1─*` applications, medical_records, appointments; `1─1` adoption

**adopter_profiles** — questionario di screening (GDPR-aware)
- `user_id`, contatti, indirizzo UE, `housing_type`, `has_garden`/`fenced`,
  `rent_or_own`, `landlord_allows_pets`, `adults_in_home`, `kids_in_home`,
  `other_pets`, `experience`, `hours_alone_weekday/weekend`, `lifestyle`,
  `ideal_animal`, `contingency_plan`, `previous_surrender`, `referral_source`
- Consensi GDPR: `consent_data_processing`, `consent_marketing`, `consented_at`
- `1─1` user

**adoption_applications** — candidatura per un animale
- `animal_id`, `user_id` (adottante), `organization_id`, `assigned_to` (staff)
- `status` (state machine, vedi §5), `staff_notes`, `submitted_at`,
  `decided_at`, `decision_reason`
- vincolo unico `(animal_id, user_id)` per evitare doppioni
- `*─1` animal, user

**adoptions** — adozione finalizzata
- `animal_id`, `user_id`, `organization_id`, `application_id`, `adopted_at`,
  `fee`, `currency` (EUR), `contract_path`, `follow_up_status`
- `1─1` animal

**foster_placements** + **foster_applications** — affido (rilevante in UE)
- Analogo alle adozioni ma temporaneo: `start_date`, `end_date`, `type`
  (`pre_adoption`, `medical`, `emergency`, `long_term`)

**appointments** — visite/incontri con l'animale
- `animal_id`, `user_id`, `organization_id`, `scheduled_at`, `type`
  (`visit`, `interview`, `home_check`), `status`, `notes`

**medical_records** — cartella sanitaria
- `animal_id`, `type` (`vaccination`, `treatment`, `surgery`, `checkup`,
  `deworming`), `date`, `vet_name`, `description`, `next_due_at`, allegati

**message_threads** / **messages** — comunicazione ente ↔ adottante
- thread legato a candidatura/animale; notifiche in-app + email

**donations**
- `organization_id`, `donor_user_id?`, `amount`, `currency` (EUR),
  `type` (`one_off`, `recurring`), `provider_ref` (Stripe), `status`

**favorites** — wishlist adottante (`user_id`, `animal_id`)

**content_pages** — mini-CMS per pagina pubblica ente (translatable)

**Trasversali:** `activity_log` (audit), `notifications`, `media`,
`roles`/`permissions`/`model_has_roles` (spatie), `settings` (spatie-settings).

### Adattamenti Europa vs Baja
- Multi-specie (non solo cani) e multi-ente reale (Baja assume 1 rescue).
- Campi legali UE (P.IVA, codice fiscale), indirizzi/regioni UE, valuta EUR.
- **Microchip** e (roadmap) integrazione anagrafe canina / EU pet passport.
- GDPR: consensi espliciti, retention, oblio, geolocalizzazione approssimata sulle mappe pubbliche.
- **Affido** come cittadino di prima classe (molto usato in Italia/UE).

---

## 5. Workflow di adozione (macchina a stati)

Implementato con `spatie/laravel-model-states` su `adoption_applications.status`.

```
                 ┌──────────────┐
                 │  Submitted   │  (adottante invia; profilo completo obbligatorio)
                 └──────┬───────┘
                        ▼
                 ┌──────────────┐      withdraw ┌───────────┐
                 │ UnderReview  │──────────────▶│ Withdrawn │ (adottante)
                 └──────┬───────┘               └───────────┘
             ┌──────────┼───────────┐
             ▼          ▼           ▼
      ┌────────────┐ ┌──────────┐ ┌──────────┐
      │ Interview  │ │ Rejected │ │ Waitlist │
      │ /HomeCheck │ └──────────┘ └──────────┘
      └─────┬──────┘
            ▼
      ┌────────────┐        ┌────────────────┐
      │  Approved  │───────▶│ AdoptionPending│ (animale riservato)
      └────────────┘        └───────┬────────┘
                                    ▼
                            ┌───────────────┐
                            │   Completed   │ (crea Adoption + contratto)
                            └───────────────┘
```

**Regole/guardie principali:**
- `Submitted` richiede `adopter_profile` completo + consensi GDPR.
- Vincolo: un animale può avere una sola candidatura in `AdoptionPending`/`Completed`.
- Transizioni consentite solo a staff verificato dell'ente proprietario (Policy).
- Ogni transizione → activity log + notifica all'adottante.
- `Completed` è terminale: crea `Adoption`, aggiorna `animal.status = adopted`,
  genera contratto PDF, invia email, chiude le altre candidature dell'animale.

Workflow analogo (ridotto) per **affidi**.

---

## 6. Ruoli e permessi

`spatie/laravel-permission` in modalità **teams** (team = organization).

| Ruolo | Scope | Capacità principali |
|---|---|---|
| **Super Admin** | Piattaforma | Verifica enti, gestione utenti globali, impostazioni, moderazione |
| **Org Owner** | Ente | Tutto sull'ente + gestione staff e fatturazione |
| **Org Staff** | Ente | Animali, candidature, affidi, medico, appuntamenti, messaggi |
| **Org Volunteer** | Ente | Sola lettura + note (permessi limitati) |
| **Adopter** | Sé stesso | Profilo, candidature, favoriti, appuntamenti, messaggi |

- Staff invitato via email; `verified` prima di poter operare (come Baja, ma
  la verifica dell'*ente* la fa il Super Admin, quella dello *staff* l'Org Owner).
- Autorizzazione applicata via **Policy** su ogni modello + tenant scoping automatico.

---

## 7. Roadmap per fasi

Ogni fase è un incremento rilasciabile e testato. Le stime sono indicative
(sviluppo part-time singolo dev).

### Fase 0 — Fondamenta (rebuild) · ~1 settimana
- Nuova install Laravel 13 + Filament 5, PHP 8.4.
- Docker Compose aggiornato: PHP-FPM, Nginx, PostgreSQL, Redis, Meilisearch, Mailpit.
- Toolchain qualità: Pint, Larastan (livello max), Rector, Pest, GitHub Actions CI.
- Convenzioni base: Enums, struttura cartelle §3.2, `.env` documentato.
- **Deliverable:** app che gira in Docker, pipeline CI verde.

### Fase 1 — Dominio core + pannello Ente · ~2 settimane
- Migrazioni: organizations, users, memberships, animals, medical_records.
- Multi-tenancy Filament + spatie/permission (teams).
- Auth (register/login/verify/reset, 2FA opz.), ruoli seedati.
- Pannello Ente: CRUD Animali (con medialibrary + translatable), cartella medica.
- Factory + seeder realistici (Faker IT).
- **Deliverable:** uno staff gestisce animali del proprio ente end-to-end.

### Fase 2 — Catalogo pubblico + portale adottante + candidature · ~3 settimane
- Sito pubblico Livewire: home, ricerca/filtri (Scout+Meilisearch), scheda animale,
  pagina ente, favoriti.
- Pannello Adottante: profilo/questionario GDPR, invio candidatura, stato candidature.
- Macchina a stati candidature (§5) + Actions + Policy + notifiche email/DB.
- Pannello Ente: gestione candidature (kanban/tabella per stato, note, assegnazione).
- **Deliverable:** flusso adozione completo submitted→completed con contratto PDF.

### Fase 3 — Affidi, appuntamenti, medico avanzato · ~2 settimane
- Affido (foster) con proprio workflow.
- Appuntamenti/visite con calendario e reminder.
- Scadenzario sanitario (vaccini/richiami) con notifiche.
- **Deliverable:** gestione operativa quotidiana del canile.

### Fase 4 — Comunicazione, notifiche, donazioni · ~2 settimane
- Messaggistica ente↔adottante (thread) + notifiche realtime (Reverb).
- Donazioni con Cashier/Stripe (SEPA, one-off + ricorrenti).
- Centro notifiche in-app + email transazionali brandizzate.
- **Deliverable:** engagement e raccolta fondi.

### Fase 5 — GDPR, i18n, ricerca avanzata · ~2 settimane
- Gestione consensi, export dati personali, cancellazione/anonimizzazione,
  retention automatica (job schedulati), cookie consent, privacy policy.
- Traduzioni IT/EN complete (interfaccia + contenuti), pronto per ES/FR/DE.
- Facet di ricerca, salvataggio ricerche, alert nuovi animali.
- **Deliverable:** conformità e pronto multi-paese.

### Fase 6 — Governance piattaforma + analytics · ~1-2 settimane
- Pannello Admin: verifica/sospensione enti, moderazione, utenti globali, settings.
- Dashboard/KPI (adozioni, tempi medi, tasso conversione) con widget Filament.
- Report esportabili (CSV/PDF).
- **Deliverable:** operabilità della piattaforma da parte del gestore.

### Fase 7 — Hardening & deploy produzione · ~1-2 settimane
- Copertura test target (dominio 100%, feature critiche), Larastan pulito.
- Sicurezza: rate limiting, policy review, security headers, dependency audit.
- Osservabilità: Pulse, health checks, Sentry/log, backup automatici (spatie-backup).
- Deploy: Forge/Ploi o Kubernetes; Horizon + Reverb + scheduler come servizi;
  storage S3 UE; SSL; CI/CD con zero-downtime deploy.
- **Deliverable:** ArkVel in produzione.

### Fase 8 — Estensioni (post-MVP)
- API pubblica (Sanctum) + eventuale app mobile.
- Integrazione anagrafe canina nazionale / microchip / EU pet passport.
- Import/export verso altri gestionali canile; portali di adozione aggregati.
- Firma elettronica contratti; QR in scheda animale; PWA.

---

## 8. Test, qualità, CI/CD

- **Pest**: unit (Enums, States, Actions), feature (flussi Filament/Livewire),
  architettura (Pest arch) per far rispettare le convenzioni di §3.2.
- **Larastan** livello max, **Pint** (preset Laravel), **Rector** per upgrade.
- **GitHub Actions**: lint → static analysis → test (Postgres+Redis service) →
  build asset → (deploy su tag).
- **Copertura**: dominio adozione/affido 100%; regressione su ogni bug.

---

## 9. Deployment & operatività

- **Infra UE** per data residency (Hetzner/Scaleway/OVH).
- Servizi: app (PHP-FPM+Nginx), PostgreSQL, Redis, Meilisearch, Reverb, Horizon,
  scheduler, storage S3-compatibile UE.
- **Backup** giornalieri cifrati (spatie-backup) + verifica ripristino.
- **Health checks** (spatie-health) + alerting; **Pulse** per performance.
- Secrets via env/secret manager; migrazioni in deploy; `optimize` in build.

---

## 10. Decisioni aperte da confermare

1. **Portale adottante**: pannello Filament dedicato (più rapido) vs frontend
   Livewire custom (più libertà UX). *Proposta: Filament per il dashboard,
   Livewire per il catalogo pubblico.*
2. **Specie supportate**: solo cani/gatti all'MVP o generico da subito?
   *Proposta: enum generico, UI focalizzata cani/gatti all'inizio.*
3. **Pagamenti donazioni**: Stripe da subito o rimandare a Fase 4? *Proposta: Fase 4.*
4. **Paese/i pilota**: solo Italia all'inizio (semplifica campi legali/anagrafe)
   o multi-paese da subito? *Proposta: Italia pilota, architettura multi-paese.*
5. **Riuso vs rebuild pulito**: confermato rebuild da zero (nuovo skeleton),
   riusando il presente doc e il dominio Baja come riferimento.
