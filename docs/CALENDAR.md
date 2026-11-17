# ArkVel — Appointment calendar & external synchronization

> Phase 3.5. Extends appointments (Phase 3) with a real in-app calendar and
> bidirectional synchronization with external calendars (Google, Proton, Apple,
> Outlook, Fastmail, Nextcloud, iCloud). Designed for an organization handling dozens of
> appointments a week.

---

## 1. In-app calendar (`/manage`)

In the organization panel, **Appointments → Calendar**: **month/week/day** views
(FullCalendar v6, MIT), color-coded by status, tenant-scoped.

- **Click an event** → modal with details and staff actions: Confirm, Complete,
  No-show, Cancel — all delegating to the domain Actions
  (`app/Actions/Appointments/*`), honoring `AppointmentPolicy` and the guards.
- **Drag-and-drop** to reschedule (future-slot guard + anti-overlap;
  `RescheduleAppointment`); **click an empty slot** to create (the requester is restricted
  to users already in a relationship with the organization — GDPR).
- Interactivity (drag/create) is enabled only for those with `appointment.manage`
  (a verified member); the **volunteer** sees a read-only calendar.

The list/table view with filters (`AppointmentResource`) remains available as a secondary
view.

> Technical choice: no Filament 5 calendar plugin (none stable/maintained).
> FullCalendar is loaded via `FilamentAsset::register` (global CDN build,
> self-hostable locally) and initialized with Alpine — no fragile dependency.

---

## 2. iCal subscription feed (universal, one-way)

Each organization has a **secret URL** to subscribe to (`/calendar/{organization}/{token}`)
that any client can subscribe to: **Google, Proton, Apple, Outlook**. Automatic
refresh, iCalendar standard, a single mechanism for every provider.

- Under **Appointments → Calendar → iCal feed**: copyable URL + regenerate/revoke the
  token (invalidates the old URL). Visible only to those who manage.
- High-entropy token in the URL (like the secret feeds of Google/Fastmail),
  compared in constant time (`hash_equals`); wrong token/nonexistent organization →
  an indistinguishable `404`; rate-limited.
- Every appointment also has an **`.ics` download** ("Add to calendar") and the
  confirmation/reschedule emails attach the `.ics`.

It's the simplest way to get appointments into the staff's personal calendar without
linking accounts.

---

## 3. Bidirectional connections (Google / CalDAV)

Provider-agnostic architecture: an `ExternalCalendarProvider` interface
(`pushEvent`/`deleteEvent`/`pullChanges`) + a registry; adding a provider = adding
a class. Credentials are **encrypted at rest** (`credentials` cast `encrypted:array`),
never logged, never in error messages. Managed under **Appointments → Calendar connections**.

### 3.1 Google Calendar (OAuth)
Connect the organization's Google Calendar; ArkVel creates/updates/deletes events via the API
(no `google/apiclient` SDK: an HTTP client only, config-gated). **To enable it** you need
the credentials of a Google Cloud project in `.env`:

```
GOOGLE_CALENDAR_CLIENT_ID=...
GOOGLE_CALENDAR_CLIENT_SECRET=...
GOOGLE_CALENDAR_REDIRECT_URI=https://<host>/calendar/google/callback
```

Required scope: `https://www.googleapis.com/auth/calendar.events` (offline, refresh
token). Without credentials the "Connect Google" action is disabled (inert feature).

### 3.2 CalDAV (Fastmail, Nextcloud, iCloud, mailbox.org, …)
Connect a CalDAV calendar with a **collection URL + username + app password**.
No global configuration: each organization enters its own data,
verified (PROPFIND) before saving. Push via `PUT`/`DELETE`, pull via `REPORT`
sync-collection + `calendar-multiget`. The XML parsing is **XXE-safe** (no
`LIBXML_NOENT`, DOCTYPE rejected): a hostile server cannot read local files.

> **Proton Calendar does NOT expose CalDAV** (the Proton Bridge is mail-only, IMAP/SMTP).
> For Proton use the **iCal subscription feed** (§2), which Proton Calendar supports.

---

## 4. Bidirectional synchronization

- **Push (ArkVel → external):** every appointment mutation (create/confirm/reschedule/
  cancel) triggers, via an observer, a queued job (`SyncAppointmentToCalendarsJob`,
  after-commit) that pushes to **all** of the organization's active connections. Fault isolation
  per connection; a broken connection moves to `Error` and doesn't block the others.
- **Pull (external → ArkVel):** the scheduled command **`arkvel:sync-calendars`** (every
  15 min) pulls changes from the connected calendars (direction=Both) and reconciles them:
  an external **reschedule** → `RescheduleAppointment`; an external **deletion** →
  `CancelAppointment`. ArkVel stays **authoritative**: external changes always go
  through the Actions (guards, activity log, notifications); a change that would violate a guard
  (past slot, overlap, terminal state) is **discarded**, not forced.
- **Convergence (no ping-pong):** we act only when the external value *differs*
  from ArkVel's. A reschedule from a pull re-pushes the same value; the next pull
  sees `start == scheduled_at` → no-op. Converges in one round.
- **Tenant isolation:** an external event can only touch appointments of the connection's
  organization (verified both by event id and by mapping).

### Infrastructure
The push uses the existing `queue` worker (Redis); the pull uses the `scheduler` service
(`schedule:work`) from Phase 3. Connections and event↔appointment mappings live
in `calendar_connections` and `appointment_external_events`.

---

## 5. Privacy / GDPR

Exported/synchronized events are **minimized**: they contain only
`SUMMARY = "{type}: {animal}"`, `LOCATION = organization + city` (never the street), date/time and status.
**Never** the requester's email, phone, last name, or internal notes. The minimization
policy is defined in a single place (`AppointmentEventMapper`), shared by the ICS feed
and external providers. Connecting a third-party calendar (e.g. Google) is a transfer
of data to that provider: minimization limits the data exposed to the
bare minimum needed to identify the meeting.

---

## 6. How to try it

1. `docker compose up -d` (app :8090, Mailpit :8025) and `docker compose exec -T php php
   artisan migrate:fresh --seed`. Each demo organization has an active **Log connection**
   (dummy provider: logs pushes to the logs, demonstrable without credentials).
2. Staff login (`staff+milano@arkvel.test` / `password`) → **Appointments → Calendar**:
   drag an event, create from a slot, manage from the modal.
3. **iCal feed:** copy the URL from "iCal feed" and subscribe from Google/Proton/Apple.
4. **Reminders/due dates:** `php artisan arkvel:send-appointment-reminders` /
   `arkvel:send-medical-reminders` (emails in Mailpit :8025).
5. **Reconciliation pull:** `php artisan arkvel:sync-calendars` (with real connections
   configured; in the demo the Log connection produces no external changes).
6. **Google/CalDAV live:** set the `GOOGLE_CALENDAR_*` vars (Google) or connect a CalDAV
   from the Connections page; then connect from the UI. The tests all run with a fake HTTP client
   (`Http::fake`), so no credentials are needed for the suite.

---

## 7. Component map

| Area | Files |
|---|---|
| Calendar UI | `app/Filament/Shelter/Pages/AppointmentCalendar.php` (+ view), `CalendarConnections.php` |
| ICS export | `app/Support/Calendar/{IcsWriter,AppointmentCalendarExport,IcsStatus}.php`, `Http/Controllers/CalendarFeedController.php` |
| Mapping/PII | `app/Support/Calendar/{AppointmentEventMapper,CalendarEventData}.php` |
| Providers | `app/Support/Calendar/Providers/*` (Log/Google/CalDav), `CalDav/CalDavClient.php`, `Google/*` |
| Connections/pipeline | `app/Models/{CalendarConnection,AppointmentExternalEvent}.php`, `Jobs/SyncAppointmentToCalendarsJob.php`, `Observers/AppointmentObserver.php` |
| Pull/reconcile | `app/Support/Calendar/CalendarReconciler.php`, `Console/Commands/SyncCalendarsCommand.php` |
