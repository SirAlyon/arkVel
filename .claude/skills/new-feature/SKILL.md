---
name: new-feature
description: Orchestrated end-to-end workflow to build a new ArkVel feature — understand, design, implement, test, review, and pass the quality gate. Use when the user asks to build/add a feature to the ArkVel platform.
---

# /new-feature — workflow orchestrato

Sei l'orchestratore. Coordini i sotto-agenti; non svolgi tu il lavoro pesante se un
agente specializzato è più adatto. Delega con lo strumento Agent.

## Precondizioni
- Leggi `CLAUDE.md`, `laravel/CLAUDE.md` e la sezione pertinente di `docs/PIANO-DI-SVILUPPO.md`.
- Assicurati che lo stack sia attivo: `docker compose up -d`.

## Passi
1. **Capire.** Riformula la feature e definisci i criteri di accettazione. Se ambigua,
   chiedi chiarimenti (max 2-3 domande) prima di procedere.
2. **Progettare.** Definisci la slice verticale: migrazioni, model, enum/stato, Action,
   Resource Filament / componente Livewire, Policy, factory, test. Per decisioni
   architetturali non banali valuta l'agente `Plan`.
3. **Implementare.** Delega ad `Agent(feature-builder)` (o `filament-builder` se il grosso
   è UI Filament). Passa criteri di accettazione e vincoli.
4. **Testare.** Se la copertura non è adeguata, delega ad `Agent(test-author)` per
   completare unit + feature test finché verdi.
5. **Revisionare.** Delega ad `Agent(standards-reviewer)` sul diff. Applica i fix Blocker/Major.
6. **Quality gate.** Esegui `/quality-gate` (pint + phpstan + pest). Deve essere verde.
7. **Riepilogo.** Elenca file cambiati, decisioni, copertura test, e proponi il messaggio
   di commit (Conventional Commits) su branch dedicato. **Non** committare su `main`.

## Regole
- Tutti i comandi PHP nel container: `docker compose exec -T php ...`.
- La feature non è "fatta" senza test e quality gate verde (vedi `CLAUDE.md` §2–§3).
