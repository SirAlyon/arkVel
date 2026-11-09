<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "motion" (resources/views/styleguide/groups/motion.blade.php).
 */

return [
    'demo_name' => 'Luna',

    'rules' => [
        'title' => 'Regole',
        'css' => 'Prima il CSS: transizioni, @starting-style e View Transitions. JavaScript solo dove il CSS non arriva.',
        'library' => 'Motion (MIT) per le micro-interazioni, caricata con import dinamico solo al primo uso (il cuore dei preferiti).',
        'tokens' => 'Durate ed easing solo dai token: interazioni entro 300 ms (--duration-slow), entrate con --duration-reveal, ease-out, ease-in-out ed ease-pop.',
        'reduced' => 'Con «riduci movimento» non si anima niente: le durate CSS vanno a zero e le animazioni JavaScript non partono.',
        'layout' => 'Solo opacità e trasformazioni: nessuno spostamento del layout (CLS) e niente sopra la piega o sull’immagine principale.',
    ],

    'favorite' => [
        'title' => 'Cuore dei preferiti',
        'description' => 'Al click il cuore fa un piccolo pop (ease-pop, 300 ms) quando diventa preferito e un rimbalzo quando torna vuoto. È solo un riscontro visivo: lo stato resta quello del server.',
        'example' => 'Prova il cuore',
        'a11y_state' => 'Lo stato è in aria-pressed e nel cuore pieno o vuoto, non nell’animazione.',
        'a11y_reduced' => 'Con «riduci movimento» il cuore cambia stato senza animarsi.',
    ],

    'toast' => [
        'title' => 'Conferma dei toast',
        'description' => 'Il toast sale e compare (220 ms, ease-out); l’icona del tipo entra subito dopo con un piccolo pop (ease-pop). Tutto in CSS, con @starting-style.',
        'example' => 'Lancia una conferma',
        'launch' => 'Salva',
        'sample_title' => 'Modifiche salvate',
        'sample_description' => 'Ti avviseremo quando l’ente risponde.',
        'a11y_reduced' => 'Con «riduci movimento» il toast compare senza movimento; l’annuncio per gli screen reader non cambia.',
    ],

    'transition' => [
        'title' => 'Foto card → scheda',
        'description' => 'Aprendo la scheda di un animale da una card (catalogo, home, pagina dell’ente, animali simili), la foto si sposta e si ingrandisce fino alla galleria. Con il tasto Indietro torna nella sua card.',
        'example' => 'Come si usa',
        'hint' => 'È una View Transition di wire:navigate: card e galleria hanno lo stesso nome, animal-photo-<id>. In partenza resta solo la foto che porta alla pagina di destinazione, così le altre card non volano attraverso lo schermo. I browser senza View Transitions cambiano pagina senza animazione.',
        'a11y_reduced' => 'Con «riduci movimento» Livewire salta la transizione.',
        'a11y_unique' => 'Il nome deve essere unico nella pagina: lo stesso animale non può comparire due volte con la foto animata (photo-transition="false" su x-site.animal-card).',
    ],

    'reveal' => [
        'title' => 'Reveal allo scroll',
        'description' => 'Sezioni e card compaiono con una dissolvenza e un piccolo spostamento quando entrano nella finestra; una riga di card entra in sequenza.',
        'example' => 'Scorri fino a qui',
        'hint' => 'Questi blocchi erano sotto la piega al caricamento: sono comparsi quando sono entrati nella finestra.',
        'list_label' => 'Esempi di reveal',
        'a11y_nojs' => 'Senza JavaScript tutto è visibile: lo stato nascosto lo aggiunge solo lo script.',
        'a11y_fold' => 'Quello che è già in vista al caricamento non viene mai nascosto (niente lampeggi, niente ritardi sull’immagine principale).',
        'a11y_focus' => 'Un blocco ancora nascosto che riceve il focus da tastiera compare subito; in stampa è sempre visibile.',
        'a11y_reduced' => 'Con «riduci movimento» non si nasconde e non si anima niente.',
        'items' => [
            'one' => 'Primo',
            'two' => 'Secondo',
            'three' => 'Terzo',
            'four' => 'Quarto',
            'five' => 'Quinto',
            'six' => 'Sesto',
            'seven' => 'Settimo',
            'eight' => 'Ottavo',
        ],
    ],
];
