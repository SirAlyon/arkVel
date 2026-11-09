<?php

declare(strict_types=1);

/*
 | Catalogo pubblico degli animali (/animali, UI-6b): ricerca, filtri con i conteggi,
 | ordinamento, chip dei filtri attivi, caricamento continuo e stati vuoti.
 */
return [
    'title' => 'Animali in adozione',
    'intro' => 'Cani, gatti e altri animali che aspettano una famiglia negli enti verificati. Cerca per nome o filtra per trovare chi fa per te.',

    'search' => [
        'label' => 'Cerca un animale',
        'placeholder' => 'Nome, razza o carattere',
        'help' => 'I risultati si aggiornano mentre scrivi.',
        'submit' => 'Cerca',
    ],

    'facets' => [
        'species' => 'Specie',
        'sex' => 'Sesso',
        'size' => 'Taglia',
        'region' => 'Regione',
        'organization' => 'Ente',
    ],

    'filters' => [
        'title' => 'Filtri',
        'description' => 'I numeri dicono quanti animali trovi con ogni scelta.',
        'open' => 'Filtri',
        'open_active' => '{1}:count filtro attivo|[2,*]:count filtri attivi',
        'all' => 'Tutti',
        'all_region' => 'Tutte le regioni',
        'all_organization' => 'Tutti gli enti',
        'option' => ':label (:count)',
        'apply' => 'Applica i filtri',
        'show_results' => '{0}Nessun risultato|{1}Mostra :count animale|[2,*]Mostra :count animali',
        'reset' => 'Azzera i filtri',
    ],

    'sort' => [
        'label' => 'Ordina per',
        'apply' => 'Ordina',
        'relevance' => 'Consigliati',
        'newest' => 'Arrivati da poco',
        'waiting' => 'In attesa da più tempo',
        'name' => 'Nome (A–Z)',
    ],

    'chips' => [
        'label' => 'Filtri attivi',
        'search' => '«:search»',
        'search_facet' => 'Ricerca',
        'described' => ':facet: :value',
        'reset' => 'Azzera tutto',
    ],

    'results' => [
        'count' => '{0}Nessun animale trovato|{1}:count animale trovato|[2,*]:count animali trovati',
        'range' => ':summary, mostrati dal :from al :to',
        'loading' => 'Caricamento di altri animali…',
    ],

    'more' => [
        'progress' => 'Hai visto :shown animali su :total',
        'action' => 'Carica altri animali',
    ],

    'pagination' => [
        'label' => 'Pagine del catalogo',
    ],

    'empty' => [
        'filtered_title' => 'Nessun animale con questi filtri',
        'filtered_description' => 'Prova a togliere un filtro o a cercare un altro nome: gli enti aggiungono nuovi animali ogni settimana.',
        'catalog_title' => 'Il catalogo è vuoto, per ora',
        'catalog_description' => 'Gli enti stanno preparando nuove schede. Intanto puoi conoscere i rifugi e le associazioni della piattaforma.',
        'catalog_action' => 'Scopri gli enti',
        'past_title' => 'Questa pagina non c’è',
        'past_description' => 'Il catalogo ha meno pagine di così: forse alcuni animali hanno già trovato casa.',
        'past_action' => 'Torna alla prima pagina',
    ],

    'end' => [
        'title' => 'Non hai ancora trovato il tuo compagno?',
        'description' => 'Conosci gli enti che ospitano questi animali: ogni rifugio ha la sua storia e altri modi per dare una mano.',
        'action' => 'Scopri gli enti',
    ],
];
