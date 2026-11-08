<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "pickers" (resources/views/styleguide/groups/pickers.blade.php).
 */

return [

    'combobox' => [
        'title' => 'Combobox',
        'description' => 'Campo a pill con una lista di opzioni filtrata mentre si scrive. Per scegliere tra molte voci (razze, rifugi, comuni); per poche opzioni basta il select nativo.',
        'a11y_pattern' => 'Pattern APG «combobox con listbox popup»: role="combobox", aria-expanded, aria-controls, aria-autocomplete="list" e aria-activedescendant. Il focus resta sempre nel campo.',
        'a11y_keyboard' => '↓ / ↑ aprono e scorrono le opzioni, Home / End vanno alla prima / ultima, Invio sceglie, Esc chiude (il secondo Esc svuota), Tab chiude e conferma.',
        'a11y_state' => 'L’opzione scelta ha aria-selected e l’icona check; quella attiva ha l’anello del focus: lo stato non passa dal solo colore.',
        'a11y_live' => '«Nessun risultato» è scritto nella lista; il numero dei risultati arriva agli screen reader in una live region polite, quando si smette di scrivere.',
        'basic' => 'In un campo',
        'breed' => 'Razza',
        'breed_help' => 'Scrivi qualche lettera: la ricerca ignora maiuscole e accenti.',
        'breed_placeholder' => 'Cerca una razza',
        'described' => 'Con descrizione e valore iniziale',
        'shelter' => 'Rifugio',
        'model' => 'Valore legato (x-model / wire:model)',
        'species' => 'Specie',
        'current' => 'Valore:',
        'empty_value' => 'nessuno',
        'error_title' => 'Obbligatorio, con errore',
        'error' => 'Scegli una razza dall’elenco.',
        'disabled' => 'Disattivato',
        'standalone' => 'Da solo, con icona, su superficie',
        'standalone_label' => 'Cerca un comune',
        'server' => 'Ricerca lato server (Livewire)',
        'server_text' => 'Con search="nomeProprietà" il componente non filtra da solo: scrive il testo nella proprietà Livewire (dopo 300 ms) e mostra le opzioni che il server restituisce.',
    ],

    'date_picker' => [
        'title' => 'Selettore di date',
        'description' => 'Wrapper di Cally (web component MIT): campo a pill con la data scritta per esteso e calendario in un pannello, oppure sempre visibile. Cally si scarica solo nelle pagine che lo usano.',
        'a11y_dialog' => 'Il bottone «Apri il calendario» ha aria-haspopup="dialog" e aria-expanded; il pannello è un dialog con nome. All’apertura il focus va sul giorno scelto (o su oggi).',
        'a11y_keyboard' => 'Nella griglia: frecce (giorno e settimana), Home / End (inizio e fine settimana), PagSu / PagGiù (mese), Maiusc+PagSu / PagGiù (anno), Invio o Spazio per scegliere. Esc chiude e il focus torna al bottone.',
        'a11y_days' => 'Ogni giorno ha la data per esteso come nome. Oggi ha un anello e aria-current, i giorni non disponibili sono barrati e con aria-disabled: mai solo colore.',
        'a11y_live' => 'Dopo la scelta una live region annuncia «Data scelta: …»; nella variante inline la data scelta è scritta sotto il calendario.',
        'popover' => 'Popover in un campo',
        'visit' => 'Data della visita',
        'visit_help' => 'Puoi scegliere da oggi in poi.',
        'disallowed' => 'Giorni non disponibili e bottone per cancellare',
        'pickup' => 'Data di ritiro',
        'pickup_help' => 'Il rifugio è chiuso la domenica e nei giorni festivi segnati.',
        'model' => 'Valore legato (x-model / wire:model)',
        'birth' => 'Data di nascita (stimata)',
        'current' => 'Valore ISO:',
        'empty_value' => 'nessuno',
        'error_title' => 'Obbligatorio, con errore',
        'error' => 'Scegli la data della visita.',
        'inline' => 'Inline (prenotazione)',
        'inline_label' => 'Giorno della visita al rifugio',
        'disabled' => 'Disattivato',
    ],

    'breeds' => [
        'labrador' => 'Labrador',
        'golden' => 'Golden retriever',
        'german_shepherd' => 'Pastore tedesco',
        'border_collie' => 'Border collie',
        'beagle' => 'Beagle',
        'dachshund' => 'Bassotto',
        'jack_russell' => 'Jack Russell',
        'poodle' => 'Barboncino',
        'irish_setter' => 'Setter irlandese',
        'epagneul' => 'Épagneul breton',
        'maremma' => 'Pastore maremmano',
        'mixed' => 'Meticcio',
    ],

    'shelters' => [
        'faro' => 'Rifugio Il Faro',
        'faro_city' => 'Genova · cani e gatti',
        'querce' => 'Canile Le Querce',
        'querce_city' => 'Bologna · cani',
        'gattopardo' => 'Oasi felina Gattopardo',
        'gattopardo_city' => 'Palermo · gatti',
        'coda' => 'Associazione Coda Felice',
        'coda_city' => 'Torino · cani, gatti e conigli',
    ],

    'species' => [
        'dog' => 'Cane',
        'cat' => 'Gatto',
        'rabbit' => 'Coniglio',
        'ferret' => 'Furetto',
        'guinea_pig' => 'Porcellino d’India',
    ],

    'towns' => [
        'milano' => 'Milano',
        'monza' => 'Monza',
        'modena' => 'Modena',
        'macerata' => 'Macerata',
        'forli' => 'Forlì',
        'cefalu' => 'Cefalù',
    ],
];
