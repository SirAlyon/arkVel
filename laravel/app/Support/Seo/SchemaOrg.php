<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\OrganizationType;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Site\WebUrl;

/**
 * Nodi schema.org (JSON-LD) del sito pubblico. Ogni metodo restituisce un nodo senza
 * `@context`: {@see SeoData::jsonLdScript()} li riunisce in un unico `@graph`, dove si
 * richiamano per `@id` (sito, ArkVel, ente).
 *
 * Solo dati reali e visibili sulla pagina: nessun campo inventato. I valori vuoti
 * vengono omessi invece di essere pubblicati come stringhe vuote.
 */
final class SchemaOrg
{
    /**
     * Il sito. Nessuna `SearchAction` (sitelinks search box): Google l'ha ritirata nel
     * novembre 2024 e non la usa più; il markup sarebbe solo peso.
     *
     * @return array<string, mixed>
     */
    public function website(string $locale): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::websiteId(),
            'url' => route('home'),
            'name' => __('seo.site_name'),
            'description' => __('seo.descriptions.site'),
            'inLanguage' => $locale,
            'publisher' => ['@id' => self::siteOrganizationId()],
        ];
    }

    /**
     * ArkVel come organizzazione che pubblica il sito. Nessun `logo`: il progetto non
     * ha ancora un file di logo pubblico (lo aggiungerà la shell/brand).
     *
     * @return array<string, mixed>
     */
    public function siteOrganization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => self::siteOrganizationId(),
            'name' => __('seo.site_name'),
            'url' => route('home'),
            'description' => __('seo.descriptions.site'),
        ];
    }

    /**
     * Pagina di elenco (catalogo, elenco enti).
     *
     * @return array<string, mixed>
     */
    public function collectionPage(string $name, string $url, string $description, string $locale): array
    {
        return [
            '@type' => 'CollectionPage',
            '@id' => $url,
            'url' => $url,
            'name' => $name,
            'description' => $description,
            'inLanguage' => $locale,
            'isPartOf' => ['@id' => self::websiteId()],
        ];
    }

    /**
     * @param  list<Breadcrumb>  $breadcrumbs
     * @return array<string, mixed>
     */
    public function breadcrumbList(array $breadcrumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (Breadcrumb $breadcrumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $breadcrumb->name,
                    'item' => $breadcrumb->url,
                ],
                $breadcrumbs,
                array_keys($breadcrumbs),
            ),
        ];
    }

    /**
     * L'ente, col tipo schema.org più specifico onesto ({@see self::organizationType()}).
     *
     * Indirizzo APPROSSIMATO di proposito (privacy, piano UI §4): solo città, regione e
     * paese, mai via, CAP o coordinate. Email, telefono e sito sono quelli che l'ente
     * pubblica già sulla sua pagina. Nessun `logo`: gli enti non hanno ancora un logo
     * (medialibrary) — da aggiungere quando esisterà.
     *
     * @return array<string, mixed>
     */
    public function organization(Organization $organization, string $locale): array
    {
        $url = route('organizations.show', $organization);
        $website = self::webUrl($organization->website);

        return self::withoutEmpty([
            '@type' => self::organizationType($organization->type),
            '@id' => self::organizationId($organization),
            'name' => $organization->name,
            'url' => $url,
            'description' => SeoText::excerpt(self::translated($organization->getTranslation('description', $locale, false)), 500),
            'address' => self::withoutEmpty([
                '@type' => 'PostalAddress',
                'addressLocality' => $organization->city,
                'addressRegion' => $organization->region,
                'addressCountry' => $organization->country,
            ], ignore: ['@type']),
            'email' => $organization->email,
            'telephone' => $organization->phone,
            'sameAs' => $website !== null ? [$website] : [],
        ]);
    }

    /**
     * La scheda di un animale.
     *
     * schema.org non ha un tipo "animale in adozione" (né `Animal`/`Pet`). Le
     * alternative scartate:
     *  - `Product` (+ `Offer`): un animale in adozione non è una merce; senza offerte né
     *    recensioni Google lo segnala come Product incompleto, e un'offerta con prezzo
     *    sarebbe un dato inventato (l'eventuale contributo d'adozione non è un prezzo);
     *  - `Thing` + `additionalProperty`: la proprietà non esiste su Thing (solo su
     *    Product/Place/…), quindi markup non valido.
     * Scelta: un `ItemPage` (la pagina che descrive UN elemento) il cui `mainEntity` è un
     * `Thing` con nome, descrizione e foto reali, e la cui `sourceOrganization` è l'ente
     * che ha pubblicato la scheda. Onesto, valido e senza dati inventati.
     *
     * @return array<string, mixed>
     */
    public function animalPage(Animal $animal, string $canonical, string $description, ?string $image, string $locale): array
    {
        $organization = $animal->organization;

        return self::withoutEmpty([
            '@type' => 'ItemPage',
            '@id' => $canonical,
            'url' => $canonical,
            'name' => $animal->name,
            'description' => $description,
            'inLanguage' => $locale,
            'isPartOf' => ['@id' => self::websiteId()],
            'dateModified' => $animal->updated_at?->toIso8601String(),
            'primaryImageOfPage' => $image !== null ? ['@type' => 'ImageObject', 'url' => $image] : null,
            'mainEntity' => self::withoutEmpty([
                '@type' => 'Thing',
                'name' => $animal->name,
                'description' => SeoText::excerpt(self::translated($animal->getTranslation('description', $locale, false)), 500),
                'image' => $image,
            ], ignore: ['@type']),
            'sourceOrganization' => [
                '@type' => self::organizationType($organization->type),
                '@id' => self::organizationId($organization),
                'name' => $organization->name,
                'url' => route('organizations.show', $organization),
            ],
        ]);
    }

    /**
     * Tipo schema.org dell'ente. `AnimalShelter` esiste (LocalBusiness › AnimalShelter)
     * ed è esatto per canili e rifugi; un'associazione è un ente non profit (`NGO`);
     * per una società non c'è un sottotipo più preciso che sia certamente vero, quindi
     * resta `Organization`.
     */
    public static function organizationType(OrganizationType $type): string
    {
        return match ($type) {
            OrganizationType::CanileComunale,
            OrganizationType::CanilePrivato,
            OrganizationType::Rifugio => 'AnimalShelter',
            OrganizationType::Associazione => 'NGO',
            OrganizationType::Societa => 'Organization',
        };
    }

    public static function websiteId(): string
    {
        return route('home').'#website';
    }

    public static function siteOrganizationId(): string
    {
        return route('home').'#organization';
    }

    public static function organizationId(Organization $organization): string
    {
        return route('organizations.show', $organization).'#organization';
    }

    /**
     * Il sito dell'ente per `sameAs`, solo se è un URL http(s) valido: niente
     * `javascript:`, `mailto:`, `ftp:` o altri schemi, che non identificano una pagina web.
     */
    private static function webUrl(mixed $value): ?string
    {
        return WebUrl::safe($value);
    }

    private static function translated(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Toglie null, stringhe vuote, array vuoti e nodi rimasti senza dati (un nodo con
     * solo `@type` non dice nulla).
     *
     * @param  array<string, mixed>  $node
     * @param  list<string>  $ignore  chiavi che da sole non bastano a tenere il nodo
     * @return array<string, mixed>
     */
    private static function withoutEmpty(array $node, array $ignore = []): array
    {
        $filtered = array_filter(
            $node,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
        );

        return array_diff_key($filtered, array_flip($ignore)) === [] ? [] : $filtered;
    }
}
