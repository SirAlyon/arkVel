# ArkVel — Deploy in produzione con Docker

> Deploy del sito su un **VPS unico** riusando lo stack Docker del progetto
> (`docker-compose.prod.yml`): php-fpm, nginx, PostgreSQL, Redis, Meilisearch, Reverb,
> code e scheduler, con **Caddy** davanti per il TLS automatico. I passi **applicativi** di
> contorno (scout, media, verifiche post-deploy) e le **variabili** sono in
> [RILASCIO-UI.md](RILASCIO-UI.md). Taglia consigliata: **VPS 4 GB / 2 vCPU** (il tier 1 GB
> va in OOM: Meilisearch + worker immagini da 512 MB + build). Migrazione futura su AWS:
> stesso stack Docker su un'istanza EC2.

---

## 1. Server e DNS

1. VPS **Ubuntu 4 GB** (Hetzner/DO/EC2…). Installa **Docker Engine + plugin Compose**
   (`docker compose version` deve rispondere). Apri le porte **80** e **443**.
2. **DNS (Porkbun)**: record **A** `@` → IP del server; **A** `www` → stesso IP. TTL basso
   (600s) durante il go-live. Aspetta che il dominio risolva prima dell'`up` (Caddy deve
   poter emettere il certificato via HTTP-01).
3. Posta transazionale: su Porkbun aggiungi **SPF, DKIM, DMARC** del provider SMTP scelto (§6).

---

## 2. Codice e file d'ambiente

```sh
git clone https://github.com/SirAlyon/arkVel.git && cd arkVel
git checkout main
```

Servono **DUE** file d'ambiente distinti:

**a) `.env` nella ROOT** (variabili del compose) — da `.env.prod.example`:

```sh
cp .env.prod.example .env
```
Compila: `APP_DOMAIN` (es. `arkvel.eu`), `ACME_EMAIL`, `POSTGRES_PASSWORD` (robusta),
`MEILI_MASTER_KEY` (robusta, non `masterKey`).

**b) `laravel/.env`** (env dell'app) — da `laravel/.env.example`, con i valori di produzione:

```ini
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # generata al §3
APP_URL=https://<dominio>
APP_TRUSTED_HOSTS=<dominio>,www.<dominio>
TRUSTED_PROXIES=*             # dietro Caddy: IP reale + schema https

DB_CONNECTION=pgsql
DB_HOST=postgres              # nome del servizio nella rete Docker
DB_PORT=5432
DB_DATABASE=arkvel
DB_USERNAME=arkvel
DB_PASSWORD=<= POSTGRES_PASSWORD del .env di root>

REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=<= MEILI_MASTER_KEY del .env di root>

MAIL_MAILER=smtp              # provider reale (§6)
MAIL_HOST=... / MAIL_PORT=... / MAIL_USERNAME=... / MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@<dominio>"

MEDIA_QUEUE=media             # coda dedicata conversioni immagini
PRIVACY_POLICY_VERSION=2026-11

# Stripe (donazioni) — LIVE o test; webhook → https://<dominio>/stripe/webhook
STRIPE_KEY= / STRIPE_SECRET= / STRIPE_WEBHOOK_SECRET=

# --- Reverb (websocket) dietro Caddy: server e client DISACCOPPIATI ---
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<random>        # RIGENERA, non i valori di sviluppo
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
# Il BACKEND pubblica gli eventi verso Reverb INTERNAMENTE (niente hairpin via dominio):
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
# Il BROWSER si connette in wss sul 443 attraverso Caddy (valori ESPLICITI, non ${REVERB_HOST}):
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=<dominio>
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

> I `VITE_REVERB_*` sono **compilati negli asset** da `npm run build` (§4): vanno impostati
> **prima** della build, o il browser tenterà di connettersi all'host sbagliato.

---

## 3. Immagini e dipendenze (prima volta)

```sh
# Costruisci le immagini (php/nginx):
docker compose -f docker-compose.prod.yml build

# Dipendenze PHP di produzione + APP_KEY (il codice è bind-montato: vendor vive sull'host):
docker compose -f docker-compose.prod.yml run --rm php composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml run --rm php php artisan key:generate
```

---

## 4. Asset, database, indice (prima volta)

```sh
# Asset Vite (CSS, ui.js, chunk mappa/galleria) — DOPO aver impostato i VITE_REVERB_* (§2):
docker compose -f docker-compose.prod.yml run --rm php sh -lc "npm ci && npm run build"

# Schema + indice + media + link storage:
docker compose -f docker-compose.prod.yml run --rm php php artisan migrate --force
docker compose -f docker-compose.prod.yml run --rm php php artisan storage:link
docker compose -f docker-compose.prod.yml run --rm php php artisan scout:sync-index-settings
docker compose -f docker-compose.prod.yml run --rm php php artisan scout:import "App\\Models\\Animal"
docker compose -f docker-compose.prod.yml run --rm php php artisan arkvel:media:regenerate --only-missing

# Cache di produzione:
docker compose -f docker-compose.prod.yml run --rm php sh -lc "php artisan config:cache && php artisan route:cache && php artisan view:cache"
```

> `DemoDataSeeder` è **bloccato in produzione** (nessun dato demo/PII). Il **primo super
> admin** si crea a mano:
> ```sh
> docker compose -f docker-compose.prod.yml run --rm php php artisan tinker
> >>> $u = App\Models\User::create(['name'=>'Admin','email'=>'tu@dominio','password'=>Hash::make('...')]);
> >>> $u->email_verified_at = now(); $u->save();
> >>> app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(0);
> >>> $u->assignRole(App\Enums\RoleName::SuperAdmin->value);
> ```
> Da lì gli enti si registrano self-service su `/manage/new` e il super admin li verifica in `/admin`.

---

## 5. Avvio

```sh
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml ps        # tutti up; postgres healthy
docker compose -f docker-compose.prod.yml logs -f caddy   # certificato TLS emesso
```

Caddy ottiene il certificato per `<dominio>` e `www` al primo colpo (serve il DNS già
propagato e le porte 80/443 aperte). Da qui il sito è su **https://<dominio>**.

---

## 6. Posta (deliverability)

Transazionali critiche (verifica email, reset password, notifiche, contratti). Usa un SMTP
reale (Postmark/SES/Mailgun). Configura **SPF/DKIM/DMARC** sul dominio o le mail vanno in
spam. `MAIL_FROM_ADDRESS` sul dominio.

---

## 7. Deploy successivi (aggiornamenti)

```sh
cd arkVel && git pull origin main
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm php composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml run --rm php sh -lc "npm ci && npm run build"
docker compose -f docker-compose.prod.yml run --rm php php artisan migrate --force
docker compose -f docker-compose.prod.yml run --rm php php artisan scout:sync-index-settings
docker compose -f docker-compose.prod.yml run --rm php sh -lc "php artisan config:cache && php artisan route:cache && php artisan view:cache"
docker compose -f docker-compose.prod.yml up -d        # ricrea i container cambiati
docker compose -f docker-compose.prod.yml restart queue queue-media scheduler reverb
```

---

## 8. Backup

- **DB**: `docker compose -f docker-compose.prod.yml exec -T postgres pg_dump -U arkvel arkvel | gzip > backup-$(date +%F).sql.gz`, in cron giornaliero verso storage esterno (S3/offsite). **Prova il restore** su staging prima del go-live.
- **Media/contratti**: il volume con `storage/app` (foto, PDF) → snapshot/offsite.
- **Snapshot** del server dal provider prima del cutover DNS.

---

## 9. Verifiche post-deploy

Esegui le verifiche di [RILASCIO-UI.md §6](RILASCIO-UI.md): `/sitemap.xml` e `/robots.txt`,
`Content-Language`/`Vary`, `/accedi`-`/registrati`-reset-verifica, catalogo+ricerca+mappa,
**pagine legali `/privacy` e `/termini`**, **donazione Stripe end-to-end** col webhook (ora
raggiungibile), **realtime** (apri due sessioni: un messaggio compare live → Reverb ok),
reminder schedulati, Core Web Vitals. Poi **cutover DNS** definitivo su Porkbun e monitora i log.

---

## Note

- **Sicurezza**: in questo compose Postgres/Redis/Meilisearch NON sono esposti all'host (solo
  rete interna); l'unica superficie pubblica è Caddy (80/443). Tieni così.
- **Reverb**: il client usa `wss://<dominio>/app/...` (instradato da Caddy a reverb:8080); il
  backend pubblica su `reverb:8080` internamente. Se il realtime non parte, verifica i
  `VITE_REVERB_*` (devono essere il dominio pubblico, 443, https) e che gli asset siano stati
  ricompilati dopo averli impostati.
- **Hardening futuro**: immagini immutabili (COPY del codice invece del bind-mount),
  `REVERB_ALLOWED_ORIGINS=<dominio>`, worker gestiti con limiti di risorse, log centralizzati.
- **AWS**: stesso `docker-compose.prod.yml` su un'istanza EC2 (o, più avanti, servizi gestiti
  RDS/ElastiCache separando DB/cache dal box).
