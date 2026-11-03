# ArkVel — Donazioni (Stripe)

> Fase 4. Donazioni **una tantum** e **ricorrenti mensili** verso un ente, tramite
> Stripe Checkout. Un solo account Stripe di **piattaforma**; le donazioni sono
> registrate **per-ente**. I payout per-ente (Stripe Connect) sono **fuori scope**.

---

## 1. Config-gating: niente chiavi, niente donazioni

Tutto il flusso è inerte finché Stripe non è configurato. Il predicato unico è
`App\Support\Billing\StripeConfig::configured()` (true quando ci sono **publishable
key** + **secret key**). Finché è false:

- la sezione donazioni **non compare** sul profilo pubblico dell'ente;
- le Action lanciano `DonationsNotConfigured` (backstop anche nell'impl Stripe);
- nessun endpoint Checkout/webhook ha effetto.

I test girano sempre con Stripe **non configurato** (chiavi vuote pinnate in
`phpunit.xml`) e usano un seam fake: **nessun test tocca la rete**.

---

## 2. Configurare Stripe

Le credenziali si risolvono **DB-over-env**: il valore salvato da UI vince sull'`.env`.

**Via UI (consigliato).** Pannello **Admin → Impostazioni → Integrazione pagamenti**
(`App\Filament\Pages\StripeIntegrationSettings`, solo Super Admin): publishable key,
secret key, webhook signing secret. I segreti sono **cifrati at-rest** (tabella
`platform_settings`, cast `encrypted`) e **non vengono mai ripresentati** al browser;
un submit col campo vuoto **non** sovrascrive il segreto già salvato.

**Via `.env`** (fallback): `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`,
`CASHIER_CURRENCY=eur`. Applicate a runtime su `cashier.*` da `BillingServiceProvider`.

Il banner di stato della pagina indica l'origine effettiva: `db` / `env` / non configurato.

---

## 3. Webhook

Endpoint unico: **`POST /stripe/webhook`** (`StripeWebhookController`), escluso dalla
CSRF (nessuna sessione: lo chiama Stripe).

- La firma `Stripe-Signature` è **verificata** contro `StripeConfig::webhookSecret()`
  quando impostato → firma errata/assente = **400**. Se il secret è vuoto
  (dev/test) la verifica è **saltata**: in produzione impostalo sempre.
- Controller **sottile**: verifica firma, instrada per tipo/`mode`, delega alle Action
  di riconciliazione. Idempotente (grafo di stato + unicità `provider_ref`). Eventi
  non pertinenti → **200** no-op (niente ritentativi inutili di Stripe).

Eventi da abilitare sull'endpoint Stripe:

| Evento | Effetto |
|---|---|
| `checkout.session.completed` (mode `payment`) | una tantum → **Completed** + `paid_at` |
| `checkout.session.async_payment_succeeded` | una tantum SEPA assestata → **Completed** |
| `checkout.session.expired` / `async_payment_failed` | una tantum → **Failed** |
| `checkout.session.completed` (mode `subscription`) | ricorrente → **Active** + salva `sub_...` |
| `invoice.paid` / `invoice.payment_succeeded` | addebito ricorrente → riga `Donation` nel registro |
| `invoice.payment_failed` | ricorrente → **PastDue** |
| `customer.subscription.deleted` | ricorrente → **Canceled** |

In locale: `stripe listen --forward-to localhost:8090/stripe/webhook`.

---

## 4. Donazione una tantum (P4-6)

Sul profilo pubblico dell'ente (`/enti/{slug}`), sezione **Sostieni l'ente**: importi
preset o libero, messaggio, e — per gli **ospiti** — nome + email (il donatore loggato
porta i propri dati). Flusso:

1. `App\Actions\Donations\StartDonationCheckout` — guardie **prima** di scrivere
   (config, importo **1€–10.000€**, identità donatore o email ospite), crea la riga
   `Donation` **Pending** in transazione, `provider_ref` = id sessione Checkout,
   redirige all'URL ospitato.
2. Ritorno su `/enti/{slug}/grazie` (o `/annulla`). La conferma reale arriva dal
   **webhook** (`ReconcileDonationFromStripe`), non dalla landing.

Il seam fakeable è `CreatesDonationCheckout` (impl `StripeDonationCheckout`).

---

## 5. Donazione ricorrente mensile (P4-7)

Stessa sezione pubblica, scelta frequenza **Mensile** — disponibile **solo per utenti
autenticati** (serve un account per gestirla/disdirla; agli ospiti è mostrato un hint).

- Il model **`RecurringDonation`** (tabella `recurring_donations`) è l'abbonamento di
  un utente verso un ente; stato `pending → active → past_due → canceled`
  (`RecurringDonationStatus`). `stripe_subscription_id` è valorizzato dal webhook.
- Ogni **invoice pagata** crea una riga `Donation` (`type = recurring`, Completed)
  collegata via `donations.recurring_donation_id`: il **registro d'ente** mostra così
  uniformemente una tantum e addebiti ricorrenti.
- Checkout `mode=subscription` con `price_data` ricorrente inline (nessun catalogo
  prezzi); `metadata.recurring_donation_id` propagato su session **e** subscription per
  la riconciliazione di tutti gli eventi. Seam fakeable `ManagesRecurringDonation`
  (impl `StripeRecurringDonationCheckout`), che gestisce anche la disdetta.

Action: `StartRecurringDonationCheckout`, `ReconcileSubscriptionFromStripe`,
`CancelRecurringDonation` (idempotente, disdice su Stripe + stato Canceled).

---

## 6. Dove si vedono le donazioni

- **Ente** (`/manage → Donazioni`): lista **read-only** tenant-scoped, tab per esito,
  una tantum + addebiti ricorrenti. Permesso `donation.view`.
- **Adottante** (`/account → Le mie donazioni`): le proprie ricorrenti + storico
  addebiti, con azione **Disdici** sulle attive (`RecurringDonationPolicy`, ownership).

---

## 7. File principali

- `app/Support/Billing/` — `StripeConfig` (DB-over-env), seam `CreatesDonationCheckout`
  / `ManagesRecurringDonation` + impl Stripe, DTO `DonationCheckoutSession`.
- `app/Actions/Donations/` — Start/Reconcile/Cancel (one-off + ricorrenti).
- `app/Http/Controllers/StripeWebhookController.php` — endpoint unico, firma + routing.
- `app/Models/{Donation,RecurringDonation}.php` + `app/Enums/{DonationType,DonationStatus,RecurringDonationStatus}.php`.
- `app/Filament/Shelter/Resources/Donations/` (ente) · `app/Filament/Adopter/Pages/MyDonations.php` (adottante) · `app/Filament/Pages/StripeIntegrationSettings.php` (admin).
- `app/Providers/BillingServiceProvider.php` — binding dei seam + applicazione chiavi DB su `cashier.*`.
