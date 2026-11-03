# ArkVel — Piano UI frontend pubblico (Livewire)

> Obiettivo: un sito pubblico moderno, curato, "da web agency", costruito **solo con software
> open-source / gratuito**. I pannelli Filament (`/account`, `/manage`, `/admin`) **non si toccano**,
> con un'unica eccezione concordata: lo spostamento di login/registrazione adottante (fase UI-5).
>
> Stato: proposta approvata il 2026-11-03. Ogni fase va lanciata in una chat nuova con il
> super-prompt in fondo al documento.

---

## 1. Stato di partenza (analisi 2026-11-03)

- **Stack:** Tailwind 4 (config CSS-first in `resources/css/app.css`), Vite 8, Livewire 4.4
  (arriva tramite Filament), Alpine (incluso in Livewire), Echo/Reverb.
- **Token attuali:** palette `brand` (terracotta) e `accent` (teal), neutri `stone`,
  font Instrument Sans via Bunny Fonts.
- **Componenti Blade:** `site-header`, `site-footer`, `animal-card`, `badge`, `button`,
  `filter-select`. Icone SVG inline (~32), nessuna libreria UI.
- **Pagine Livewire pubbliche:** `Home`, `AnimalCatalog` (Scout/Meilisearch), `AnimalDetail`
  (496 righe: candidatura, affido, prenotazione, contatto), `FavoriteButton`,
  `OrganizationIndex`, `OrganizationPage` (+ donazione Stripe), `DonationThankYou`,
  `DonationCancelled`.
- **Lacune:**
  - immagini: una sola conversione `thumb` 400×400, usata anche nella scheda animale;
    niente `srcset`, AVIF/WebP o placeholder;
  - nessuna dark mode, mappa o transizione tra pagine;
  - locale di default `en` (lo standard è IT), nessun selettore di lingua,
    `#[Title]` hard-coded in italiano;
  - nessun test browser;
  - login e registrazione esistono solo come pagine Filament;
  - `welcome.blade.php` di default ancora presente.

---

## 1-bis. Direzione visiva scelta: **A · Editoriale caldo** (2026-11-03)

Mockup di riferimento: canvas "ArkVel — Direzioni visive", artboard A
(https://claude.ai/artifact/2iJmacpuA1C2YsvhA3cL86). Prosegue il brand terracotta esistente.

- **Tipografia:**
  - display: **Fraunces** (serif variabile, asse `opsz`), pesi 400/600/700; corsivo per
    l'enfasi di una parola nei titoli;
  - testo/UI: **Instrument Sans** 400/500/600 (già in uso);
  - entrambi self-hosted via Fontsource.
- **Colori di partenza** (da convertire in OKLCH e da estendere in scale 50-950 in UI-1):

  | Ruolo | Valore |
  |---|---|
  | Fondo | `#FBF6F0` (crema) |
  | Superficie | `#FFFFFF` |
  | Superficie alternata | `#F3E9DE` |
  | Bordo | `#EADFD3` |
  | Inchiostro | `#2A1D17` |
  | Testo secondario | `#5E4D43` |
  | Primario (terracotta) | `#B9471F`, hover `#8E3415` |
  | Secondario (salvia) | `#4F6B57`, tinta `#E3ECE2` |
  | Foto placeholder | `#E7C9B4` / `#C9D6C8` |

  La dark mode va derivata in UI-1 (fondo bruno scuro caldo, non nero puro).
- **Forme:**
  - raggi generosi: card 28 px, pannelli 32 px, bottoni e chip "pill";
  - foto hero ad arco (raggio superiore 200 px);
  - ombre morbide e calde, usate poco.
- **Pattern ricorrenti:**
  - eyebrow maiuscoletto salvia sopra i titoli;
  - titoli grandi con parola in corsivo terracotta;
  - passi numerati con filetto superiore;
  - banda CTA salvia;
  - card con badge tondi (es. "Anche in affido", "Senior").
- **Tono:** editoriale, caldo, rassicurante; molto spazio bianco, foto protagoniste.

---

## 2. Stack frontend scelto (tutto open-source / gratuito)

| Esigenza | Scelta | Licenza |
|---|---|---|
| Componenti base | Libreria **nostra** `x-ui.*` (Blade + Alpine + Tailwind), partendo da [Sheaf UI](https://sheafui.dev/) (copia il codice nel progetto) e [Pines](https://devdojo.com/pines) come riferimento | MIT |
| Primitive Alpine | Plugin Focus, Collapse, Intersect, Anchor, Mask, Sort, quelli già inclusi in Livewire 4 (*verificare in UI-0 quali sono inclusi*) | MIT |
| Date picker (prenotazioni) | [Cally](https://github.com/wickynilliams/cally) (web component accessibile, localizzabile, < 9 KB) | MIT |
| Carosello / galleria | [Embla Carousel](https://www.embla-carousel.com/) + [PhotoSwipe](https://photoswipe.com/) (lightbox) | MIT |
| Icone | [Lucide](https://lucide.dev/) via `mallardduck/blade-lucide-icons` | ISC/MIT |
| Font | Self-hosting via [Fontsource](https://fontsource.org/) (niente CDN esterni, scelta GDPR-friendly) | OFL |
| Animazioni | CSS/Tailwind (`starting:`, `@starting-style`), `wire:transition` (View Transitions API), [Motion](https://motion.dev/) per le micro-interazioni; GSAP (gratuito, licenza standard non OSS) solo se serve per 1-2 sezioni | MIT / free |
| Mappa | [MapLibre GL JS v6](https://maplibre.org/) + tile [OpenFreeMap](https://openfreemap.org/) | BSD / free |
| Ricerca | Scout + Meilisearch (già presenti): facet distribution e `_geoRadius` | MIT |
| Test UI | Pest 4 Browser plugin (Playwright): screenshot di confronto, assert di accessibilità | MIT |

**Esclusi:** Flux Pro (a pagamento); maryUI/daisyUI (aspetto generico); React/shadcn (fuori stack).

---

## 3. Tooling AI (agenti, skill, MCP)

| Strumento | Ruolo | Setup |
|---|---|---|
| **Laravel Boost** | MCP con doc aggiornate (Livewire 4, Tailwind 4, Pest 4), schema DB, log browser, skill `livewire-development` e `tailwindcss-development` | `docker compose exec -T php composer require laravel/boost --dev` → `boost:install`. MCP: `docker compose exec -T php php artisan boost:mcp` in `.mcp.json` |
| **frontend-design** (plugin ufficiale Anthropic) | Impone una direzione estetica precisa invece del look generico | `/plugin install frontend-design@claude-plugins-official` |
| **Playwright MCP** | L'agente naviga, interagisce, fa screenshot multi-viewport | `claude mcp add playwright -- npx @playwright/mcp@latest` |
| **Chrome DevTools MCP** | Performance trace (LCP/INP/CLS), console, rete | `claude mcp add chrome-devtools -- npx chrome-devtools-mcp@latest` |
| Agente `ui-builder` (nuovo) | Costruisce componenti/pagine secondo il design system | `.claude/agents/ui-builder.md` |
| Agente `design-reviewer` (nuovo, read-only) | Screenshot a 375/768/1440 px in light e dark, audit axe, confronto con il design system e con il checklist §6 | `.claude/agents/design-reviewer.md` |
| Skill `/ui-review` (nuova) | Lancia `design-reviewer` sulle pagine toccate | `.claude/skills/ui-review/` |

> Nota Boost: `boost:install` riscrive solo il blocco `<laravel-boost-guidelines>` in CLAUDE.md;
> il testo esistente resta.

---

## 4. Regole trasversali delle fasi UI

1. **Filament intoccabile.** Nessuna modifica a Resource, Page, tema o CSS dei pannelli.
   Unica eccezione: la configurazione auth di `AdopterPanelProvider` in UI-5.
2. **Nessun cambio di comportamento.** Il restyling non sposta logica. La logica resta nelle
   Action; i componenti Livewire restano sottili. I test Feature esistenti si adattano al nuovo
   markup ma devono restare verdi.
3. **i18n:** nessuna stringa hard-coded (anche `#[Title]`, `alt`, `aria-label`), chiavi in
   `lang/{it,en}`.
4. **Accessibilità WCAG 2.2 AA:**
   - focus visibile e non coperto, target ≥ 24×24 px;
   - alternative al drag, `prefers-reduced-motion`, contrasto verificato.
5. **Performance budget (mobile):** LCP ≤ 2,5 s, INP ≤ 200 ms, CLS ≤ 0,1; JS di pagina contenuto
   (librerie pesanti, cioè mappa e lightbox, caricate solo dove servono, con import dinamico).
6. **Privacy:** font e asset self-hosted; mappe con posizione approssimata; nessun tracker.
7. **Quality gate** (pint + phpstan + pest) verde ad ogni commit; dalla fase UI-2 in poi anche
   test browser e `/ui-review` sulle pagine toccate.
8. **Branch** `feat/ui-<fase>` da `main`, Conventional Commits, un commit per componente o pagina.

---

## 5. Fasi

### UI-0 · Fondamenta e tooling

> **Stato: completata il 2026-11-03** (branch `feat/ui-0-foundations`, partito da
> `feat/phase-4-communication` a fase 4 chiusa).
>
> **Cosa è stato fatto:**
> - **Boost 2.10:** guidelines in `laravel/CLAUDE.md`, 9 skill in `laravel/.claude/skills/`
>   (incluse `livewire-development` e `tailwindcss-development`).
> - **Livewire** è ora una dipendenza diretta (`^4.4.8`): serve per la skill Livewire di Boost e
>   corregge l'advisory PKSA-xhv3-nr94-h8db.
> - **`.mcp.json` in root:** `laravel-boost` (nel container), `playwright`, `chrome-devtools`.
> - **Agenti e skill:** `.claude/agents/ui-builder.md`, `.claude/agents/design-reviewer.md`,
>   skill `/ui-review`.
> - **Test browser:**
>   - `pestphp/pest-plugin-browser` 4.3 e `playwright` 1.64.1 (versione esatta, allineata al
>     Dockerfile);
>   - estensione `sockets` aggiunta all'immagine `php` e alla CI;
>   - servizio `browser-tests` (`Dockerfile.browser`, Debian + Chromium, profilo `testing`),
>     lanciato con `docker compose --profile testing run --rm browser-tests`;
>   - `tests/Browser` è fuori dai testsuite di phpunit, quindi escluso dalla run standard;
>   - per fissare la lingua del browser nei test si usa `visit(...)->withLocale('it-IT')`.
> - **Lingua:**
>   - default `it` in config;
>   - enum `AppLocale` (it, en);
>   - middleware `SetLocale` sul gruppo `web`: sessione → Accept-Language → default;
>   - `POST /lingua/{locale}` (`locale.update`);
>   - `#[Title]` sostituiti da `public.titles.*` tradotti;
>   - `tests/TestCase.php` azzera l'Accept-Language predefinito del client di test.
> - `welcome.blade.php` rimosso.
>
> **Da fare a mano (interattivo):** `/plugin install frontend-design@claude-plugins-official`
> e approvare i server MCP del `.mcp.json` al prossimo avvio di Claude Code.
>
> **Note tecniche utili alle fasi successive:**
> - **Boost:** installarlo **senza `-W`**, altrimenti aggiorna mezzo lock.
> - **Plugin Alpine già inclusi in Livewire 4** (verificato su `dist/livewire.esm.js`): Morph,
>   History, Intersect, Sort, Resize, Collapse, Anchor, Focus (x-trap), Persist, Navigate,
>   Mask. Non installarli via npm.
> - **Reverb:** sulla macchina di sviluppo la porta 8080 può essere occupata da altri progetti
>   Docker.
> - **Più sessioni sulla stessa working copy:** mai in contemporanea. Per lavori paralleli
>   usare un `git worktree`.

Elenco originale della fase:
- Installare Boost (+ `.mcp.json`), Playwright MCP, Chrome DevTools MCP e il plugin frontend-design.
- Creare gli agenti `ui-builder` e `design-reviewer` e la skill `/ui-review`.
- Pest Browser plugin nel container `php`: Playwright e i browser vanno aggiunti al Dockerfile.
  Scrivere un primo smoke test browser della Home.
- Locale di default `it` (fallback `en`), middleware `SetLocale` (sessione, header, preferenza
  utente), `#[Title]` tradotti.
- Rimuovere `welcome.blade.php`.
- Verificare quali plugin Alpine sono inclusi in Livewire 4.

### UI-1 · Direzione visiva e design token
- Scegliere una delle direzioni dei mockup (vedi moodboard).
- Token in `@theme`:
  - colori OKLCH (brand, accent, neutri, semantici) per tema chiaro e scuro;
  - tipografia fluida (`clamp`), font display + font testo self-hosted;
  - raggi, ombre, spaziature, durate ed easing delle animazioni.
- Dark mode via classe + `prefers-color-scheme`, con toggle persistito.
- Pagina `/_styleguide` solo in `local` (route condizionata all'ambiente).

### UI-2 · Libreria componenti `x-ui.*`
- **Primitivi:** button (varianti/dimensioni/stato loading via `data-loading`), link, badge, tag,
  input, textarea, select, checkbox/radio/switch, field (label + help + errore), combobox
  (Alpine Anchor), date picker (wrapper Cally).
- **Overlay e feedback:** dialog, drawer/sheet, dropdown, tooltip, toast (store Alpine +
  eventi Livewire), tabs, accordion, skeleton, empty state, avatar, icon (Lucide).
- **Dominio:** `animal-card` v2, `organization-card`, `stat`, `section-heading`, `cta-band`.
- Ogni componente: documentato nella styleguide, test di rendering, test browser con assert
  di accessibilità.

### UI-3 · Shell del sito
- Header nuovo (trasparente sulla hero, poi solido allo scroll), menu mobile a drawer,
  selettore lingua, menu account (link a `/account` e, per i membri di un ente, a `/manage`).
- Footer editoriale (newsletter placeholder, link legali, lingue).
- `wire:navigate` con prefetch al passaggio del mouse; View Transitions tra pagine;
  barra di progresso navigazione.
- SEO: meta e Open Graph per pagina, JSON-LD (`Organization`, `Animal`→`Thing`/`Product`
  da valutare, `BreadcrumbList`), sitemap, breadcrumb.

### UI-4 · Pipeline immagini
- Conversioni medialibrary: `card` (4:5), `hero` (16:9 / 3:2), `og` (1200×630), WebP + AVIF,
  `withResponsiveImages()` (placeholder sfocato incluso), tutte in coda.
- Componente `x-ui.picture`: `srcset`/`sizes`, `fetchpriority="high"` per l'immagine LCP,
  `width`/`height` dichiarati, lazy loading per le altre.
- Comando di rigenerazione delle conversioni per i media esistenti.
- Galleria scheda animale: Embla + PhotoSwipe (import dinamico).

### UI-5 · Auth pubblica in Livewire (login/registrazione adottante)
- Pagine Livewire pubbliche col nuovo design:
  - `/accedi` (`login`), `/registrati` (`register`), `/password-dimenticata` (`password.request`);
  - `/reimposta-password/{token}` (`password.reset`);
  - `/verifica-email` (`verification.notice`) + `verification.verify` firmato.
- **Logica in Action** (`app/Actions/Auth/...`): `AuthenticateUser` (rate limit, rigenerazione
  sessione), `RegisterAdopter`, `SendPasswordResetLink`, `ResetPassword`.
  **Prima di scriverle, analizzare cosa fa oggi la registrazione Filament dell'adottante**
  (ruoli/permessi, profilo, consensi GDPR, eventi/observer) e replicarlo identico.
  Le Action vanno coperte al 100%.
- Consensi privacy alla registrazione (registrati con activitylog), honeypot e rate limit.
- **Unica modifica Filament (solo configurazione):** in `AdopterPanelProvider` rimuovere
  `login()`, `registration()`, `passwordReset()` ed `emailVerification()` e redirigere gli utenti
  non autenticati alla route `login` pubblica; la verifica email passa al middleware `verified`
  di Laravel.
  - Il pannello `/manage` (enti) e `/admin` mantengono il login Filament.
  - Logout dal pannello adottante → home pubblica.
- **Test:**
  - un feature test per ogni flusso, compreso il rate limit;
  - regressione: `/account` senza login → redirect a `/accedi`; utente non verificato →
    `/verifica-email`;
  - i test esistenti di `tests/Feature/Adopter` restano verdi.

### UI-6 · Pagine (un commit per pagina)
- **UI-6a Home:** hero editoriale con foto grande, griglia bento di animali in evidenza,
  numeri chiave, "come funziona l'adozione" in 3-4 passi, storie di adozione (placeholder),
  CTA donazione/enti.
- **UI-6b Catalogo:**
  - ricerca istantanea (solo i risultati si aggiornano: island o `wire:model.live.debounce`);
  - facet con conteggi da Meilisearch, chip dei filtri attivi, drawer filtri su mobile, ordinamento;
  - caricamento continuo con `wire:intersect` (con fallback a paginazione);
  - skeleton e stati vuoti curati.
- **UI-6c Scheda animale:**
  - prima il refactor: dividere `AnimalDetail` in componenti figli per candidatura (form a più
    passi), affido, prenotazione (date picker Cally + slot) e contatto, senza cambiare il
    comportamento;
  - poi il redesign: galleria, pannello azioni che resta visibile scorrendo (bottom sheet su
    mobile), schede informative, animali simili, transizione animata card → hero.
- **UI-6d Enti:** indice con card e filtri; pagina ente con cover, info, animali, widget
  donazione (importi preimpostati, importo libero, ricorrente se supportato) e pagine
  grazie/annulla curate.

### UI-7 · Motion, micro-interazioni e mappa
- Micro-interazioni: preferito (cuore animato), hover delle card, reveal allo scroll con
  `wire:intersect` + Motion, conferme toast. Tutto disattivato con `prefers-reduced-motion`.
- Mappa MapLibre (import dinamico, `wire:ignore` + Alpine) su catalogo e pagina ente, con
  posizioni approssimate e un elenco alternativo accessibile.
- GSAP solo se una sezione d'effetto lo giustifica.

### UI-8 · Qualità e rilascio
- Suite browser su tutte le pagine pubbliche e sui flussi auth: viewport mobile/desktop,
  light/dark, screenshot di confronto, assert di accessibilità.
- Audit Chrome DevTools MCP sui Core Web Vitals; correggere fino a rispettare il budget §4.
- Test browser in CI (job separato, con cache dei browser Playwright).
- `/ui-review` finale e checklist WCAG 2.2 AA documentata.

---

## 6. Checklist di revisione design (usata da `design-reviewer`)

- Gerarchia tipografica chiara; massimo 2 famiglie di font; scala coerente con i token.
- Solo colori dai token; contrasto AA in light e dark.
- Spaziature dalla scala; allineamento alla griglia; nessun overflow orizzontale a 320 px.
- Ogni stato previsto: hover, focus, active, disabled, loading, empty, error, success.
- Immagini con `alt` tradotto, proporzioni stabili, nessun CLS.
- Animazioni ≤ 300 ms per le interazioni, easing dai token, rispetto di reduced-motion.
- Copy tradotto IT/EN, nessuna stringa hard-coded.
- Coerenza: stessi componenti per gli stessi pattern; nessun markup duplicato fuori da `x-ui.*`.

---

## 7. Super-prompt per fase

Ogni prompt va incollato in una chat nuova. Il contesto comune è richiamato in testa.

### Contesto comune (prefisso di ogni prompt)

```
Progetto ArkVel (repo corrente). Leggi CLAUDE.md, laravel/CLAUDE.md e docs/PIANO-UI-FRONTEND.md
(in particolare §2 stack, §4 regole trasversali, §6 checklist). Stiamo rifacendo la UI del sito
pubblico Livewire; i pannelli Filament NON si toccano (unica eccezione documentata in UI-5).
Solo librerie open-source/gratuite elencate in §2. Comandi sempre via Docker
(docker compose exec -T php ...; npm sul host o nel container node se presente).
Crea un branch feat/ui-<fase> da main. Quality gate verde a ogni commit. Usa gli agenti del
progetto (ui-builder, test-author, design-reviewer, standards-reviewer) dove ha senso.
```

### UI-0
```
[Contesto comune] Esegui la fase UI-0 di docs/PIANO-UI-FRONTEND.md §5:
installa e configura Laravel Boost (via Docker, .mcp.json con boost:mcp nel container),
documenta l'installazione di Playwright MCP, Chrome DevTools MCP e plugin frontend-design
(comandi che devo lanciare io, se richiedono interazione); crea .claude/agents/ui-builder.md,
.claude/agents/design-reviewer.md (read-only, Playwright MCP, checklist §6) e la skill
.claude/skills/ui-review; installa pestphp/pest-plugin-browser con Playwright nel Dockerfile php
e scrivi uno smoke test browser della Home; imposta locale it/fallback en con middleware
SetLocale e traduci i #[Title]; rimuovi welcome.blade.php; verifica quali plugin Alpine sono
inclusi in Livewire 4 e annotalo nel piano. Chiudi aggiornando il piano con lo stato della fase.
```

### UI-1
```
[Contesto comune] Esegui la fase UI-1. Direzione visiva scelta: "A · Editoriale caldo", specifica
in §1-bis (riferimento visivo: artboard A del canvas linkato lì).
Usa la skill frontend-design. Definisci i token in resources/css/app.css (@theme, OKLCH,
light/dark, tipografia fluida, font self-hosted via Fontsource, motion token), dark mode con
toggle persistito, e la pagina /_styleguide solo in local. Non modificare ancora le pagine.
Verifica i contrasti AA e fai uno /ui-review della styleguide.
```

### UI-2
```
[Contesto comune] Esegui la fase UI-2: crea la libreria x-ui.* (elenco in §5 UI-2) partendo dai
pattern di Sheaf UI/Pines (MIT, adattati ai nostri token), icone Lucide via blade-lucide-icons,
date picker come wrapper di Cally. Ogni componente: accessibile (ARIA, tastiera, focus),
documentato in /_styleguide, test di rendering + test browser con assert di accessibilità.
Un commit per gruppo coerente di componenti. Le pagine esistenti non vanno ancora migrate.
```

### UI-3
```
[Contesto comune] Esegui la fase UI-3: nuova shell (layout app, header, menu mobile, selettore
lingua, menu account, footer) con i componenti x-ui; wire:navigate con prefetch, View
Transitions e barra di progresso; SEO (meta/OG per pagina, JSON-LD, sitemap, breadcrumb).
Adatta i test esistenti al nuovo markup senza cambiare comportamenti. /ui-review finale.
```

### UI-4
```
[Contesto comune] Esegui la fase UI-4: nuove conversioni medialibrary (card, hero, og; WebP/AVIF;
responsive images con placeholder; in coda), componente x-ui.picture, comando di rigenerazione,
galleria Embla + PhotoSwipe con import dinamico. Test sulle conversioni e test browser sulla
galleria. Misura il CLS/LCP prima e dopo con Chrome DevTools MCP.
```

### UI-5
```
[Contesto comune] Esegui la fase UI-5 (auth pubblica Livewire). PRIMA analizza cosa fa oggi la
registrazione/login Filament del pannello adopter (AdopterPanelProvider, eventuali Register
page custom, observer, ruoli spatie/permission, consensi GDPR, verifica email) e riportamelo.
Poi implementa: pagine Livewire /accedi, /registrati, /password-dimenticata,
/reimposta-password/{token}, /verifica-email con le route standard Laravel (login, register,
password.request, password.reset, verification.notice, verification.verify); logica in
app/Actions/Auth (100% coperte); rate limit, honeypot, consensi registrati. In
AdopterPanelProvider (unica modifica Filament ammessa) rimuovi login/registration/passwordReset/
emailVerification e redirigi i non autenticati alla route login pubblica; /manage e /admin
mantengono il login Filament. Test di regressione come da §5 UI-5. I test di tests/Feature/Adopter
devono restare verdi.
```

### UI-6a / 6b / 6c / 6d
```
[Contesto comune] Esegui la fase UI-6<x> (<Home | Catalogo | Scheda animale | Enti>) come da §5.
Usa solo componenti x-ui (creane di nuovi in x-ui se servono, con test). Nessuna logica nuova
nei componenti Livewire: se serve, una nuova Action testata. Per la scheda animale: prima un
commit di refactor (componenti figli, comportamento identico, test verdi), poi il redesign.
Test feature aggiornati + test browser (mobile/desktop, light/dark, accessibilità, screenshot).
/ui-review e misura dei Core Web Vitals prima di chiudere.
```

### UI-7
```
[Contesto comune] Esegui la fase UI-7: micro-interazioni (Motion, reduced-motion), reveal allo
scroll con wire:intersect, mappa MapLibre v6 + OpenFreeMap con import dinamico su catalogo e
pagina ente (coordinate approssimate lato server, elenco alternativo accessibile). Verifica INP
con Chrome DevTools MCP. Test browser.
```

### UI-8
```
[Contesto comune] Esegui la fase UI-8: completa la suite browser (tutte le pagine pubbliche + auth,
mobile/desktop, light/dark, screenshot, accessibilità), aggiungi il job browser alla CI
(.github/workflows/ci.yml) con cache Playwright, audit Core Web Vitals fino al budget §4,
checklist WCAG 2.2 AA documentata in docs/. /ui-review finale su tutto il sito.
```
