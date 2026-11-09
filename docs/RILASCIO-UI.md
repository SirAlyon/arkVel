# ArkVel — Rilascio della nuova UI pubblica

> Passi per mettere in produzione il sito pubblico rifatto nelle fasi UI-0…UI-8
> (docs/PIANO-UI-FRONTEND.md). I pannelli Filament non cambiano, salvo l'autenticazione del
> pannello adottante (UI-5).

---

## 1. Bloccanti prima del rilascio

| Punto | Perché | Dove |
|---|---|---|
| **Pagina dell'informativa privacy (e dei termini)** | La registrazione registra la presa visione dell'informativa con la sua versione (`PRIVACY_POLICY_VERSION`), ma il link compare solo quando esiste la route `legal.privacy` (`App\Support\Site\LegalLinks`). Il footer mostra «In arrivo» | testi legali a cura del titolare del trattamento |
| **Informativa: tile della mappa** | Quando una mappa entra in vista, il browser del visitatore contatta `tiles.openfreemap.org` (vede IP e area consultata; niente cookie né tracker). Va citato nell'informativa, oppure si passa a tile self-hosted (`MAP_STYLE_LIGHT` / `MAP_STYLE_DARK`) | UI-7 |
| **Host fidati** | Fuori da local e test, una richiesta con un header `Host` diverso dall'host di `APP_URL` riceve 400. Health check del bilanciatore che chiamano per IP: aggiungere l'host in `APP_TRUSTED_HOSTS` | UI-3 |

---

## 2. Variabili d'ambiente

Nuove o rilevanti per la UI pubblica (valori di esempio in `laravel/.env.example`):

| Variabile | Default | A cosa serve |
|---|---|---|
| `APP_URL` | — | Unica fonte degli URL assoluti di canonical, Open Graph, sitemap e robots |
| `APP_TRUSTED_HOSTS` | vuoto | Host extra accettati oltre a quello di `APP_URL`, separati da virgola |
| `TRUSTED_PROXIES` | vuoto | Proxy fidati (es. `*` dietro un bilanciatore): servono gli IP reali per i rate limit di login e registrazione |
| `PRIVACY_POLICY_VERSION` | `2026-11` | Versione dell'informativa registrata alla registrazione: aggiornarla a ogni nuova versione |
| `SCOUT_DRIVER`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | `meilisearch` | Ricerca e faccette del catalogo |
| `MEDIA_QUEUE` | vuoto (coda di default) | Coda dedicata alle conversioni delle immagini (vedi §4) |
| `MAP_STYLE_LIGHT`, `MAP_STYLE_DARK` | stili OpenFreeMap `positron` / `fiord` | Stili della mappa; cambiarli per tile self-hosted |
| `MAP_GRID_DEGREES` | `0.02` (~2 km) | Griglia di approssimazione della posizione degli enti (privacy) |
| `UI_REVEAL` | `true` | Animazioni di comparsa allo scroll (spente comunque con reduced-motion) |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | vuoti | Donazioni: senza, il widget di donazione non compare |

---

## 3. Deploy

```sh
cd laravel
composer install --no-dev --optimize-autoloader
npm ci && npm run build                     # asset Vite (CSS, ui.js, chunk di mappa e galleria)
php artisan migrate --force                 # include la normalizzazione delle email (UI-8)
php artisan scout:sync-index-settings       # ordinamento e faccette del catalogo (UI-6)
php artisan scout:import "App\Models\Animal" # solo al primo rilascio o dopo un indice vuoto
php artisan arkvel:media:regenerate --only-missing   # conversioni card/hero/og/zoom (UI-4)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- `scout:sync-index-settings` va rieseguito a ogni modifica di `config/scout.php`. Senza, il
  catalogo degrada (ordinamento senza `featured`) e scrive un warning nel log.
- La migrazione delle email non fonde account che differiscono solo per maiuscole: li salta e
  scrive un warning con gli id, da risolvere a mano.
- `arkvel:media:regenerate` va rilanciato dopo un cambio di driver immagini, librerie (WebP,
  AVIF) o conversioni, a coda vuota.

---

## 4. Worker delle code

Le conversioni delle immagini girano in coda: su una foto da 12 MP un job dura 6–12 s (AVIF).
Il worker di `docker-compose.yml` usa il timeout di default (60 s).

- Consigliato: coda dedicata `MEDIA_QUEUE=media` con un worker apposito, per esempio
  `php artisan queue:work --queue=media --timeout=300 --tries=2 --memory=512`, e
  `retry_after` della connessione maggiore del timeout (es. 360).
- Minimo con un solo worker: `--timeout=120`.

---

## 5. Nginx

Riferimento: `nginx/conf/default.conf`.

- gzip per CSS, JS, JSON, SVG e XML (l'immagine `nginx:alpine` non ha brotli);
- `/build/*` (asset con hash): `Cache-Control: public, max-age=31536000, immutable`;
  `/build/manifest.json`: `no-cache`;
- `/images/*`: una settimana;
- `/robots.txt` passa a Laravel (è dinamico, con la riga `Sitemap` assoluta);
- l'HTML porta gli header di Laravel: `no-cache`, `Content-Language` e `Vary: Accept-Language`
  (la stessa URL serve italiano o inglese).

---

## 6. Verifiche dopo il deploy

- `/sitemap.xml` risponde `application/xml` con URL assoluti dell'host di produzione;
  `/robots.txt` contiene la riga `Sitemap`.
- Una pagina pubblica ha `Content-Language` e `Vary: Accept-Language`; gli asset in `/build`
  sono compressi e con cache lunga.
- `/accedi`, `/registrati`, reset password e verifica email funzionano; `/account` da ospite
  porta a `/accedi`; `/manage` e `/admin` mantengono il loro login.
- Catalogo con ricerca e faccette, vista mappa con le tile, pagina ente con la mappa e il widget
  donazione (se Stripe è configurato).
- Core Web Vitals su home, catalogo e scheda animale (375 px): LCP ≤ 2,5 s, CLS ≤ 0,1,
  INP ≤ 200 ms. Le misure vanno prese con le animazioni di comparsa attive.

---

## 7. Punti aperti residui

Dalle tabelle «Aperti» del piano UI, nessuno bloccante salvo §1:

| Gravità | Punto |
|---|---|
| Minor | Ricerca lato server del combobox: id delle opzioni per indice (oggi il combobox non è usato nelle pagine) |
| Minor | Foto piccole ingrandite dai ritagli `card` / `hero` / `og`: valutare una risoluzione minima all'upload (pannello Filament) |
| Minor | Allegati delle schede mediche non ripuliti dai metadati |
| Nit | Bianco su `block-leaf` in dark a 4,64:1 (AA, poco margine) |
| Nit | Con una Content Security Policy, gli script inline (tema, styleguide) richiederanno un nonce |
| Nit | Stile mappa `fiord` (dark): etichette maiuscole e un triangolo più chiaro a zoom basso (upstream) |
