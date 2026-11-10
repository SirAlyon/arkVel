# ArkVel — Deploy in produzione (pilota)

> Runbook infrastrutturale per il go-live. I passi **applicativi** (build, migrate, scout,
> media, cache) e i **bloccanti/variabili** sono in [RILASCIO-UI.md](RILASCIO-UI.md): questo
> documento copre il livello **infra** (server, Forge, DNS, Reverb/Meilisearch, TLS, backup).
> Stack da far girare: php-fpm, nginx, **PostgreSQL**, **Redis**, **Meilisearch**, worker di
> **coda**, **scheduler**, **Reverb** (websocket). Niente Vercel (serverless: non adatto).

---

## 0. Scelta: Laravel Forge + VPS

**Forge** gestisce un VPS con php-fpm, nginx, TLS, daemon (Reverb), worker di coda e
scheduler cron: è il percorso più rapido e affidabile per questo stack.

- **Provider consigliato**: Hetzner o DigitalOcean, server ~**4 vCPU / 8 GB** (abbondante per
  un pilota 3-5 enti). In alternativa **AWS EC2** collegato a Forge (identico a livello app).
- Forge installa **PHP 8.4, nginx, PostgreSQL, Redis**. **Meilisearch** e **Reverb** li
  aggiungiamo a parte (§4, §5).

---

## 1. DNS (Porkbun)

Nel pannello Porkbun del dominio:

- record **A** `@` → IP del server; **A** `www` → stesso IP (o CNAME a `@`).
- se usi IPv6, aggiungi gli **AAAA** corrispondenti.
- TTL basso (es. 600s) durante il go-live, poi rialzalo.
- la mail transazionale richiede record a parte (§7): **SPF, DKIM, DMARC**.

In Forge imposta il **dominio** del sito su quello definitivo (non l'IP): è la fonte di
`APP_URL`, canonical, sitemap e Open Graph.

---

## 2. Sito su Forge

1. **Create Site** → root del progetto Laravel = `laravel/` (il repo ha l'app in `laravel/`,
   l'infra Docker in root: su Forge serve la cartella `laravel/`). PHP **8.4**.
2. Collega il **repository** Git (`SirAlyon/arkVel`), branch `main`, deploy automatico off
   finché non hai validato il primo deploy a mano.
3. **Database**: crea un DB PostgreSQL + utente dedicato (Forge lo fa dalla tab Database).
4. **TLS**: Forge → SSL → Let's Encrypt sul dominio (e `www`). Redirect HTTP→HTTPS.

---

## 3. Variabili d'ambiente (server)

Dalla tab **Environment** di Forge. Partendo da [laravel/.env.example](../laravel/.env.example),
i valori che CAMBIANO in produzione:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio>
APP_TRUSTED_HOSTS=<dominio>,www.<dominio>   # fuori da local un Host diverso → 400
TRUSTED_PROXIES=*                           # dietro il proxy/LB: IP reali per i rate limit

DB_CONNECTION=pgsql / DB_HOST=127.0.0.1 / DB_DATABASE/DB_USERNAME/DB_PASSWORD  # dal passo 2.3
REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database   # o redis
SESSION_SECURE_COOKIE=true

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=<chiave-robusta-generata>   # NON 'masterKey'

# Reverb (RIGENERA credenziali uniche, non quelle di sviluppo):
REVERB_APP_ID=<random> / REVERB_APP_KEY=<random> / REVERB_APP_SECRET=<random>
REVERB_HOST=<dominio> / REVERB_SCHEME=https / REVERB_PORT=443   # il browser si connette via wss sul 443
# lato server il daemon Reverb ascolta su 127.0.0.1:8080 (proxy in §5)

# Stripe LIVE (o test finché non vai live) + webhook verso https://<dominio>/stripe/webhook
STRIPE_KEY=... / STRIPE_SECRET=... / STRIPE_WEBHOOK_SECRET=...

# Mail reale (§7)
MAIL_MAILER=smtp / MAIL_HOST=... / MAIL_PORT=... / MAIL_USERNAME=... / MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@<dominio>"

PRIVACY_POLICY_VERSION=2026-11
MEDIA_QUEUE=media   # coda dedicata conversioni immagini (§6)
```

Dopo ogni modifica dell'env: `php artisan config:cache`.

> Le chiavi Google Calendar e Stripe possono anche essere inserite **da UI admin** (pagine
> Super-Admin), che sovrascrivono l'.env a runtime — comodo per non mettere i segreti nel file.

---

## 4. Meilisearch

Non è gestito da Forge: installalo sul server (binario ufficiale o pacchetto), come servizio
systemd, in ascolto su `127.0.0.1:7700`, con una **master key robusta** (= `MEILISEARCH_KEY`).

Al primo deploy (e dopo un indice vuoto):

```
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Animal"
```

`scout:sync-index-settings` va rieseguito a ogni modifica di `config/scout.php`.

---

## 5. Reverb (websocket) dietro TLS

- **Daemon Forge**: comando `php artisan reverb:start --host=127.0.0.1 --port=8080`
  (directory del sito). Forge lo tiene vivo e lo riavvia.
- **Nginx**: aggiungi un location per l'upgrade WebSocket che fa da proxy al daemon, es.:

  ```nginx
  location /app {                     # path del client Reverb/Echo
      proxy_pass http://127.0.0.1:8080;
      proxy_http_version 1.1;
      proxy_set_header Upgrade $http_upgrade;
      proxy_set_header Connection "upgrade";
      proxy_set_header Host $host;
      proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
      proxy_set_header X-Forwarded-Proto $scheme;
  }
  ```

  Così il browser si connette in **wss://<dominio>** sul 443 e nginx inoltra al daemon locale.
  Verifica il path atteso dal client (config Reverb/Echo) e allinealo al `location`.

---

## 6. Worker di coda e scheduler

- **Scheduler** (Forge → Scheduler): `php artisan schedule:run` ogni minuto. Copre i reminder
  sanitari/appuntamenti e `arkvel:sync-calendars`.
- **Queue – default** (Forge → Daemons/Queue): `php artisan queue:work redis --tries=3`.
- **Queue – media** (conversioni immagini, job lunghi): worker dedicato
  `php artisan queue:work redis --queue=media --timeout=300 --tries=2 --memory=512`, con
  `retry_after` > timeout. Richiede `MEDIA_QUEUE=media` (vedi RILASCIO-UI.md §4).

---

## 7. Email (deliverability)

Transazionali critiche (verifica indirizzo, reset password, notifiche, contratti). Usa un
provider SMTP reale (Postmark/SES/Mailgun/…). Sul dominio Porkbun configura **SPF**, **DKIM**,
**DMARC** del provider scelto, o le mail finiscono in spam. `MAIL_FROM_ADDRESS` sul dominio.

---

## 8. Primo deploy

Deploy script di Forge (adatta i path; i comandi app sono in RILASCIO-UI.md §3):

```sh
cd $FORGE_SITE_PATH            # la cartella laravel/
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan scout:sync-index-settings
php artisan arkvel:media:regenerate --only-missing
php artisan config:cache && php artisan route:cache && php artisan view:cache
# riavvio daemon: Forge riavvia queue/reverb a fine deploy (o: php artisan queue:restart)
```

Primo popolamento indice: `php artisan scout:import "App\Models\Animal"` (una tantum).
**Non** seminare i dati demo in produzione (`DemoDataSeeder` è bloccato se `APP_ENV=production`).

---

## 9. Backup

- **DB**: backup giornaliero di PostgreSQL (Forge Backups verso S3, oppure `pg_dump` in cron)
  + **test di restore** su staging prima del go-live.
- **Media**: `storage/app` (foto animali, contratti PDF) su S3 o snapshot del volume.
- **Snapshot** del server dal provider prima del cutover DNS.

---

## 10. Go-live e verifiche

1. Deploy su un sottodominio di **staging** e passa le verifiche di [RILASCIO-UI.md §6]
   (sitemap/robots, Content-Language, auth, catalogo+ricerca+mappa, donazioni Stripe
   end-to-end col webhook ora raggiungibile, realtime Reverb, email reali, CWV).
2. **Bloccanti legali** (RILASCIO-UI.md §1): informativa privacy/termini pubblicate con i
   contenuti validati dal titolare (le pagine esistono già: `/privacy`, `/termini`).
3. Cutover DNS su Porkbun → dominio definitivo. Rialza i TTL. Monitora log ed errori.

---

## Variante AWS

Se scegli AWS invece di Hetzner/DO: in Forge collega il tuo account AWS e provisiona
un'istanza **EC2** (es. t3.large). Da lì in poi tutto è identico (§2–§10). Postgres/Redis
restano sull'istanza (o, se vuoi gestiti, RDS/ElastiCache: più robusto ma più setup — non
necessario per il pilota).
