<?php

declare(strict_types=1);

namespace App\Support\Seo\Sitemap;

use App\Models\Animal;
use App\Models\Organization;
use App\Support\Seo\CanonicalUrl;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le pagine pubbliche indicizzabili, per la sitemap.
 *
 * La visibilità usa ESATTAMENTE gli stessi confini delle pagine pubbliche, così la
 * sitemap non può elencare ciò che la pagina negherebbe con un 404:
 *  - animali: {@see Animal::publiclyVisible()} (Available + ente Verified; i soft-deleted
 *    sono già esclusi dallo scope globale), lo stesso di AnimalDetail/AnimalCatalog;
 *  - enti: {@see Organization::verified()}, lo stesso predicato di OrganizationPage
 *    (`status === Verified`).
 * Le landing di donazione (noindex) e le pagine filtrate del catalogo restano fuori.
 *
 * URL sul dominio canonico ({@see CanonicalUrl}), mai sull'host della richiesta: la
 * sitemap è in cache per tutti (SitemapController) e non deve poter essere avvelenata.
 *
 * Letture a blocchi (`lazyById`): la memoria resta costante al crescere del catalogo.
 * Oltre 50.000 URL servirà un indice di sitemap (limite del protocollo).
 */
final readonly class PublicSitemap
{
    private const int CHUNK = 500;

    public function __construct(private CanonicalUrl $url) {}

    /**
     * @return Generator<int, SitemapUrl>
     */
    public function urls(): Generator
    {
        yield new SitemapUrl($this->url->route('home'));
        yield new SitemapUrl($this->url->route('catalog'));
        yield new SitemapUrl($this->url->route('organizations.index'));

        $organizations = Organization::query()
            ->verified()
            ->select(['id', 'slug', 'updated_at'])
            ->withMax(['animals as latest_animal_update' => function (Builder $query): void {
                /** @var Builder<Animal> $query */
                $query->publiclyVisible();
            }], 'updated_at');

        foreach ($organizations->lazyById(self::CHUNK) as $organization) {
            /** @var Organization $organization */
            yield new SitemapUrl(
                $this->url->route('organizations.show', $organization),
                $this->latest($organization->updated_at, $organization->getAttribute('latest_animal_update')),
            );
        }

        $animals = Animal::query()
            ->publiclyVisible()
            ->select(['id', 'updated_at']);

        foreach ($animals->lazyById(self::CHUNK) as $animal) {
            /** @var Animal $animal */
            yield new SitemapUrl($this->url->route('animals.show', $animal), $animal->updated_at);
        }
    }

    /**
     * La pagina di un ente cambia con l'ente e con i suoi animali visibili.
     */
    private function latest(?CarbonInterface $organizationUpdate, mixed $latestAnimalUpdate): ?CarbonInterface
    {
        $animalUpdate = is_string($latestAnimalUpdate) ? CarbonImmutable::parse($latestAnimalUpdate) : null;

        if ($organizationUpdate === null || $animalUpdate === null) {
            return $organizationUpdate ?? $animalUpdate;
        }

        return $animalUpdate->greaterThan($organizationUpdate) ? $animalUpdate : $organizationUpdate;
    }
}
