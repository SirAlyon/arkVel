<?php

declare(strict_types=1);

/*
 | SEO del sito pubblico (App\Support\Seo): titoli, descrizioni, breadcrumb e
 | testi dei dati strutturati. I titoli di home, catalogo ed elenco enti restano in
 | public.titles.* e vengono letti da lì.
 */

return [
    'site_name' => 'ArkVel',

    'titles' => [
        'animal' => ':name — ArkVel',
        'organization' => ':name — ArkVel',
        'page' => ':title — ArkVel',
        'catalog_paged' => 'Catalogo, pagina :page — ArkVel',
        'organizations_paged' => 'Enti, pagina :page — ArkVel',
        'organization_paged' => ':name, pagina :page — ArkVel',
    ],

    'descriptions' => [
        'catalog' => 'Cani, gatti e altri animali in adozione presso canili, rifugi e associazioni verificate. Cerca per specie, taglia e sesso.',
        'organizations' => 'Canili, rifugi e associazioni verificate che fanno adottare i loro animali su ArkVel.',
        'animal' => ':name (:species) cerca casa: in adozione presso :organization.',
        'organization' => ':name (:type): scopri gli animali in adozione e come sostenerlo.',
        'donation_thanks' => 'Grazie per aver sostenuto :name.',
        'donation_cancelled' => 'La donazione a :name è stata annullata.',
        'site' => 'Piattaforma open source per l’adozione di animali da canili, rifugi e associazioni verificate.',
    ],

    'breadcrumb' => [
        'label' => 'Percorso di navigazione',
        'home' => 'Home',
    ],
];
