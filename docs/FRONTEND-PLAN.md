# ArkVel — Public frontend UI plan (Livewire)

> Goal: a modern, polished, "web-agency-grade" public site, built **only with open-source /
> free software**. The Filament panels (`/account`, `/manage`, `/admin`) **are not touched**,
> with a single agreed exception: moving the adopter login/registration (phase UI-5).
>
> Status: proposal approved on 2026-11-03. UI-0…UI-8 complete: the public UI refactor is done. Each phase should be launched in a new chat with the
> super-prompt at the end of this document.

---

## 1. Starting point (analysis 2026-11-03)

- **Stack:** Tailwind 4 (CSS-first config in `resources/css/app.css`), Vite 8, Livewire 4.4
  (coming in through Filament), Alpine (included in Livewire), Echo/Reverb.
- **Current tokens:** a `brand` (terracotta) and `accent` (teal) palette, `stone` neutrals,
  Instrument Sans font via Bunny Fonts (configured in Vite but never loaded in the layout: `@fonts`
  is missing). Replaced in UI-1 with direction B (§1-bis); `brand-*`/`accent-*` remain only
  as temporary aliases (see §5 UI-1).
- **Blade components:** `site-header`, `site-footer`, `animal-card`, `badge`, `button`,
  `filter-select`. Inline SVG icons (~32), no UI library.
- **Public Livewire pages:** `Home`, `AnimalCatalog` (Scout/Meilisearch), `AnimalDetail`
  (496 lines: application, fostering, booking, contact), `FavoriteButton`,
  `OrganizationIndex`, `OrganizationPage` (+ Stripe donation), `DonationThankYou`,
  `DonationCancelled`.
- **Gaps:**
  - images: a single `thumb` 400×400 conversion, also used on the animal page;
    no `srcset`, AVIF/WebP or placeholder;
  - no dark mode, map or page transitions;
  - default locale `en` (the standard is IT), no language switcher,
    `#[Title]` hard-coded in Italian;
  - no browser tests;
  - login and registration exist only as Filament pages;
  - the default `welcome.blade.php` still present.

---

## 1-bis. Chosen visual direction: **B · Affectionate pop** (2026-11-03)

Reference mockup: the "ArkVel — Visual directions" canvas, artboard **B · Affectionate pop**
(https://claude.ai/artifact/2iJmacpuA1C2YsvhA3cL86).

> **Change of direction.** The first choice was "A · Warm editorial" (Fraunces + Instrument
> Sans; cream, terracotta and sage). On 2026-11-03, after UI-0 and before UI-1, it was
> replaced by B. The terracotta brand exits the stage: the current `brand-*`/`accent-*` tokens in
> `resources/css/app.css` are to be replaced in UI-1. The Filament panels don't change, because
> they already use their own colors (Amber, Rose, Emerald).

- **Typography:**
  - **a single family:** [Bricolage Grotesque](https://fontsource.org/fonts/bricolage-grotesque),
    variable with an optical `opsz` axis 12–96, weights 400/600/800, OFL license;
  - self-hosted via Fontsource (`@fontsource-variable/bricolage-grotesque`);
  - very tight 800 headings (`letter-spacing` from −0.035em to −0.045em, `line-height` ~0.95–1.05),
    fluid H1 `clamp(48px, 7vw, 96px)`;
  - body text 17 px, `line-height` 1.5; labels and buttons 600–700;
  - Instrument Sans and the Bunny Fonts loading in `vite.config.js` are to be removed.
- **Starting colors** (to be converted to OKLCH and extended into 50-950 scales in UI-1):

  | Role | Value | Use |
  |---|---|---|
  | Background | `#FFFFFF` | page |
  | Surface | `#F3F1EA` | cards, pill nav, "how it works" panels |
  | Border | `#E4E1D6` | decorative only (see the contrasts) |
  | Ink (navy) | `#1B1B3A` | text, primary button, dark cards; hover `#3A3A70` |
  | Secondary text | `#4A4A68` | proposed, to be validated in UI-1 |
  | Primary (yellow) | `#FFD23F` | hero block, CTA on dark or green backgrounds, badges |
  | Peach | `#FFB4A2` | photo blocks and step numbers; text over it `#5A2A20` |
  | Green | `#2D6A4F` | stats, donation band (white text) |
  | Mint | `#B7E4C7` | photo blocks and step numbers |

- **Verified contrasts** (WCAG 2.x):

  | Pair | Ratio | Result |
  |---|---|---|
  | navy on yellow | 11.5:1 | AA/AAA |
  | navy on peach | 9.7:1 | AA/AAA |
  | navy on mint | 11.8:1 | AA/AAA |
  | navy on surface | 14.7:1 | AA/AAA |
  | `#5A2A20` on peach | 6.9:1 | AA |
  | white on green | 6.4:1 | AA |
  | `#4A4A68` on white / surface | 8.5:1 / 7.5:1 | AA/AAA |
  | **yellow on green** | **4.43:1** | **not good for normal text**: on green, yellow is only a background (button with navy text) or text ≥ 24 px |
  | **`#E4E1D6` on white** | **1.31:1** | **decorative only**: input and control borders need a tone ≥ 3:1 (WCAG 1.4.11) |

- **Dark mode** (to be derived in UI-1, not in the mockup):
  - a very dark navy background (e.g. `#13132B`), lighter navy surfaces, text `#F7F5EE`
    (16.7:1), secondary text around `#B8B6C8` (9.1:1);
  - yellow stays an accent (buttons, badges, numbers), **never** a large surface: no full-yellow
    hero block, which would glare in the dark (use a dark surface with yellow accents);
  - peach, mint and green should be desaturated or darkened as blocks, with light text.
- **Shapes:**
  - very generous radii: panels 40 px, cards 32 px, images in cards 24 px, steps 28 px;
  - rounded-square icons at 14–16 px;
  - buttons, chips, nav and the search field all "pill" (999 px);
  - no shadows: depth comes from the flat color blocks.
- **Recurring patterns:**
  - logo: a rounded yellow square badge with a paw (Lucide `paw-print` icon) + a lowercase
    `arkvel` 800 wordmark;
  - main nav as a group of pills on a surface, the active item solid navy; "Log in" a pill
    with a 2 px navy border;
  - **block hero (bento):** a large yellow block with a navy badge slightly rotated (−2°),
    a huge H1, a subtitle and a white pill search; on the right a peach photo block and two
    stats (one green, one surface);
  - quick filters as chips (All / Dogs / Cats / Other), active solid navy;
  - animal card: a surface container with 12 px padding, a rounded photo on a rotating colored
    background (yellow / peach / mint), a favorite heart in a 44 px white circle, 800 name;
    the grid's last card navy with a yellow "Browse the catalog" CTA;
  - "how it works" in a surface panel: 4 steps on white cards with a number in a colored square;
  - a green donation band with a yellow CTA;
  - Lucide line icons, never emoji.
- **Tone:** pop, affectionate, playful yet dependable; full, flat colors, large typography,
  everything rounded. The animals stay the stars, but color frames them.
- **Accessibility of the direction:**
  - targets ≥ 44 px (already in the mockup);
  - don't convey states with block color alone.

---

## 2. Chosen frontend stack (all open-source / free)

| Need | Choice | License |
|---|---|---|
| Base components | **Our own** `x-ui.*` library (Blade + Alpine + Tailwind), starting from [Sheaf UI](https://sheafui.dev/) (copy the code into the project) and [Pines](https://devdojo.com/pines) as a reference | MIT |
| Alpine primitives | The Focus, Collapse, Intersect, Anchor, Mask, Sort plugins, those already included in Livewire 4 (*verify in UI-0 which are included*) | MIT |
| Date picker (bookings) | [Cally](https://github.com/wickynilliams/cally) (accessible, localizable web component, < 9 KB) | MIT |
| Carousel / gallery | [Embla Carousel](https://www.embla-carousel.com/) + [PhotoSwipe](https://photoswipe.com/) (lightbox) | MIT |
| Icons | [Lucide](https://lucide.dev/) via `mallardduck/blade-lucide-icons` | ISC/MIT |
| Fonts | Self-hosting via [Fontsource](https://fontsource.org/) (no external CDNs, a GDPR-friendly choice) | OFL |
| Animations | CSS/Tailwind (`starting:`, `@starting-style`), `wire:transition` (View Transitions API), [Motion](https://motion.dev/) for micro-interactions; GSAP (free, standard non-OSS license) only if needed for 1-2 sections | MIT / free |
| Map | [MapLibre GL JS v6](https://maplibre.org/) + [OpenFreeMap](https://openfreemap.org/) tiles | BSD / free |
| Search | Scout + Meilisearch (already present): facet distribution and `_geoRadius` | MIT |
| UI tests | Pest 4 Browser plugin (Playwright): comparison screenshots, accessibility assertions | MIT |

**Excluded:** Flux Pro (paid); maryUI/daisyUI (generic look); React/shadcn (off-stack).

---

## 3. AI tooling (agents, skills, MCP)

| Tool | Role | Setup |
|---|---|---|
| **Laravel Boost** | MCP with up-to-date docs (Livewire 4, Tailwind 4, Pest 4), DB schema, browser logs, `livewire-development` and `tailwindcss-development` skills | `docker compose exec -T php composer require laravel/boost --dev` → `boost:install`. MCP: `docker compose exec -T php php artisan boost:mcp` in `.mcp.json` |
| **frontend-design** (official Anthropic plugin) | Enforces a precise aesthetic direction instead of the generic look | `/plugin install frontend-design@claude-plugins-official` |
| **Playwright MCP** | The agent navigates, interacts, takes multi-viewport screenshots | `claude mcp add playwright -- npx @playwright/mcp@latest` |
| **Chrome DevTools MCP** | Performance traces (LCP/INP/CLS), console, network | `claude mcp add chrome-devtools -- npx chrome-devtools-mcp@latest` |
| `ui-builder` agent (new) | Builds components/pages per the design system | `.claude/agents/ui-builder.md` |
| `design-reviewer` agent (new, read-only) | Screenshots at 375/768/1440 px in light and dark, axe audit, comparison with the design system and the §6 checklist | `.claude/agents/design-reviewer.md` |
| `/ui-review` skill (new) | Runs `design-reviewer` on the pages touched | `.claude/skills/ui-review/` |

> Boost note: `boost:install` only rewrites the `<laravel-boost-guidelines>` block in CLAUDE.md;
> the existing text stays.

---

## 4. Cross-cutting rules for the UI phases

1. **Filament untouchable.** No changes to Resources, Pages, the theme or the panels' CSS.
   The only exception: the auth configuration of `AdopterPanelProvider` in UI-5.
2. **No behavior change.** The restyle doesn't move logic. Logic stays in the
   Actions; the Livewire components stay thin. The existing Feature tests adapt to the new
   markup but must stay green.
3. **i18n:** no hard-coded strings (including `#[Title]`, `alt`, `aria-label`), keys in
   `lang/{it,en}`.
4. **WCAG 2.2 AA accessibility:**
   - visible, unobscured focus, targets ≥ 24×24 px;
   - drag alternatives, `prefers-reduced-motion`, verified contrast.
5. **Performance budget (mobile):** LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1; per-page JS kept small
   (heavy libraries, i.e. the map and lightbox, loaded only where needed, with dynamic import).
6. **Privacy:** self-hosted fonts and assets; maps with approximate location; no trackers.
7. **Quality gate** (pint + phpstan + pest) green on every commit; from UI-2 onward also
   browser tests and `/ui-review` on the pages touched.
8. **Branch** `feat/ui-<phase>` from `main`, Conventional Commits, one commit per component or page.

---

## 5. Phases

### UI-0 · Foundations and tooling

> **Status: completed on 2026-11-03** (branch `feat/ui-0-foundations`, forked from
> `feat/phase-4-communication` with phase 4 closed).
>
> **What was done:**
> - **Boost 2.10:** guidelines in `laravel/CLAUDE.md`, 9 skills in `laravel/.claude/skills/`
>   (including `livewire-development` and `tailwindcss-development`).
> - **Livewire** is now a direct dependency (`^4.4.8`): it's needed for Boost's Livewire skill and
>   fixes advisory PKSA-xhv3-nr94-h8db.
> - **`.mcp.json` in the root:** `laravel-boost` (in the container), `playwright`, `chrome-devtools`.
> - **Agents and skills:** `.claude/agents/ui-builder.md`, `.claude/agents/design-reviewer.md`,
>   the `/ui-review` skill.
> - **Browser tests:**
>   - `pestphp/pest-plugin-browser` 4.3 and `playwright` 1.64.1 (exact version, aligned with the
>     Dockerfile);
>   - the `sockets` extension added to the `php` image and to CI;
>   - a `browser-tests` service (`Dockerfile.browser`, Debian + Chromium, `testing` profile),
>     run with `docker compose --profile testing run --rm browser-tests`;
>   - `tests/Browser` is outside the phpunit testsuites, so it's excluded from the standard run;
>   - to pin the browser's language in tests, use `visit(...)->withLocale('it-IT')`.
> - **Language:**
>   - default `it` in config;
>   - `AppLocale` enum (it, en);
>   - `SetLocale` middleware on the `web` group: session → Accept-Language → default;
>   - `POST /lingua/{locale}` (`locale.update`);
>   - `#[Title]` replaced by translated `public.titles.*`;
>   - `tests/TestCase.php` clears the test client's default Accept-Language.
> - `welcome.blade.php` removed.
>
> **Manual steps (interactive):** `/plugin install frontend-design@claude-plugins-official`
> and approve the `.mcp.json` MCP servers on the next Claude Code start.
>
> **Technical notes useful for later phases:**
> - **Boost:** install it **without `-W`**, otherwise it updates half the lock.
> - **Alpine plugins already included in Livewire 4** (verified against `dist/livewire.esm.js`): Morph,
>   History, Intersect, Sort, Resize, Collapse, Anchor, Focus (x-trap), Persist, Navigate,
>   Mask. Don't install them via npm.
> - **Reverb:** on the dev machine port 8080 may be taken by other Docker projects.
> - **Multiple sessions on the same working copy:** never at the same time. For parallel work
>   use a `git worktree`.

> **`/ui-review` outcome for UI-0 (2026-11-03).** No regressions: language, `<html lang>` and
> titles correct in IT/EN; browser suite 3/3 green; LCP 560 ms, CLS 0, INP 176 ms on the home
> (375 px, CPU 4×, Fast 4G). Pre-existing issues, assigned to phases:
>
> | Severity | Issue | Phase |
> |---|---|---|
> | Major | Enum labels in Italian even in the EN version (`getLabel()` hard-coded in 27 enums; public: `Species`, `Sex`, `Size`, `EnergyLevel`, `AppointmentType`, `OrganizationType`) → `__('enums.…')` + `lang/{it,en}/enums.php`, with a test | **UI-1** ✅ resolved |
> | Major | Contrast below AA (`stone-400`/`stone-500` on `brand-50`, light text on the CTA gradient) | UI-1 (tokens) ✅ AA tokens verified by tests → UI-6 (pages) |
> | Major | Horizontal overflow at 320 px caused by the header nav | UI-3 ✅ |
> | Major | Font never loaded (`@fonts` missing in the layout) | UI-1 ✅ resolved (Fontsource) |
> | Minor | No visible language switcher (Major if UI-0 ships to production before UI-3) | UI-3 ✅ |
> | Minor | Echo/Reverb imported on every guest page (WebSocket errors in the console) → dynamic import only where needed | UI-3 ✅ the public layout no longer loads `app.js` (Echo only serves the panels) |
> | Minor | Heading jump `h1` → `h3` in the catalog; favorite buttons with an identical name | UI-2 ✅ `x-ui.animal-card` with `heading-level` and favorite "Save <name> to favorites" (also the `FavoriteButton` Livewire); the catalog adopts the card in UI-6b |
> | Minor | Images without `width`/`height`, the page photo = a 400×300 thumbnail enlarged, gallery `alt` all identical | UI-4 ✅ components ready (`x-ui.picture`, `x-ui.gallery` with alt per photo); the pages adopt them in UI-6 |
> | Nit | Missing `Vary: Accept-Language` and `Content-Language` | UI-8 ✅ |
> | Nit | Hard-coded " — ArkVel" suffix in `AnimalDetail` and `OrganizationPage` → `public.titles.*` | UI-3 ✅ (`seo.titles.*`) |
> | Nit | Catalog search without a visible label | UI-6b |

Original list for the phase:
- Install Boost (+ `.mcp.json`), Playwright MCP, Chrome DevTools MCP and the frontend-design plugin.
- Create the `ui-builder` and `design-reviewer` agents and the `/ui-review` skill.
- Pest Browser plugin in the `php` container: Playwright and the browsers must be added to the Dockerfile.
  Write a first Home browser smoke test.
- Default locale `it` (fallback `en`), `SetLocale` middleware (session, header, user
  preference), translated `#[Title]`.
- Remove `welcome.blade.php`.
- Verify which Alpine plugins are included in Livewire 4.

### UI-1 · Visual direction and design tokens

> **Status: completed on 2026-11-03** (branch `feat/ui-1-tokens`, forked from
> `feat/ui-0-foundations`; 4 commits, quality gate green on every commit: 1428 tests, browser
> suite 8/8).
>
> **What was done:**
> - **Enums translated IT/EN:**
>   - the 26 enums with `HasLabel` read from `lang/{it,en}/enums.php`; the IT texts are unchanged,
>     so the Filament panels don't change;
>   - `AppLocale` keeps the endonyms;
>   - `EnumLabelsTranslationTest` discovers the enums from the filesystem and fails if a case lacks
>     the key in either language.
> - **Tokens** in `resources/css/app.css` (`@theme static`):
>   - **OKLCH 50-950 palette:** `navy`, `dusk` (cool neutrals), `sand` (warm neutrals), `sun`,
>     `peach`, `leaf` (green and mint), `cherry` (errors). The mockup's colors are anchored
>     exactly: `navy-900` #1B1B3A, `navy-700` #3A3A70, `navy-950` #13132B, `dusk-700` #4A4A68,
>     `dusk-300` #B8B6C8, `sand-100` #F3F1EA, `sand-200` #E4E1D6, `sand-50` #F7F5EE,
>     `sun-300` #FFD23F, `peach-300` #FFB4A2, `peach-800` #5A2A20, `leaf-200` #B7E4C7,
>     `leaf-700` #2D6A4F;
>   - **semantic** (to use in components; they change in `.dark`):
>     - backgrounds and text: `canvas`, `surface`, `surface-raised`, `fg`, `fg-muted`;
>     - borders: `line` decorative only, `line-strong` for inputs and controls (≥ 3:1);
>     - actions: `primary`/`primary-hover`/`on-primary`, `highlight`/`on-highlight` (yellow
>       CTA), `link`, `focus`, `stamp`/`on-stamp` (the hero stamp), `inverse`/`on-inverse`
>       (dark card);
>     - blocks: `block-sun`, `block-peach`, `block-mint`, `block-leaf`, each with its `on-*`;
>     - states: `success`, `warning`, `danger`, `info`, each with `-subtle` and `on-*-subtle`,
>       plus `on-danger`;
>   - **fluid typography:** `text-display` (48→96 px, 800, −0.045em), `text-h1`…`text-h5`,
>     `text-lead`, `text-body` (17 px / 1.5), `text-small`, `text-caption`;
>   - **shapes:** `rounded-panel` 40, `rounded-card` 32, `rounded-step` 28, `rounded-media` 24,
>     `rounded-icon` 14, `rounded-icon-lg` 16 px. No shadows, except `shadow-overlay` for the
>     floating layers;
>   - **spacing:** `gutter`, `panel`, `section` (fluid), `max-w-page` (1240 px);
>   - **motion:** `ease-out`, `ease-in-out`, `ease-pop`, `--duration-instant`…`--duration-slow`
>     (≤ 300 ms) and `--duration-reveal`, all at ~0 with `prefers-reduced-motion`;
>   - **focus:** a 3 px ring. Inside `bg-inverse`, `bg-stamp` and `bg-block-*` it takes the
>     block's `on-*` color (the ring is drawn against the container's background).
> - **Contrasts verified by tests:**
>   - `App\Support\DesignTokens\ContrastPairs` lists the 41 allowed pairs (`ContrastKind`
>     Text ≥ 4.5 / NonText ≥ 3);
>   - `ColorContrastTest` reads `app.css` and verifies them in light and dark (82 cases), together with
>     the plan's reference colors and the forbidden pairs (yellow on green, `line`).
> - **Font:** Bricolage Grotesque Variable with the `opsz` axis, from
>   `@fontsource-variable/bricolage-grotesque` 5.3 (latin subset, 77 KB woff2). Instrument Sans
>   and Bunny Fonts removed: this resolves the "font never loaded" Major.
> - **Dark mode:**
>   - a `.dark` class on `<html>` (`@custom-variant dark`), applied before the first paint by
>     `partials/theme-script` (localStorage `arkvel-theme` = light, dark or system);
>   - follows `prefers-color-scheme`, `livewire:navigated` and other tabs;
>   - `<x-ui.theme-toggle>` (light / dark / auto) with `aria-pressed`, icons only below
>     640 px; multiple switchers on the same page stay in sync.
> - **`/_styleguide`** (route `styleguide`, middleware `EnsureLocalEnvironment`: 404 outside
>   `local`). It contains:
>   - a bento preview of the direction;
>   - semantic tokens and the palette with computed values;
>   - a contrast table computed by the browser on the active theme;
>   - typography, shapes, spacing, motion;
>   - theme and language switchers.
> - **Tests:** feature (environments, sections, EN, toggle), unit (contrasts) and browser (system
>   theme, persistence across reload, syncing, contrasts in light and dark, axe with no
>   critical/serious issues).
>
> **Derived dark** (§1-bis), retouched after the review: each layer is a step of lighter navy,
> because the first version (surface on canvas 1.09:1) was almost invisible:
>
> | Token | Value |
> |---|---|
> | `canvas` | navy-950 #13132B |
> | `surface` | navy-800 |
> | `surface-raised` | navy-700 |
> | `inverse` | navy-600, text `sand-50` (no glaring cream card) |
> | `fg` / `fg-muted` | `sand-50` / `dusk-300` |
> | `line-strong` | `dusk-400` (≥ 3.7:1 on all surfaces) |
> | `primary`, `highlight`, `focus`, `link`, `stamp` | yellow `sun-300` with navy text |
> | `block-sun` | navy-800, i.e. the hero is a dark surface with yellow accents |
> | `block-peach` | peach-800, text `peach-100` (9.9:1). In UI-2 it had moved to peach-700, but in UI-6 it turned out to be nearly identical to the `danger` red (1.03:1): better a dark peach than a block that looks like an error |
> | `block-mint` | leaf-800, text `leaf-100` (7.7:1; it was leaf-900, invisible on the card) |
> | `block-leaf` | leaf-600, white text (4.64:1, AA at the limit: lighter than the mint, so they stay distinguishable) |
>
> **`/ui-review` outcome for the styleguide and the standards review (2026-11-03).**
>
> - **Measurements:** LCP 1.04 s, CLS 0.0001, INP 80 ms (375 px, CPU 4×). No overflow from 320 to
>   1440 px; every pair in the table passes the threshold in light and dark (35 at the time of the
>   review, 41 after adding the focus-on-block pairs).
> - **Fixed in this phase:**
>   - invisible focus on the CTAs inside the navy and green blocks: navy on navy 1:1;
>   - the pill search without a ≥ 3:1 outline: it now has an `on-block-sun` border, and on mobile it
>     no longer wraps;
>   - dark: surface nearly invisible on the canvas, the `inverse` card a glaring cream, mint and
>     green indistinguishable;
>   - chips with the decorative border;
>   - scrollable areas unreachable by keyboard;
>   - untranslated styleguide strings;
>   - the transition of the focus ring and the toggle's initial state;
>   - tests with generic global helpers;
>   - the dark contrast test that could pass on stale results.
> - **Open:**
>
>   | Severity | Item | Phase |
>   |---|---|---|
>   | Minor | `block-peach` in dark (#5A2A20) reads as rust brown more than peach. An alternative to eyeball: a coral-pink around `oklch(40% 0.11 22)` | UI-2 ✅ now peach-700 (see §5 UI-2: it remains an aesthetic Nit) |
>   | Minor | The toggle has `aria-pressed="false"` on all buttons until Alpine starts (only noticeable in local, with an unminified `livewire.js`). The state could be read already in the theme script | UI-3 ✅ |
>   | Nit | Skip link and `preload` of the latin woff2 in the public shell | UI-3 ✅ |
>   | Nit | `x-ui.icon` (Lucide) in place of the toggle's inline SVG icons (`{!! !!}` with constant markup) | UI-2 ✅ |
>   | Nit | The focus overrides apply to the exact classes `bg-inverse`, `bg-stamp`, `bg-block-*`. A variant with opacity (`bg-inverse/90`) doesn't inherit them: the `x-ui` components must use the solid classes | UI-2 ✅ honored throughout the library; the floating layers (`.ui-layer`, `.ui-picker-layer`) restore the theme's focus |
>   | Nit | With a CSP, the inline scripts (theme, styleguide) will need a nonce | UI-8 |
>
> **Technical notes useful for later phases:**
> - **Legacy aliases:**
>   - the current pages still use `brand-*` and `accent-*`. They are now aliases of the new
>     palette: `brand-50/100/200` → `sand`, `brand-300`…`950` → `navy`, `accent-*` → `leaf`
>     scaled by one step (white on `accent-600` = 6.4:1);
>   - **they are to be removed in UI-6** when the pages migrate; then `grep brand-\|accent-` must
>     return zero results;
>   - `text-stone-500` on `bg-brand-50` stays at 4.38:1: it's UI-0's contrast Major, to be
>     closed in UI-6.
> - **Dark mode opt-in:** `app.blade.php` does **not** yet include `partials.theme-script`, because
>   the legacy pages use fixed colors. It's to be included in UI-3 together with the shell using the
>   semantic tokens (`bg-canvas text-fg` on the body).
> - **Alpine outside Livewire:** a view without Livewire components (like the styleguide) must
>   include `@livewireStyles`/`@livewireScripts` to have Alpine.
> - **A new color or pair:** add the pair to `ContrastPairs` and let `ColorContrastTest`
>   verify it. The test reads only `oklch(L% C H)` values and `var(--color-…)` references.
> - **Busy MCP browsers:** if Playwright or Chrome DevTools MCP answer "Browser is already
>   in use", Playwright from the host works with the system Chrome:
>   `require('laravel/node_modules/playwright').chromium.launch({ channel: 'chrome' })`.

Original list for the phase:
- Direction: **B · Affectionate pop** (§1-bis). Replace the terracotta/teal `brand`/`accent` tokens
  and remove Instrument Sans/Bunny Fonts; load Bricolage Grotesque via Fontsource.
- Tokens in `@theme`:
  - OKLCH colors (brand, accent, neutrals, semantic) for the light and dark themes;
  - fluid typography (`clamp`), a display font + a self-hosted text font;
  - radii, shadows, spacing, animation durations and easings.
- Dark mode via a class + `prefers-color-scheme`, with a persisted toggle.
- A `/_styleguide` page in `local` only (the route gated on the environment).

### UI-2 · The `x-ui.*` component library

> **Status: completed on 2026-11-08** (branch `feat/ui-2-components`, forked from `main`; one commit
> per component group, each verified in isolation: pint, phpstan, full Pest and the browser
> suite. At the end of the phase: 1737 tests, browser suite 78/78).
>
> **What was done:**
> - **Dependencies (§2):** `mallardduck/blade-lucide-icons` 2.0 (Lucide icons) and `cally` 0.9 (date
>   picker, dynamic import). `blade-ui-kit/blade-icons` was already there, through Filament.
> - **The `x-ui.*` library** in `resources/views/components/ui/`, six groups:
>   - **actions:** `icon`, `button` (5 variants, 3 sizes ≥ 44 px, link, icon-only, loading
>     with Livewire's `data-loading`), `link`, `badge`, `tag` (filter with `aria-pressed`,
>     removable);
>   - **forms:** `field` (a class component `App\View\Components\Ui\Field`), `input`,
>     `textarea`, `select`, `checkbox(-group)`, `radio(-group)`, `switch`;
>   - **pickers:** `combobox` (APG, x-anchor, server-side search), `date-picker` (Cally,
>     popover or inline);
>   - **overlays:** `dialog`, `drawer` (side or bottom sheet), `dropdown`, `tooltip`, `toast`
>     + `toaster`;
>   - **content:** `tabs`, `accordion`, `skeleton`, `empty-state`, `avatar` (+ group);
>   - **domain:** `animal-card` v2 (+ `favorite`, `cta`, `skeleton`), `organization-card`,
>     `stat`, `section-heading`, `cta-band`.
> - `x-ui.theme-toggle` uses `x-ui.icon`. The `FavoriteButton` Livewire uses
>   `x-ui.animal-card.favorite`: a fixed name "Save <name> to favorites" and the state in
>   `aria-pressed` (APG toggle), without changing behavior.
> - **Tokens:** new `danger-hover`; in dark `block-peach` moves to peach-700. New pairs in
>   `ContrastPairs`: `on-danger`/`danger-hover`, `primary` on `canvas` and `surface` (selected
>   controls), `focus` on `danger-subtle`, `danger` on `surface-raised`.
> - **Styleguide:** one page per group (`/_styleguide/{group}`, `StyleguideGroups`), with a
>   layout and documentation components `x-styleguide.*` (section, example with code, accessibility
>   notes). Each component has examples per variant and state, also on colored backgrounds and in
>   dark.
> - **Tests:** for each group a rendering file (`tests/Feature/Ui/Components`) and a browser one
>   (`tests/Browser/Components`: axe in light and dark, keyboard, targets ≥ 44 px, overflow at
>   mobile). Unit for `App\Support\Ui` (`ControlAttributes`, `FieldControl`, `ResolvedControl`).
>   A shared helper `Tests\Support\Ui\BrowserScripts`.
> - **Docker:** `icu-data-full` in the `php` image: without it, `Number::format(locale: 'it')`
>   formatted in English.
>
> **Technical notes useful for later phases:**
> - **Shell (UI-3):** the public layout must load `@vite(['resources/css/app.css',
>   'resources/js/app.js', 'resources/js/ui.js'])`, include `partials.theme-script` and a single
>   `<x-ui.toaster />` at the end of the `<body>`. From Livewire a toast is dispatched with
>   `$this->dispatch('toast', type: 'success', title: …)`; `session('toast')` is also read.
> - **Component JS:** `resources/js/ui.js` loads each `resources/js/ui/<group>.js` (a glob
>   excluding names with a space); the modules register stores and `Alpine.data()` on `alpine:init`.
>   A group's CSS (`resources/css/ui/<group>.css`) is imported by its JS module.
> - **Modules:** inside `x-ui.field` the controls take id, name, `required`, help and error from the
>   field with `@aware(['uiField' => null])`. An own, different id, name or `required` throws
>   an exception (use the field's `for` / `name` / `required`). `@aware` doesn't cross a child
>   Livewire component: the field must go inside the Livewire view. Outside a field, the error
>   is also read from the `wire:model` key.
> - **Floating layers:** menus, tooltips, combobox lists and the calendar are `popover="manual"`
>   in the top layer, positioned with `x-anchor` (`.fixed`). A modal dialog makes everything
>   else inert, including external popovers: that's why every dialog and drawer has its own toast outlet.
> - **Animal card:** an extended link over the name (a pseudo-element), the favorite as a sibling above
>   (z-index), `heading-level` to align with the page hierarchy. The photo with a plain `<img>`
>   and fixed proportions: `x-ui.picture` arrives in UI-4.
> - **Multi-agent workflow:** the groups were built in parallel on the same working
>   copy (Docker mounts only `./laravel`, so no worktree), with per-group ownership of files (views,
>   styleguide page, `lang/*/ui_<group>.php` and `styleguide_<group>.php`, tests, JS module) and
>   serialized builds + browser tests via a script with a lock.
> - **Sync copies:** the folder's sync client recreates `"file 2.ext"` copies,
>   especially after `git stash`. Duplicate migrations break the tests and a duplicate JS module
>   registered the listeners twice: check `git status` and move them away before testing.
> - **Coverage:** the container has no pcov/xdebug, so the 100% coverage of the classes in
>   `App\Support\Ui` is verified by hand (every branch has a test).
>
> **`/ui-review` outcome for the styleguide (2026-11-08).**
>
> - **Measurements** (375 px, CPU 4×, slow network): LCP 1.28–1.32 s, CLS ≤ 0.029, INP ≤ 56 ms. axe:
>   no violations across 7 pages in light and dark; no overflow at 320 px; clean console.
> - **Fixed in this phase** (standards review and `/ui-review`):
>   - toasts under an open dialog's backdrop, and therefore not announced: each dialog and drawer
>     has its own outlet;
>   - toasts that covered the focused element (WCAG 2.4.11): the page scrolls to show it,
>     on mobile at most 2 toasts remain, Esc closes the non-error ones;
>   - the `x-ui.tabs` tab with focus outside the scrollable list at 320–375 px;
>   - the drawer's fixed header and footer over the focused element; the footer CTA wrapping inside
>     the pill;
>   - an own `id` on a control inside `x-ui.field` that detached the label and descriptions;
>     rules on duplicate id and error in 7 views (now in `App\Support\Ui`); `x-ui.field`
>     recognized by any ancestor with a `label` (now `@aware(['uiField'])`);
>   - the date picker header that didn't follow value changes from outside; the combobox id that
>     changed on every server-side search; `label for` pointing at a `div`;
>   - a skeleton with `aria-busy` that prevented the announcement; focus lost on `body` when closing a toast;
>   - the yellow avatar invisible in dark on a surface; initials cut off in avatar groups;
>     the info badge invisible in dark (`info-subtle` now navy-600);
>   - a favorite button with a name that changed along with `aria-pressed`; a landmark name
>     duplicated on the tokens page; mixed UK/US English text (now US, like the rest
>     of the app); a JS module loaded twice from a sync copy.
> - **Open:**
>
>   | Severity | Item | Phase |
>   |---|---|---|
>   | Nit | In dark `block-peach` reads as brick/terracotta to one reviewer; the redder alternatives get confused with `danger` | UI-6 ✅ peach-800 (see UI-1's dark table) |
>   | Nit | In dark `primary`, `highlight` and `stamp` are the same yellow: documented in the styleguide (a single yellow CTA per group); the yellow "Featured" stamp on a yellow photo separates only by its border | UI-6 |
>   | Nit | CLS 0.029 on the domain page from the font swap: `preload` the woff2 | UI-3 ✅ (preload in the public layout; the styleguide has its own layout) |
>   | Minor | Combobox in server-side search: option ids by index, `aria-activedescendant` may point to another option after a response; the mode to be tried on a real page | UI-8 (the catalog uses select and radio, not the combobox) |
>   | Minor | Tall dialogs on mobile: the toast stack can cover the bottom of the dialog (they stay closeable and reachable) | UI-7 |
>   | Nit | `x-ui.checkbox`/`x-ui.radio` recognize the group with `@aware` (legend + name); the textarea counter not updated if Livewire clears the value from the server | UI-6c |
- **Primitives:** button (variants/sizes/loading state via `data-loading`), link, badge, tag,
  input, textarea, select, checkbox/radio/switch, field (label + help + error), combobox
  (Alpine Anchor), date picker (Cally wrapper).
- **Overlays and feedback:** dialog, drawer/sheet, dropdown, tooltip, toast (Alpine store +
  Livewire events), tabs, accordion, skeleton, empty state, avatar, icon (Lucide).
- **Domain:** `animal-card` v2, `organization-card`, `stat`, `section-heading`, `cta-band`.
- Every component: documented in the styleguide, a rendering test, a browser test with accessibility
  assertions.

### UI-3 · Site shell

> **Status: completed on 2026-11-08** (branch `feat/ui-3-shell`, forked from `feat/ui-2-components`;
> quality gate and browser suite green: 1928 tests, browser 112/112). The shell and SEO were
> built in parallel by two agents with separate files, then reviewed (standards + `/ui-review`)
> and fixed before the commit.
>
> **What was done:**
> - **Public layout** (`layouts/app.blade.php`): semantic tokens, skip link, woff2 preload
>   (`App\Support\Site\FontPreload` from the Vite manifest), only `app.css` + `ui.js` (Echo stays
>   with the panels), a single `<x-ui.toaster />`, explicit `@livewireStyles`/`@livewireScripts`.
> - **Per-page opt-in** with `->layoutData([...])`: `themeable` (dark theme; without it, the page
>   stays light with `data-theme-forced="light"` and the theme switcher doesn't appear) and
>   `transparentHeader` (a transparent header over the hero, solid on scroll). In UI-6 each migrated
>   page passes `themeable => true`; the Home also `transparentHeader => true`.
> - **Header** (`components/site/header`): logo, pill nav (`x-ui.nav-link`, `aria-current`
>   `page` on the page and `true` on the section), language (`x-ui.language-switcher`, POST form without
>   JS), account menu (`x-ui.dropdown`) or Log in/Register; below `md` everything moves into an
>   `x-ui.drawer`. Items centralized in `App\Support\Site\SiteNavigation`.
> - **Account:** `App\Support\Site\AccountLinks` is the single place with the URLs for login,
>   registration, the adopter area, organization management and logout. Today login and registration point to
>   the adopter Filament panel: **in UI-5 only `login()` and `register()` change**. The link to
>   `/manage` appears for verified members of an operable organization (`operableOrganizations()`, the
>   same rule as the shelter panel). Public logout `POST /esci` (`logout.public`, Action
>   `LogoutUser`): it doesn't go through the adopter panel, which would assign the Adopter role.
> - **Footer:** links, a newsletter placeholder (disabled form), legal notes "coming soon" without
>   broken links (TODO legal pages), language, theme.
> - **Navigation:** `wire:navigate.hover` on internal links, `wire:transition.navigate` on the
>   `<main>` (animation with the tokens, skipped with reduced-motion), a progress bar with
>   `var(--color-primary)` (`config/livewire.php`), a persistent `aria-live` region that announces
>   the loaded page; open dialogs close before navigating.
> - **SEO** (`App\Support\Seo`): per-page `SeoData` (title, description, canonical, robots,
>   Open Graph, Twitter card) built by `PublicPageSeo`; JSON-LD (WebSite, Organization,
>   AnimalShelter/NGO for organizations with an address limited to city/region only, ItemPage + Thing for the animal
>   page, BreadcrumbList) **in the body**, because with `wire:navigate` the `<head>` scripts would
>   accumulate; visible breadcrumbs (`x-ui.breadcrumb`); no hreflang (the same URL for IT
>   and EN, language in the session); `noindex` on donations, text searches, an empty catalog, out-of-range
>   pages.
> - **Sitemap and robots:** `/sitemap.xml` with the same visibility scopes as the pages
>   (10-minute cache, XMLWriter) and a dynamic `/robots.txt`, both without a session and with URLs
>   always from `APP_URL` (`CanonicalUrl`). Nginx passes `/robots.txt` to Laravel.
> - **Trusted hosts:** `trustHosts` (the `APP_URL` host + `APP_TRUSTED_HOSTS`) outside local and test:
>   a fake Host header gets a 400 and can't poison the canonical, OG or the cached sitemap.
>   In production, health checks by IP must be added to `APP_TRUSTED_HOSTS`.
> - **Styleguide:** a `/_styleguide/shell` page (nav-link, language, a preview of header and footer).
>
> **Review outcome (2026-11-08):** `/ui-review` "ship" (no overflow from 320 to 1440 px,
> no WebSocket, the light theme forced on the legacy pages, axe 0 violations on the shell, also
> in simulated dark; LCP 0.7–1 s, CLS 0, INP ≤ 56 ms at 375 px). Fixed before the commit:
> sitemap poisoning via the Host header, HTML entities in the SEO descriptions, logout that
> assigned the Adopter role, a useless session for crawlers, out-of-range pages being indexable,
> the account menu name, "Register" not hiding below `lg`, breadcrumb at 320 px,
> the shell missing from the styleguide.
>
> **Open:**
>
> | Severity | Item | Phase |
> |---|---|---|
> | Minor | Legacy pages inside the new shell: a ~45 px misalignment between breadcrumb/header (`max-w-page`) and the old content at 1440 px; the pages' contrasts and `heading-order` already known | UI-6 ✅ |
> | Nit | With the mouse, `wire:navigate.hover` prefetches and then requests the page again on click (2 fetches): verify Livewire 4's behavior | UI-8 |
> | Nit | Local Nginx without compression or Cache-Control on `/build/*` | UI-8 ✅ |
> | Nit | Breadcrumb at 320 px: the intermediate items truncate early (10ch cap) | UI-6 ✅ cap only below `md` |
- A new header (transparent over the hero, then solid on scroll), a mobile drawer menu,
  a language switcher, an account menu (link to `/account` and, for organization members, to `/manage`).
- Footer (newsletter placeholder, legal links, languages).
- `wire:navigate` with prefetch on hover; View Transitions between pages;
  a navigation progress bar.
- SEO: per-page meta and Open Graph, JSON-LD (`Organization`, `Animal`→`Thing`/`Product`
  to be assessed, `BreadcrumbList`), sitemap, breadcrumb.

### UI-4 · Image pipeline

> **Status: completed on 2026-11-08** (branch `feat/ui-4-images`, forked from `feat/ui-3-shell`;
> 2039 tests, browser 123/123). Components and pipeline ready; **adoption in the pages is in UI-6**.
>
> **What was done:**
> - **Conversions** (`App\Enums\ImageConversion`, `App\Support\Media\ImageConversions`): `card`
>   800×1000 (4:5), `hero` 1536×1024 (3:2: cameras' native ratio, crops an animal less), `og`
>   1200×630 JPEG, `zoom` (up to 2048 px, for the lightbox: without it, the original would be
>   loaded). Each in JPEG + `-webp` + `-avif` if GD supports them (the plain name is always
>   JPEG), all queued with responsive images and a placeholder (`og` without). `thumb` unchanged
>   and synchronous (Filament and the legacy pages use it).
> - **Docker:** GD with `libwebp` and `libavif`. Without them, the formats aren't registered and JPEG remains.
> - **Privacy:** on upload the originals of photos (`photos`, `Animal`'s `avatar`, the `CleansImageMetadata`
>   contract) are re-encoded without EXIF/GPS (`StripImageMetadata`, synchronous,
>   before the conversions, idempotent; on error it logs and doesn't block the upload). The
>   markup never uses the original's URL: while the conversions are queued, the `thumb` is shown
>   with the preset's proportions. Not covered: `MedicalRecord` attachments.
> - **`x-ui.picture`:** a `Media`, a `Picture` object or static URLs; `<source>` AVIF/WebP, `srcset`/
>   `sizes`, `width`/`height`, `loading=lazy` or `priority` (`fetchpriority=high`), a placeholder as a
>   CSS background, `alt` required (or `decorative`), a fallback with the paw.
> - **`x-ui.gallery`:** Embla Carousel 8.6 and PhotoSwipe 5.4 (MIT) with dynamic import (Embla only
>   with multiple photos, PhotoSwipe's core only on open); off-screen slides `inert`, "n of N",
>   buttons and thumbnails (`thumb`), arrows, a modal lightbox with focus return, alt per photo
>   (the media's `alt` property or "Photo n of N of <name>"), reduced-motion also in the swipe;
>   without JS it's a scrollable list.
> - **Command** `php artisan arkvel:media:regenerate` (`--model`, `--collection`, `--conversion`,
>   `--only-missing`, `--dry-run`, `--force`) over the `RegenerateImageConversions` Action. It must
>   be re-run after a change of driver, libraries or conversions, with an empty queue.
> - **SEO:** `og:image` from the `og` conversion, with `thumb` while it's queued.
> - **Styleguide:** `/_styleguide/media` with static sample images.
>
> **Notes for UI-6 (integration):**
> - card: `<x-ui.picture :media="$animal->getFirstMedia('photos') ?? $animal->getFirstMedia('avatar')"
>   conversion="card" fill :alt="…" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw" />`
>   in the 4:5 box; `x-ui.animal-card` accepts a `Media`; in the catalog queries `with('media')`;
> - page: `<x-ui.gallery :media="$animal->getMedia('photos')" :name="$animal->name" priority
>   sizes="(min-width: 1024px) 60vw, 100vw" />`;
> - after deploy: `php artisan arkvel:media:regenerate --only-missing`.
>
> **Standards review outcome (2026-11-08):** fixed before the commit the original with
> EXIF/GPS used as a fallback and in the no-JS link, `og` generated but not used, the order of the formats
> (JPEG is now generated first), oversized thumbnails, `Fit::Crop` on the thumb
> (now `Fit::Max`, no enlargements), global state in the gallery, reduced-motion in the swipe.
>
> **Open:**
>
> | Severity | Item | Phase |
> |---|---|---|
> | Minor | A `PerformConversionsJob` on a 12 MP photo takes 6–12 s (AVIF): a dedicated `media` queue is needed (`MEDIA_QUEUE`) with `--timeout=300` and a higher `retry_after`, or at least `--timeout=120` on the single worker (`docker-compose.yml`, deploy) | UI-8 |
> | Minor | `card`/`hero`/`og` crop and can enlarge small photos: consider a minimum resolution at upload in `AnimalForm` (Filament: to be agreed) | UI-8 |
> | Nit | `--only-missing` run with jobs still queued can enqueue them twice (harmless) | — |
> | Nit | Medical record attachments (`MedicalRecord`) not cleaned of metadata | UI-8 |
- medialibrary conversions: `card` (4:5), `hero` (16:9 / 3:2), `og` (1200×630), WebP + AVIF,
  `withResponsiveImages()` (blurred placeholder included), all queued.
- An `x-ui.picture` component: `srcset`/`sizes`, `fetchpriority="high"` for the LCP image,
  declared `width`/`height`, lazy loading for the others.
- A command to regenerate the conversions for existing media.
- The animal page gallery: Embla + PhotoSwipe (dynamic import).

### UI-5 · Public auth in Livewire (adopter login/registration)

> **Status: completed on 2026-11-08** (branch `feat/ui-5-auth`, forked from `feat/ui-4-images`;
> 2223 tests, browser 142/142). Reviewed by a security review (compared with the Filament pages
> it replaces) and by `/ui-review`, fixed before the commit.
>
> **How it was with Filament (initial analysis):** the adopter panel's native pages (no
> custom ones), no observer on `User`; registration created only the user (no profile or
> consents) and sent the verification email; the Adopter role was assigned by `EnsureAdopterRole` on the
> first panel request; reset said whether the email didn't exist; a rate limit of 5/min on
> login per IP, 2/min on registration and reset.
>
> **What was done:**
> - **Pages** (`app/Livewire/Auth`, public layout, `themeable`, `noindex`): `/accedi`
>   (`login`), `/registrati` (`register`), `/password-dimenticata` (`password.request`),
>   `/reimposta-password/{token}` (`password.reset`), `/verifica-email` (`verification.notice`),
>   signed `verification.verify` and `verification.send`; logout `POST /esci` is now called `logout`.
> - **Actions** in `app/Actions/Auth` with DTOs in `app/Data/Auth` (email normalized to lowercase):
>   `AuthenticateUser`, `RegisterAdopter` (creates the user, assigns the Adopter role, records the
>   acknowledgment of the privacy policy with its version in activitylog), `SendPasswordResetLink`,
>   `ResetPassword` (no automatic login), `VerifyUserEmail`, `SendEmailVerification`,
>   `AssignAdopterRole` (extracted from `EnsureAdopterRole`).
> - **Security:** rate limit per email+IP and per IP on login (anti password spraying), a limit
>   per IP on every registration submission **before** validation (no enumeration of
>   registered users), a honeypot with a minimum time, reset with an always-identical response, `IntendedUrl` (internal
>   redirects only), a regenerated session, passwords cleared after an error, `GuestOnly` also
>   on the Livewire requests, `TRUSTED_PROXIES` from env to get the real IPs behind a proxy.
> - **Destination after login** (`App\Support\Auth\PostLoginDestination`): Super Admin →
>   `/admin`, member of an operable organization → `/manage`, others → `/account`. Staff no longer receive
>   the Adopter role when entering from the public site.
> - **Adopter panel** (the only Filament change, configuration only): login,
>   registration, reset and verification removed; `AuthenticateAdopter` sends guests to `/accedi`,
>   `EnsureEmailIsVerified` sends unverified users to `/verifica-email`; **persistent** auth
>   middleware (otherwise Livewire doesn't reapply it on `/livewire/update`). `/manage` and
>   `/admin` keep the Filament login.
> - **Email** verification and reset (`app/Notifications/Auth`) with `auth_public.mail.*` texts in
>   the user's language; `/manage` keeps using the Filament notifications.
> - `App\Support\Site\AccountLinks` points to the new routes; a new `x-ui.error-summary`.
>
> **Open:**
>
> | Severity | Item | Phase |
> |---|---|---|
> | **Production blocker** | The privacy policy page (and terms) is missing: registration records the acknowledgment with `config('arkvel.privacy_policy_version')`, the link will appear on its own once the `legal.privacy` route exists (`App\Support\Site\LegalLinks`) | before release |
> | Minor | Registrations made from the Filament panels (e.g. `/manage`) don't normalize the email to lowercase: an account with uppercase wouldn't log in from `/accedi`. There's no legacy data today; a migration lowercasing the emails closes the point | UI-8 ✅ |
> | Nit | Verification/reset links already sent with the old panel URLs (`/account/email-verification/…`) respond 404 after deploy (they expire in 60 minutes) | — |
> | Nit | `pest --parallel` hung once on a worker: the serial suite takes ~20 minutes | UI-8 |
- Public Livewire pages with the new design:
  - `/accedi` (`login`), `/registrati` (`register`), `/password-dimenticata` (`password.request`);
  - `/reimposta-password/{token}` (`password.reset`);
  - `/verifica-email` (`verification.notice`) + signed `verification.verify`.
- **Logic in Actions** (`app/Actions/Auth/...`): `AuthenticateUser` (rate limit, session
  regeneration), `RegisterAdopter`, `SendPasswordResetLink`, `ResetPassword`.
  **Before writing them, analyze what the adopter's Filament registration does today**
  (roles/permissions, profile, GDPR consents, events/observers) and replicate it identically.
  The Actions must be 100% covered.
- Privacy consents at registration (recorded with activitylog), a honeypot and a rate limit.
- **The only Filament change (configuration only):** in `AdopterPanelProvider` remove
  `login()`, `registration()`, `passwordReset()` and `emailVerification()` and redirect
  unauthenticated users to the public `login` route; email verification moves to Laravel's `verified`
  middleware.
  - The `/manage` (organizations) and `/admin` panels keep the Filament login.
  - Logout from the adopter panel → the public home.
- **Tests:**
  - a feature test for each flow, including the rate limit;
  - regression: `/account` without login → redirect to `/accedi`; an unverified user →
    `/verifica-email`;
  - the existing `tests/Feature/Adopter` tests stay green.

### UI-6 · Pages (one commit per page)

> **Status: completed on 2026-11-09** (branch `feat/ui-6-pages`, forked from `feat/ui-5-auth`;
> 2500 tests, browser 195/195). The four pages were built in parallel by four
> agents with separate files (texts in `lang/*/page_{home,catalog,animal,organizations}.php`), then
> reviewed (standards + `/ui-review`) and fixed.
>
> **Commits:** the animal page went first through a behavior-preserving refactor
> (`11ad59e`, child components `AnimalDetail/*` and the `ContactOrganizationAboutAnimal` Action), then
> cards with photos (`657f2b5`), pages (`6e5bae1`) and the legacy cleanup (`0e3c118`). **A deliberate
> deviation from "one commit per page":** the shared changes (pagination, card, favorites,
> button, breadcrumb) are interwoven with all the pages and one commit per page didn't pass
> the gate in isolation.
>
> **What was done:**
> - **Home:** a bento hero (search, cover animal, real numbers), a showcase filterable by
>   species in a Livewire island, "how it works" in 4 steps, an honest placeholder for the stories,
>   a donation band; a transparent header over the hero.
> - **Catalog:** instant search with a visible label and a live region, facets with counts
>   (`CountsCatalogFacets`: Meilisearch or database per `scout.driver`, always limited to verified
>   organizations), active-filter chips, a filter drawer on mobile, sorting, continuous loading
>   (`wire:intersect`) with "Load more" and fallback pagination, empty states; a GET form
>   without JS; a compatible query string (`q`, `species`…).
> - **Animal page:** gallery, info cards (identikit, temperament, compatibility, health),
>   descriptions in the available language with `lang`, a sticky actions panel on desktop and a bottom
>   sheet on mobile with application, fostering, booking (date picker + free time: no
>   new rules) and contact, an error summary and outcome messages with focus, a note for guests,
>   similar animals (`FindSimilarAnimals`).
> - **Organizations:** an index with filters (`q`, `region`, `type`; the old `regione`/`tipo` are still
>   read), an organization page with animals, numbers and a donation widget (Stripe behavior unchanged),
>   thank-you/cancel pages.
> - **Shared:** `x-ui.pagination`, `x-site.animal-card` (photo, location `PublicLocation`, age
>   `AnimalAge`), favorites with one query per page (`FindFavoritedAnimalIds` + a `favorited` prop
>   on `FavoriteButton`), `WebUrl::safe()`, re-indexing of animals when an organization's status
>   changes (`OrganizationObserver` → `SyncOrganizationAnimalsSearchIndex`), the `animals.show` route
>   numeric only, buttons that wrap on mobile, a fix to `x-ui.toast` (a `@foreach` with
>   destructuring and an apostrophe in a comment broke the state of Livewire 4's loops).
> - **Cleanup:** removed the `brand-*`/`accent-*` aliases, the legacy components (`x-animal-card`,
>   `x-badge`, `x-button`, `x-filter-select`) and the orphan `public.*` keys. The blocks' dark
>   tokens revised (UI-1's table).
>
> **Deploy steps:** `php artisan scout:sync-index-settings` (without it, sorting on `featured`
> fails: the catalog degrades and logs a warning) and `php artisan arkvel:media:regenerate
> --only-missing`.
>
> **Open:**
>
> | Severity | Item | Phase |
> |---|---|---|
> | Minor | Catalog: clicking a pagination link while `wire:intersect` is still loading the next page produces console errors (drawer and toaster morphed with navigation already started) | UI-8 ✅ |
> | Nit | After "Load more" all the loaded pages are marked `aria-current` in the pagination | UI-8 ✅ |
> | Nit | Animated card → page transition not done (requires `view-transition-name` per animal on the card and gallery) | UI-7 ✅ |
> | Nit | The photos cover the card's colored block: direction B's "colored background" shows only without a photo | UI-7 ✅ a frame in the card's tone |
> | Nit | White on `block-leaf` in dark at 4.64:1: AA but with little margin | — |
- **UI-6a Home:** a block (bento) hero per §1-bis, a grid of featured animals with filter chips,
  key numbers, "how adoption works" in 3-4 steps, adoption stories (placeholder),
  a donation/organizations CTA.
- **UI-6b Catalog:**
  - instant search (only the results update: an island or `wire:model.live.debounce`);
  - facets with counts from Meilisearch, active-filter chips, a filter drawer on mobile, sorting;
  - continuous loading with `wire:intersect` (with a pagination fallback);
  - polished skeletons and empty states.
- **UI-6c Animal page:**
  - first the refactor: split `AnimalDetail` into child components for application (a multi-step
    form), fostering, booking (Cally date picker + slots) and contact, without changing
    behavior;
  - then the redesign: gallery, an actions panel that stays visible on scroll (a bottom sheet on
    mobile), info cards, similar animals, an animated card → hero transition.
- **UI-6d Organizations:** an index with cards and filters; an organization page with cover, info, animals, a donation
  widget (preset amounts, custom amount, recurring if supported) and polished
  thank-you/cancel pages.

### UI-7 · Motion, micro-interactions and map

> **Status: completed on 2026-11-09** (branch `feat/ui-7-motion-map`, forked from `feat/ui-6-pages`;
> 2593 tests, browser 234/234). Motion and map built in parallel by two agents, then reviewed
> (standards + `/ui-review`) and fixed.
>
> **Map** (`maplibre-gl` 6, BSD-3; OpenFreeMap tiles, `positron`/`fiord` styles configurable with
> `MAP_STYLE_LIGHT`/`MAP_STYLE_DARK`):
> - the catalog with a "List / Map" view (`?view=map`, in the history): organizations with animals that
>   match the filters (`BuildCatalogMap`, same visibility rules as the catalog), markers and
>   clusters as 44 px buttons in list order, a `dialog` panel always inside the
>   frame, labels in the page's language, Ctrl/⌘ + wheel or two fingers to move the map,
>   an alternative list always present, a fallback if the tiles don't arrive; in map view neither
>   the grid nor favorites are loaded;
> - organization page: a "Where it is" section with a circle over the approximate area;
> - **privacy:** organizations' coordinates are published only as the center of a cell of a
>   grid (`ApproximateLocation`, `MAP_GRID_DEGREES`, default 0.02° ≈ 2 km; a circle radius of
>   1.6 km, which always contains the real position); never an address, postal code or exact
>   coordinates;
> - **external host:** when a map enters the viewport the browser contacts `tiles.openfreemap.org`
>   (it sees the IP and the area browsed; no keys, cookies or trackers). It must be mentioned in the privacy
>   policy; in production you can switch to self-hosted tiles by changing only the style's URL.
> - A bug found in manual verification and fixed: `maplibre-gl.css` reset the container to
>   `position: relative` (0 height, an invisible map despite being "ready"): there's a regression test.
>
> **Motion:** `motion` 14 (MIT) only as `motion/mini` for the favorites heart (5 KB gzip,
> loaded on the first hover/focus); the rest in CSS. Reveal on scroll with `x-reveal`
> (IntersectionObserver, never above the fold or on the LCP, visible without JS and with reduced-motion,
> disableable with `UI_REVEAL=false`), a pop of the toast icon, a photo that moves from the card to the
> page with Livewire's View Transitions (`AnimalPhotoTransition`, unique names per page),
> a frame in the card's tone around the photo. A deliberate deviation from the plan: no
> `wire:intersect` + Motion for the reveals (lighter this way).
>
> **Tests and environment:** phpunit.xml and `TestCase` force `SCOUT_DRIVER=collection` (the development
> Meilisearch index had been dirtied by a manual trial); the catalog filters verified organizations
> even in the engine query, so a stale index doesn't show phantom cards.
>
> **Measurements** (375 px, CPU 4×): LCP 0.6–0.9 s, CLS 0 (even with reveal and the map), INP ≤ 72 ms.
>
> **Open:**
>
> | Severity | Item | Phase |
> |---|---|---|
> | Minor | The browser suite run right after the full Pest sometimes hangs after `HomeTest`; on its own it always passes in ~2.5 minutes | UI-8 |
> | Nit | `fiord` style (dark): uppercase labels and a lighter triangle at low zoom (upstream style) | — |
> | Nit | Vector tiles blocked but the style loaded: an empty map with no message (the list is there) | — |
- Micro-interactions: favorite (an animated heart), card hover, reveal on scroll with
  `wire:intersect` + Motion, toast confirmations. All disabled with `prefers-reduced-motion`.
- A MapLibre map (dynamic import, `wire:ignore` + Alpine) on the catalog and organization page, with
  approximate positions and an accessible alternative list.
- GSAP only if an effect section justifies it.

### UI-8 · Quality and release

> **Status: completed on 2026-11-09** (branch `feat/ui-8-quality`, forked from
> `feat/ui-7-motion-map`). This phase's agents stopped several times because of the Mac
> sleeping: the work was split into small pieces and partly completed by the main session.
>
> **What was done:**
> - **CI:** a `browser` job in `.github/workflows/ci.yml` (Node 24, `npm ci && npm run build`,
>   a cache of the Playwright browsers keyed on its version, Pest `tests/Browser`, screenshots as
>   artifacts on failure; `memory_limit=1G`). Validated with actionlint; the first real run
>   on GitHub should be watched.
> - **Nginx:** gzip (brotli not available in the alpine image), `immutable` for `/build/*`,
>   `no-cache` for the manifest, one week for `/images/*`.
> - **Language headers:** a `ContentLanguage` middleware (`Content-Language` and
>   `Vary: Accept-Language` on HTML responses only).
> - **Catalog:** the in-flight Livewire requests of the replaced components are cancelled
>   when `wire:navigate` starts (`shell.js`: no more console errors following
>   pagination during continuous loading); a single page `aria-current` in the
>   pagination, the others loaded are "already shown".
> - **Email:** a canonical form (lowercase, no spaces) in a single place (`EmailAddress`) used by the
>   form DTOs and by `User`'s mutator (also covers the Filament panels without touching them); a
>   migration for the existing emails that skips and logs the conflicting accounts.
> - **Documents:** [WCAG-CHECKLIST.md](WCAG-CHECKLIST.md) (WCAG 2.2 AA criteria, how they're
>   met and where they're tested) and [UI-RELEASE.md](UI-RELEASE.md) (blockers, variables,
>   deploy steps, workers, nginx, checks).
>
> **Final Core Web Vitals** (375 px, CPU 4×, network ~1.6 Mbps / 150 ms, compressed assets):
>
> | Page | LCP | CLS |
> |---|---|---|
> | Home | 0.92 s | 0 |
> | Catalog (grid) | 0.84 s | 0 |
> | Catalog (map) | 0.79 s | 0 |
> | Animal page | 0.89 s | 0 |
> | Organizations index | 0.78 s | 0 |
> | Organization page | 0.80 s | 0 |
> | Log in | 0.76 s | 0 |
>
> INP measured in phases UI-6/UI-7 on the main interactions: ≤ 72 ms. The §4 budget is met
> everywhere.
>
> **Still open:** see the "Remaining open items" section of [UI-RELEASE.md](UI-RELEASE.md).
> A production blocker: the privacy policy page.
- A browser suite over all the public pages and the auth flows: mobile/desktop viewports,
  light/dark, comparison screenshots, accessibility assertions.
- A Chrome DevTools MCP audit of the Core Web Vitals; fix until the §4 budget is met.
- Browser tests in CI (a separate job, with a cache of the Playwright browsers).
- A final `/ui-review` and a documented WCAG 2.2 AA checklist.

---

## 6. Design review checklist (used by `design-reviewer`)

- A clear typographic hierarchy; at most 2 font families; a scale consistent with the tokens.
- Only colors from the tokens; AA contrast in light and dark.
- Spacing from the scale; alignment to the grid; no horizontal overflow at 320 px.
- Every expected state: hover, focus, active, disabled, loading, empty, error, success.
- Images with translated `alt`, stable proportions, no CLS.
- Animations ≤ 300 ms for interactions, easing from the tokens, respect for reduced-motion.
- Copy translated IT/EN, no hard-coded strings.
- Consistency: the same components for the same patterns; no duplicate markup outside `x-ui.*`.

---

## 7. Per-phase super-prompt

Each prompt should be pasted into a new chat. The common context is referenced at the top.

### Common context (prefix of every prompt)

```
ArkVel project (current repo). Read CLAUDE.md, laravel/CLAUDE.md and docs/FRONTEND-PLAN.md
(in particular §2 stack, §4 cross-cutting rules, §6 checklist). We are rebuilding the UI of the
public Livewire site; the Filament panels are NOT to be touched (the only documented exception is
in UI-5). Only the open-source/free libraries listed in §2. Commands always via Docker
(docker compose exec -T php ...; npm on the host or in the node container if present).
Create a feat/ui-<phase> branch from main. Quality gate green on every commit. Use the project's
agents (ui-builder, test-author, design-reviewer, standards-reviewer) where it makes sense.
```

### UI-0
```
[Common context] Run phase UI-0 of docs/FRONTEND-PLAN.md §5:
install and configure Laravel Boost (via Docker, .mcp.json with boost:mcp in the container),
document the installation of Playwright MCP, Chrome DevTools MCP and the frontend-design plugin
(commands I have to run myself, if they require interaction); create .claude/agents/ui-builder.md,
.claude/agents/design-reviewer.md (read-only, Playwright MCP, §6 checklist) and the skill
.claude/skills/ui-review; install pestphp/pest-plugin-browser with Playwright in the php Dockerfile
and write a Home browser smoke test; set locale it/fallback en with the SetLocale middleware
and translate the #[Title]; remove welcome.blade.php; verify which Alpine plugins are
included in Livewire 4 and note it in the plan. Finish by updating the plan with the phase status.
```

### UI-1
```
[Common context] Run phase UI-1. Chosen visual direction: "B · Affectionate pop", specified
in §1-bis (visual reference: the "B · Affectionate pop" artboard of the canvas linked there; direction A
was discarded, ignore the existing terracotta tokens). Respect the contrast table in
§1-bis and derive dark mode per the guidance given there.
Also resolve the items assigned to UI-1 in the "UI-0 /ui-review outcome" table (§5 UI-0), in
particular the enum labels translated IT/EN.
Use the frontend-design skill. Define the tokens in resources/css/app.css (@theme, OKLCH,
light/dark, fluid typography, a self-hosted font via Fontsource, motion tokens), dark mode with
a persisted toggle, and the /_styleguide page in local only. Don't modify the pages yet.
Verify the AA contrasts and do a /ui-review of the styleguide.
```

### UI-2
```
[Common context] Run phase UI-2: create the x-ui.* library (list in §5 UI-2) starting from the
patterns of Sheaf UI/Pines (MIT, adapted to our tokens), Lucide icons via blade-lucide-icons,
a date picker as a wrapper of Cally. Every component: accessible (ARIA, keyboard, focus),
documented in /_styleguide, a rendering test + a browser test with accessibility assertions.
One commit per coherent group of components. The existing pages are not to be migrated yet.
```

### UI-3
```
[Common context] Run phase UI-3: a new shell (app layout, header, mobile menu, language
switcher, account menu, footer) with the x-ui components; wire:navigate with prefetch, View
Transitions and a progress bar; SEO (per-page meta/OG, JSON-LD, sitemap, breadcrumb).
Adapt the existing tests to the new markup without changing behavior. A final /ui-review.
```

### UI-4
```
[Common context] Run phase UI-4: new medialibrary conversions (card, hero, og; WebP/AVIF;
responsive images with a placeholder; queued), the x-ui.picture component, a regeneration command,
an Embla + PhotoSwipe gallery with dynamic import. Tests on the conversions and a browser test on the
gallery. Measure CLS/LCP before and after with Chrome DevTools MCP.
```

### UI-5
```
[Common context] Run phase UI-5 (public Livewire auth). FIRST analyze what the adopter panel's
Filament registration/login does today (AdopterPanelProvider, any custom Register
pages, observers, spatie/permission roles, GDPR consents, email verification) and report it to me.
Then implement: the Livewire pages /accedi, /registrati, /password-dimenticata,
/reimposta-password/{token}, /verifica-email with the standard Laravel routes (login, register,
password.request, password.reset, verification.notice, verification.verify); logic in
app/Actions/Auth (100% covered); rate limit, honeypot, recorded consents. In
AdopterPanelProvider (the only allowed Filament change) remove login/registration/passwordReset/
emailVerification and redirect unauthenticated users to the public login route; /manage and /admin
keep the Filament login. Regression tests per §5 UI-5. The tests/Feature/Adopter tests
must stay green.
```

### UI-6a / 6b / 6c / 6d
```
[Common context] Run phase UI-6<x> (<Home | Catalog | Animal page | Organizations>) per §5.
Use only x-ui components (create new ones in x-ui if needed, with tests). No new logic
in the Livewire components: if needed, a new tested Action. For the animal page: first a
refactor commit (child components, identical behavior, green tests), then the redesign.
Updated feature tests + browser tests (mobile/desktop, light/dark, accessibility, screenshots).
/ui-review and a Core Web Vitals measurement before closing.
```

### UI-7
```
[Common context] Run phase UI-7: micro-interactions (Motion, reduced-motion), reveal on
scroll with wire:intersect, a MapLibre v6 + OpenFreeMap map with dynamic import on the catalog and
organization page (approximate coordinates server-side, an accessible alternative list). Verify INP
with Chrome DevTools MCP. Browser tests.
```

### UI-8
```
[Common context] Run phase UI-8: complete the browser suite (all the public pages + auth,
mobile/desktop, light/dark, screenshots, accessibility), add the browser job to CI
(.github/workflows/ci.yml) with a Playwright cache, a Core Web Vitals audit until the §4 budget,
a WCAG 2.2 AA checklist documented in docs/. A final /ui-review over the whole site.
```
