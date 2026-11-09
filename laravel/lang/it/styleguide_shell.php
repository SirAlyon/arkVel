<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "shell" (resources/views/styleguide/groups/shell.blade.php).
 */

return [
    'nav_link' => [
        'title' => 'Voce di navigazione',
        'description' => 'Link "pill" dell’header e riga a tutta larghezza del menu mobile. La voce attiva è navy piena; wire:navigate.hover precarica la pagina al passaggio del mouse o al focus.',
        'a11y_current' => 'Lo stato attivo non è solo un colore: aria-current="page" sulla pagina della voce, aria-current="true" dentro la sua sezione (es. la scheda di un animale per «Catalogo»).',
        'a11y_target' => 'Target da 44 px (pill) e 48 px (riga del menu mobile).',
        'a11y_navigate' => 'Verso i pannelli Filament (altro layout) la navigazione SPA si spegne con :navigate="false".',
        'pill' => 'Pill nell’header, sulla pagina corrente',
        'section' => 'Pill dentro la sezione corrente',
        'block' => 'Riga del menu mobile, con icona',
    ],

    'language_switcher' => [
        'title' => 'Selettore della lingua',
        'description' => 'Controllo segmentato: un bottone per lingua, che salva la scelta in sessione con un POST (funziona anche senza JavaScript).',
        'a11y_pressed' => 'La lingua attiva ha aria-pressed="true"; il gruppo ha il nome «Lingua».',
        'a11y_lang' => 'Ogni opzione ha l’attributo lang: lo screen reader pronuncia l’endonimo nella lingua giusta.',
        'a11y_compact' => 'Nella versione compatta il codice visibile (IT, EN) è all’inizio del nome accessibile, seguito dall’endonimo.',
        'full' => 'Con l’endonimo (menu mobile, footer)',
        'compact' => 'Compatto (header)',
    ],

    'header' => [
        'title' => 'Header',
        'description' => 'Logo, navigazione a pillole, lingua e account; sotto md la navigazione passa nel menu mobile (drawer). Sticky, con il bordo che compare allo scroll; sulla hero può essere trasparente (transparentHeader). Qui in anteprima statica, variante ospite.',
        'a11y_logo' => 'Il logo è il link alla home: nome «arkvel, pagina iniziale», che inizia col wordmark visibile.',
        'a11y_menu' => 'Il menu mobile è un dialog modale: focus intrappolato, Esc chiude e il focus torna al bottone.',
        'a11y_account' => 'Il menu account mostra il nome di battesimo e, per gli screen reader, il suo scopo («Il tuo account»).',
        'guest' => 'Ospite (riduci la finestra per il menu mobile)',
    ],

    'footer' => [
        'title' => 'Footer',
        'description' => 'Logo e tagline, link principali e dell’account, newsletter (segnaposto, non ancora attiva), note legali in arrivo, lingua e copyright. Variante ospite.',
        'a11y_newsletter' => 'Il modulo della newsletter è disattivato e lo dice a parole: nessun campo che sembra funzionare e non funziona.',
        'a11y_legal' => 'Le pagine legali non esistono ancora: testo con l’etichetta «In arrivo», nessun link rotto.',
        'guest' => 'Ospite',
    ],
];
