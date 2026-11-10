<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Animal;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Translation\Translator;

/**
 * Costruisce il {@see SeoData} di ogni pagina pubblica. I componenti Livewire lo
 * chiedono qui nel `render()` e lo passano al layout: nessuna regola SEO nei componenti.
 *
 * Regole trasversali:
 *  - canonical sempre generato dalla route (mai dall'URL della richiesta): parametri di
 *    tracciamento (utm_*, fbclid, …) e filtri non finiscono mai nel canonical. L'unico
 *    parametro conservato è `page` > 1 nelle liste senza filtri, perché ogni pagina ha
 *    contenuti propri;
 *  - catalogo: i filtri di faccetta (specie/taglia/sesso) puntano al catalogo base, la
 *    ricerca libera (`q`) e i risultati vuoti sono `noindex` (le pagine di ricerca
 *    interna non vanno indicizzate, i link restano seguiti);
 *  - indice degli enti: con un filtro attivo (nome, regione, tipo) canonical all'indice
 *    base e `noindex`;
 *  - liste paginate (catalogo, enti, pagina ente): una pagina oltre l'ultima (`?page=999`)
 *    è vuota, quindi `noindex` (altrimenti sarebbe un soft 404 indicizzabile);
 *  - landing di ritorno da Stripe (grazie/annullo): `noindex`;
 *  - pagine di autenticazione (accesso, registrazione, password, verifica email): `noindex`
 *    e senza breadcrumb;
 *  - ogni pagina con breadcrumb aggiunge il proprio `BreadcrumbList` al JSON-LD.
 */
final readonly class PublicPageSeo
{
    /**
     * La lingua viene dal Translator (impostata da SetLocale prima del render): una sola
     * fonte per titoli, `inLanguage` e `og:locale`.
     */
    public function __construct(
        private SchemaOrg $schema,
        private SeoImage $images,
        private Translator $translator,
    ) {}

    public function home(): SeoData
    {
        return $this->make(
            title: __('public.titles.home'),
            description: __('public.meta.default_description'),
            canonical: route('home'),
            jsonLd: [$this->schema->website($this->locale()), $this->schema->siteOrganization()],
        );
    }

    /**
     * @param  string  $search  testo della ricerca libera (`q`)
     * @param  bool  $hasFacetFilters  specie, taglia o sesso attivi
     * @param  int  $page  pagina corrente della paginazione
     * @param  int  $total  risultati totali della ricerca
     * @param  int  $lastPage  ultima pagina con risultati
     */
    public function catalog(string $search, bool $hasFacetFilters, int $page, int $total, int $lastPage): SeoData
    {
        $isFiltered = trim($search) !== '' || $hasFacetFilters;
        $isPaged = ! $isFiltered && $page > 1;

        $canonical = $isPaged ? route('catalog', ['page' => $page]) : route('catalog');
        $title = $isPaged ? __('seo.titles.catalog_paged', ['page' => $page]) : __('public.titles.catalog');
        $description = __('seo.descriptions.catalog');

        return $this->make(
            title: $title,
            description: $description,
            canonical: $canonical,
            noindex: trim($search) !== '' || $total === 0 || self::isPastLastPage($page, $lastPage),
            breadcrumbs: [$this->homeCrumb(), new Breadcrumb(__('public.nav.catalog'), route('catalog'))],
            jsonLd: [$this->schema->collectionPage($title, $canonical, $description, $this->locale())],
        );
    }

    /**
     * @param  bool  $isFiltered  ricerca per nome, regione o tipo attivi: una pagina di
     *                            ricerca interna, fuori dall'indice (come il catalogo filtrato)
     */
    public function organizations(int $page, int $lastPage, bool $isFiltered = false): SeoData
    {
        $isPaged = ! $isFiltered && $page > 1;

        $canonical = $isPaged ? route('organizations.index', ['page' => $page]) : route('organizations.index');
        $title = $isPaged ? __('seo.titles.organizations_paged', ['page' => $page]) : __('public.titles.organizations');
        $description = __('seo.descriptions.organizations');

        return $this->make(
            title: $title,
            description: $description,
            canonical: $canonical,
            noindex: $isFiltered || self::isPastLastPage($page, $lastPage),
            breadcrumbs: [$this->homeCrumb(), $this->organizationsCrumb()],
            jsonLd: [$this->schema->collectionPage($title, $canonical, $description, $this->locale())],
        );
    }

    public function animal(Animal $animal): SeoData
    {
        $locale = $this->locale();
        $canonical = route('animals.show', $animal);
        $image = $this->images->forAnimal($animal);

        $description = SeoText::excerpt($this->stringOrNull($animal->getTranslation('description', $locale, false)))
            ?? __('seo.descriptions.animal', [
                'name' => $animal->name,
                'species' => $animal->species->getLabel(),
                'organization' => $animal->organization->name,
            ]);

        return $this->make(
            title: __('seo.titles.animal', ['name' => $animal->name]),
            description: $description,
            canonical: $canonical,
            image: $image,
            imageAlt: $image !== null ? __('public.animal.photo_alt', ['name' => $animal->name]) : null,
            type: OpenGraphType::Article,
            breadcrumbs: [
                $this->homeCrumb(),
                new Breadcrumb(__('public.nav.catalog'), route('catalog')),
                new Breadcrumb($animal->name, $canonical),
            ],
            jsonLd: [$this->schema->animalPage($animal, $canonical, $description, $image, $locale)],
            modifiedAt: $animal->updated_at,
        );
    }

    public function organization(Organization $organization, int $page, int $lastPage): SeoData
    {
        $locale = $this->locale();
        $base = route('organizations.show', $organization);
        $isPaged = $page > 1;

        $description = SeoText::excerpt($this->stringOrNull($organization->getTranslation('description', $locale, false)))
            ?? __('seo.descriptions.organization', [
                'name' => $organization->name,
                'type' => $organization->type->getLabel(),
            ]);

        return $this->make(
            title: $isPaged
                ? __('seo.titles.organization_paged', ['name' => $organization->name, 'page' => $page])
                : __('seo.titles.organization', ['name' => $organization->name]),
            description: $description,
            canonical: $isPaged ? route('organizations.show', [$organization, 'page' => $page]) : $base,
            noindex: self::isPastLastPage($page, $lastPage),
            breadcrumbs: [
                $this->homeCrumb(),
                $this->organizationsCrumb(),
                new Breadcrumb($organization->name, $base),
            ],
            jsonLd: [$this->schema->organization($organization, $locale)],
        );
    }

    public function donationThanks(Organization $organization): SeoData
    {
        return $this->donationLanding(
            $organization,
            heading: __('donations.public.thanks_title'),
            description: __('seo.descriptions.donation_thanks', ['name' => $organization->name]),
            url: route('organizations.donate.thanks', $organization),
        );
    }

    public function donationCancelled(Organization $organization): SeoData
    {
        return $this->donationLanding(
            $organization,
            heading: __('donations.public.cancel_title'),
            description: __('seo.descriptions.donation_cancelled', ['name' => $organization->name]),
            url: route('organizations.donate.cancel', $organization),
        );
    }

    /**
     * Pagine legali pubbliche (/privacy, /termini): indicizzabili, con breadcrumb dalla home.
     * Il canonical arriva dalla route; il titolo e la descrizione dai file lang della pagina.
     *
     * @param  string  $titleKey  chiave del titolo completo (es. `legal_privacy.meta.title`)
     * @param  string  $descriptionKey  chiave della meta description
     * @param  string  $breadcrumbKey  chiave dell'etichetta breve per il breadcrumb
     */
    public function legal(string $titleKey, string $descriptionKey, string $breadcrumbKey, string $canonical): SeoData
    {
        return $this->make(
            title: __($titleKey),
            description: __($descriptionKey),
            canonical: $canonical,
            breadcrumbs: [$this->homeCrumb(), new Breadcrumb(__($breadcrumbKey), $canonical)],
        );
    }

    /**
     * Pagine di autenticazione del sito pubblico (/accedi, /registrati, …): fuori
     * dall'indice, senza breadcrumb. Il canonical è sempre un URL della route senza dati
     * personali o segreti (la pagina di reimpostazione, che ha il token nell'URL, passa
     * quello della richiesta del link).
     */
    public function auth(string $heading, string $description, string $canonical): SeoData
    {
        return $this->make(
            title: __('seo.titles.page', ['title' => $heading]),
            description: $description,
            canonical: $canonical,
            noindex: true,
        );
    }

    /**
     * Landing di ritorno dal Checkout: utile solo a chi ha appena donato, quindi fuori
     * dall'indice.
     */
    private function donationLanding(Organization $organization, string $heading, string $description, string $url): SeoData
    {
        return $this->make(
            title: __('seo.titles.page', ['title' => $heading]),
            description: $description,
            canonical: $url,
            noindex: true,
            breadcrumbs: [
                $this->homeCrumb(),
                $this->organizationsCrumb(),
                new Breadcrumb($organization->name, route('organizations.show', $organization)),
                new Breadcrumb($heading, $url),
            ],
        );
    }

    /**
     * @param  list<Breadcrumb>  $breadcrumbs
     * @param  list<array<string, mixed>>  $jsonLd
     */
    private function make(
        string $title,
        string $description,
        string $canonical,
        ?string $image = null,
        ?string $imageAlt = null,
        OpenGraphType $type = OpenGraphType::Website,
        bool $noindex = false,
        array $breadcrumbs = [],
        array $jsonLd = [],
        ?CarbonInterface $modifiedAt = null,
    ): SeoData {
        if ($breadcrumbs !== []) {
            $jsonLd[] = $this->schema->breadcrumbList($breadcrumbs);
        }

        return new SeoData(
            title: $title,
            description: $description,
            canonical: $canonical,
            locale: $this->locale(),
            image: $image,
            imageAlt: $imageAlt,
            type: $type,
            noindex: $noindex,
            breadcrumbs: $breadcrumbs,
            jsonLd: $jsonLd,
            modifiedAt: $modifiedAt,
        );
    }

    private function locale(): string
    {
        return $this->translator->getLocale();
    }

    /**
     * Una pagina oltre l'ultima della lista (es. `?page=999`): vuota. La prima pagina non
     * lo è mai, anche senza elementi (è la pagina base della lista).
     */
    private static function isPastLastPage(int $page, int $lastPage): bool
    {
        return $page > max(1, $lastPage);
    }

    private function homeCrumb(): Breadcrumb
    {
        return new Breadcrumb(__('seo.breadcrumb.home'), route('home'));
    }

    private function organizationsCrumb(): Breadcrumb
    {
        return new Breadcrumb(__('public.nav.organizations'), route('organizations.index'));
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
