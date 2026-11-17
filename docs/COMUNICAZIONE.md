# ArkVel — Communication & notifications

> Phase 4. Threaded **organization ↔ adopter** messaging, **real-time** updates
> (Reverb), an in-app **notification center**, and **branded** transactional email.

---

## 1. Threaded messaging (organization ↔ adopter)

A `MessageThread` links an **adopter** to an **organization** (optionally around an
animal); the `Message` records are the individual messages, with **unread** tracking per
side (`adopter_last_read_at` / `org_last_read_at`).

- Domain in `app/Actions/Messaging/` — `StartMessageThread`, `PostMessage`,
  `MarkThreadRead` (mutations in a transaction, guards, notifications). No logic in the
  models; authorization via `MessageThreadPolicy`.
- UI: **adopter** `/account → Messages` (`MyMessages`), **organization** `/manage →
  Conversations` (`Conversations`, tenant-scoped), plus **"Contact the organization"** from the
  public animal page (`AnimalDetail`).
- The unread count for the organization is computed at the DB level (`unreadForOrg`).

---

## 2. Realtime (Reverb)

Self-hosted **Reverb** websocket + **Laravel Echo** (bundled via npm, built with
`npm run build`). In Docker the `reverb` service runs (`0.0.0.0:8080`); the browser
connects to `localhost:8080`. No external account; development credentials in `.env`.

- Private channels in `routes/channels.php`: `App.Models.User.{id}` (personal notifications)
  and `message-thread.{thread}`. **Note**: these routes live **outside** the Filament
  panels → the `spatie/permission` team **is not set**. Before `can('view')`
  we call `setPermissionsTeamId($thread->organization_id)`, otherwise verified staff
  would be rejected (same pattern as the .ics feed, see [CALENDARIO.md](CALENDARIO.md)).
- `MessageSent` is `ShouldBroadcast` with a payload **without PII** (only id + `sender_side`):
  the client receives the event and reloads the authorized thread, not the content from the channel.
- Broadcast database notifications update the **bell** in real time
  (`BroadcastDatabaseNotificationSent`).

In tests broadcasting is `null` (`phpunit.xml`): no real connection.

---

## 3. In-app notification center

Filament bell (`->databaseNotifications()`) on the **adopter** and **organization** panels,
with dedicated `Notifications` pages (`InteractsWithNotificationCenter`). The notifications
are `ShouldQueue`, `via = ['mail','database']`, sent `afterCommit`, i18n.

> The `notifications.data` column **must** be `json` (not `text`), or Postgres breaks
> the bell query (`data->>'format'`).

---

## 4. Branded transactional email

The **`arkvel`** markdown theme (`config/mail.php` → `markdown.theme`), stylesheet
`resources/views/vendor/mail/html/themes/arkvel.css` with the brand colors
(terracotta `#c5522a`, teal `#148361`). All notification emails use this theme;
in development **Mailpit** intercepts them (`http://localhost:8025`).

---

## 5. Key files

- `app/Models/{MessageThread,Message}.php` · `app/Policies/MessageThreadPolicy.php` ·
  `app/Actions/Messaging/*`.
- `app/Events/MessageSent.php` · `app/Listeners/BroadcastDatabaseNotificationSent.php` ·
  `routes/channels.php`.
- `app/Filament/{Adopter,Shelter}/Pages/{MyMessages,Conversations,Notifications}.php` ·
  `app/Filament/Notifications/Support/InteractsWithNotificationCenter.php`.
- `app/Notifications/NewMessageReceived.php` · mail theme in
  `resources/views/vendor/mail/html/themes/arkvel.css`.
