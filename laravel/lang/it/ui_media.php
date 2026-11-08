<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "media" (resources/views/components/ui):
 | picture, gallery (+ lightbox PhotoSwipe).
 */

return [
    'gallery' => [
        'label' => 'Foto di :name',
        'roledescription' => 'carosello',
        'slide_roledescription' => 'foto',
        'slide' => ':position di :total',
        'photo_alt' => 'Foto :position di :total di :name',
        'open' => '(apri a schermo intero)',
        'previous' => 'Foto precedente',
        'next' => 'Foto successiva',
        'counter' => 'Foto :position di :total',
        'thumbnails' => 'Scegli una foto',
        'thumbnail' => 'Mostra la foto :position di :total',
        'lightbox' => [
            'label' => 'Foto di :name a schermo intero',
            'close' => 'Chiudi',
            'zoom' => 'Ingrandisci o riduci',
            'previous' => 'Foto precedente',
            'next' => 'Foto successiva',
            'error' => 'Non è stato possibile caricare la foto.',
        ],
    ],
];
