# ArkVel — Calendario appuntamenti & sincronizzazione esterna

> Fase 3.5. Estende gli appuntamenti (Fase 3) con un vero calendario in-app e la
> sincronizzazione bidirezionale con i calendari esterni (Google, Proton, Apple,
> Outlook, Fastmail, Nextcloud, iCloud). Pensato per un ente che gestisce decine di
> appuntamenti a settimana.

---

## 1. Calendario in-app (`/manage`)

Nel pannello ente, **Appuntamenti → Calendario**: viste **mese/settimana/giorno**
(FullCalendar v6, MIT), colori per stato, tenant-scoped.

- **Click su un evento** → modale con dettaglio e azioni staff: Conferma, Completa,
  Assente (no-show), Annulla — tutte delegano alle Action di dominio
  (`app/Actions/Appointments/*`), rispettano `AppointmentPolicy` e le guardie.
- **Drag-and-drop** per riprogrammare (guardia slot futuro + anti-overlap;
  `RescheduleAppointment`); **click su slot vuoto** per creare (richiedente ristretto
  ai soli utenti già in relazione con l'ente — GDPR).
- L'interattività (drag/create) è abilitata solo per chi ha `appointment.manage`
  (membro verificato); il **volontario** vede un calendario in sola lettura.

La vista lista/tabella con filtri (`AppointmentResource`) resta disponibile come vista
secondaria.

> Scelta tecnica: nessun plugin calendario Filament 5 (nessuno stabile/mantenuto).
> FullCalendar è caricato via `FilamentAsset::register` (build globale su CDN,
> vendorizzabile in locale) e inizializzato con Alpine — nessuna dipendenza fragile.

---

## 2. Feed iCal in sottoscrizione (universale, sola andata)

Ogni ente ha un **URL segreto** di sottoscrizione (`/calendar/{ente}/{token}`) a cui
qualunque client si abbona: **Google, Proton, Apple, Outlook**. Aggiornamento
automatico, standard iCalendar, un solo meccanismo per tutti i provider.

- In **Appuntamenti → Calendario → Feed iCal**: URL copiabile + rigenera/revoca il
  token (invalida il vecchio URL). Visibile solo a chi gestisce.
- Token ad alta entropia nell'URL (come i feed segreti di Google/Fastmail),
  confrontato a tempo costante (`hash_equals`); token errato/ente inesistente →
  `404` indistinguibile; rate-limited.
- Ogni appuntamento ha anche un **download `.ics`** ("Aggiungi al calendario") e le
  email di conferma/spostamento allegano l'`.ics`.

È il modo più semplice per far entrare gli appuntamenti nel calendario personale dello
staff senza collegare account.

---

## 3. Connessioni bidirezionali (Google / CalDAV)

Architettura provider-agnostica: interfaccia `ExternalCalendarProvider`
(`pushEvent`/`deleteEvent`/`pullChanges`) + registry; aggiungere un provider = aggiungere
una classe. Le credenziali sono **cifrate at-rest** (`credentials` cast `encrypted:array`),
mai loggate, mai in messaggi d'errore. Gestione in **Appuntamenti → Connessioni calendario**.

### 3.1 Google Calendar (OAuth)
Collega il Google Calendar dell'ente; ArkVel crea/aggiorna/cancella gli eventi via API
(nessuna SDK `google/apiclient`: solo client HTTP, config-gated). **Per abilitarlo** servono
le credenziali di un progetto Google Cloud in `.env`:

```
GOOGLE_CALENDAR_CLIENT_ID=...
GOOGLE_CALENDAR_CLIENT_SECRET=...
GOOGLE_CALENDAR_REDIRECT_URI=https://<host>/calendar/google/callback
```

Scope richiesto: `https://www.googleapis.com/auth/calendar.events` (offline, refresh
token). Senza credenziali l'azione "Collega Google" è disabilitata (feature inerte).

### 3.2 CalDAV (Fastmail, Nextcloud, iCloud, mailbox.org, …)
Collega un calendario CalDAV con **URL della collezione + username + app-password**.
Nessuna configurazione globale: ogni ente inserisce i propri dati,
verificati (PROPFIND) prima di salvare. Push via `PUT`/`DELETE`, pull via `REPORT`
sync-collection + `calendar-multiget`. Il parsing XML è **XXE-safe** (niente
`LIBXML_NOENT`, DOCTYPE rifiutato): un server ostile non può leggere file locali.

> **Proton Calendar NON espone CalDAV** (il Proton Bridge è solo mail, IMAP/SMTP).
> Per Proton usa il **feed iCal in sottoscrizione** (§2), che Proton Calendar supporta.

---

## 4. Sincronizzazione bidirezionale

- **Push (ArkVel → esterni):** ogni mutazione di un appuntamento (crea/conferma/sposta/
  annulla) scatena, via observer, un job accodato (`SyncAppointmentToCalendarsJob`,
  after-commit) che spinge verso **tutte** le connessioni attive dell'ente. Isolamento
  dei guasti per connessione; una connessione rotta passa a `Error` e non blocca le altre.
- **Pull (esterni → ArkVel):** il comando schedulato **`arkvel:sync-calendars`** (ogni
  15 min) tira le modifiche dai calendari collegati (direction=Both) e le riconcilia:
  uno **spostamento** esterno → `RescheduleAppointment`; una **cancellazione** esterna →
  `CancelAppointment`. ArkVel resta **autoritativo**: le modifiche esterne passano sempre
  dalle Action (guardie, activity log, notifiche); una modifica che violerebbe una guardia
  (slot passato, overlap, stato terminale) viene **scartata**, non forzata.
- **Convergenza (niente ping-pong):** si agisce solo quando il valore esterno *differisce*
  da quello ArkVel. Una riprogrammazione da pull ri-spinge lo stesso valore; il pull
  successivo vede `start == scheduled_at` → no-op. Converge in un giro.
- **Isolamento tenant:** un evento esterno può toccare solo appuntamenti dell'ente della
  connessione (verificato sia per id evento sia per mappatura).

### Infrastruttura
Il push usa il worker `queue` (Redis) già presente; il pull usa il servizio `scheduler`
(`schedule:work`) della Fase 3. Le connessioni e le mappature evento↔appuntamento vivono
in `calendar_connections` e `appointment_external_events`.

---

## 5. Privacy / GDPR

Gli eventi esportati/sincronizzati sono **minimizzati**: contengono solo
`SUMMARY = "{tipo}: {animale}"`, `LOCATION = ente + città` (mai la via), data/ora e stato.
**Mai** email, telefono, cognome del richiedente né note interne. La politica di
minimizzazione è definita in un unico punto (`AppointmentEventMapper`), condivisa da feed
ICS e provider esterni. Collegare un calendario di terze parti (es. Google) è un
trasferimento di dati verso quel fornitore: la minimizzazione limita il dato esposto al
minimo indispensabile per identificare l'incontro.

---

## 6. Come provarlo

1. `docker compose up -d` (app :8090, Mailpit :8025) e `docker compose exec -T php php
   artisan migrate:fresh --seed`. Ogni ente demo ha una **connessione Log** attiva
   (provider fittizio: registra i push nei log, dimostrabile senza credenziali).
2. Login staff (`staff+milano@arkvel.test` / `password`) → **Appuntamenti → Calendario**:
   trascina un evento, crea da uno slot, gestisci dal modale.
3. **Feed iCal:** copia l'URL da "Feed iCal" e abbonati da Google/Proton/Apple.
4. **Reminder/scadenze:** `php artisan arkvel:send-appointment-reminders` /
   `arkvel:send-medical-reminders` (email in Mailpit :8025).
5. **Pull di riconciliazione:** `php artisan arkvel:sync-calendars` (con connessioni reali
   configurate; in demo la connessione Log non produce cambi esterni).
6. **Google/CalDAV live:** imposta le `GOOGLE_CALENDAR_*` (Google) o collega un CalDAV
   dalla pagina Connessioni; poi collega dall'UI. I test girano tutti con HTTP fittizio
   (`Http::fake`), quindi non servono credenziali per la suite.

---

## 7. Mappa dei componenti

| Ambito | File |
|---|---|
| Calendario UI | `app/Filament/Shelter/Pages/AppointmentCalendar.php` (+ view), `CalendarConnections.php` |
| Export ICS | `app/Support/Calendar/{IcsWriter,AppointmentCalendarExport,IcsStatus}.php`, `Http/Controllers/CalendarFeedController.php` |
| Mapping/PII | `app/Support/Calendar/{AppointmentEventMapper,CalendarEventData}.php` |
| Provider | `app/Support/Calendar/Providers/*` (Log/Google/CalDav), `CalDav/CalDavClient.php`, `Google/*` |
| Connessioni/pipeline | `app/Models/{CalendarConnection,AppointmentExternalEvent}.php`, `Jobs/SyncAppointmentToCalendarsJob.php`, `Observers/AppointmentObserver.php` |
| Pull/reconcile | `app/Support/Calendar/CalendarReconciler.php`, `Console/Commands/SyncCalendarsCommand.php` |
