---
name: ui-review
description: Visual + accessibility review of ArkVel's public Livewire pages touched by the current change — screenshots at mobile/tablet/desktop in light and dark, WCAG 2.2 AA checks, Core Web Vitals — via the design-reviewer agent. Use after UI work, before committing.
---

# /ui-review

1. **Prepare the app**
   - make sure the stack is up: `docker compose up -d`;
   - build the assets on the host: `cd laravel && npm run build`, unless `npm run dev` is
     already running.
2. **Pick the pages**
   - the ones named by the user; otherwise
   - the public routes affected by `git diff --name-only` (views in
     `resources/views/livewire`, `resources/views/components`, `app/Livewire`,
     `resources/css`).
3. **Delegate** to the `design-reviewer` agent with the list of URLs
   (http://localhost:8090/...) and what changed.
4. **Run the browser suite:** `docker compose --profile testing run --rm browser-tests`.
5. **Report** to the user:
   - the reviewer's findings by severity;
   - the browser suite result;
   - the verdict.

   Do not fix anything unless the user asks. If they do, fix the Blockers and Majors first,
   then re-run this skill.
