<?php

declare(strict_types=1);

use App\Enums\CatalogFacet;
use App\Enums\OrganizationStatus;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Catalog\CatalogFilters;
use App\Support\Catalog\Contracts\CountsCatalogFacets;
use App\Support\Catalog\DatabaseFacetCounter;
use App\Support\Catalog\MeilisearchFacetCounter;
use App\Support\Catalog\VerifiedOrganizationIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;

/*
 | Conteggi delle faccette del catalogo (UI-6b): l'implementazione su database (driver
 | Scout collection dei test, database, null) e quella su Meilisearch (client finto: si
 | verificano le query multi-search e la lettura della facet distribution), più il binding.
 */

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function countedAnimal(Organization $organization, array $attributes = []): Animal
{
    return Animal::factory()->available()->forOrganization($organization)->create([
        'species' => Species::Dog,
        'sex' => Sex::Male,
        'size' => Size::Medium,
        ...$attributes,
    ]);
}

describe('database counter', function (): void {
    it('counts visible animals per value of every facet', function (): void {
        $lazio = Organization::factory()->verified()->create(['region' => 'Lazio']);
        $veneto = Organization::factory()->verified()->create(['region' => 'Veneto']);

        countedAnimal($lazio, ['species' => Species::Dog, 'sex' => Sex::Male, 'size' => Size::Large]);
        countedAnimal($lazio, ['species' => Species::Dog, 'sex' => Sex::Female, 'size' => null]);
        countedAnimal($veneto, ['species' => Species::Cat, 'sex' => Sex::Female, 'size' => Size::Small]);

        $counts = (new DatabaseFacetCounter)->count(new CatalogFilters);

        expect($counts->for(CatalogFacet::Species))->toEqual(['dog' => 2, 'cat' => 1])
            ->and($counts->for(CatalogFacet::Sex))->toEqual(['male' => 1, 'female' => 2])
            ->and($counts->for(CatalogFacet::Size))->toEqual(['large' => 1, 'small' => 1])
            ->and($counts->for(CatalogFacet::Region))->toEqual(['Lazio' => 2, 'Veneto' => 1])
            ->and($counts->for(CatalogFacet::Organization))->toEqual([(string) $lazio->id => 2, (string) $veneto->id => 1])
            ->and($counts->count(CatalogFacet::Species, 'other'))->toBe(0);
    });

    it('never counts drafts, adopted animals or animals of unverified organizations', function (): void {
        $verified = Organization::factory()->verified()->create(['region' => 'Lazio']);
        $pending = Organization::factory()->pending()->create(['region' => 'Molise']);
        $suspended = Organization::factory()->suspended()->create(['region' => 'Molise']);

        countedAnimal($verified);
        Animal::factory()->draft()->forOrganization($verified)->create(['species' => Species::Cat]);
        Animal::factory()->adopted()->forOrganization($verified)->create(['species' => Species::Cat]);
        countedAnimal($pending, ['species' => Species::Cat]);
        countedAnimal($suspended, ['species' => Species::Cat]);

        $counts = (new DatabaseFacetCounter)->count(new CatalogFilters);

        expect($counts->for(CatalogFacet::Species))->toEqual(['dog' => 1])
            ->and($counts->for(CatalogFacet::Region))->toEqual(['Lazio' => 1])
            ->and($counts->for(CatalogFacet::Organization))->toEqual([(string) $verified->id => 1]);
    });

    it('counts each facet ignoring its own choice but honouring the others', function (): void {
        $organization = Organization::factory()->verified()->create();

        countedAnimal($organization, ['species' => Species::Dog, 'sex' => Sex::Male]);
        countedAnimal($organization, ['species' => Species::Dog, 'sex' => Sex::Female]);
        countedAnimal($organization, ['species' => Species::Cat, 'sex' => Sex::Male]);

        $counts = (new DatabaseFacetCounter)->count(new CatalogFilters(species: Species::Dog));

        expect($counts->for(CatalogFacet::Species))->toEqual(['dog' => 2, 'cat' => 1])
            ->and($counts->for(CatalogFacet::Sex))->toEqual(['male' => 1, 'female' => 1]);
    });

    it('follows the free search of the results', function (): void {
        $organization = Organization::factory()->verified()->create();

        countedAnimal($organization, ['name' => 'Argo', 'species' => Species::Dog]);
        countedAnimal($organization, ['name' => 'Micia', 'species' => Species::Cat]);

        $counts = (new DatabaseFacetCounter)->count(new CatalogFilters(search: 'argo'));

        expect($counts->for(CatalogFacet::Species))->toEqual(['dog' => 1]);
    });

    it('counts nothing for an impossible organization and region pair', function (): void {
        $lazio = Organization::factory()->verified()->create(['region' => 'Lazio']);
        $veneto = Organization::factory()->verified()->create(['region' => 'Veneto']);
        countedAnimal($lazio);
        countedAnimal($veneto);

        $counts = (new DatabaseFacetCounter)->count(new CatalogFilters(
            region: 'Lazio',
            regionOrganizationIds: [$lazio->id],
            organization: $veneto,
        ));

        expect($counts->for(CatalogFacet::Species))->toBe([])
            // Ogni faccetta ignora la propria scelta: le regioni contano l'ente scelto, gli
            // enti la regione scelta.
            ->and($counts->for(CatalogFacet::Region))->toEqual(['Veneto' => 1])
            ->and($counts->for(CatalogFacet::Organization))->toEqual([(string) $lazio->id => 1]);
    });
});

describe('meilisearch counter', function (): void {
    it('asks once for the inactive facets and once per active facet', function (): void {
        $organization = Organization::factory()->verified()->create();
        $visible = 'organization_id IN ['.$organization->id.']';
        $client = Mockery::mock(Client::class);

        $client->shouldReceive('multiSearch')->once()->withArgs(function (array $queries) use ($visible): bool {
            $payload = array_map(static fn (SearchQuery $query): array => $query->toArray(), $queries);

            expect($payload)->toHaveCount(3)
                ->and($payload[0])->toMatchArray([
                    'indexUid' => 'animals',
                    'q' => 'rex',
                    'filter' => ['species IN ["dog"]', 'organization_id IN [4, 9]', $visible],
                    'facets' => ['sex', 'size', 'organization_id'],
                    'limit' => 0,
                ])
                ->and($payload[1])->toMatchArray(['filter' => ['organization_id IN [4, 9]', $visible], 'facets' => ['species']])
                ->and($payload[2])->toMatchArray(['filter' => ['species IN ["dog"]', $visible], 'facets' => ['region']]);

            return true;
        })->andReturn(['results' => [
            ['facetDistribution' => ['sex' => ['male' => 2, 'female' => 1], 'size' => ['large' => 3], 'organization_id' => ['4' => 3]]],
            ['facetDistribution' => ['species' => ['dog' => 3, 'cat' => 5]]],
            ['facetDistribution' => ['region' => ['Lazio' => 3, 'Veneto' => 0]]],
        ]]);

        $counts = (new MeilisearchFacetCounter($client, new VerifiedOrganizationIds))->count(new CatalogFilters(
            search: 'rex',
            species: Species::Dog,
            region: 'Lazio',
            regionOrganizationIds: [4, 9],
        ));

        expect($counts->for(CatalogFacet::Sex))->toBe(['male' => 2, 'female' => 1])
            ->and($counts->for(CatalogFacet::Size))->toBe(['large' => 3])
            ->and($counts->for(CatalogFacet::Organization))->toBe(['4' => 3])
            ->and($counts->for(CatalogFacet::Species))->toBe(['dog' => 3, 'cat' => 5])
            ->and($counts->for(CatalogFacet::Region))->toBe(['Lazio' => 3])
            ->and($organization->exists)->toBeTrue();
    });

    it('needs a single query without filters, still limited to verified organizations', function (): void {
        $first = Organization::factory()->verified()->create();
        $second = Organization::factory()->verified()->create();
        Organization::factory()->suspended()->create();
        $client = Mockery::mock(Client::class);

        $client->shouldReceive('multiSearch')->once()->withArgs(function (array $queries) use ($first, $second): bool {
            expect($queries)->toHaveCount(1)
                ->and($queries[0]->toArray())->toMatchArray([
                    'q' => '',
                    'facets' => ['species', 'sex', 'size', 'region', 'organization_id'],
                    'filter' => ['organization_id IN ['.$first->id.', '.$second->id.']'],
                ]);

            return true;
        })->andReturn(['results' => [[]]]);

        $counts = (new MeilisearchFacetCounter($client, new VerifiedOrganizationIds))->count(new CatalogFilters);

        expect($counts->for(CatalogFacet::Species))->toBe([]);
    });

    it('does not query an impossible combination', function (): void {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('multiSearch')->once()->withArgs(function (array $queries): bool {
            // Solo le query delle faccette attive (ente e regione), ognuna senza la propria scelta.
            expect($queries)->toHaveCount(2);

            return true;
        })->andReturn(['results' => [
            ['facetDistribution' => ['region' => ['Lazio' => 1]]],
            ['facetDistribution' => ['organization_id' => ['4' => 1]]],
        ]]);

        $organization = Organization::factory()->verified()->create();

        $counts = (new MeilisearchFacetCounter($client, new VerifiedOrganizationIds))->count(new CatalogFilters(
            region: 'Lazio',
            regionOrganizationIds: [4],
            organization: $organization,
        ));

        expect($counts->for(CatalogFacet::Species))->toBe([])
            ->and($counts->for(CatalogFacet::Region))->toBe(['Lazio' => 1])
            ->and($counts->for(CatalogFacet::Organization))->toBe(['4' => 1]);
    });

    it('builds the filters in the format of the Scout engine', function (): void {
        $organization = Organization::factory()->verified()->create();
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('multiSearch')->once()->withArgs(function (array $queries) use ($organization): bool {
            expect($queries[0]->toArray()['filter'])->toBe(['sex IN ["female"]', 'size IN ["small"]', 'organization_id IN ['.$organization->id.']']);

            return true;
        })->andReturn(['results' => []]);

        (new MeilisearchFacetCounter($client, new VerifiedOrganizationIds))->count(new CatalogFilters(sex: Sex::Female, size: Size::Small));
    });

    it('never counts the animals of a suspended organization still in the index', function (): void {
        $verified = Organization::factory()->verified()->create();
        $suspended = Organization::factory()->verified()->create();
        countedAnimal($verified);
        countedAnimal($suspended, ['species' => Species::Cat]);

        // Lo stato cambia senza toccare gli animali (es. da un comando): il filtro di
        // visibilità esclude l'ente anche se i suoi documenti fossero ancora nell'indice.
        Organization::withoutEvents(fn () => $suspended->update(['status' => OrganizationStatus::Suspended]));

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('multiSearch')->once()->withArgs(function (array $queries) use ($verified): bool {
            foreach ($queries as $query) {
                expect($query->toArray()['filter'])->toContain('organization_id IN ['.$verified->id.']');
            }

            return true;
        })->andReturn(['results' => [['facetDistribution' => ['species' => ['dog' => 1]]]]]);

        $counts = (new MeilisearchFacetCounter($client, new VerifiedOrganizationIds))->count(new CatalogFilters);

        expect($counts->for(CatalogFacet::Species))->toBe(['dog' => 1]);
    });

    it('does not ask the engine when no organization is verified', function (): void {
        Organization::factory()->suspended()->create();
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('multiSearch');

        $counts = (new MeilisearchFacetCounter($client, new VerifiedOrganizationIds))->count(new CatalogFilters(species: Species::Dog));

        expect($counts->for(CatalogFacet::Species))->toBe([])
            ->and($counts->for(CatalogFacet::Region))->toBe([]);
    });

    it('looks the verified organizations up once per request', function (): void {
        Organization::factory()->verified()->create();
        $ids = app(VerifiedOrganizationIds::class);

        DB::enableQueryLog();
        $ids->all();
        $ids->all();
        app(VerifiedOrganizationIds::class)->all();

        expect(DB::getQueryLog())->toHaveCount(1);
    });
});

it('binds the counter of the configured search engine', function (): void {
    config(['scout.driver' => 'collection']);
    expect(app(CountsCatalogFacets::class))->toBeInstanceOf(DatabaseFacetCounter::class);

    config(['scout.driver' => 'meilisearch']);
    expect(app(CountsCatalogFacets::class))->toBeInstanceOf(MeilisearchFacetCounter::class);
});
