# ArkVel — Piano UI frontend pubblico (Livewire)

> Obiettivo: un sito pubblico moderno, curato, "da web agency", costruito **solo con software
> open-source / gratuito**. I pannelli Filament (`/account`, `/manage`, `/admin`) **non si toccano**,
> con un'unica eccezione concordata: lo spostamento di login/registrazione adottante (fase UI-5).
>
> Stato: proposta approvata il 2026-11-03. UI-0…UI-8 completate: refactor della UI pubblica concluso. Ogni fase va lanciata in una chat nuova con il
> super-prompt in fondo al documento.

---

## 1. Stato di partenza (analisi 2026-11-03)

- **Stack:** Tailwind 4 (config CSS-first in `resources/css/app.css`), Vite 8, Livewire 4.4
  (arriva tramite Filament), Alpine (incluso in Livewire), Echo/Reverb.
- **Token attuali:** palette `brand` (terracotta) e `accent` (teal), neutri `stone`,
  font Instrument Sans via Bunny Fonts (configurato in Vite ma mai caricato nel layout: manca
  `@fonts`). Sostituiti in UI-1 con la direzione B (§1-bis); `brand-*`/`accent-*` restano solo
  come alias temporanei (vedi §5 UI-1).
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

## 1-bis. Direzione visiva scelta: **B · Pop affettuoso** (2026-11-03)

Mockup di riferimento: canvas "ArkVel — Direzioni visive", artboard **B · Pop affettuoso**
(https://claude.ai/artifact/2iJmacpuA1C2YsvhA3cL86).

> **Cambio di direzione.** La prima scelta era "A · Editoriale caldo" (Fraunces + Instrument
> Sans; crema, terracotta e salvia). Il 2026-11-03, dopo UI-0 e prima di UI-1, è stata
> sostituita da B. Il brand terracotta esce di scena: i token `brand-*`/`accent-*` attuali di
> `resources/css/app.css` vanno rimpiazzati in UI-1. I pannelli Filament non cambiano, perché
> usano già colori propri (Amber, Rose, Emerald).

- **Tipografia:**
  - **una sola famiglia:** [Bricolage Grotesque](https://fontsource.org/fonts/bricolage-grotesque),
    variabile con asse ottico `opsz` 12–96, pesi 400/600/800, licenza OFL;
  - self-hosted via Fontsource (`@fontsource-variable/bricolage-grotesque`);
  - titoli 800 molto stretti (`letter-spacing` da −0.035em a −0.045em, `line-height` ~0.95–1.05),
    H1 fluido `clamp(48px, 7vw, 96px)`;
  - testo 17 px, `line-height` 1.5; etichette e bottoni 600–700;
  - Instrument Sans e il caricamento via Bunny Fonts in `vite.config.js` vanno rimossi.
- **Colori di partenza** (da convertire in OKLCH e da estendere in scale 50-950 in UI-1):

  | Ruolo | Valore | Uso |
  |---|---|---|
  | Fondo | `#FFFFFF` | pagina |
  | Superficie | `#F3F1EA` | card, nav a pillola, pannelli "come funziona" |
  | Bordo | `#E4E1D6` | solo decorativo (vedi i contrasti) |
  | Inchiostro (navy) | `#1B1B3A` | testo, bottone primario, card scure; hover `#3A3A70` |
  | Testo secondario | `#4A4A68` | proposta, da validare in UI-1 |
  | Primario (giallo) | `#FFD23F` | blocco hero, CTA su fondi scuri o verdi, badge |
  | Pesca | `#FFB4A2` | blocchi foto e numeri dei passi; testo sopra `#5A2A20` |
  | Verde | `#2D6A4F` | stat, banda donazione (testo bianco) |
  | Menta | `#B7E4C7` | blocchi foto e numeri dei passi |

- **Contrasti verificati** (WCAG 2.x):

  | Coppia | Rapporto | Esito |
  |---|---|---|
  | navy su giallo | 11,5:1 | AA/AAA |
  | navy su pesca | 9,7:1 | AA/AAA |
  | navy su menta | 11,8:1 | AA/AAA |
  | navy su superficie | 14,7:1 | AA/AAA |
  | `#5A2A20` su pesca | 6,9:1 | AA |
  | bianco su verde | 6,4:1 | AA |
  | `#4A4A68` su bianco / superficie | 8,5:1 / 7,5:1 | AA/AAA |
  | **giallo su verde** | **4,43:1** | **non va bene per testo normale**: sul verde il giallo è solo un fondo (bottone con testo navy) o testo ≥ 24 px |
  | **`#E4E1D6` su bianco** | **1,31:1** | **solo decorativo**: per i bordi di input e controlli serve un tono ≥ 3:1 (WCAG 1.4.11) |

- **Dark mode** (da derivare in UI-1, non c'è nel mockup):
  - fondo navy molto scuro (es. `#13132B`), superfici navy più chiare, testo `#F7F5EE`
    (16,7:1), testo secondario tipo `#B8B6C8` (9,1:1);
  - il giallo resta accento (bottoni, badge, numeri), **mai** grande superficie: niente blocco
    hero giallo pieno, che al buio abbaglia (usare una superficie scura con accenti gialli);
  - pesca, menta e verde vanno desaturati o scuriti come blocchi, con testo chiaro.
- **Forme:**
  - raggi molto generosi: pannelli 40 px, card 32 px, immagini nelle card 24 px, passi 28 px;
  - icone quadrate stondate da 14–16 px;
  - bottoni, chip, nav e campo di ricerca tutti "pill" (999 px);
  - nessuna ombra: la profondità viene dai blocchi di colore piatto.
- **Pattern ricorrenti:**
  - logo: badge giallo quadrato stondato con zampa (icona Lucide `paw-print`) + wordmark
    `arkvel` minuscolo 800;
  - nav principale come gruppo di pillole su superficie, voce attiva navy piena; "Accedi" pill
    con bordo navy 2 px;
  - **hero a blocchi (bento):** blocco giallo grande con badge navy leggermente ruotato (−2°),
    H1 enorme, sottotitolo e ricerca a pillola bianca; a destra un blocco foto pesca e due
    stat (una verde, una superficie);
  - filtri rapidi a chip (Tutti / Cani / Gatti / Altri), attivo navy pieno;
  - card animale: contenitore superficie con padding 12 px, foto stondata su fondo colorato a
    rotazione (giallo / pesca / menta), cuore preferiti in un cerchio bianco da 44 px, nome 800;
    ultima card della griglia navy con CTA gialla "Sfoglia il catalogo";
  - "come funziona" in un pannello superficie: 4 passi su card bianche con numero in un quadrato
    colorato;
  - banda donazione verde con CTA gialla;
  - icone Lucide a tratto, mai emoji.
- **Tono:** pop, affettuoso, giocoso ma affidabile; colori pieni e piatti, tipografia grande,
  tutto stondato. Gli animali restano protagonisti, ma il colore fa da cornice.
- **Accessibilità della direzione:**
  - target ≥ 44 px (già nel mockup);
  - non comunicare stati solo con il colore dei blocchi.

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

> **Esito `/ui-review` di UI-0 (2026-11-03).** Nessuna regressione: lingua, `<html lang>` e
> titoli corretti in IT/EN; suite browser 3/3 verde; LCP 560 ms, CLS 0, INP 176 ms sulla home
> (375 px, CPU 4×, Fast 4G). Problemi già presenti, assegnati alle fasi:
>
> | Gravità | Problema | Fase |
> |---|---|---|
> | Major | Etichette degli enum in italiano anche nella versione EN (`getLabel()` hard-coded in 27 enum; pubblici: `Species`, `Sex`, `Size`, `EnergyLevel`, `AppointmentType`, `OrganizationType`) → `__('enums.…')` + `lang/{it,en}/enums.php`, con test | **UI-1** ✅ risolto |
> | Major | Contrasto sotto AA (`stone-400`/`stone-500` su `brand-50`, testo chiaro sul gradiente CTA) | UI-1 (token) ✅ token AA verificati da test → UI-6 (pagine) |
> | Major | Overflow orizzontale a 320 px causato dalla nav dell'header | UI-3 ✅ |
> | Major | Font mai caricato (manca `@fonts` nel layout) | UI-1 ✅ risolto (Fontsource) |
> | Minor | Nessun selettore di lingua visibile (Major se UI-0 va in produzione prima di UI-3) | UI-3 ✅ |
> | Minor | Echo/Reverb importato su ogni pagina ospite (errori WebSocket in console) → import dinamico solo dove serve | UI-3 ✅ il layout pubblico non carica più `app.js` (Echo serve solo ai pannelli) |
> | Minor | Salto di titoli `h1` → `h3` nel catalogo; bottoni preferiti con nome identico | UI-2 ✅ `x-ui.animal-card` con `heading-level` e preferito «Salva <nome> nei preferiti» (anche il Livewire `FavoriteButton`); il catalogo adotta la card in UI-6b |
> | Minor | Immagini senza `width`/`height`, foto della scheda = miniatura 400×300 ingrandita, `alt` della galleria tutti uguali | UI-4 ✅ componenti pronti (`x-ui.picture`, `x-ui.gallery` con alt per foto); le pagine li adottano in UI-6 |
> | Nit | Mancano `Vary: Accept-Language` e `Content-Language` | UI-8 ✅ |
> | Nit | Suffisso " — ArkVel" hard-coded in `AnimalDetail` e `OrganizationPage` → `public.titles.*` | UI-3 ✅ (`seo.titles.*`) |
> | Nit | Ricerca del catalogo senza label visibile | UI-6b |

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

> **Stato: completata il 2026-11-03** (branch `feat/ui-1-tokens`, partito da
> `feat/ui-0-foundations`; 4 commit, quality gate verde a ogni commit: 1428 test, suite
> browser 8/8).
>
> **Cosa è stato fatto:**
> - **Enum tradotti IT/EN:**
>   - i 26 enum con `HasLabel` leggono da `lang/{it,en}/enums.php`; i testi IT sono invariati,
>     quindi i pannelli Filament non cambiano;
>   - `AppLocale` resta con gli endonimi;
>   - `EnumLabelsTranslationTest` scopre gli enum dal filesystem e fallisce se un case non
>     ha la chiave in entrambe le lingue.
> - **Token** in `resources/css/app.css` (`@theme static`):
>   - **palette OKLCH 50-950:** `navy`, `dusk` (neutri freddi), `sand` (neutri caldi), `sun`,
>     `peach`, `leaf` (verde e menta), `cherry` (errori). I colori del mockup sono ancorati
>     esatti: `navy-900` #1B1B3A, `navy-700` #3A3A70, `navy-950` #13132B, `dusk-700` #4A4A68,
>     `dusk-300` #B8B6C8, `sand-100` #F3F1EA, `sand-200` #E4E1D6, `sand-50` #F7F5EE,
>     `sun-300` #FFD23F, `peach-300` #FFB4A2, `peach-800` #5A2A20, `leaf-200` #B7E4C7,
>     `leaf-700` #2D6A4F;
>   - **semantici** (da usare nei componenti; cambiano in `.dark`):
>     - fondi e testo: `canvas`, `surface`, `surface-raised`, `fg`, `fg-muted`;
>     - bordi: `line` solo decorativo, `line-strong` per input e controlli (≥ 3:1);
>     - azioni: `primary`/`primary-hover`/`on-primary`, `highlight`/`on-highlight` (CTA
>       gialla), `link`, `focus`, `stamp`/`on-stamp` (bollino della hero), `inverse`/`on-inverse`
>       (card scura);
>     - blocchi: `block-sun`, `block-peach`, `block-mint`, `block-leaf`, ciascuno con `on-*`;
>     - stati: `success`, `warning`, `danger`, `info`, ciascuno con `-subtle` e `on-*-subtle`,
>       più `on-danger`;
>   - **tipografia fluida:** `text-display` (48→96 px, 800, −0.045em), `text-h1`…`text-h5`,
>     `text-lead`, `text-body` (17 px / 1.5), `text-small`, `text-caption`;
>   - **forme:** `rounded-panel` 40, `rounded-card` 32, `rounded-step` 28, `rounded-media` 24,
>     `rounded-icon` 14, `rounded-icon-lg` 16 px. Nessuna ombra, tranne `shadow-overlay` per i
>     livelli flottanti;
>   - **spazi:** `gutter`, `panel`, `section` (fluidi), `max-w-page` (1240 px);
>   - **motion:** `ease-out`, `ease-in-out`, `ease-pop`, `--duration-instant`…`--duration-slow`
>     (≤ 300 ms) e `--duration-reveal`, tutte a ~0 con `prefers-reduced-motion`;
>   - **focus:** anello di 3 px. Dentro `bg-inverse`, `bg-stamp` e `bg-block-*` prende il
>     colore `on-*` del blocco (l'anello è disegnato sul fondo del contenitore).
> - **Contrasti verificati da test:**
>   - `App\Support\DesignTokens\ContrastPairs` elenca le 41 coppie ammesse (`ContrastKind`
>     Text ≥ 4,5 / NonText ≥ 3);
>   - `ColorContrastTest` legge `app.css` e le verifica in light e dark (82 casi), insieme ai
>     colori di riferimento del piano e alle coppie vietate (giallo su verde, `line`).
> - **Font:** Bricolage Grotesque Variable con asse `opsz`, da
>   `@fontsource-variable/bricolage-grotesque` 5.3 (subset latin 77 KB woff2). Instrument Sans
>   e Bunny Fonts rimossi: risolve il Major "font mai caricato".
> - **Dark mode:**
>   - classe `.dark` su `<html>` (`@custom-variant dark`), applicata prima del primo paint da
>     `partials/theme-script` (localStorage `arkvel-theme` = light, dark o system);
>   - segue `prefers-color-scheme`, `livewire:navigated` e le altre schede;
>   - `<x-ui.theme-toggle>` (chiaro / scuro / automatico) con `aria-pressed`, solo icone sotto i
>     640 px; più selettori sulla stessa pagina restano sincronizzati.
> - **`/_styleguide`** (route `styleguide`, middleware `EnsureLocalEnvironment`: 404 fuori da
>   `local`). Contiene:
>   - anteprima bento della direzione;
>   - token semantici e palette con valori calcolati;
>   - tabella dei contrasti calcolata dal browser sul tema attivo;
>   - tipografia, forme, spazi, motion;
>   - selettori di tema e lingua.
> - **Test:** feature (ambienti, sezioni, EN, toggle), unit (contrasti) e browser (tema di
>   sistema, persistenza al reload, sincronizzazione, contrasti in light e dark, axe senza
>   problemi critical/serious).
>
> **Dark derivata** (§1-bis), ritoccata dopo la review: ogni livello è un gradino di navy più
> chiaro, perché la prima versione (surface su canvas 1,09:1) era quasi invisibile:
>
> | Token | Valore |
> |---|---|
> | `canvas` | navy-950 #13132B |
> | `surface` | navy-800 |
> | `surface-raised` | navy-700 |
> | `inverse` | navy-600, testo `sand-50` (niente card crema abbagliante) |
> | `fg` / `fg-muted` | `sand-50` / `dusk-300` |
> | `line-strong` | `dusk-400` (≥ 3,7:1 su tutte le superfici) |
> | `primary`, `highlight`, `focus`, `link`, `stamp` | giallo `sun-300` con testo navy |
> | `block-sun` | navy-800, cioè la hero è una superficie scura con accenti gialli |
> | `block-peach` | peach-800, testo `peach-100` (9,9:1). In UI-2 era passato a peach-700, ma in UI-6 si è visto che è quasi identico al rosso di `danger` (1,03:1): meglio un pesca scuro che un blocco che sembra un errore |
> | `block-mint` | leaf-800, testo `leaf-100` (7,7:1; era leaf-900, invisibile sulla card) |
> | `block-leaf` | leaf-600, testo bianco (4,64:1, AA al limite: più chiaro della menta, così restano distinguibili) |
>
> **Esito `/ui-review` della styleguide e review degli standard (2026-11-03).**
>
> - **Misure:** LCP 1,04 s, CLS 0,0001, INP 80 ms (375 px, CPU 4×). Nessun overflow da 320 a
>   1440 px; tutte le coppie della tabella superano la soglia in light e dark (35 al momento della
>   review, 41 dopo l'aggiunta dei focus sui blocchi).
> - **Corretti nella fase:**
>   - focus invisibile sulle CTA dentro i blocchi navy e verdi: navy su navy 1:1;
>   - ricerca a pillola senza contorno ≥ 3:1: ora ha un bordo `on-block-sun`, e su mobile non
>     va più a capo;
>   - dark: surface quasi invisibile sul canvas, card `inverse` crema abbagliante, menta e
>     verde indistinguibili;
>   - chip con il bordo decorativo;
>   - aree scorrevoli non raggiungibili da tastiera;
>   - stringhe della styleguide non tradotte;
>   - transizione dell'anello di focus e dello stato iniziale del toggle;
>   - test con helper globali generici;
>   - test dei contrasti dark che poteva passare su risultati non aggiornati.
> - **Aperti:**
>
>   | Gravità | Punto | Fase |
>   |---|---|---|
>   | Minor | `block-peach` in dark (#5A2A20) si legge marrone ruggine più che pesca. Alternativa da valutare a occhio: un corallo-rosa intorno a `oklch(40% 0.11 22)` | UI-2 ✅ ora peach-700 (vedi §5 UI-2: resta un Nit estetico) |
>   | Minor | Il toggle ha `aria-pressed="false"` su tutti i bottoni finché Alpine non parte (si nota solo in local, con `livewire.js` non minificato). Si può leggere lo stato già nello script del tema | UI-3 ✅ |
>   | Nit | Skip link e `preload` del woff2 latin nella shell pubblica | UI-3 ✅ |
>   | Nit | `x-ui.icon` (Lucide) al posto delle icone SVG inline del toggle (`{!! !!}` con markup costante) | UI-2 ✅ |
>   | Nit | Gli override del focus valgono sulle classi esatte `bg-inverse`, `bg-stamp`, `bg-block-*`. Una variante con opacità (`bg-inverse/90`) non li eredita: i componenti `x-ui` devono usare le classi piene | UI-2 ✅ rispettato in tutta la libreria; i livelli flottanti (`.ui-layer`, `.ui-picker-layer`) rimettono il focus del tema |
>   | Nit | Con una CSP, gli script inline (tema, styleguide) richiederanno un nonce | UI-8 |
>
> **Note tecniche utili alle fasi successive:**
> - **Alias legacy:**
>   - le pagine attuali usano ancora `brand-*` e `accent-*`. Ora sono alias della nuova
>     palette: `brand-50/100/200` → `sand`, `brand-300`…`950` → `navy`, `accent-*` → `leaf`
>     scalato di un passo (bianco su `accent-600` = 6,4:1);
>   - **vanno rimossi in UI-6** quando le pagine migrano; poi `grep brand-\|accent-` deve
>     restituire zero risultati;
>   - `text-stone-500` su `bg-brand-50` resta a 4,38:1: è il Major di contrasto di UI-0, da
>     chiudere in UI-6.
> - **Dark mode opt-in:** `app.blade.php` **non** include ancora `partials.theme-script`, perché
>   le pagine legacy usano colori fissi. Va incluso in UI-3 insieme alla shell con i token
>   semantici (`bg-canvas text-fg` sul body).
> - **Alpine fuori da Livewire:** una vista senza componenti Livewire (come la styleguide) deve
>   includere `@livewireStyles`/`@livewireScripts` per avere Alpine.
> - **Nuovo colore o coppia:** aggiungere la coppia in `ContrastPairs` e lasciare che
>   `ColorContrastTest` la verifichi. Il test legge solo valori `oklch(L% C H)` e riferimenti
>   `var(--color-…)`.
> - **Browser MCP occupati:** se Playwright o Chrome DevTools MCP rispondono "Browser is already
>   in use", Playwright dal host funziona con Chrome di sistema:
>   `require('laravel/node_modules/playwright').chromium.launch({ channel: 'chrome' })`.

Elenco originale della fase:
- Direzione: **B · Pop affettuoso** (§1-bis). Sostituire i token `brand`/`accent` terracotta/teal
  e rimuovere Instrument Sans/Bunny Fonts; caricare Bricolage Grotesque via Fontsource.
- Token in `@theme`:
  - colori OKLCH (brand, accent, neutri, semantici) per tema chiaro e scuro;
  - tipografia fluida (`clamp`), font display + font testo self-hosted;
  - raggi, ombre, spaziature, durate ed easing delle animazioni.
- Dark mode via classe + `prefers-color-scheme`, con toggle persistito.
- Pagina `/_styleguide` solo in `local` (route condizionata all'ambiente).

### UI-2 · Libreria componenti `x-ui.*`

> **Stato: completata il 2026-11-08** (branch `feat/ui-2-components`, partito da `main`; un commit
> per gruppo di componenti, ognuno verificato in isolamento: pint, phpstan, Pest completo e
> suite browser. A fine fase: 1737 test, suite browser 78/78).
>
> **Cosa è stato fatto:**
> - **Dipendenze (§2):** `mallardduck/blade-lucide-icons` 2.0 (icone Lucide) e `cally` 0.9 (date
>   picker, import dinamico). `blade-ui-kit/blade-icons` c'era già, tramite Filament.
> - **Libreria `x-ui.*`** in `resources/views/components/ui/`, sei gruppi:
>   - **actions:** `icon`, `button` (5 varianti, 3 dimensioni ≥ 44 px, link, solo icona, attesa
>     con `data-loading` di Livewire), `link`, `badge`, `tag` (filtro con `aria-pressed`,
>     rimovibile);
>   - **forms:** `field` (componente a classe `App\View\Components\Ui\Field`), `input`,
>     `textarea`, `select`, `checkbox(-group)`, `radio(-group)`, `switch`;
>   - **pickers:** `combobox` (APG, x-anchor, ricerca lato server), `date-picker` (Cally,
>     popover o inline);
>   - **overlays:** `dialog`, `drawer` (laterale o bottom sheet), `dropdown`, `tooltip`, `toast`
>     + `toaster`;
>   - **content:** `tabs`, `accordion`, `skeleton`, `empty-state`, `avatar` (+ gruppo);
>   - **domain:** `animal-card` v2 (+ `favorite`, `cta`, `skeleton`), `organization-card`,
>     `stat`, `section-heading`, `cta-band`.
> - `x-ui.theme-toggle` usa `x-ui.icon`. Il Livewire `FavoriteButton` usa
>   `x-ui.animal-card.favorite`: nome fisso «Salva <nome> nei preferiti» e stato in
>   `aria-pressed` (toggle APG), senza cambiare comportamento.
> - **Token:** nuovo `danger-hover`; in dark `block-peach` passa a peach-700. Coppie nuove in
>   `ContrastPairs`: `on-danger`/`danger-hover`, `primary` su `canvas` e `surface` (controlli
>   selezionati), `focus` su `danger-subtle`, `danger` su `surface-raised`.
> - **Styleguide:** una pagina per gruppo (`/_styleguide/{group}`, `StyleguideGroups`), con
>   layout e componenti di documentazione `x-styleguide.*` (sezione, esempio con codice, note di
>   accessibilità). Ogni componente ha esempi per variante e stato, anche su fondi colorati e in
>   dark.
> - **Test:** per ogni gruppo un file di rendering (`tests/Feature/Ui/Components`) e uno browser
>   (`tests/Browser/Components`: axe in light e dark, tastiera, target ≥ 44 px, overflow a
>   mobile). Unit per `App\Support\Ui` (`ControlAttributes`, `FieldControl`, `ResolvedControl`).
>   Helper condiviso `Tests\Support\Ui\BrowserScripts`.
> - **Docker:** `icu-data-full` nell'immagine `php`: senza, `Number::format(locale: 'it')`
>   formattava all'inglese.
>
> **Note tecniche utili alle fasi successive:**
> - **Shell (UI-3):** il layout pubblico deve caricare `@vite(['resources/css/app.css',
>   'resources/js/app.js', 'resources/js/ui.js'])`, includere `partials.theme-script` e un solo
>   `<x-ui.toaster />` in fondo al `<body>`. Da Livewire un toast si lancia con
>   `$this->dispatch('toast', type: 'success', title: …)`; anche `session('toast')` viene letto.
> - **JS dei componenti:** `resources/js/ui.js` carica ogni `resources/js/ui/<gruppo>.js` (glob
>   che esclude i nomi con spazio); i moduli registrano store e `Alpine.data()` su `alpine:init`.
>   Il CSS di un gruppo (`resources/css/ui/<gruppo>.css`) è importato dal suo modulo JS.
> - **Moduli:** dentro `x-ui.field` i controlli prendono id, name, `required`, aiuto ed errore dal
>   field con `@aware(['uiField' => null])`. Un id, name o `required` proprio e diverso lancia
>   un'eccezione (usare `for` / `name` / `required` del field). `@aware` non attraversa un
>   componente Livewire figlio: il field va dentro la vista Livewire. Fuori da un field l'errore
>   si legge anche dalla chiave `wire:model`.
> - **Livelli flottanti:** menu, tooltip, liste di combobox e calendario sono `popover="manual"`
>   nel top layer, posizionati con `x-anchor` (`.fixed`). Un dialog modale rende inerte tutto il
>   resto, anche i popover esterni: per questo ogni dialog e drawer ha il suo outlet dei toast.
> - **Card animale:** link esteso sul nome (pseudo-elemento), preferito come fratello sopra
>   (z-index), `heading-level` da allineare alla gerarchia della pagina. Foto con `<img>`
>   semplice e proporzioni fisse: `x-ui.picture` arriva in UI-4.
> - **Workflow multi-agente:** i gruppi sono stati costruiti in parallelo sulla stessa working
>   copy (Docker monta solo `./laravel`, quindi niente worktree), con file di proprietà per
>   gruppo (viste, pagina di styleguide, `lang/*/ui_<gruppo>.php` e `styleguide_<gruppo>.php`,
>   test, modulo JS) e build + test browser serializzati da uno script con lock.
> - **Copie di sincronizzazione:** il client di sync della cartella ricrea copie `"file 2.ext"`,
>   soprattutto dopo `git stash`. Le migrazioni duplicate rompono i test e un modulo JS duplicato
>   registrava due volte i listener: controllare `git status` e spostarle via prima di testare.
> - **Copertura:** il container non ha pcov/xdebug, quindi la copertura al 100% delle classi in
>   `App\Support\Ui` è verificata a mano (ogni ramo ha un test).
>
> **Esito `/ui-review` della styleguide (2026-11-08).**
>
> - **Misure** (375 px, CPU 4×, rete lenta): LCP 1,28–1,32 s, CLS ≤ 0,029, INP ≤ 56 ms. axe:
>   nessuna violazione su 7 pagine in light e dark; nessun overflow da 320 px; console pulita.
> - **Corretti nella fase** (review degli standard e `/ui-review`):
>   - toast sotto il backdrop di un dialog aperto, e quindi non annunciati: ogni dialog e drawer
>     ha il suo outlet;
>   - toast che coprivano l'elemento con il focus (WCAG 2.4.11): la pagina scorre per mostrarlo,
>     su mobile restano al massimo 2 toast, Esc chiude quelli non di errore;
>   - scheda di `x-ui.tabs` con il focus fuori dalla lista scorrevole a 320–375 px;
>   - header e footer fissi del drawer sopra l'elemento con il focus; CTA del footer a capo dentro
>     la pill;
>   - `id` proprio di un controllo dentro `x-ui.field` che staccava label e descrizioni;
>     regole su id ed errore duplicate in 7 viste (ora in `App\Support\Ui`); `x-ui.field`
>     riconosciuto da qualunque antenato con `label` (ora `@aware(['uiField'])`);
>   - intestazione del date picker che non seguiva i cambi di valore dall'esterno; id del
>     combobox che cambiava a ogni ricerca lato server; `label for` puntato a un `div`;
>   - skeleton con `aria-busy` che impediva l'annuncio; focus perso su `body` chiudendo un toast;
>   - avatar giallo invisibile in dark su una superficie; iniziali tagliate nei gruppi di
>     avatar; badge info invisibile in dark (`info-subtle` ora navy-600);
>   - bottone preferiti con nome che cambiava insieme ad `aria-pressed`; nome di landmark
>     duplicato nella pagina dei token; testi inglesi misti UK/US (ora US, come il resto
>     dell'app); modulo JS caricato due volte da una copia di sincronizzazione.
> - **Aperti:**
>
>   | Gravità | Punto | Fase |
>   |---|---|---|
>   | Nit | In dark `block-peach` per un revisore si legge mattone/terracotta; le alternative più rosse si confondono con `danger` | UI-6 ✅ peach-800 (vedi tabella dark di UI-1) |
>   | Nit | In dark `primary`, `highlight` e `stamp` sono lo stesso giallo: documentato nella styleguide (un solo CTA giallo per gruppo); il bollino «In evidenza» giallo su una foto gialla si separa solo col bordo | UI-6 |
>   | Nit | CLS 0,029 sulla pagina domain per lo swap del font: `preload` del woff2 | UI-3 ✅ (preload nel layout pubblico; la styleguide ha un layout suo) |
>   | Minor | Combobox in ricerca lato server: id delle opzioni per indice, `aria-activedescendant` può puntare a un'altra opzione dopo una risposta; modalità da provare su una pagina vera | UI-8 (il catalogo usa select e radio, non il combobox) |
>   | Minor | Dialog alti su mobile: la pila dei toast può coprire il fondo del dialog (restano chiudibili e raggiungibili) | UI-7 |
>   | Nit | `x-ui.checkbox`/`x-ui.radio` riconoscono il gruppo con `@aware` (legend + name); contatore della textarea non aggiornato se Livewire azzera il valore dal server | UI-6c |
- **Primitivi:** button (varianti/dimensioni/stato loading via `data-loading`), link, badge, tag,
  input, textarea, select, checkbox/radio/switch, field (label + help + errore), combobox
  (Alpine Anchor), date picker (wrapper Cally).
- **Overlay e feedback:** dialog, drawer/sheet, dropdown, tooltip, toast (store Alpine +
  eventi Livewire), tabs, accordion, skeleton, empty state, avatar, icon (Lucide).
- **Dominio:** `animal-card` v2, `organization-card`, `stat`, `section-heading`, `cta-band`.
- Ogni componente: documentato nella styleguide, test di rendering, test browser con assert
  di accessibilità.

### UI-3 · Shell del sito

> **Stato: completata il 2026-11-08** (branch `feat/ui-3-shell`, partito da `feat/ui-2-components`;
> quality gate e suite browser verdi: 1928 test, browser 112/112). Shell e SEO sono state
> costruite in parallelo da due agenti con file separati, poi riviste (standard + `/ui-review`)
> e corrette prima del commit.
>
> **Cosa è stato fatto:**
> - **Layout pubblico** (`layouts/app.blade.php`): token semantici, skip link, preload del woff2
>   (`App\Support\Site\FontPreload` dal manifest Vite), solo `app.css` + `ui.js` (Echo resta
>   ai pannelli), un `<x-ui.toaster />`, `@livewireStyles`/`@livewireScripts` espliciti.
> - **Opt-in per pagina** con `->layoutData([...])`: `themeable` (tema scuro; senza, la pagina
>   resta chiara con `data-theme-forced="light"` e il selettore del tema non compare) e
>   `transparentHeader` (header trasparente sulla hero, solido allo scroll). In UI-6 ogni pagina
>   migrata passa `themeable => true`; la Home anche `transparentHeader => true`.
> - **Header** (`components/site/header`): logo, nav a pillole (`x-ui.nav-link`, `aria-current`
>   `page` sulla pagina e `true` sulla sezione), lingua (`x-ui.language-switcher`, form POST senza
>   JS), menu account (`x-ui.dropdown`) o Accedi/Registrati; sotto `md` tutto passa in un
>   `x-ui.drawer`. Voci centralizzate in `App\Support\Site\SiteNavigation`.
> - **Account:** `App\Support\Site\AccountLinks` è l'unico punto con gli URL di login,
>   registrazione, area adottante, gestione ente e logout. Oggi login e registrazione puntano al
>   pannello Filament adottante: **in UI-5 cambiano solo `login()` e `register()`**. Il link a
>   `/manage` compare ai membri verificati di un ente operabile (`operableOrganizations()`, la
>   stessa regola del pannello shelter). Logout pubblico `POST /esci` (`logout.public`, Action
>   `LogoutUser`): non passa dal pannello adottante, che assegnerebbe il ruolo Adopter.
> - **Footer:** link, newsletter segnaposto (form disabilitato), note legali «in arrivo» senza
>   link rotti (TODO pagine legali), lingua, tema.
> - **Navigazione:** `wire:navigate.hover` sui link interni, `wire:transition.navigate` sul
>   `<main>` (animazione con i token, saltata con reduced-motion), barra di progresso con
>   `var(--color-primary)` (`config/livewire.php`), regione `aria-live` persistente che annuncia
>   la pagina caricata; i dialog aperti si chiudono prima di navigare.
> - **SEO** (`App\Support\Seo`): `SeoData` per pagina (title, description, canonical, robots,
>   Open Graph, Twitter card) costruito da `PublicPageSeo`; JSON-LD (WebSite, Organization,
>   AnimalShelter/NGO per gli enti con indirizzo solo città/regione, ItemPage + Thing per la scheda
>   animale, BreadcrumbList) **nel body**, perché con `wire:navigate` gli script del `<head>` si
>   accumulerebbero; breadcrumb visibili (`x-ui.breadcrumb`); niente hreflang (stessa URL per IT
>   ed EN, lingua in sessione); `noindex` su donazioni, ricerche testuali, catalogo vuoto, pagine
>   fuori range.
> - **Sitemap e robots:** `/sitemap.xml` con gli stessi scope di visibilità delle pagine
>   (cache 10 minuti, XMLWriter) e `/robots.txt` dinamico, entrambi senza sessione e con URL
>   sempre da `APP_URL` (`CanonicalUrl`). Nginx passa `/robots.txt` a Laravel.
> - **Host fidati:** `trustHosts` (host di `APP_URL` + `APP_TRUSTED_HOSTS`) fuori da local e test:
>   un header Host falso riceve 400 e non può avvelenare canonical, OG o la sitemap in cache.
>   In produzione gli health check per IP vanno aggiunti a `APP_TRUSTED_HOSTS`.
> - **Styleguide:** pagina `/_styleguide/shell` (nav-link, lingua, anteprima di header e footer).
>
> **Esito delle review (2026-11-08):** `/ui-review` «ship» (nessun overflow da 320 a 1440 px,
> nessun WebSocket, tema chiaro forzato sulle pagine legacy, axe 0 violazioni sulla shell, anche
> in dark simulato; LCP 0,7–1 s, CLS 0, INP ≤ 56 ms a 375 px). Corretti prima del commit:
> avvelenamento della sitemap via header Host, entità HTML nelle descrizioni SEO, logout che
> assegnava il ruolo Adopter, sessione inutile per i crawler, pagine fuori range indicizzabili,
> nome del menu account, «Registrati» che non si nascondeva sotto `lg`, breadcrumb a 320 px,
> shell assente dalla styleguide.
>
> **Aperti:**
>
> | Gravità | Punto | Fase |
> |---|---|---|
> | Minor | Pagine legacy dentro la shell nuova: disallineamento di ~45 px tra breadcrumb/header (`max-w-page`) e contenuto vecchio a 1440 px; contrasti e `heading-order` delle pagine già noti | UI-6 ✅ |
> | Nit | Con il mouse `wire:navigate.hover` prefetcha e poi richiede di nuovo la pagina al click (2 fetch): verificare il comportamento di Livewire 4 | UI-8 |
> | Nit | Nginx locale senza compressione né Cache-Control su `/build/*` | UI-8 ✅ |
> | Nit | Breadcrumb a 320 px: le voci intermedie si accorciano presto (tetto 10ch) | UI-6 ✅ tetto solo sotto `md` |
- Header nuovo (trasparente sulla hero, poi solido allo scroll), menu mobile a drawer,
  selettore lingua, menu account (link a `/account` e, per i membri di un ente, a `/manage`).
- Footer (newsletter placeholder, link legali, lingue).
- `wire:navigate` con prefetch al passaggio del mouse; View Transitions tra pagine;
  barra di progresso navigazione.
- SEO: meta e Open Graph per pagina, JSON-LD (`Organization`, `Animal`→`Thing`/`Product`
  da valutare, `BreadcrumbList`), sitemap, breadcrumb.

### UI-4 · Pipeline immagini

> **Stato: completata il 2026-11-08** (branch `feat/ui-4-images`, partito da `feat/ui-3-shell`;
> 2039 test, browser 123/123). Componenti e pipeline pronti; **l'adozione nelle pagine è in UI-6**.
>
> **Cosa è stato fatto:**
> - **Conversioni** (`App\Enums\ImageConversion`, `App\Support\Media\ImageConversions`): `card`
>   800×1000 (4:5), `hero` 1536×1024 (3:2: proporzione nativa delle fotocamere, ritaglia meno un
>   animale), `og` 1200×630 JPEG, `zoom` (fino a 2048 px, per la lightbox: senza, si caricherebbe
>   l'originale). Ognuna in JPEG + `-webp` + `-avif` se GD li supporta (il nome semplice è sempre
>   JPEG), tutte in coda con responsive images e placeholder (`og` senza). `thumb` invariata e
>   sincrona (la usano Filament e le pagine legacy).
> - **Docker:** GD con `libwebp` e `libavif`. Senza, i formati non vengono registrati e resta il JPEG.
> - **Privacy:** al caricamento l'originale delle foto (`photos`, `avatar` di `Animal`, contratto
>   `CleansImageMetadata`) viene ricodificato senza EXIF/GPS (`StripImageMetadata`, sincrono,
>   prima delle conversioni, idempotente, in caso di errore logga e non blocca l'upload). Il
>   markup non usa mai l'URL dell'originale: finché le conversioni sono in coda si mostra la
>   `thumb` con le proporzioni del preset. Non coperti: gli allegati di `MedicalRecord`.
> - **`x-ui.picture`:** `Media`, oggetto `Picture` o URL statici; `<source>` AVIF/WebP, `srcset`/
>   `sizes`, `width`/`height`, `loading=lazy` o `priority` (`fetchpriority=high`), placeholder come
>   sfondo CSS, `alt` obbligatorio (o `decorative`), fallback con la zampa.
> - **`x-ui.gallery`:** Embla Carousel 8.6 e PhotoSwipe 5.4 (MIT) con import dinamico (Embla solo
>   con più foto, il core di PhotoSwipe solo all'apertura); slide fuori vista `inert`, «n di N»,
>   bottoni e miniature (`thumb`), frecce, lightbox modale con ritorno del focus, alt per foto
>   (proprietà `alt` del media o «Foto n di N di <nome>»), reduced-motion anche nello swipe;
>   senza JS è una lista scorrevole.
> - **Comando** `php artisan arkvel:media:regenerate` (`--model`, `--collection`, `--conversion`,
>   `--only-missing`, `--dry-run`, `--force`) sopra l'Action `RegenerateImageConversions`. Va
>   rilanciato dopo un cambio di driver, librerie o conversioni, a coda vuota.
> - **SEO:** `og:image` dalla conversione `og`, con `thumb` finché è in coda.
> - **Styleguide:** `/_styleguide/media` con immagini d'esempio statiche.
>
> **Note per UI-6 (integrazione):**
> - card: `<x-ui.picture :media="$animal->getFirstMedia('photos') ?? $animal->getFirstMedia('avatar')"
>   conversion="card" fill :alt="…" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw" />`
>   nel box 4:5; `x-ui.animal-card` accetta un `Media`; nelle query del catalogo `with('media')`;
> - scheda: `<x-ui.gallery :media="$animal->getMedia('photos')" :name="$animal->name" priority
>   sizes="(min-width: 1024px) 60vw, 100vw" />`;
> - dopo il deploy: `php artisan arkvel:media:regenerate --only-missing`.
>
> **Esito della review degli standard (2026-11-08):** corretti prima del commit l'originale con
> EXIF/GPS usato come fallback e nel link senza JS, `og` generata ma non usata, ordine dei formati
> (il JPEG ora viene generato per primo), miniature sovradimensionate, `Fit::Crop` sulla thumb
> (ora `Fit::Max`, niente ingrandimenti), stato globale nella galleria, reduced-motion nello swipe.
>
> **Aperti:**
>
> | Gravità | Punto | Fase |
> |---|---|---|
> | Minor | Un `PerformConversionsJob` su una foto da 12 MP dura 6–12 s (AVIF): serve una coda `media` dedicata (`MEDIA_QUEUE`) con `--timeout=300` e `retry_after` più alto, oppure almeno `--timeout=120` sul worker unico (`docker-compose.yml`, deploy) | UI-8 |
> | Minor | `card`/`hero`/`og` ritagliano e possono ingrandire foto piccole: valutare una risoluzione minima all'upload in `AnimalForm` (Filament: va concordato) | UI-8 |
> | Nit | `--only-missing` lanciato con job ancora in coda può accodarli due volte (innocuo) | — |
> | Nit | Allegati delle schede mediche (`MedicalRecord`) non ripuliti dai metadati | UI-8 |
- Conversioni medialibrary: `card` (4:5), `hero` (16:9 / 3:2), `og` (1200×630), WebP + AVIF,
  `withResponsiveImages()` (placeholder sfocato incluso), tutte in coda.
- Componente `x-ui.picture`: `srcset`/`sizes`, `fetchpriority="high"` per l'immagine LCP,
  `width`/`height` dichiarati, lazy loading per le altre.
- Comando di rigenerazione delle conversioni per i media esistenti.
- Galleria scheda animale: Embla + PhotoSwipe (import dinamico).

### UI-5 · Auth pubblica in Livewire (login/registrazione adottante)

> **Stato: completata il 2026-11-08** (branch `feat/ui-5-auth`, partito da `feat/ui-4-images`;
> 2223 test, browser 142/142). Rivista da una review di sicurezza (confronto con le pagine
> Filament sostituite) e da `/ui-review`, corrette prima del commit.
>
> **Com'era con Filament (analisi iniziale):** pagine native del pannello adottante (nessuna
> custom), nessun observer su `User`; la registrazione creava solo l'utente (niente profilo né
> consensi) e mandava la mail di verifica; il ruolo Adopter lo assegnava `EnsureAdopterRole` alla
> prima richiesta del pannello; il reset diceva se l'email non esisteva; rate limit 5/min sul
> login per IP, 2/min su registrazione e reset.
>
> **Cosa è stato fatto:**
> - **Pagine** (`app/Livewire/Auth`, layout pubblico, `themeable`, `noindex`): `/accedi`
>   (`login`), `/registrati` (`register`), `/password-dimenticata` (`password.request`),
>   `/reimposta-password/{token}` (`password.reset`), `/verifica-email` (`verification.notice`),
>   `verification.verify` firmato e `verification.send`; logout `POST /esci` ora si chiama `logout`.
> - **Action** in `app/Actions/Auth` con DTO in `app/Data/Auth` (email normalizzata minuscola):
>   `AuthenticateUser`, `RegisterAdopter` (crea l'utente, assegna il ruolo Adopter, registra la
>   presa visione dell'informativa con versione in activitylog), `SendPasswordResetLink`,
>   `ResetPassword` (nessun login automatico), `VerifyUserEmail`, `SendEmailVerification`,
>   `AssignAdopterRole` (estratta da `EnsureAdopterRole`).
> - **Sicurezza:** rate limit per email+IP e per IP sul login (anti password spraying), limite
>   per IP su ogni invio della registrazione **prima** della validazione (niente enumerazione degli
>   iscritti), honeypot con tempo minimo, reset con risposta sempre uguale, `IntendedUrl` (solo
>   redirect interni), sessione rigenerata, password svuotate dopo un errore, `GuestOnly` anche
>   sulle richieste Livewire, `TRUSTED_PROXIES` da env per avere gli IP reali dietro un proxy.
> - **Destinazione dopo il login** (`App\Support\Auth\PostLoginDestination`): Super Admin →
>   `/admin`, membro di un ente operabile → `/manage`, altri → `/account`. Lo staff non riceve
>   più il ruolo Adopter entrando dal sito pubblico.
> - **Pannello adottante** (unica modifica Filament, solo configurazione): rimossi login,
>   registrazione, reset e verifica; `AuthenticateAdopter` manda gli ospiti a `/accedi`,
>   `EnsureEmailIsVerified` gli utenti non verificati a `/verifica-email`; middleware di auth
>   **persistenti** (altrimenti Livewire non li riapplica su `/livewire/update`). `/manage` e
>   `/admin` mantengono il login Filament.
> - **Mail** di verifica e reset (`app/Notifications/Auth`) con testi `auth_public.mail.*` nella
>   lingua dell'utente; `/manage` continua a usare le notifiche Filament.
> - `App\Support\Site\AccountLinks` punta alle nuove route; nuovo `x-ui.error-summary`.
>
> **Aperti:**
>
> | Gravità | Punto | Fase |
> |---|---|---|
> | **Blocker per la produzione** | Manca la pagina dell'informativa privacy (e dei termini): la registrazione registra la presa visione con `config('arkvel.privacy_policy_version')`, il link comparirà da solo quando esisterà la route `legal.privacy` (`App\Support\Site\LegalLinks`) | prima del rilascio |
> | Minor | Le registrazioni fatte dai pannelli Filament (es. `/manage`) non normalizzano l'email in minuscolo: un account con maiuscole non accederebbe da `/accedi`. Oggi non ci sono dati legacy; una migrazione che porta le email in minuscolo chiude il punto | UI-8 ✅ |
> | Nit | I link di verifica/reset già inviati con le vecchie URL del pannello (`/account/email-verification/…`) rispondono 404 dopo il deploy (scadono in 60 minuti) | — |
> | Nit | `pest --parallel` si è bloccato una volta su un worker: la suite seriale impiega ~20 minuti | UI-8 |
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

> **Stato: completata il 2026-11-09** (branch `feat/ui-6-pages`, partito da `feat/ui-5-auth`;
> 2500 test, browser 195/195). Le quattro pagine sono state costruite in parallelo da quattro
> agenti con file separati (testi in `lang/*/page_{home,catalog,animal,organizations}.php`), poi
> riviste (standard + `/ui-review`) e corrette.
>
> **Commit:** la scheda animale è passata prima da un refactor a comportamento invariato
> (`11ad59e`, componenti figli `AnimalDetail/*` e Action `ContactOrganizationAboutAnimal`), poi
> card con foto (`657f2b5`), pagine (`6e5bae1`) e pulizia del legacy (`0e3c118`). **Deviazione
> voluta dal "un commit per pagina":** le modifiche condivise (paginazione, card, preferiti,
> bottone, breadcrumb) sono intrecciate con tutte le pagine e un commit per pagina non passava
> il gate in isolamento.
>
> **Cosa è stato fatto:**
> - **Home:** hero bento (ricerca, animale in copertina, numeri reali), vetrina filtrabile per
>   specie in un'island Livewire, «come funziona» in 4 passi, segnaposto onesto per le storie,
>   banda donazione; header trasparente sulla hero.
> - **Catalogo:** ricerca istantanea con label visibile e live region, faccette con conteggi
>   (`CountsCatalogFacets`: Meilisearch o database secondo `scout.driver`, sempre limitati agli enti
>   verificati), chip dei filtri attivi, drawer dei filtri su mobile, ordinamento, caricamento
>   continuo (`wire:intersect`) con «Carica altri» e paginazione di riserva, stati vuoti; form GET
>   senza JS; query string compatibile (`q`, `species`…).
> - **Scheda animale:** galleria, schede informative (identikit, carattere, compatibilità, salute),
>   descrizioni nella lingua disponibile con `lang`, pannello azioni sticky su desktop e bottom
>   sheet su mobile con candidatura, affido, prenotazione (date picker + orario libero: nessuna
>   regola nuova) e contatto, riepilogo errori e messaggi di esito con focus, nota per gli ospiti,
>   animali simili (`FindSimilarAnimals`).
> - **Enti:** indice con filtri (`q`, `region`, `type`; i vecchi `regione`/`tipo` sono ancora
>   letti), pagina ente con animali, numeri e widget donazione (comportamento Stripe invariato),
>   pagine grazie/annullo.
> - **Condivisi:** `x-ui.pagination`, `x-site.animal-card` (foto, località `PublicLocation`, età
>   `AnimalAge`), preferiti con una query per pagina (`FindFavoritedAnimalIds` + prop `favorited`
>   di `FavoriteButton`), `WebUrl::safe()`, re-indicizzazione degli animali al cambio di stato di un
>   ente (`OrganizationObserver` → `SyncOrganizationAnimalsSearchIndex`), route `animals.show`
>   solo numerica, bottoni che vanno a capo su mobile, fix di `x-ui.toast` (un `@foreach` con
>   destrutturazione e un apostrofo in un commento rompevano lo stato dei loop di Livewire 4).
> - **Pulizia:** rimossi gli alias `brand-*`/`accent-*`, i componenti legacy (`x-animal-card`,
>   `x-badge`, `x-button`, `x-filter-select`) e le chiavi `public.*` orfane. Token dark dei
>   blocchi rivisti (tabella di UI-1).
>
> **Passi di deploy:** `php artisan scout:sync-index-settings` (senza, l'ordinamento su `featured`
> fallisce: il catalogo degrada e logga un warning) e `php artisan arkvel:media:regenerate
> --only-missing`.
>
> **Aperti:**
>
> | Gravità | Punto | Fase |
> |---|---|---|
> | Minor | Catalogo: cliccando un link di paginazione mentre `wire:intersect` sta ancora caricando la pagina successiva compaiono errori in console (drawer e toaster morfati a navigazione già partita) | UI-8 ✅ |
> | Nit | Dopo «Carica altri» tutte le pagine caricate sono marcate `aria-current` nella paginazione | UI-8 ✅ |
> | Nit | Transizione animata card → scheda non fatta (richiede `view-transition-name` per animale su card e galleria) | UI-7 ✅ |
> | Nit | Le foto coprono il blocco colorato della card: lo «sfondo colorato» della direzione B si vede solo senza foto | UI-7 ✅ cornice nel tono della card |
> | Nit | Bianco su `block-leaf` in dark a 4,64:1: AA ma con poco margine | — |
- **UI-6a Home:** hero a blocchi (bento) come da §1-bis, griglia di animali in evidenza con chip filtro,
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

> **Stato: completata il 2026-11-09** (branch `feat/ui-7-motion-map`, partito da `feat/ui-6-pages`;
> 2593 test, browser 234/234). Motion e mappa costruiti in parallelo da due agenti, poi riviste
> (standard + `/ui-review`) e corrette.
>
> **Mappa** (`maplibre-gl` 6, BSD-3; tile OpenFreeMap, stili `positron`/`fiord` configurabili con
> `MAP_STYLE_LIGHT`/`MAP_STYLE_DARK`):
> - catalogo con vista «Elenco / Mappa» (`?view=map`, nella cronologia): enti con animali che
>   corrispondono ai filtri (`BuildCatalogMap`, stesse regole di visibilità del catalogo), marker e
>   cluster come bottoni da 44 px nell'ordine dell'elenco, riquadro `dialog` sempre dentro la
>   cornice, etichette nella lingua della pagina, Ctrl/⌘ + rotella o due dita per muovere la mappa,
>   elenco alternativo sempre presente, fallback se le tile non arrivano; in vista mappa non si
>   caricano griglia né preferiti;
> - pagina ente: sezione «Dove si trova» con un cerchio sull'area approssimata;
> - **privacy:** le coordinate degli enti sono pubblicate solo come centro di una cella di una
>   griglia (`ApproximateLocation`, `MAP_GRID_DEGREES`, default 0,02° ≈ 2 km; raggio del cerchio
>   1,6 km, che contiene sempre la posizione reale); mai indirizzo, CAP o coordinate esatte;
> - **host esterno:** quando una mappa entra in vista il browser contatta `tiles.openfreemap.org`
>   (vede IP e area consultata; niente chiavi, cookie o tracker). Va citato nell'informativa
>   privacy; in produzione si può passare a tile self-hosted cambiando solo l'URL dello stile.
> - Bug trovato in verifica manuale e corretto: `maplibre-gl.css` rimetteva il contenitore in
>   `position: relative` (altezza 0, mappa invisibile pur «pronta»): c'è un test di regressione.
>
> **Motion:** `motion` 14 (MIT) solo come `motion/mini` per il cuore dei preferiti (5 KB gzip,
> caricato al primo hover/focus); il resto in CSS. Comparsa allo scroll con `x-reveal`
> (IntersectionObserver, mai sopra la piega né sull'LCP, visibile senza JS e con reduced-motion,
> disattivabile con `UI_REVEAL=false`), pop dell'icona dei toast, foto che passa dalla card alla
> scheda con le View Transitions di Livewire (`AnimalPhotoTransition`, nomi univoci per pagina),
> cornice nel tono della card intorno alla foto. Scostamento voluto dal piano: niente
> `wire:intersect` + Motion per le reveal (più leggero così).
>
> **Test e ambiente:** phpunit.xml e `TestCase` forzano `SCOUT_DRIVER=collection` (l'indice
> Meilisearch di sviluppo si era sporcato con una prova manuale); il catalogo filtra gli enti
> verificati anche nella query al motore, quindi un indice stantio non mostra card fantasma.
>
> **Misure** (375 px, CPU 4×): LCP 0,6–0,9 s, CLS 0 (anche con reveal e mappa), INP ≤ 72 ms.
>
> **Aperti:**
>
> | Gravità | Punto | Fase |
> |---|---|---|
> | Minor | La suite browser lanciata subito dopo Pest completo a volte si blocca dopo `HomeTest`; da sola passa sempre in ~2,5 minuti | UI-8 |
> | Nit | Stile `fiord` (dark): etichette maiuscole e un triangolo più chiaro a zoom basso (stile upstream) | — |
> | Nit | Tile vettoriali bloccate ma stile caricato: mappa vuota senza messaggio (l'elenco c'è) | — |
- Micro-interazioni: preferito (cuore animato), hover delle card, reveal allo scroll con
  `wire:intersect` + Motion, conferme toast. Tutto disattivato con `prefers-reduced-motion`.
- Mappa MapLibre (import dinamico, `wire:ignore` + Alpine) su catalogo e pagina ente, con
  posizioni approssimate e un elenco alternativo accessibile.
- GSAP solo se una sezione d'effetto lo giustifica.

### UI-8 · Qualità e rilascio

> **Stato: completata il 2026-11-09** (branch `feat/ui-8-quality`, partito da
> `feat/ui-7-motion-map`). Gli agenti di questa fase si sono fermati più volte per lo stop del
> Mac: il lavoro è stato diviso in pezzi piccoli e in parte completato dalla sessione principale.
>
> **Cosa è stato fatto:**
> - **CI:** job `browser` in `.github/workflows/ci.yml` (Node 22, `npm ci && npm run build`,
>   cache dei browser Playwright sulla sua versione, Pest `tests/Browser`, screenshot come
>   artifact se fallisce; `memory_limit=1G`). Validato con actionlint; la prima esecuzione reale
>   su GitHub va osservata.
> - **Nginx:** gzip (brotli non disponibile nell'immagine alpine), `immutable` per `/build/*`,
>   `no-cache` per il manifest, una settimana per `/images/*`.
> - **Header di lingua:** middleware `ContentLanguage` (`Content-Language` e
>   `Vary: Accept-Language` sulle sole risposte HTML).
> - **Catalogo:** le richieste Livewire in volo dei componenti sostituiti vengono annullate
>   all'avvio di `wire:navigate` (`shell.js`: niente più errori in console seguendo la
>   paginazione durante il caricamento continuo); una sola pagina `aria-current` nella
>   paginazione, le altre caricate sono «già mostrate».
> - **Email:** forma canonica (minuscolo, senza spazi) in un punto unico (`EmailAddress`) usata dai
>   DTO dei moduli e dal mutator di `User` (copre anche i pannelli Filament senza toccarli);
>   migrazione per le email esistenti che salta e logga gli account in conflitto.
> - **Documenti:** [WCAG-CHECKLIST.md](WCAG-CHECKLIST.md) (criteri WCAG 2.2 AA, come sono
>   soddisfatti e dove sono testati) e [RILASCIO-UI.md](RILASCIO-UI.md) (bloccanti, variabili,
>   passi di deploy, worker, nginx, verifiche).
>
> **Core Web Vitals finali** (375 px, CPU 4×, rete ~1,6 Mbps / 150 ms, asset compressi):
>
> | Pagina | LCP | CLS |
> |---|---|---|
> | Home | 0,92 s | 0 |
> | Catalogo (griglia) | 0,84 s | 0 |
> | Catalogo (mappa) | 0,79 s | 0 |
> | Scheda animale | 0,89 s | 0 |
> | Indice enti | 0,78 s | 0 |
> | Pagina ente | 0,80 s | 0 |
> | Accedi | 0,76 s | 0 |
>
> INP misurato nelle fasi UI-6/UI-7 sulle interazioni principali: ≤ 72 ms. Budget §4 rispettato
> ovunque.
>
> **Resta aperto:** vedi la sezione «Punti aperti residui» di [RILASCIO-UI.md](RILASCIO-UI.md).
> Bloccante per la produzione: la pagina dell'informativa privacy.
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
[Contesto comune] Esegui la fase UI-1. Direzione visiva scelta: "B · Pop affettuoso", specifica
in §1-bis (riferimento visivo: artboard "B · Pop affettuoso" del canvas linkato lì; la direzione A
è stata scartata, ignora i token terracotta esistenti). Rispetta la tabella dei contrasti di
§1-bis e deriva la dark mode secondo le indicazioni lì riportate.
Risolvi anche i punti assegnati a UI-1 nella tabella "Esito /ui-review di UI-0" (§5 UI-0), in
particolare le etichette degli enum tradotte IT/EN.
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
