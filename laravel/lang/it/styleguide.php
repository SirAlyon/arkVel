<?php

declare(strict_types=1);

/*
 | Styleguide del design system (/_styleguide, solo in ambiente local).
 */

return [
    'title' => 'Styleguide — ArkVel',
    'heading' => 'Styleguide',
    'intro' => 'I token del sito pubblico (direzione «Pop affettuoso»): colori, testo, forme, spazi e movimento, in tema chiaro e scuro.',
    'language' => 'Lingua',
    'toc' => 'Sezioni',

    'page_title' => ':group — :styleguide',
    'pages' => 'Pagine della styleguide',
    'tokens' => 'Token',
    'a11y' => 'Accessibilità',
    'show_code' => 'Mostra il codice',
    'examples' => 'Esempi',
    'groups' => [
        'actions' => [
            'title' => 'Azioni',
            'intro' => 'Icone, bottoni, link, badge e chip: gli elementi base con cui si agisce e si etichetta.',
        ],
        'forms' => [
            'title' => 'Moduli',
            'intro' => 'Campi, etichette, aiuti ed errori: input, area di testo, select, checkbox, radio e interruttore.',
        ],
        'pickers' => [
            'title' => 'Selettori',
            'intro' => 'Scelte guidate: combobox con ricerca e selettore di date (Cally).',
        ],
        'overlays' => [
            'title' => 'Livelli',
            'intro' => 'Dialog, pannello laterale, menu a tendina, tooltip e notifiche toast.',
        ],
        'content' => [
            'title' => 'Contenuti',
            'intro' => 'Schede, accordion, skeleton, stati vuoti e avatar.',
        ],
        'domain' => [
            'title' => 'Dominio',
            'intro' => 'I componenti di ArkVel: card animale ed ente, numeri, titoli di sezione e bande CTA.',
        ],
    ],

    'sections' => [
        'preview' => 'Anteprima',
        'colors' => 'Colori',
        'contrast' => 'Contrasti',
        'typography' => 'Tipografia',
        'shapes' => 'Forme',
        'spacing' => 'Spazi',
        'motion' => 'Movimento',
    ],

    'preview' => [
        'disabled' => 'Disattivato',
        'intro' => 'Come si combinano i token nei blocchi della home. Non è ancora un componente: serve a giudicare la direzione nei due temi.',
        'stamp' => 'Rifugi verificati in tutta Europa',
        'headline' => 'Trova il tuo nuovo migliore amico.',
        'lead' => 'Cani, gatti e tanti altri ti aspettano. Candidati in pochi passi, incontrali al rifugio.',
        'search_label' => 'Cerca un animale',
        'search_placeholder' => 'Cerca per nome, razza o città',
        'search' => 'Cerca',
        'photo' => 'Spazio per la foto',
        'stat_animals' => 'animali disponibili',
        'stat_organizations' => 'enti verificati',
        'filters' => 'Filtra per specie',
        'all' => 'Tutti',
        'dogs' => 'Cani',
        'cats' => 'Gatti',
        'others' => 'Altri',
        'primary' => 'Candidati',
        'secondary' => 'Accedi',
        'cta_card' => 'Altri amici da conoscere',
        'cta' => 'Sfoglia il catalogo',
        'donate' => 'Non puoi adottare? Fai la differenza con una donazione.',
        'donate_cta' => 'Dona a un ente',
    ],

    'colors' => [
        'on' => 'testo: :token',
        'semantic' => 'Token semantici',
        'semantic_intro' => 'Da usare nei componenti: cambiano valore con il tema. «Aa» mostra il testo previsto sopra il fondo.',
        'palette' => 'Palette',
        'palette_intro' => 'Scale 50–950 in OKLCH. I colori del mockup sono ancorati esatti; le classi di palette si usano solo dentro i token semantici o per casi documentati.',
        'groups' => [
            'backgrounds' => 'Fondi',
            'text' => 'Testo e bordi',
            'actions' => 'Azioni',
            'blocks' => 'Blocchi di colore',
            'status' => 'Stati',
        ],
    ],

    'contrast' => [
        'table' => 'Tabella dei contrasti',
        'intro' => 'Rapporti calcolati dal browser sul tema attivo. Testo almeno 4,5:1; bordi dei controlli e focus almeno 3:1. Le stesse coppie sono verificate dal test ColorContrastTest.',
        'pair' => 'Coppia',
        'sample' => 'Esempio',
        'ratio' => 'Rapporto',
        'minimum' => 'Minimo',
        'result' => 'Esito',
        'pass' => 'Supera',
        'fail' => 'Non supera',
        'text' => 'Testo',
        'non_text' => 'Bordo / focus',
        'forbidden' => 'Vietati: giallo su verde (4,43:1) per testo sotto i 24 px; bordo decorativo `line` (1,31:1) per input e controlli.',
    ],

    'typography' => [
        'intro' => 'Una sola famiglia, Bricolage Grotesque variabile (self-hosted, asse ottico automatico). I titoli scalano con la finestra.',
        'sample_title' => 'Ogni zampa ha una storia',
        'sample_text' => 'Luna è arrivata al rifugio in primavera. Ama le passeggiate lunghe, va d’accordo con gli altri cani e cerca una famiglia paziente.',
    ],

    'shapes' => [
        'pill' => 'pillola',
        'intro' => 'Raggi in ordine di gerarchia; bottoni, chip e campi di ricerca sono sempre a pillola. Nessuna ombra, tranne per i livelli flottanti.',
        'overlay' => 'Livello flottante (shadow-overlay)',
    ],

    'spacing' => [
        'container' => 'max-w-page · larghezza massima 1240 px',
        'intro' => 'Spazi fluidi per margini laterali, interno dei pannelli e distanza tra sezioni. Il contenuto è largo al massimo 1240 px.',
    ],

    'motion' => [
        'intro' => 'Durate ed easing condivisi. Le interazioni restano entro 300 ms; con «riduci movimento» attivo le durate vanno a zero.',
        'play' => 'Riproduci',
        'reset' => 'Riporta indietro',
        'reduced' => 'Movimento ridotto attivo: le animazioni sono disattivate.',
    ],
];
