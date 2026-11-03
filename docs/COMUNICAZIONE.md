# ArkVel — Comunicazione & notifiche

> Fase 4. Messaggistica **ente ↔ adottante** a thread, aggiornamenti **in tempo reale**
> (Reverb), **centro notifiche** in-app ed email transazionali **brandizzate**.

---

## 1. Messaggistica a thread (ente ↔ adottante)

Un `MessageThread` lega un **adottante** a un **ente** (opzionalmente attorno a un
animale); i `Message` sono i singoli messaggi, con tracciamento del **non letto** per
lato (`adopter_last_read_at` / `org_last_read_at`).

- Dominio in `app/Actions/Messaging/` — `StartMessageThread`, `PostMessage`,
  `MarkThreadRead` (mutazioni in transazione, guardie, notifiche). Nessuna logica nei
  model; autorizzazione via `MessageThreadPolicy`.
- UI: **adottante** `/account → Messaggi` (`MyMessages`), **ente** `/manage →
  Conversazioni` (`Conversations`, tenant-scoped), più **"Contatta l'ente"** dalla
  scheda animale pubblica (`AnimalDetail`).
- Il conteggio non letti per l'ente è calcolato lato DB (`unreadForOrg`).

---

## 2. Realtime (Reverb)

Websocket self-hosted **Reverb** + **Laravel Echo** (bundlato via npm, build con
`npm run build`). In Docker gira il servizio `reverb` (`0.0.0.0:8080`); il browser si
connette a `localhost:8080`. Nessun account esterno; credenziali di sviluppo nell'`.env`.

- Canali privati in `routes/channels.php`: `App.Models.User.{id}` (notifiche personali)
  e `message-thread.{thread}`. **Attenzione**: queste rotte vivono **fuori** dai pannelli
  Filament → il team di `spatie/permission` **non è impostato**. Prima di `can('view')`
  si fa `setPermissionsTeamId($thread->organization_id)`, altrimenti lo staff verificato
  verrebbe respinto (stesso pattern del feed .ics, vedi [CALENDARIO.md](CALENDARIO.md)).
- `MessageSent` è `ShouldBroadcast` con payload **senza PII** (solo id + `sender_side`):
  il client riceve l'evento e ricarica il thread autorizzato, non il contenuto dal canale.
- Le notifiche database broadcastate aggiornano la **campanella** in tempo reale
  (`BroadcastDatabaseNotificationSent`).

In test il broadcast è `null` (`phpunit.xml`): nessuna connessione reale.

---

## 3. Centro notifiche in-app

Campanella Filament (`->databaseNotifications()`) sui pannelli **adottante** e **ente**,
con pagine dedicate `Notifications` (`InteractsWithNotificationCenter`). Le notifiche
sono `ShouldQueue`, `via = ['mail','database']`, inviate `afterCommit`, i18n.

> La colonna `notifications.data` **deve** essere `json` (non `text`), o Postgres rompe
> la query della campanella (`data->>'format'`).

---

## 4. Email transazionali brandizzate

Tema markdown **`arkvel`** (`config/mail.php` → `markdown.theme`), foglio di stile
`resources/views/vendor/mail/html/themes/arkvel.css` con i colori del brand
(terracotta `#c5522a`, teal `#148361`). Tutte le mail di notifica usano questo tema;
in sviluppo le intercetta **Mailpit** (`http://localhost:8025`).

---

## 5. File principali

- `app/Models/{MessageThread,Message}.php` · `app/Policies/MessageThreadPolicy.php` ·
  `app/Actions/Messaging/*`.
- `app/Events/MessageSent.php` · `app/Listeners/BroadcastDatabaseNotificationSent.php` ·
  `routes/channels.php`.
- `app/Filament/{Adopter,Shelter}/Pages/{MyMessages,Conversations,Notifications}.php` ·
  `app/Filament/Notifications/Support/InteractsWithNotificationCenter.php`.
- `app/Notifications/NewMessageReceived.php` · tema mail in
  `resources/views/vendor/mail/html/themes/arkvel.css`.
