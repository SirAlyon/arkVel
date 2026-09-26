<?php

declare(strict_types=1);

use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Livewire\AnimalCatalog;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

if (! function_exists('verifiedOrg')) {
    function verifiedOrg(): Organization
    {
        return Organization::factory()->verified()->create();
    }
}

it('renders the catalog page', function () {
    $this->get(route('catalog'))->assertOk()->assertSeeLivewire(AnimalCatalog::class);
});

it('lists available animals of verified organizations', function () {
    $org = verifiedOrg();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Pongo']);

    Livewire::test(AnimalCatalog::class)->assertSee('Pongo');
});

it('finds an animal by name via search', function () {
    $org = verifiedOrg();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Cerbero']);
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Fufi']);

    Livewire::test(AnimalCatalog::class)
        ->set('search', 'Cerbero')
        ->assertSee('Cerbero')
        ->assertDontSee('Fufi');
});

it('narrows results by species filter', function () {
    $org = verifiedOrg();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'CaneUno', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'GattoUno', 'species' => Species::Cat]);

    Livewire::test(AnimalCatalog::class)
        ->set('species', Species::Dog->value)
        ->assertSee('CaneUno')
        ->assertDontSee('GattoUno');
});

it('narrows results by size filter', function () {
    $org = verifiedOrg();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Grosso', 'size' => Size::Large]);
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Piccino', 'size' => Size::Small]);

    Livewire::test(AnimalCatalog::class)
        ->set('size', Size::Large->value)
        ->assertSee('Grosso')
        ->assertDontSee('Piccino');
});

it('narrows results by sex filter', function () {
    $org = verifiedOrg();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Maschietto', 'sex' => Sex::Male]);
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Femminuccia', 'sex' => Sex::Female]);

    Livewire::test(AnimalCatalog::class)
        ->set('sex', Sex::Male->value)
        ->assertSee('Maschietto')
        ->assertDontSee('Femminuccia');
});

it('never leaks draft animals or animals of unverified organizations', function () {
    $verified = verifiedOrg();
    $pending = Organization::factory()->pending()->create();

    Animal::factory()->available()->forOrganization($verified)->create(['name' => 'VisibileOk']);
    Animal::factory()->draft()->forOrganization($verified)->create(['name' => 'BozzaLeak']);
    Animal::factory()->available()->forOrganization($pending)->create(['name' => 'PendingLeak']);
    Animal::factory()->adopted()->forOrganization($verified)->create(['name' => 'AdottatoLeak']);

    Livewire::test(AnimalCatalog::class)
        ->assertSee('VisibileOk')
        ->assertDontSee('BozzaLeak')
        ->assertDontSee('PendingLeak')
        ->assertDontSee('AdottatoLeak');
});

it('shows an empty state when nothing matches', function () {
    $org = verifiedOrg();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Rex']);

    Livewire::test(AnimalCatalog::class)
        ->set('search', 'zzz-nessuna-corrispondenza-zzz')
        ->assertSee(__('public.catalog.empty_title'));
});

it('binds filters to the query string', function () {
    $catalog = Livewire::test(AnimalCatalog::class);

    // Le proprietà con #[Url] devono essere dichiarate come tali sul componente.
    expect((new AnimalCatalog)->search)->toBe('');
    $catalog->set('species', Species::Dog->value)->assertSet('species', Species::Dog->value);
});
