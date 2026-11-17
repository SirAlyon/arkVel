# ArkVel — Releasing the new public UI

> Steps to put into production the public site rebuilt in phases UI-0…UI-8
> (docs/FRONTEND-PLAN.md). The Filament panels don't change, except for the adopter
> panel's authentication (UI-5).

---

## 1. Blockers before release

| Item | Why | Where |
|---|---|---|
| **Privacy policy page (and terms)** | Registration records acknowledgment of the privacy policy with its version (`PRIVACY_POLICY_VERSION`), but the link only appears once the `legal.privacy` route exists (`App\Support\Site\LegalLinks`). The footer shows "Coming soon" | legal texts, the responsibility of the data controller |
| **Privacy policy: map tiles** | When a map enters the viewport, the visitor's browser contacts `tiles.openfreemap.org` (which sees the IP and the area browsed; no cookies or trackers). This must be mentioned in the privacy policy, or switch to self-hosted tiles (`MAP_STYLE_LIGHT` / `MAP_STYLE_DARK`) | UI-7 |
| **Trusted hosts** | Outside local and test, a request with a `Host` header different from the `APP_URL` host gets a 400. Load-balancer health checks that call by IP: add the host to `APP_TRUSTED_HOSTS` | UI-3 |

---

## 2. Environment variables

New or relevant to the public UI (example values in `laravel/.env.example`):

| Variable | Default | What it's for |
|---|---|---|
| `APP_URL` | — | The single source of the absolute URLs for canonical, Open Graph, sitemap and robots |
| `APP_TRUSTED_HOSTS` | empty | Extra hosts accepted besides the `APP_URL` one, comma-separated |
| `TRUSTED_PROXIES` | empty | Trusted proxies (e.g. `*` behind a load balancer): real IPs are needed for the login and registration rate limits |
| `PRIVACY_POLICY_VERSION` | `2026-11` | The privacy policy version recorded at registration: update it with each new version |
| `SCOUT_DRIVER`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | `meilisearch` | Catalog search and facets |
| `MEDIA_QUEUE` | empty (default queue) | Dedicated queue for image conversions (see §4) |
| `MAP_STYLE_LIGHT`, `MAP_STYLE_DARK` | OpenFreeMap `positron` / `fiord` styles | Map styles; change them for self-hosted tiles |
| `MAP_GRID_DEGREES` | `0.02` (~2 km) | Grid used to approximate organizations' locations (privacy) |
| `UI_REVEAL` | `true` | Reveal-on-scroll animations (disabled anyway with reduced-motion) |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | empty | Donations: without them, the donation widget doesn't appear |

---

## 3. Deploy

```sh
cd laravel
composer install --no-dev --optimize-autoloader
npm ci && npm run build                     # Vite assets (CSS, ui.js, map and gallery chunks)
php artisan migrate --force                 # includes email normalization (UI-8)
php artisan scout:sync-index-settings       # catalog sorting and facets (UI-6)
php artisan scout:import "App\Models\Animal" # only on the first release or after an empty index
php artisan arkvel:media:regenerate --only-missing   # card/hero/og/zoom conversions (UI-4)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- `scout:sync-index-settings` must be re-run on every change to `config/scout.php`. Without it, the
  catalog degrades (sorting without `featured`) and writes a warning to the log.
- The email migration does not merge accounts that differ only in case: it skips them and
  writes a warning with the ids, to be resolved by hand.
- `arkvel:media:regenerate` must be re-run after a change of image driver, libraries (WebP,
  AVIF) or conversions, with an empty queue.

---

## 4. Queue workers

Image conversions run on the queue: on a 12 MP photo a job takes 6–12 s (AVIF).
The `docker-compose.yml` worker uses the default timeout (60 s).

- Recommended: a dedicated `MEDIA_QUEUE=media` queue with its own worker, for example
  `php artisan queue:work --queue=media --timeout=300 --tries=2 --memory=512`, and the
  connection's `retry_after` greater than the timeout (e.g. 360).
- Minimum with a single worker: `--timeout=120`.

---

## 5. Nginx

Reference: `nginx/conf/default.conf`.

- gzip for CSS, JS, JSON, SVG and XML (the `nginx:alpine` image has no brotli);
- `/build/*` (hashed assets): `Cache-Control: public, max-age=31536000, immutable`;
  `/build/manifest.json`: `no-cache`;
- `/images/*`: one week;
- `/robots.txt` passes through to Laravel (it's dynamic, with an absolute `Sitemap` line);
- the HTML carries Laravel's headers: `no-cache`, `Content-Language` and `Vary: Accept-Language`
  (the same URL serves Italian or English).

---

## 6. Post-deploy checks

- `/sitemap.xml` responds with `application/xml` and absolute URLs on the production host;
  `/robots.txt` contains the `Sitemap` line.
- A public page has `Content-Language` and `Vary: Accept-Language`; the assets in `/build`
  are compressed and long-cached.
- `/accedi`, `/registrati`, password reset and email verification work; `/account` as a guest
  leads to `/accedi`; `/manage` and `/admin` keep their own login.
- The catalog with search and facets, the map view with tiles, the organization page with the map and the donation
  widget (if Stripe is configured).
- Core Web Vitals on home, catalog and animal page (375 px): LCP ≤ 2.5 s, CLS ≤ 0.1,
  INP ≤ 200 ms. Measurements should be taken with the reveal animations enabled.

---

## 7. Remaining open items

From the "Open" tables of the UI plan, none blocking except §1:

| Severity | Item |
|---|---|
| Minor | Server-side search in the combobox: option ids by index (the combobox isn't used in the pages today) |
| Minor | Small photos enlarged by the `card` / `hero` / `og` crops: consider a minimum resolution at upload (Filament panel) |
| Minor | Medical record attachments not cleaned of metadata |
| Nit | White on `block-leaf` in dark at 4.64:1 (AA, little margin) |
| Nit | With a Content Security Policy, the inline scripts (theme, styleguide) will need a nonce |
| Nit | `fiord` map style (dark): uppercase labels and a lighter triangle at low zoom (upstream) |
