<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "media" (resources/views/styleguide/groups/media.blade.php).
 | Le foto d'esempio sono illustrazioni statiche in public/images/styleguide/media.
 */

return [
    'alt' => [
        'dog' => 'Illustrazione: la testa di un cane stilizzato su fondo giallo, con un sole chiaro e una collina',
        'cat' => 'Illustrazione: la testa di un gatto stilizzato su fondo pesca, con un sole chiaro e una collina',
        'rabbit' => 'Illustrazione: un coniglio stilizzato su fondo verde menta, con un sole chiaro e una collina',
        'bird' => 'Illustrazione: un uccellino stilizzato su fondo lavanda, con un sole chiaro e una collina',
    ],
    'demo_name' => 'Luna',

    'conversions' => [
        'title' => 'Conversioni',
        'description' => 'Le conversioni medialibrary delle foto pubbliche (App\Enums\ImageConversion), tutte in coda. Accanto alla thumb 400 px che resta per Filament e per le pagine attuali.',
        'caption' => 'Conversioni registrate su Animal (photos, avatar)',
        'name' => 'Nome',
        'size' => 'Dimensioni',
        'use' => 'Uso',
        'formats' => 'Formati in questo ambiente',
        'responsive' => 'Responsive images',
        'yes' => 'Sì',
        'no' => 'No',
        'max' => 'entro :size px, senza ritaglio',
        'use_card' => 'Card del catalogo, miniature della galleria',
        'use_hero' => 'Foto principale e slide della scheda animale',
        'use_og' => 'Anteprima Open Graph (social)',
        'use_zoom' => 'Lightbox, foto intera',
        'environment' => 'Driver immagine: :driver. AVIF: :avif. WebP: :webp. Un formato non supportato non si registra: la pagina degrada da sola a WebP o JPEG.',
        'supported' => 'supportato',
        'unsupported' => 'non supportato',
        'regenerate' => 'Per i media esistenti (filtri: --model, --collection, --conversion, --dry-run):',
    ],

    'picture' => [
        'title' => 'Immagine responsive',
        'description' => 'Un <picture> con un <source> AVIF e uno WebP e l’<img> JPEG di fallback: il browser scarica il primo formato che sa leggere, alla larghezza giusta per sizes. Width e height dichiarati tengono ferme le proporzioni; il placeholder sfocato fa da sfondo finché la foto arriva, senza JavaScript.',
        'a11y_alt' => 'alt obbligatorio e tradotto: descrive la foto, non «foto di». Solo se il testo vicino dice già tutto si usa decorative (alt vuoto). Senza nessuno dei due il componente lancia un’eccezione.',
        'a11y_cls' => 'width e height sempre dichiarati, anche nel fallback: niente salti di layout (CLS) mentre le foto caricano.',
        'a11y_priority' => 'priority solo sull’immagine LCP (la prima foto visibile): fetchpriority alto e nessun lazy loading. Tutte le altre sono lazy.',
        'formats' => 'Formati e srcset',
        'formats_hint' => 'Card 4:5 in tre formati e due larghezze. Con un Media basta media e conversion: sorgenti, srcset, dimensioni e placeholder vengono dalle conversioni generate.',
        'priority' => 'Immagine LCP (priority)',
        'fill' => 'Riempie il contenitore (fill)',
        'fill_hint' => 'Il contenitore decide le proporzioni (qui aspect-4/5 e 1:1), l’immagine lo copre con object-cover.',
        'preview' => 'Conversioni in coda (anteprima)',
        'preview_hint' => 'Finché le conversioni in coda non sono pronte si vede la thumb sincrona (qui una foto 3:2) nelle proporzioni della conversione (4:5), imposte con aspect-ratio e object-cover: nessun salto quando arriva la foto vera. Mai l’originale, che pesa MB e può contenere la posizione GPS.',
        'fallback' => 'Senza immagine',
        'fallback_hint' => 'media null: un blocco colorato con la zampa, con le proporzioni della conversione. Tre toni, come le card; lo slot sostituisce la zampa.',
        'fallback_slot' => 'Foto in arrivo',
    ],

    'gallery' => [
        'title' => 'Galleria',
        'description' => 'Carosello Embla con miniature e lightbox PhotoSwipe (entrambi MIT), scaricati solo quando una galleria si inizializza. Senza JavaScript resta una lista di foto scorrevole, ognuna un link alla sua foto più grande.',
        'a11y_region' => 'Regione con aria-roledescription «carosello» e nome «Foto di <nome>»; ogni slide è un gruppo «n di N». Le slide fuori vista sono inert: il Tab raggiunge solo la foto mostrata.',
        'a11y_keyboard' => 'Frecce sinistra e destra ovunque nella galleria; bottoni precedente e successiva da 44 px (aria-disabled agli estremi, così il focus non si perde); miniature come bottoni con aria-current; il contatore è annunciato.',
        'a11y_lightbox' => 'Invio o Spazio sulla foto aprono la lightbox (dialog modale con nome, focus intrappolato, Esc chiude); alla chiusura il focus torna alla foto mostrata.',
        'a11y_alt' => 'Un alt per foto: custom property alt del Media (anche per lingua), altrimenti «Foto n di N di <nome>». Mai lo stesso alt per tutte.',
        'a11y_motion' => 'Con reduced-motion gli spostamenti e l’apertura della lightbox sono istantanei. Lo swipe ha sempre l’alternativa dei bottoni (WCAG 2.5.7).',
        'demo' => 'Scheda animale',
        'demo_hint' => 'Quattro foto statiche. In una pagina vera si passano i Media della collection photos.',
        'single' => 'Una sola foto',
        'single_hint' => 'Niente carosello né miniature: resta la foto con la sua lightbox.',
    ],
];
