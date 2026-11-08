<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;

/**
 * URL del font del testo da precaricare nella shell pubblica (<link rel="preload">).
 *
 * È il subset latin di Bricolage Grotesque Variable (Fontsource, asse opsz), lo stesso file
 * che app.css dichiara per primo nell'@font-face: precaricarlo evita il CLS dello swap del
 * font. L'URL viene dal manifest di Vite (nome con hash); senza build (o con un asset
 * mancante) il preload si salta, la pagina resta valida.
 */
final readonly class FontPreload
{
    public const string ASSET = 'node_modules/@fontsource-variable/bricolage-grotesque/files/bricolage-grotesque-latin-opsz-normal.woff2';

    public function __construct(private Vite $vite) {}

    public function url(): ?string
    {
        try {
            $url = $this->vite->asset(self::ASSET);
        } catch (ViteException) {
            return null;
        }

        return $url !== '' ? $url : null;
    }
}
