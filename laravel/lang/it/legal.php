<?php

declare(strict_types=1);

/*
 | Intelaiatura comune delle pagine legali (/privacy, /termini): avviso di bozza, etichette
 | di versione e indice. I contenuti veri sono in legal_privacy.php e legal_terms.php.
 |
 | ⚠️ BOZZA — i testi legali di questo gruppo (qui e in legal_privacy/legal_terms) sono una
 | prima stesura tecnica e NON hanno valore legale finché non vengono validati dal titolare
 | del trattamento (kiba.studio di Alessio Sada) e, dove opportuno, da un consulente legale.
 */

return [
    'draft' => [
        'label' => 'Avviso',
        'title' => 'Bozza da validare',
        'body' => 'Questo documento è una bozza di lavoro e non ha ancora valore legale: va rivisto e approvato dal titolare del trattamento prima della pubblicazione definitiva.',
    ],

    'toc' => 'In questa pagina',
    'updated' => 'Ultimo aggiornamento: :date',
    'version' => 'Versione :version',
];
