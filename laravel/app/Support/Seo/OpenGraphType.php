<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Valori di `og:type` usati dal sito pubblico (https://ogp.me/#types).
 *
 * `article` per le schede animale (un contenuto su un singolo soggetto, con data di
 * aggiornamento); `website` per tutto il resto. Valori tecnici del protocollo, non
 * testi per l'utente: nessuna label tradotta.
 */
enum OpenGraphType: string
{
    case Website = 'website';
    case Article = 'article';
}
