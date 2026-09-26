# ArkVel — Guida per agenti e standard di sviluppo

Piattaforma open-source per adozione e gestione animali (canili, rifugi, associazioni,
società) in Europa. Stack: **Laravel 13 + Filament 5 + PHP 8.4 + PostgreSQL 16**.

- **Piano & roadmap:** [docs/PIANO-DI-SVILUPPO.md](docs/PIANO-DI-SVILUPPO.md) — leggilo prima di lavorare su una fase.
- **Codice applicativo:** vive in [laravel/](laravel/). L'infrastruttura Docker è nella root.
- Regole Laravel/Filament specifiche: [laravel/CLAUDE.md](laravel/CLAUDE.md).

---

## 1. Come si eseguono i comandi (SEMPRE via Docker)

Il PHP locale è 8.2 (troppo vecchio). **Ogni** comando PHP/artisan/composer/tool va
eseguito nel container `php` (PHP 8.4):

```sh
docker compose exec -T php php artisan <cmd>
docker compose exec -T php composer <cmd>
docker compose exec -T php ./vendor/bin/pint
docker compose exec -T php ./vendor/bin/phpstan analyse --no-progress
docker compose exec -T php ./vendor/bin/pest
```

Se lo stack non è attivo: `docker compose up -d`. App su http://localhost:8090,
Mailpit http://localhost:8025, Meilisearch http://localhost:7700.

---

## 2. Quality gate — NON negoziabile

Prima di considerare **conclusa** qualunque modifica al codice, devono passare tutti e tre:

```sh
docker compose exec -T php ./vendor/bin/pint            # stile (PSR-12, preset Laravel)
docker compose exec -T php ./vendor/bin/phpstan analyse # analisi statica (Larastan livello 6+)
docker compose exec -T php ./vendor/bin/pest            # test
```

Scorciatoia: usa la skill `/quality-gate`. La CI ([.github/workflows/ci.yml](.github/workflows/ci.yml))
esegue gli stessi controlli: se falliscono in locale, falliranno in CI.

---

## 3. Politica di test — test dopo OGNI funzionalità

**Ogni funzionalità nuova o modificata deve arrivare con i suoi test, nello stesso cambiamento.**
Codice senza test non è considerato completo.

- **Framework:** Pest 4. File in `laravel/tests/{Unit,Feature}`.
- **Copertura attesa:** dominio (`app/Actions`, `app/States`, `app/Enums`) → **100%**.
  Flussi critici (adozione, affido, permessi, tenancy) → coperti da feature test.
- **Unit** per Actions/States/DTO/Enum (logica pura, senza DB dove possibile).
- **Feature** per flussi HTTP/Filament: usa `RefreshDatabase`, le **factory** dei modelli
  e gli helper di testing di Filament/Livewire.
- Un bug corretto = un test di regressione che lo riproduce.
- Scrivi prima l'aspettativa (o almeno contestualmente), non "dopo, se avanza tempo".

---

## 4. Principi di progettazione (SOLID + OOP moderno)

Applichiamo SOLID in modo pragmatico, con OOP idiomatico Laravel/PHP 8.4.

- **S — Single Responsibility.** Un caso d'uso = una classe **Action** invokable
  (`app/Actions/...`, un solo `__invoke()`/`handle()`). Controller, Pages e Resource
  Filament restano *sottili*: validano/autorizzano e delegano all'Action.
- **O — Open/Closed.** Estendi aggiungendo nuove Action/State/Policy, non gonfiando
  classi esistenti. Gli stati del ciclo di vita si aggiungono come nuove classi di stato.
- **L — Liskov.** Le implementazioni di un contratto sono intercambiabili; niente
  override che rompono le aspettative del tipo base.
- **I — Interface Segregation.** Contratti piccoli e mirati (es. `GeneratesContract`,
  `SendsNotification`), non interfacce "tuttofare".
- **D — Dependency Inversion.** Dipendi da interfacce per i servizi con più
  implementazioni (pagamenti, notifiche, geocoding, storage); fai il binding nei
  Service Provider. Inietta via costruttore, **niente `new` di dipendenze** né stato globale.

### OOP / PHP 8.4
- `declare(strict_types=1);` in **tutti** i file PHP.
- Tipizza **tutto**: proprietà, parametri, ritorni. Niente `mixed` se evitabile.
- `readonly` per value object/DTO; costruttori promossi; `enum` per ogni insieme
  di valori fissi (implementa `HasLabel`/`HasColor` per l'uso in Filament).
- Preferisci l'immutabilità; evita setter che mutano stato condiviso.
- Niente logica di business nei Model: i Model contengono relazioni, cast, scope,
  accessor/mutator. La logica sta in Action/Service. I Model restano "magri".
- DTO tipizzati con `spatie/laravel-data` per passare dati alle Action.
- Ciclo di vita (adozione/affido) via `spatie/laravel-model-states`: **mai** mutare
  a mano una colonna di stato saltando la macchina a stati.

---

## 5. Regole trasversali del dominio

- **Autorizzazione:** ogni Model ha una **Policy**. I permessi passano da
  `spatie/laravel-permission` in modalità *teams* (team = organizzazione/tenant).
- **Multi-tenancy:** i dati degli enti sono isolati per tenant (Filament tenancy).
  Ogni query su risorse d'ente deve essere tenant-scoped: mai fughe cross-tenant.
- **Privacy/GDPR by design:** minimizza i dati personali, registra i consensi,
  rispetta retention e diritto all'oblio (vedi piano §5/§Fase 5). L'audit passa da
  `spatie/laravel-activitylog`.
- **i18n:** stringhe UI tradotte (base IT, fallback EN); contenuti multilingua via
  `spatie/laravel-translatable`. Niente stringhe hard-coded destinate all'utente.
- **Validazione:** valida sempre l'input (schema Filament / Form Request / regole
  `laravel-data`). Non fidarti mai dei dati in ingresso.

---

## 6. Convenzioni Filament 5

- Una **Resource** per Model; usa i componenti nativi (form/table/infolist/schemas v5).
- Riusa componenti di form estraendoli; non duplicare schemi tra create/edit.
- Usa **RelationManager** per le relazioni, **Widgets** per KPI/dashboard.
- Azioni di dominio nelle Resource → delegano alle classi **Action**, non contengono logica.
- Rispetta le Policy: le Resource devono onorare l'autorizzazione e il tenant scoping.
- In dubbio sull'API v5, **consulta la doc** (`https://filamentphp.com/docs/5.x/...`)
  o Laravel Boost se installato — non improvvisare firme di metodo.

---

## 7. Git & workflow

- **Mai committare su `main`.** Un branch per fase/feature (`rebuild/...`, `feat/...`, `fix/...`).
- **Conventional Commits** (`feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`).
- Un commit = un cambiamento coerente, con i suoi test, quality gate verde.
- Non committare `.env`, `vendor/`, asset generati, log.

---

## 8. Struttura AI del progetto (agenti & skill)

L'orchestrazione la fa **questa sessione principale**: analizza, pianifica e delega ai
sotto-agenti specializzati. Non esiste (né serve) un "agente orchestratore" separato.

**Agenti** (in `.claude/agents/`) — delega con lo strumento Agent:
- `feature-builder` — implementa una feature verticale end-to-end secondo questi standard (con test + quality gate).
- `filament-builder` — costruisce Resource/Page/Widget Filament 5 idiomatici.
- `test-author` — scrive/estende test Pest ed esegue la suite.
- `standards-reviewer` — revisione read-only del diff contro SOLID/OOP/standard di progetto.

**Skill** (in `.claude/skills/`) — invoca con `/<nome>`:
- `/new-feature` — workflow completo: capire → progettare → implementare → testare → revisionare → quality gate.
- `/quality-gate` — esegue pint + phpstan + pest e riporta l'esito.

Per lavori ampi e parallelizzabili si può usare l'orchestrazione a workflow (fan-out
di più agenti); farlo solo su richiesta esplicita.
