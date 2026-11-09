<?php

declare(strict_types=1);

use App\Actions\Organizations\ListOrganizationDirectoryFacets;
use App\Actions\Organizations\ListPublicOrganizations;
use App\Actions\Organizations\SummarizeOrganizationAnimals;
use App\Actions\Public\CountCompletedAdoptions;
use App\Data\Organizations\OrganizationDirectoryFilters;
use App\Enums\OrganizationType;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelData\Data;

uses(RefreshDatabase::class);

/*
 | Indice pubblico degli enti (UI-6d): filtri normalizzati, elenco dei soli enti verificati
 | con il conteggio degli animali visibili, opzioni dei filtri e numeri della pagina ente.
 */

describe('directory filters', function (): void {
    it('normalizes the raw input', function (): void {
        $filters = OrganizationDirectoryFilters::fromInput('  Faro  ', ' Liguria ', 'rifugio', ['Liguria', 'Toscana']);

        expect($filters)->toBeInstanceOf(Data::class)
            ->and($filters->search)->toBe('Faro')
            ->and($filters->region)->toBe('Liguria')
            ->and($filters->type)->toBe(OrganizationType::Rifugio)
            ->and($filters->isFiltered())->toBeTrue();
    });

    it('treats empty and unknown values as no filter', function (): void {
        $filters = OrganizationDirectoryFilters::fromInput('   ', '', 'non-esiste', ['Liguria']);

        expect($filters->search)->toBeNull()
            ->and($filters->region)->toBeNull()
            ->and($filters->type)->toBeNull()
            ->and($filters->isFiltered())->toBeFalse();
    });

    it('caps the search length', function (): void {
        $filters = OrganizationDirectoryFilters::fromInput(str_repeat('è', 150), '', '', []);

        expect(mb_strlen((string) $filters->search))->toBe(OrganizationDirectoryFilters::MAX_SEARCH_LENGTH);
    });

    it('ignores a region that no verified organization has', function (): void {
        $filters = OrganizationDirectoryFilters::fromInput('', 'Atlantide', '', ['Liguria']);

        expect($filters->region)->toBeNull()->and($filters->isFiltered())->toBeFalse();
    });

    it('writes the active filters with the same names as the catalog', function (): void {
        expect(OrganizationDirectoryFilters::fromInput('faro', 'Liguria', 'rifugio', ['Liguria'])->queryParameters())
            ->toBe(['q' => 'faro', 'region' => 'Liguria', 'type' => 'rifugio'])
            ->and((new OrganizationDirectoryFilters)->queryParameters())->toBe([]);
    });
});

describe('list public organizations', function (): void {
    it('lists only verified organizations, alphabetically', function (): void {
        Organization::factory()->verified()->create(['name' => 'Rifugio Zeta']);
        Organization::factory()->verified()->create(['name' => 'Canile Alfa']);
        Organization::factory()->pending()->create(['name' => 'Ente In Attesa']);
        Organization::factory()->suspended()->create(['name' => 'Ente Sospeso']);

        $names = app(ListPublicOrganizations::class)
            ->handle(new OrganizationDirectoryFilters)
            ->getCollection()
            ->pluck('name')
            ->all();

        expect($names)->toBe(['Canile Alfa', 'Rifugio Zeta']);
    });

    it('counts only the publicly visible animals', function (): void {
        $organization = Organization::factory()->verified()->create();
        Animal::factory()->available()->count(2)->forOrganization($organization)->create();
        Animal::factory()->draft()->forOrganization($organization)->create();
        Animal::factory()->adopted()->forOrganization($organization)->create();

        $listed = app(ListPublicOrganizations::class)->handle(new OrganizationDirectoryFilters)->getCollection()->sole();

        expect($listed->getAttribute('available_animals_count'))->toBe(2);
    });

    it('filters by name, case-insensitively', function (): void {
        Organization::factory()->verified()->create(['name' => 'Rifugio Il Faro']);
        Organization::factory()->verified()->create(['name' => 'Canile di Bari']);

        $names = app(ListPublicOrganizations::class)
            ->handle(OrganizationDirectoryFilters::fromInput('faro', '', '', []))
            ->getCollection()
            ->pluck('name')
            ->all();

        expect($names)->toBe(['Rifugio Il Faro']);
    });

    it('takes the LIKE wildcards of the search literally', function (string $search, array $expected): void {
        Organization::factory()->verified()->create(['name' => 'Rifugio 100% Amore']);
        Organization::factory()->verified()->create(['name' => 'Canile_Nord']);
        Organization::factory()->verified()->create(['name' => 'Canile Sud!']);

        $names = app(ListPublicOrganizations::class)
            ->handle(new OrganizationDirectoryFilters(search: $search))
            ->getCollection()
            ->pluck('name')
            ->all();

        expect($names)->toBe($expected);
    })->with([
        'percent' => ['%', ['Rifugio 100% Amore']],
        'underscore' => ['_', ['Canile_Nord']],
        'escape character' => ['!', ['Canile Sud!']],
        'plain text' => ['canile', ['Canile Sud!', 'Canile_Nord']],
    ]);

    it('filters by region and type together', function (): void {
        Organization::factory()->verified()->create(['name' => 'Rifugio Toscano', 'region' => 'Toscana', 'type' => OrganizationType::Rifugio]);
        Organization::factory()->verified()->create(['name' => 'Canile Toscano', 'region' => 'Toscana', 'type' => OrganizationType::CanileComunale]);
        Organization::factory()->verified()->create(['name' => 'Rifugio Lombardo', 'region' => 'Lombardia', 'type' => OrganizationType::Rifugio]);

        $names = app(ListPublicOrganizations::class)
            ->handle(new OrganizationDirectoryFilters(region: 'Toscana', type: OrganizationType::Rifugio))
            ->getCollection()
            ->pluck('name')
            ->all();

        expect($names)->toBe(['Rifugio Toscano']);
    });

    it('paginates by twelve', function (): void {
        Organization::factory()->verified()->count(13)->create();

        $page = app(ListPublicOrganizations::class)->handle(new OrganizationDirectoryFilters);

        expect($page->perPage())->toBe(ListPublicOrganizations::PER_PAGE)
            ->and($page->total())->toBe(13)
            ->and($page->lastPage())->toBe(2);
    });
});

describe('directory facets', function (): void {
    it('offers only the regions and types of verified organizations', function (): void {
        Organization::factory()->verified()->create(['region' => 'Toscana', 'type' => OrganizationType::Rifugio]);
        Organization::factory()->verified()->create(['region' => 'Abruzzo', 'type' => OrganizationType::Rifugio]);
        Organization::factory()->verified()->create(['region' => null, 'type' => OrganizationType::Associazione]);
        Organization::factory()->pending()->create(['region' => 'Sardegna', 'type' => OrganizationType::Societa]);

        $facets = app(ListOrganizationDirectoryFacets::class)->handle();

        expect($facets->regions)->toBe(['Abruzzo', 'Toscana'])
            ->and($facets->types)->toBe([OrganizationType::Rifugio, OrganizationType::Associazione]);
    });

    it('is empty without verified organizations', function (): void {
        Organization::factory()->pending()->create();

        $facets = app(ListOrganizationDirectoryFacets::class)->handle();

        expect($facets->regions)->toBe([])->and($facets->types)->toBe([]);
    });
});

describe('organization animal summary', function (): void {
    it('counts the animals up for adoption and the completed adoptions', function (): void {
        $organization = Organization::factory()->verified()->create();
        Animal::factory()->available()->count(3)->forOrganization($organization)->create();
        Animal::factory()->draft()->forOrganization($organization)->create();
        Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create();

        foreach (range(1, 2) as $ignored) {
            $adopted = Animal::factory()->adopted()->forOrganization($organization)->create();
            Adoption::factory()->forApplication(AdoptionApplication::factory()->adoptionPending()->forAnimal($adopted)->create())->create();
        }

        $summary = app(SummarizeOrganizationAnimals::class)->handle($organization);

        expect($summary->available)->toBe(3)->and($summary->adopted)->toBe(2);
    });

    it('counts the completed adoptions like the home page: adoption records, not the animal status', function (): void {
        $organization = Organization::factory()->verified()->create();
        // Segnato «adottato» a mano dall'ente, senza un'adozione sulla piattaforma.
        Animal::factory()->adopted()->forOrganization($organization)->create();
        $adopted = Animal::factory()->adopted()->forOrganization($organization)->create();
        Adoption::factory()->forApplication(AdoptionApplication::factory()->adoptionPending()->forAnimal($adopted)->create())->create();

        expect(app(SummarizeOrganizationAnimals::class)->handle($organization)->adopted)->toBe(1)
            ->and(app(CountCompletedAdoptions::class)->handle($organization))->toBe(1)
            ->and(app(CountCompletedAdoptions::class)->handle())->toBe(1);
    });
});
