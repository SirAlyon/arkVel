# ArkVel — Donations (Stripe)

> Phase 4. **One-off** and **recurring monthly** donations to an organization, via
> Stripe Checkout. A single **platform** Stripe account; donations are
> recorded **per organization**. Per-organization payouts (Stripe Connect) are **out of scope**.

---

## 1. Config-gating: no keys, no donations

The entire flow is inert until Stripe is configured. The single predicate is
`App\Support\Billing\StripeConfig::configured()` (true when there is both a **publishable
key** and a **secret key**). While it's false:

- the donations section **does not appear** on the organization's public profile;
- the Actions throw `DonationsNotConfigured` (a backstop also in the Stripe implementation);
- no Checkout/webhook endpoint has any effect.

The tests always run with Stripe **unconfigured** (empty keys pinned in
`phpunit.xml`) and use a fake seam: **no test touches the network**.

---

## 2. Configuring Stripe

Credentials resolve **DB-over-env**: the value saved from the UI wins over `.env`.

**Via the UI (recommended).** **Admin → Settings → Payment integration** panel
(`App\Filament\Pages\StripeIntegrationSettings`, Super Admin only): publishable key,
secret key, webhook signing secret. The secrets are **encrypted at rest** (`platform_settings`
table, `encrypted` cast) and are **never shown again** to the browser;
a submit with an empty field does **not** overwrite the already-saved secret.

**Via `.env`** (fallback): `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`,
`CASHIER_CURRENCY=eur`. Applied at runtime to `cashier.*` by `BillingServiceProvider`.

The page's status banner shows the effective source: `db` / `env` / not configured.

---

## 3. Webhook

A single endpoint: **`POST /stripe/webhook`** (`StripeWebhookController`), excluded from
CSRF (no session: Stripe calls it).

- The `Stripe-Signature` signature is **verified** against `StripeConfig::webhookSecret()`
  when set → a wrong/missing signature = **400**. If the secret is empty
  (dev/test) verification is **skipped**: in production always set it.
- A **thin** controller: verifies the signature, routes by type/`mode`, delegates to the
  reconciliation Actions. Idempotent (state graph + `provider_ref` uniqueness). Irrelevant
  events → **200** no-op (no pointless Stripe retries).

Events to enable on the Stripe endpoint:

| Event | Effect |
|---|---|
| `checkout.session.completed` (mode `payment`) | one-off → **Completed** + `paid_at` |
| `checkout.session.async_payment_succeeded` | one-off SEPA settled → **Completed** |
| `checkout.session.expired` / `async_payment_failed` | one-off → **Failed** |
| `checkout.session.completed` (mode `subscription`) | recurring → **Active** + saves `sub_...` |
| `invoice.paid` / `invoice.payment_succeeded` | recurring charge → `Donation` row in the ledger |
| `invoice.payment_failed` | recurring → **PastDue** |
| `customer.subscription.deleted` | recurring → **Canceled** |

Locally: `stripe listen --forward-to localhost:8090/stripe/webhook`.

---

## 4. One-off donation (P4-6)

On the organization's public profile (`/enti/{slug}`), the **Support the organization**
section: preset or custom amounts, a message, and — for **guests** — name + email (a logged-in
donor brings their own data). Flow:

1. `App\Actions\Donations\StartDonationCheckout` — guards **before** writing
   (config, amount **€1–€10,000**, donor identity or guest email), creates the
   **Pending** `Donation` row in a transaction, `provider_ref` = Checkout session id,
   redirects to the hosted URL.
2. Return to `/enti/{slug}/grazie` (or `/annulla`). The real confirmation comes from the
   **webhook** (`ReconcileDonationFromStripe`), not from the landing page.

The fakeable seam is `CreatesDonationCheckout` (impl `StripeDonationCheckout`).

---

## 5. Recurring monthly donation (P4-7)

Same public section, choosing the **Monthly** frequency — available **only for authenticated
users** (an account is needed to manage/cancel it; guests are shown a hint).

- The **`RecurringDonation`** model (`recurring_donations` table) is a user's subscription
  to an organization; status `pending → active → past_due → canceled`
  (`RecurringDonationStatus`). `stripe_subscription_id` is set by the webhook.
- Every **paid invoice** creates a `Donation` row (`type = recurring`, Completed)
  linked via `donations.recurring_donation_id`: so the **organization ledger** shows
  one-off donations and recurring charges uniformly.
- Checkout `mode=subscription` with inline recurring `price_data` (no price
  catalog); `metadata.recurring_donation_id` propagated to the session **and** the subscription for
  reconciliation of all events. Fakeable seam `ManagesRecurringDonation`
  (impl `StripeRecurringDonationCheckout`), which also handles cancellation.

Actions: `StartRecurringDonationCheckout`, `ReconcileSubscriptionFromStripe`,
`CancelRecurringDonation` (idempotent, cancels on Stripe + Canceled state).

---

## 6. Where donations show up

- **Organization** (`/manage → Donations`): a **read-only** tenant-scoped list, tabs by outcome,
  one-off + recurring charges. `donation.view` permission.
- **Adopter** (`/account → My donations`): their own recurring donations + charge
  history, with a **Cancel** action on the active ones (`RecurringDonationPolicy`, ownership).

---

## 7. Key files

- `app/Support/Billing/` — `StripeConfig` (DB-over-env), seams `CreatesDonationCheckout`
  / `ManagesRecurringDonation` + the Stripe implementations, the `DonationCheckoutSession` DTO.
- `app/Actions/Donations/` — Start/Reconcile/Cancel (one-off + recurring).
- `app/Http/Controllers/StripeWebhookController.php` — single endpoint, signature + routing.
- `app/Models/{Donation,RecurringDonation}.php` + `app/Enums/{DonationType,DonationStatus,RecurringDonationStatus}.php`.
- `app/Filament/Shelter/Resources/Donations/` (organization) · `app/Filament/Adopter/Pages/MyDonations.php` (adopter) · `app/Filament/Pages/StripeIntegrationSettings.php` (admin).
- `app/Providers/BillingServiceProvider.php` — seam bindings + applying the DB keys to `cashier.*`.
