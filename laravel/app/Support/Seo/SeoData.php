<?php

declare(strict_types=1);

namespace App\Support\Seo;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Metadati SEO di una pagina pubblica, passati al layout come `$seo`
 * (`->layoutData(['seo' => …])`) e resi da `partials/seo/head` (meta, canonical,
 * Open Graph, Twitter) e `partials/seo/breadcrumbs` (percorso visibile + JSON-LD).
 *
 * Value object immutabile: lo costruisce {@see PublicPageSeo}, i componenti Livewire
 * si limitano a passarlo. URL sempre assoluti (canonical, immagine, breadcrumb).
 *
 * Niente hreflang: la lingua vive in sessione e IT/EN condividono lo stesso URL, quindi
 * non esistono URL alternativi per lingua da dichiarare (un hreflang che punta a sé
 * stesso per entrambe le lingue sarebbe falso). `og:locale` riflette la lingua con cui
 * la pagina è stata resa.
 */
final readonly class SeoData
{
    /**
     * @param  list<Breadcrumb>  $breadcrumbs  percorso dalla home alla pagina corrente (vuoto sulla home)
     * @param  list<array<string, mixed>>  $jsonLd  nodi schema.org (senza `@context`), riuniti in un unico `@graph`
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $locale,
        public ?string $image = null,
        public ?string $imageAlt = null,
        public OpenGraphType $type = OpenGraphType::Website,
        public bool $noindex = false,
        public array $breadcrumbs = [],
        public array $jsonLd = [],
        public ?CarbonInterface $modifiedAt = null,
    ) {
        foreach ([$canonical, $image] as $url) {
            if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException("SEO URLs must be absolute: [{$url}].");
            }
        }
    }

    /**
     * Valore di `<meta name="robots">`, o null quando la pagina è indicizzabile (il
     * default dei motori, nessun meta necessario). `follow`: i link restano seguiti.
     */
    public function robots(): ?string
    {
        return $this->noindex ? 'noindex, follow' : null;
    }

    /**
     * Tipo di Twitter card: grande con un'immagine, compatta senza.
     */
    public function twitterCard(): string
    {
        return $this->image !== null ? 'summary_large_image' : 'summary';
    }

    public function openGraphLocale(): string
    {
        return OpenGraphLocale::for($this->locale);
    }

    public function hasBreadcrumbs(): bool
    {
        return $this->breadcrumbs !== [];
    }

    /**
     * Il documento JSON-LD (`@context` + `@graph`) già codificato in modo sicuro per un
     * `<script type="application/ld+json">`, o null se la pagina non ha dati strutturati.
     */
    public function jsonLdScript(): ?string
    {
        if ($this->jsonLd === []) {
            return null;
        }

        return JsonLd::encode([
            '@context' => 'https://schema.org',
            '@graph' => $this->jsonLd,
        ]);
    }
}
