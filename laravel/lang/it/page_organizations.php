<?php

declare(strict_types=1);

/*
 | Pagine pubbliche degli enti (UI-6d): indice /enti, pagina dell'ente con il widget di
 | donazione, pagine di ritorno dal Checkout. I testi già esistenti restano in
 | public.organizations.*, public.organization.* e donations.public.*.
 */
return [
    'index' => [
        'eyebrow' => 'Chi si prende cura di loro',
        'filters' => [
            'title' => 'Filtra gli enti',
            'search' => 'Cerca per nome',
            'search_placeholder' => 'Es. Rifugio Il Faro',
            'region' => 'Regione',
            'region_all' => 'Tutte le regioni',
            'type' => 'Tipo di ente',
            'type_all' => 'Tutti',
            'reset' => 'Azzera i filtri',
            'apply' => 'Applica i filtri',
        ],
        'results_title' => 'Elenco degli enti',
        'results' => '{0}Nessun ente trovato|{1}:count ente trovato|[2,*]:count enti trovati',
        'empty_filtered' => [
            'title' => 'Nessun ente corrisponde ai filtri',
            'description' => 'Prova un\'altra regione o un altro tipo di ente, oppure cerca un nome diverso.',
        ],
        'empty' => [
            'title' => 'Ancora nessun ente',
            'catalog' => 'Sfoglia il catalogo',
        ],
    ],

    'show' => [
        'verified' => 'Ente verificato',
        'location' => 'Sede:',
        'contacts_title' => 'Contatti',
        'email' => 'Email:',
        'phone' => 'Telefono:',
        'stats_title' => 'L\'ente in numeri',
        'stats' => [
            'available' => '{1}animale in cerca di casa|[0,*]animali in cerca di casa',
            'adopted' => '{1}adozione conclusa|[0,*]adozioni concluse',
        ],
        'about_title' => 'Chi siamo',
        'animals_title' => 'I loro animali',
        'animals_empty_title' => 'Nessun animale in adozione, per ora',
        'browse_catalog' => 'Sfoglia il catalogo',
        'map' => [
            'title' => 'Dove si trova',
            'label' => 'Mappa della zona di :name',
            'description' => 'Il cerchio indica la zona in cui si trova l’ente, non l’indirizzo. Per una visita, contatta l’ente.',
        ],
    ],

    'donation' => [
        'monthly_note' => 'Un addebito al mese, disdici quando vuoi dal tuo account.',
        'login' => 'Accedi',
        'amount_help' => 'Da :min a :max.',
    ],

    'thanks' => [
        'next_title' => 'Vuoi fare ancora di più?',
        'next_body' => 'Dai un\'occhiata agli animali che cercano casa: forse uno di loro aspetta proprio te.',
        'catalog' => 'Sfoglia il catalogo',
    ],

    'cancelled' => [
        'help' => 'Il tuo sostegno a :name resta prezioso: puoi riprovare dalla pagina dell\'ente.',
        'catalog' => 'Vai al catalogo',
    ],
];
