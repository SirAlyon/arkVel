---
name: quality-gate
description: Run the ArkVel quality gate — Pint (code style), Larastan (static analysis) and Pest (tests) inside the Docker PHP container — and report results, fixing style issues automatically. Use before committing or when asked to check code quality.
---

# /quality-gate

Esegui i tre controlli nel container PHP (dalla root del progetto). Se lo stack non è
attivo, avvialo con `docker compose up -d`.

```sh
# 1. Stile: corregge in automatico
docker compose exec -T php ./vendor/bin/pint

# 2. Analisi statica (Larastan livello 6+)
docker compose exec -T php ./vendor/bin/phpstan analyse --no-progress

# 3. Test
docker compose exec -T php ./vendor/bin/pest
```

## Comportamento
- Pint corregge da solo lo stile; riporta i file toccati.
- Se Larastan o Pest falliscono, **analizza e correggi** le cause, poi ri-esegui finché
  tutto è verde. Non ignorare/sopprimere errori senza motivazione esplicita.
- Riepiloga alla fine: stile OK, N errori statici risolti, X test passati.
