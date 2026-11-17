# ArkVel — Public site WCAG 2.2 AA checklist

> Scope: the public Livewire site (home, catalog, animal page, organizations, donations, authentication)
> and the `x-ui.*` library. The Filament panels are out of scope.
>
> Common automated checks: **axe** (`assertNoAccessibilityIssues`) in light and dark in the browser
> tests under `laravel/tests/Browser` (pages and `Components/*`); token **contrasts** in
> `laravel/tests/Unit/DesignTokens/ColorContrastTest.php` (pairs in
> `App\Support\DesignTokens\ContrastPairs`); **targets** via `BrowserScripts::undersizedTargets`.
> axe doesn't cover everything: the criteria marked "manual" were verified in the `/ui-review` runs of
> phases UI-2…UI-7 (docs/PIANO-UI-FRONTEND.md).

| Criterion | How it's met | Where it's tested |
|---|---|---|
| **1.1.1** Non-text content | `x-ui.picture` requires `alt` (or `decorative`); decorative Lucide icons with `aria-hidden`, and with a `label` they become `role="img"`; gallery with alt per photo ("Photo n of N of …") | `MediaComponentsTest`, `ActionsComponentsTest` (feature), axe |
| **1.3.1** Info and relationships | Semantic HTML; `x-ui.field` links label, help and error (`for`, `aria-describedby`, `aria-invalid`); fieldset/legend for groups; headings with no skips | `FormsComponentsTest`, `PickersComponentsTest`, heading-hierarchy tests in `DomainComponentsTest` and `HomeTest` (browser) |
| **1.3.2** Meaningful sequence | DOM order matches visual order; map markers in list order | `MapTest` (browser) |
| **1.3.5** Identify input purpose | `autocomplete` on name, email, password (`current-password` / `new-password`), `username` in reset | `AuthTest` (browser), feature tests of the auth pages |
| **1.4.1** Use of color | States never color-only: badges with text, favorite with `aria-pressed` and a filled/empty heart, errors with icon and text, underlined links | component feature tests; manual |
| **1.4.3** Contrast (minimum) | Semantic tokens only; every text/background pair ≥ 4.5:1 in light and dark | `ColorContrastTest`, axe |
| **1.4.4** Resize text | Typography in `rem` and `clamp`, fluid layouts | manual |
| **1.4.10** Reflow | No horizontal scrolling at 320 px on any page | the "no horizontal overflow at 320 px" test in the browser tests of pages and components |
| **1.4.11** Non-text contrast | Control borders with `line-strong` (≥ 3:1), focus ring ≥ 3:1 even on colored blocks (`focusScopes`) | `ColorContrastTest` |
| **1.4.12** Text spacing | No fixed-height text containers | manual |
| **1.4.13** Content on hover or focus | `x-ui.tooltip`: appears on hover and focus, stays while the pointer is over it, Esc dismisses it without moving focus | `OverlaysComponentsTest` (browser) |
| **2.1.1** Keyboard | All widgets keyboard-operable (APG patterns): dialog, drawer, dropdown, tabs, accordion, combobox, date picker (Cally), gallery, map (markers as buttons, +/−) | browser tests of the components and `MapTest` |
| **2.1.2** No keyboard trap | Native modal dialog and drawer (`showModal`) closeable with Esc; the map traps neither focus nor scroll (Ctrl/⌘ + wheel) | `OverlaysComponentsTest`, `MapTest` |
| **2.4.1** Bypass blocks | Skip link as the first element, even after `wire:navigate` | `ShellTest` (browser) |
| **2.4.2** Page titled | Translated per-page titles (`seo.titles.*`, `public.titles.*`), updated after `wire:navigate` | `SeoPagesTest`, `ShellTest` |
| **2.4.3** Focus order | Focus managed when opening and closing layers, after form submission (error summary or outcome message), when closing a toast | `OverlaysComponentsTest`, `AnimalDetailTest`, `AuthTest` (browser) |
| **2.4.4** Link purpose | Extended link over the card name (no empty link); "See {organization}'s animals" in the map panel | `DomainComponentsTest`, `MapTest` |
| **2.4.6** Headings and labels | Visible labels on all fields, search included | component and page feature tests |
| **2.4.7** Focus visible | A global 3 px ring (`:focus-visible`), never suppressed | the "visible focus ring" test in the component browser tests |
| **2.4.11** Focus not obscured | `scroll-padding` for the sticky header, drawer footers and the page's mobile bar; toasts scroll the page; off-screen markers `inert` | `OverlaysComponentsTest`, `MapTest`, `ShellTest` |
| **2.5.3** Label in name | Accessible names start with the visible text (e.g. logo, account menu) | `HeaderTest` (feature) |
| **2.5.7** Dragging movements | Gallery and map usable with buttons (previous/next, +/−) besides swipe | `MediaComponentsTest`, `MapTest` |
| **2.5.8** Target size (minimum) | Controls ≥ 44 px (beyond the required 24); text links ≥ 24 px | `BrowserScripts::undersizedTargets` in the browser tests |
| **3.1.1** Language of page | `<html lang>` from the active language; `Content-Language` header | `ShellTest`, `ContentLanguageTest` |
| **3.1.2** Language of parts | Descriptions in the available language with `lang` when different (`TranslatedText`) | `AnimalDetailTest`, `OrganizationPageTest` (feature) |
| **3.2.1 / 3.2.2** On focus / on input | No change of context on focus; filters and search update only the results, announced in a live region | `CatalogTest` (browser) |
| **3.2.3 / 3.2.4** Consistent navigation and identification | A single shell (header, footer, breadcrumb) and shared `x-ui` components | — |
| **3.3.1** Error identification | Textual errors near the field and in `x-ui.error-summary` (with focus) | `FormsComponentsTest`, `AuthTest`, `AnimalDetailTest` |
| **3.3.2** Labels or instructions | Help linked with `aria-describedby`, a textual "required/optional" indicator | `FormsComponentsTest` |
| **3.3.3** Error suggestion | Specific translated messages (format, length, expired link with "Request a new link") | feature tests of the auth pages |
| **3.3.7** Redundant entry | No data already provided requested again in the same flow | manual |
| **3.3.8** Accessible authentication | Login with email and password, password managers supported (`autocomplete`), no cognitive test | `AuthTest` |
| **4.1.2** Name, role, value | ARIA roles and states of the APG patterns (`aria-expanded`, `aria-pressed`, `aria-selected`, `role="switch"`, `role="dialog"`…) | component feature and browser tests, axe |
| **4.1.3** Status messages | `role="status"` / `role="alert"` regions (toasts, form outcome, catalog results, page loaded after `wire:navigate`) | `OverlaysComponentsTest`, `CatalogTest`, `ShellTest` |

## Motion

`prefers-reduced-motion` (WCAG 2.3.3, AAA, adopted anyway): token durations to ~0, no
reveal-on-scroll, no View Transitions, the map jumps without animating
(`MotionTest`, `MapTest`).

## Known exceptions and limits

- **Organization content:** texts and photos uploaded by organizations (descriptions, custom `alt`)
  are not controllable from the code; in the absence of `alt`, "Photo n of N of <name>" is used.
- **Map:** it's a complement; the alternative list of the same organizations is always present. The
  place labels come from the tiles (`fiord` style in dark: uppercase).
- **White on `block-leaf` in dark:** 4.64:1, compliant but with little margin.
- **Inline scripts** (theme): with a future Content Security Policy they'll need a nonce.
