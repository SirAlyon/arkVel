# ArkVel — regole Laravel/Filament (livello app)

> Gli standard generali (SOLID, OOP, test, git, quality gate) sono nel
> [CLAUDE.md](../CLAUDE.md) di root. Questo file copre i dettagli dello strato Laravel.
> **Tutti i comandi vanno eseguiti nel container** (`docker compose exec -T php ...`):
> il PHP locale è 8.2, l'app richiede 8.4.

## Layout del codice (modular monolith)

```
app/
├── Enums/       # insiemi di valori fissi (implementano HasLabel/HasColor)
├── Models/      # Eloquent: relazioni, cast, scope, accessor — niente business logic
├── States/      # macchine a stati (spatie/laravel-model-states)
├── Actions/     # un caso d'uso = una classe invokable, con test unit
├── Data/        # DTO tipizzati (spatie/laravel-data)
├── Policies/    # autorizzazione per Model
├── Support/     # servizi trasversali, contratti, binding
├── Filament/    # Admin/ Shelter/ Adopter/  (Resource, Pages, Widgets)
└── Livewire/    # componenti del sito pubblico
```

## Regole pratiche

- `declare(strict_types=1);` in ogni file; tipizzazione completa.
- **Migrazioni**: una tabella per file, `down()` sempre coerente, FK con
  `->constrained()` e `on delete` esplicito. Enum applicativi come `string` + cast
  Eloquent verso l'enum PHP (non `enum` di colonna DB).
- **Model**: `protected function casts(): array` (stile L11+), `$fillable` esplicito,
  relazioni tipizzate con return type (`: HasMany`, `: BelongsTo`).
- **Factory** per ogni Model (servono ai test e ai seeder).
- **Enum**: label in italiano via `HasLabel`; colori via `HasColor` per gli stati.
- **Stati**: transizioni solo tramite la macchina a stati; ogni transizione logga
  (activitylog) e notifica dove previsto.
- **Filament 5**: una Resource per Model, componenti riusabili, RelationManager per
  le relazioni, Widgets per i KPI. Le azioni delegano alle classi `Action`.
  API incerta → consulta https://filamentphp.com/docs/5.x/ .
- **Tenancy**: le risorse d'ente sono sempre tenant-scoped; verifica l'assenza di
  fughe cross-tenant nei test.

## Test

- Pest 4 in `tests/{Unit,Feature}`. Config test: SQLite in memoria (vedi `phpunit.xml`).
- Ogni Action → test unit; ogni flusso Filament/HTTP → feature test con `RefreshDatabase`.
- Esegui: `docker compose exec -T php ./vendor/bin/pest`.

## Laravel Boost (opzionale)

Boost è lo strumento ufficiale Laravel per assistenza AI (guidelines per-versione +
tool via MCP). Non è installato. Per abilitarlo:
`docker compose exec -T php composer require laravel/boost --dev` poi
`docker compose exec -T php php artisan boost:install`.
