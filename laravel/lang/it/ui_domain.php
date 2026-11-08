<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "dominio" (resources/views/components/ui):
 | animal-card (+ favorite, cta, skeleton), organization-card.
 */

return [
    'animal_card' => [
        'photo_alt' => 'Foto di :name',
        'featured' => 'In evidenza',
        'size' => 'Taglia :size',
        'location' => 'Si trova a',
        'loading' => 'Caricamento degli animali…',
        'favorite' => 'Salva :name nei preferiti',
        'cta' => [
            'title' => 'Non hai ancora trovato il tuo amico?',
            'description' => 'Nel catalogo ci sono tanti altri animali che aspettano una casa.',
            'description_count' => '{0} Ogni settimana arrivano nuovi animali in cerca di casa.|{1} Nel catalogo c’è un altro animale che aspetta una casa.|[2,*] Nel catalogo ci sono altri :count animali che aspettano una casa.',
            'action' => 'Sfoglia il catalogo',
        ],
    ],

    'organization_card' => [
        'location' => 'Sede',
        'verified' => 'Verificato',
        'available' => '{0} Nessun animale disponibile|{1} :count animale disponibile|[2,*] :count animali disponibili',
    ],
];
