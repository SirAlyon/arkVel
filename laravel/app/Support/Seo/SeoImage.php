<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Animal;
use Illuminate\Contracts\Routing\UrlGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Immagine di condivisione (og:image, twitter:image, JSON-LD) di una pagina.
 *
 * PUNTO UNICO da aggiornare in UI-4: {@see self::CONVERSIONS} elenca le conversioni
 * medialibrary in ordine di preferenza e vince la prima GIÀ GENERATA. Oggi c'è solo
 * `thumb` (400×400 contain, sincrona, la stessa della copertina della scheda); UI-4
 * aggiungerà `og` (1200×630) in testa. Poiché `og` sarà in coda, finché il worker non
 * l'ha prodotta si ripiega su `thumb` invece di pubblicare un URL che non esiste.
 *
 * L'URL pubblicato è sempre un URI assoluto valido: i nomi dei file possono contenere
 * caratteri non ASCII (es. «perché.jpg»), che il disco non codifica; qui vengono
 * percent-encoded (UTF-8), e un URL che resta comunque non valido viene scartato (la
 * pagina esce senza immagine di condivisione invece di fallire).
 */
final readonly class SeoImage
{
    /**
     * @var list<string>
     */
    public const array CONVERSIONS = ['thumb'];

    public function __construct(
        private UrlGenerator $url,
    ) {}

    /**
     * URL assoluto della prima foto dell'animale (o del suo avatar), null se non ne ha.
     */
    public function forAnimal(Animal $animal): ?string
    {
        $media = $animal->getFirstMedia('photos') ?? $animal->getFirstMedia('avatar');

        return $media instanceof Media ? $this->bestUrl($media) : null;
    }

    private function bestUrl(Media $media): ?string
    {
        foreach (self::CONVERSIONS as $conversion) {
            if ($media->hasGeneratedConversion($conversion)) {
                return self::absoluteUri($this->url->to($media->getUrl($conversion)));
            }
        }

        return null;
    }

    /**
     * Codifica i byte fuori dall'ASCII stampabile (spazi e caratteri UTF-8) e restituisce
     * l'URL solo se è un URI assoluto valido.
     */
    public static function absoluteUri(string $url): ?string
    {
        $encoded = (string) preg_replace_callback(
            '/[^\x21-\x7E]/',
            static fn (array $byte): string => rawurlencode($byte[0]),
            $url,
        );

        return filter_var($encoded, FILTER_VALIDATE_URL) !== false ? $encoded : null;
    }
}
