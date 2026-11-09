<?php

declare(strict_types=1);

/*
 | Testi della home pubblica (UI-6a, App\Livewire\Home, livewire/home.blade.php).
 */

return [
    'hero' => [
        'badge' => 'Adozione responsabile',
        'title' => 'Trova il tuo prossimo migliore amico',
        'lead' => 'Cani, gatti e altri animali aspettano una famiglia nei canili, nei rifugi e nelle associazioni verificate. Ogni adozione cambia due vite.',
        'search_label' => 'Cerca un animale nel catalogo',
        'search_placeholder' => 'Nome, razza o città',
        'search_submit' => 'Cerca',
        'browse' => 'Oppure sfoglia tutto il catalogo',
        'spotlight_label' => 'Ti presento',
        'spotlight_action' => 'Conosci :name',
        'spotlight_alt' => 'Foto di :name',
        'stats_label' => 'ArkVel in numeri',
    ],

    'stats' => [
        'animals' => 'animale in cerca di casa|animali in cerca di casa',
        'organizations' => 'ente verificato|enti verificati',
        'adoptions' => 'adozione conclusa su ArkVel|adozioni concluse su ArkVel',
    ],

    'showcase' => [
        'eyebrow' => 'In vetrina',
        'title' => 'In cerca di casa',
        'description' => 'Alcuni degli amici che aspettano te, scelti dagli enti.',
        'see_all' => 'Vedi tutti',
        'filter_label' => 'Mostra solo una specie',
        'all' => 'Tutti',
        'species' => [
            'dog' => 'Cani',
            'cat' => 'Gatti',
            'other' => 'Altri animali',
        ],
        'results' => '{0} Nessun animale da mostrare|{1} Un animale in vetrina|[2,*] :count animali in vetrina',
        'results_species' => '{0} Nessun animale da mostrare: :species|{1} Un animale in vetrina: :species|[2,*] :count animali in vetrina: :species',
        'empty_filtered_title' => 'Nessun altro animale di questa specie in vetrina',
        'empty_filtered_body' => 'Nel catalogo completo puoi cercare per taglia, sesso e città.',
        'empty_title' => 'Per ora non ci sono animali in adozione',
        'empty_body' => 'Gli enti stanno preparando le schede: torna a trovarci presto.',
        'empty_action' => 'Vai al catalogo',
    ],

    'how' => [
        'eyebrow' => 'Come funziona',
        'title' => 'Adottare, passo dopo passo',
        'description' => 'Ti accompagniamo dalla prima ricerca al giorno in cui il tuo nuovo amico entra in casa.',
        'steps' => [
            'browse' => [
                'title' => 'Esplora',
                'body' => 'Cerca tra gli animali disponibili e filtra per specie, taglia e città.',
            ],
            'apply' => [
                'title' => 'Candidati',
                'body' => 'Trovato quello giusto? Invia la candidatura all’ente che se ne prende cura.',
            ],
            'meet' => [
                'title' => 'Conosciamoci',
                'body' => 'L’ente ti contatta per un colloquio e un incontro, per capire se siete fatti l’uno per l’altro.',
            ],
            'welcome' => [
                'title' => 'Accogli',
                'body' => 'Firmato il contratto di adozione, porti a casa il tuo nuovo compagno di vita.',
            ],
        ],
    ],

    'stories' => [
        'eyebrow' => 'Storie a lieto fine',
        'title' => 'Le prime storie sono ancora da scrivere',
        'body' => 'Qui racconteremo le adozioni nate su ArkVel, solo con il consenso delle famiglie e degli enti. Nessuna storia inventata: aspettiamo le vostre.',
        'action' => 'Scrivi la prossima',
    ],

    'support' => [
        'title' => 'Sostieni chi se ne prende cura',
        'body' => 'Canili, rifugi e associazioni curano e ospitano gli animali ogni giorno. Una donazione all’ente che scegli li aiuta a farlo.',
        'action' => 'Fai una donazione',
    ],

    'organizations' => [
        'title' => 'Gestisci un canile, un rifugio o un’associazione?',
        'body' => 'Pubblica i tuoi animali, ricevi le candidature e organizza gli incontri in un solo posto.',
        'action' => 'Registra il tuo ente',
    ],
];
