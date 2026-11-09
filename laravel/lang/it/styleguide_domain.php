<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "dominio" (resources/views/styleguide/groups/domain.blade.php).
 */

return [
    'demo' => [
        'age_puppy' => '6 mesi',
        'age_adult' => '3 anni',
        'age_senior' => '9 anni',
        'see_all' => 'Vedi tutti',
    ],

    'animal_card' => [
        'title' => 'Card animale',
        'description' => 'Superficie con padding 12 px, foto 4:5 stondata su un blocco colorato a rotazione (sole, pesca, menta), nome 800, badge e luogo. Tutta la card porta alla scheda; l’ultima card della griglia è scura con la CTA gialla.',
        'a11y_link' => 'Un solo link, sul nome: il suo ::after copre la card, quindi è cliccabile ovunque senza link vuoti né bottoni dentro un link.',
        'a11y_favorite' => 'Il bottone preferiti è un fratello del link, sopra di esso: ha un nome che contiene il nome dell’animale e aria-pressed; pieno o vuoto, non solo un colore.',
        'a11y_heading' => 'Il livello del titolo si sceglie con heading-level, così la pagina non salta livelli (qui h4, sotto gli h3 degli esempi).',
        'a11y_featured' => '«In evidenza» è un bollino con testo e stella, mai solo un colore.',
        'grid' => 'Griglia del catalogo con bottone preferiti',
        'grid_hint' => 'Toni a rotazione, con e senza foto. Il cuore qui è <x-ui.animal-card.favorite> (statico); nelle pagine si usa <livewire:favorite-button>, che ha lo stesso nome accessibile.',
        'grid_label' => 'Animali in evidenza',
        'minimal' => 'Solo i dati essenziali',
        'skeleton' => 'Caricamento',
        'skeleton_label' => 'Caricamento degli animali in evidenza…',
    ],

    'organization_card' => [
        'title' => 'Card ente',
        'description' => 'Logo (o iniziali), nome, sede, tipo di ente, badge «Verificato» e animali disponibili. Stesso link esteso della card animale.',
        'a11y_link' => 'Un solo link, sul nome, esteso a tutta la card.',
        'a11y_logo' => 'Il logo è decorativo (alt vuoto), perché il nome è scritto accanto.',
        'a11y_count' => 'Il numero di animali è formattato nella lingua corrente e declinato (nessuno, uno, molti).',
        'grid' => 'Griglia di enti',
        'grid_label' => 'Enti vicino a te',
    ],

    'stat' => [
        'title' => 'Numero',
        'description' => 'Un numero grande con la sua etichetta, su un blocco piatto. I numeri sono formattati nella lingua corrente.',
        'a11y_semantics' => 'Numero ed etichetta stanno nello stesso paragrafo, quindi si leggono insieme; più numeri vanno in una lista.',
        'a11y_icon' => 'L’icona è decorativa: il significato è nell’etichetta.',
        'bento' => 'Fila del bento',
        'bento_label' => 'ArkVel in numeri',
        'adopted' => 'animali adottati',
        'organizations' => 'enti in rete',
        'rating' => 'voto medio delle famiglie',
        'success' => 'adozioni andate a buon fine',
        'regions' => 'regioni coperte',
        'volunteers' => 'volontari attivi',
        'tones' => 'Toni',
    ],

    'section_heading' => [
        'title' => 'Titolo di sezione',
        'description' => 'Eyebrow opzionale, titolo, descrizione e un’azione a destra su desktop e sotto su mobile. Il titolo ha un id da usare in aria-labelledby della sezione.',
        'a11y_level' => 'Livello del titolo (heading-level) e aspetto (size) sono indipendenti.',
        'a11y_labelledby' => 'La <section> che lo contiene usa aria-labelledby con l’id del titolo.',
        'with_action' => 'Con eyebrow e azione',
        'text_eyebrow' => 'Eyebrow testuale, senza azione',
        'demo_eyebrow' => 'Nuovi arrivi',
        'demo_title' => 'Ti aspettano a casa',
        'demo_description' => 'Gli ultimi animali entrati nei rifugi della rete: cani, gatti e qualche sorpresa.',
        'demo_eyebrow_text' => 'Come funziona',
        'demo_title_small' => 'Quattro passi per adottare',
    ],

    'cta_band' => [
        'title' => 'Banda CTA',
        'description' => 'Pannello a tutta larghezza con titolo, testo e azioni, che su mobile va in colonna. La versione verde con la CTA gialla è la banda donazione.',
        'a11y_tone' => 'Il testo usa il colore on-* del fondo; il focus dei bottoni prende lo stesso colore.',
        'a11y_yellow' => 'Il giallo sul verde è solo il fondo del bottone: come testo sotto i 24 px non raggiunge 4,5:1.',
        'donation' => 'Banda donazione (leaf)',
        'donation_title' => 'Aiutaci a trovare una casa a tutti',
        'donation_text' => 'Con 10 € copri una settimana di cure per un animale in rifugio.',
        'donate' => 'Dona ora',
        'shelter' => 'Per gli enti (inverse)',
        'shelter_title' => 'Gestisci un rifugio o un’associazione?',
        'shelter_text' => 'Pubblica i tuoi animali, ricevi le candidature e organizza le visite in un solo posto.',
        'shelter_action' => 'Registra il tuo ente',
        'tones' => 'Altri toni',
        'tone_title' => 'Tono :tone',
        'tone_names' => ['sun' => 'sole', 'peach' => 'pesca', 'mint' => 'menta'],
        'tone_text' => 'Su questi fondi il bottone è primario.',
        'tone_action' => 'Scopri di più',
    ],
];
