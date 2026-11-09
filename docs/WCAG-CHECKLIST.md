# ArkVel — Checklist WCAG 2.2 AA del sito pubblico

> Ambito: sito pubblico Livewire (home, catalogo, scheda animale, enti, donazioni, autenticazione)
> e libreria `x-ui.*`. I pannelli Filament sono fuori ambito.
>
> Verifiche automatiche comuni: **axe** (`assertNoAccessibilityIssues`) in light e dark nei test
> browser di `laravel/tests/Browser` (pagine e `Components/*`); **contrasti** dei token in
> `laravel/tests/Unit/DesignTokens/ColorContrastTest.php` (coppie in
> `App\Support\DesignTokens\ContrastPairs`); **target** con `BrowserScripts::undersizedTargets`.
> axe non copre tutto: i criteri marcati «manuale» sono stati verificati nelle `/ui-review` delle
> fasi UI-2…UI-7 (docs/PIANO-UI-FRONTEND.md).

| Criterio | Come è soddisfatto | Dove è verificato |
|---|---|---|
| **1.1.1** Contenuti non testuali | `x-ui.picture` richiede `alt` (o `decorative`); icone Lucide decorative con `aria-hidden`, con `label` diventano `role="img"`; galleria con alt per foto («Foto n di N di …») | `MediaComponentsTest`, `ActionsComponentsTest` (feature), axe |
| **1.3.1** Informazioni e relazioni | HTML semantico; `x-ui.field` collega label, aiuto ed errore (`for`, `aria-describedby`, `aria-invalid`); fieldset/legend per i gruppi; titoli senza salti | `FormsComponentsTest`, `PickersComponentsTest`, test di gerarchia dei titoli in `DomainComponentsTest` e `HomeTest` (browser) |
| **1.3.2** Sequenza significativa | Ordine del DOM uguale all'ordine visivo; marker della mappa nell'ordine dell'elenco | `MapTest` (browser) |
| **1.3.5** Scopo degli input | `autocomplete` su nome, email, password (`current-password` / `new-password`), `username` nel reset | `AuthTest` (browser), feature delle pagine auth |
| **1.4.1** Uso del colore | Stati mai solo cromatici: badge con testo, preferito con `aria-pressed` e cuore pieno/vuoto, errori con icona e testo, link sottolineati | feature dei componenti; manuale |
| **1.4.3** Contrasto (minimo) | Solo token semantici; ogni coppia testo/fondo ≥ 4,5:1 in light e dark | `ColorContrastTest`, axe |
| **1.4.4** Ridimensionamento del testo | Tipografia in `rem` e `clamp`, layout fluidi | manuale |
| **1.4.10** Reflow | Nessuno scorrimento orizzontale a 320 px su tutte le pagine | test «no horizontal overflow at 320 px» nei test browser di pagine e componenti |
| **1.4.11** Contrasto non testuale | Bordi dei controlli con `line-strong` (≥ 3:1), anello di focus ≥ 3:1 anche sui blocchi colorati (`focusScopes`) | `ColorContrastTest` |
| **1.4.12** Spaziatura del testo | Nessun contenitore di testo ad altezza fissa | manuale |
| **1.4.13** Contenuto al passaggio del mouse o al focus | `x-ui.tooltip`: appare su hover e focus, resta col puntatore sopra, Esc lo chiude senza spostare il focus | `OverlaysComponentsTest` (browser) |
| **2.1.1** Tastiera | Tutti i widget da tastiera (pattern APG): dialog, drawer, dropdown, tabs, accordion, combobox, date picker (Cally), galleria, mappa (marker come bottoni, +/−) | test browser dei componenti e `MapTest` |
| **2.1.2** Nessuna trappola | Dialog e drawer modali nativi (`showModal`) chiudibili con Esc; la mappa non intrappola né il focus né lo scroll (Ctrl/⌘ + rotella) | `OverlaysComponentsTest`, `MapTest` |
| **2.4.1** Salto di blocchi | Skip link come primo elemento, anche dopo `wire:navigate` | `ShellTest` (browser) |
| **2.4.2** Titolo della pagina | Titoli tradotti per pagina (`seo.titles.*`, `public.titles.*`), aggiornati dopo `wire:navigate` | `SeoPagesTest`, `ShellTest` |
| **2.4.3** Ordine del focus | Focus gestito all'apertura e chiusura di livelli, dopo l'invio dei form (riepilogo errori o messaggio di esito), chiudendo un toast | `OverlaysComponentsTest`, `AnimalDetailTest`, `AuthTest` (browser) |
| **2.4.4** Scopo del link | Link esteso sul nome della card (nessun link vuoto); «Vedi gli animali di {ente}» nel riquadro della mappa | `DomainComponentsTest`, `MapTest` |
| **2.4.6** Intestazioni ed etichette | Label visibili su tutti i campi, ricerca compresa | feature dei componenti e delle pagine |
| **2.4.7** Focus visibile | Anello globale di 3 px (`:focus-visible`) mai soppresso | test «visible focus ring» nei browser test dei componenti |
| **2.4.11** Focus non coperto | `scroll-padding` per l'header sticky, i footer dei drawer e la barra mobile della scheda; i toast fanno scorrere la pagina; marker fuori vista `inert` | `OverlaysComponentsTest`, `MapTest`, `ShellTest` |
| **2.5.3** Etichetta nel nome | I nomi accessibili iniziano con il testo visibile (es. logo, menu account) | `HeaderTest` (feature) |
| **2.5.7** Movimenti di trascinamento | Galleria e mappa usabili con bottoni (precedente/successiva, +/−) oltre allo swipe | `MediaComponentsTest`, `MapTest` |
| **2.5.8** Dimensione del target (minimo) | Controlli ≥ 44 px (oltre i 24 richiesti); link testuali ≥ 24 px | `BrowserScripts::undersizedTargets` nei test browser |
| **3.1.1** Lingua della pagina | `<html lang>` dalla lingua attiva; header `Content-Language` | `ShellTest`, `ContentLanguageTest` |
| **3.1.2** Lingua delle parti | Descrizioni nella lingua disponibile con `lang` quando diversa (`TranslatedText`) | `AnimalDetailTest`, `OrganizationPageTest` (feature) |
| **3.2.1 / 3.2.2** Al focus / all'input | Nessun cambio di contesto al focus; filtri e ricerca aggiornano solo i risultati, annunciati in una regione live | `CatalogTest` (browser) |
| **3.2.3 / 3.2.4** Navigazione e identificazione coerenti | Shell unica (header, footer, breadcrumb) e componenti `x-ui` condivisi | — |
| **3.3.1** Identificazione degli errori | Errori testuali vicino al campo e in `x-ui.error-summary` (con focus) | `FormsComponentsTest`, `AuthTest`, `AnimalDetailTest` |
| **3.3.2** Etichette o istruzioni | Aiuti collegati con `aria-describedby`, indicatore testuale «obbligatorio/facoltativo» | `FormsComponentsTest` |
| **3.3.3** Suggerimenti per gli errori | Messaggi specifici tradotti (formato, lunghezza, link scaduto con «Chiedi un nuovo link») | feature delle pagine auth |
| **3.3.7** Inserimento ridondante | Nessun dato già fornito richiesto di nuovo nello stesso flusso | manuale |
| **3.3.8** Autenticazione accessibile | Login con email e password, gestori di password supportati (`autocomplete`), nessun test cognitivo | `AuthTest` |
| **4.1.2** Nome, ruolo, valore | Ruoli e stati ARIA dei pattern APG (`aria-expanded`, `aria-pressed`, `aria-selected`, `role="switch"`, `role="dialog"`…) | feature e browser dei componenti, axe |
| **4.1.3** Messaggi di stato | Regioni `role="status"` / `role="alert"` (toast, esito dei form, risultati del catalogo, pagina caricata dopo `wire:navigate`) | `OverlaysComponentsTest`, `CatalogTest`, `ShellTest` |

## Movimento

`prefers-reduced-motion` (WCAG 2.3.3, AAA, adottato comunque): durate dei token a ~0, nessuna
comparsa allo scroll, nessuna View Transition, la mappa salta senza animare
(`MotionTest`, `MapTest`).

## Eccezioni e limiti noti

- **Contenuti degli enti:** testi e foto caricati dagli enti (descrizioni, `alt` personalizzati)
  non sono controllabili dal codice; in mancanza di `alt` si usa «Foto n di N di <nome>».
- **Mappa:** è un complemento; l'elenco alternativo degli stessi enti è sempre presente. Le
  etichette dei luoghi vengono dalle tile (stile `fiord` in dark: maiuscole).
- **Bianco su `block-leaf` in dark:** 4,64:1, conforme ma con poco margine.
- **Script inline** (tema): con una futura Content Security Policy richiederanno un nonce.
