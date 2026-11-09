<?php

declare(strict_types=1);

use App\Enums\Species;
use App\Http\Middleware\SetLocale;
use App\Livewire\Home;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Support\Catalog\ResolveCatalogFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
 | Home pubblica (UI-6a): rendering IT/EN, opt-in di layout, dati reali e solo pubblici,
 | link, vetrina filtrabile per specie. Dati: HomeDataTest; nel browser: tests/Browser/HomeTest.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->organization = Organization::factory()->verified()->create(['city' => 'Bologna', 'region' => 'Emilia-Romagna']);
});

function homePageAnimal(Organization $organization, array $attributes = []): Animal
{
    return Animal::factory()->available()->forOrganization($organization)->create(['featured' => false, ...$attributes]);
}

it('renders the home page with the themeable and transparent header opt-ins', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Home::class)
        ->assertSee('data-transparent="true"', escape: false)
        ->assertDontSee('data-theme-forced', escape: false)
        ->assertSee('data-theme-option="dark"', escape: false);
});

it('renders the page in Italian and English', function (string $locale, string $title, string $lead, string $steps): void {
    $this->withSession([SetLocale::SESSION_KEY => $locale])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<h1 id="home-title" class="text-display text-balance">'.$title.'</h1>', escape: false)
        ->assertSee($lead)
        ->assertSee($steps);
})->with([
    'it' => ['it', 'Trova il tuo prossimo migliore amico', 'Ogni adozione cambia due vite.', 'Adottare, passo dopo passo'],
    'en' => ['en', 'Find your next best friend', 'Every adoption changes two lives.', 'Adopting, step by step'],
]);

it('keeps the heading levels in order and uses no emoji nor legacy components', function (): void {
    homePageAnimal($this->organization, ['name' => 'Fiocco']);

    $html = (string) $this->get(route('home'))->getContent();
    $main = substr($html, (int) strpos($html, '<main'), (int) strpos($html, '</main>') - (int) strpos($html, '<main'));

    preg_match_all('/<h([1-6])\b/', $main, $levels);
    $levels = array_map(intval(...), $levels[1]);

    expect($levels[0])->toBe(1)
        ->and(array_count_values($levels)[1])->toBe(1);

    foreach (array_slice($levels, 1) as $index => $level) {
        expect($level - $levels[$index])->toBeLessThanOrEqual(1);
    }

    expect($main)
        ->not->toContain('&#128062;')
        ->not->toContain('🐾')
        ->not->toMatch('/\b(brand|accent|stone)-\d/');
});

it('shows real, public-only numbers', function (): void {
    homePageAnimal($this->organization);
    homePageAnimal($this->organization);
    Animal::factory()->draft()->forOrganization($this->organization)->create();
    Animal::factory()->available()->forOrganization(Organization::factory()->pending()->create())->create();

    Livewire::test(Home::class)
        ->assertSeeHtmlInOrder(['data-home-stat="animals"', '>2<', 'animali in cerca di casa'])
        ->assertSeeHtmlInOrder(['data-home-stat="organizations"', '>1<', 'ente verificato'])
        ->assertDontSeeHtml('data-home-stat="adoptions"');
});

it('shows the available animals and never the drafts or those of unverified organizations', function (): void {
    homePageAnimal($this->organization, ['name' => 'Fiocco', 'featured' => true]);
    Animal::factory()->draft()->featured()->forOrganization($this->organization)->create(['name' => 'BozzaSegreta']);
    Animal::factory()->available()->featured()->forOrganization(Organization::factory()->pending()->create())->create(['name' => 'NascostoPending']);

    Livewire::test(Home::class)
        ->assertSee('Fiocco')
        ->assertSee('Bologna, Emilia-Romagna')
        ->assertSeeHtml('href="'.route('animals.show', Animal::query()->where('name', 'Fiocco')->sole()).'"')
        ->assertDontSee('BozzaSegreta')
        ->assertDontSee('NascostoPending');
});

it('puts an animal with a photo in the hero, with priority, and leaves it out of the showcase', function (): void {
    $luna = homePageAnimal($this->organization, ['name' => 'Luna', 'featured' => true]);
    $luna->addMedia(UploadedFile::fake()->image('luna.jpg', 900, 600))->toMediaCollection('photos');
    homePageAnimal($this->organization, ['name' => 'Argo']);

    $html = Livewire::test(Home::class)->html();
    $hero = substr($html, (int) strpos($html, 'data-home-spotlight'), (int) strpos($html, 'data-home-stats') - (int) strpos($html, 'data-home-spotlight'));
    $grid = substr($html, (int) strpos($html, 'data-home-grid'));

    expect($hero)
        ->toContain('alt="Foto di Luna"')
        ->toContain('fetchpriority="high"')
        ->toContain('aria-label="Conosci Luna"')
        ->toContain('href="'.route('animals.show', $luna).'"')
        ->and($grid)
        ->toContain('Argo')
        ->not->toContain('>Luna</a>')
        ->not->toContain('fetchpriority="high"');
});

it('shows a photo-less hero block when no animal has a photo', function (): void {
    homePageAnimal($this->organization, ['name' => 'Argo']);

    Livewire::test(Home::class)
        ->assertSeeHtml('data-picture-fallback')
        ->assertDontSeeHtml('data-home-spotlight-caption');
});

it('sends the hero search to the catalog with its query string', function (): void {
    $html = (string) $this->get(route('home'))->getContent();

    expect($html)
        ->toMatch('/<form\s+role="search"\s+method="get"\s+action="'.preg_quote(route('catalog'), '/').'"/')
        ->toMatch('/<input\s+type="search"\s+id="home-search"\s+name="q"/')
        // Label visibile collegata al campo (come nel catalogo), non solo aria-label.
        ->toMatch('/<label id="[^"]+" for="home-search"[^>]*>\s*<span>Cerca un animale nel catalogo<\/span>/')
        ->toContain('maxlength="'.ResolveCatalogFilters::MAX_SEARCH_LENGTH.'"')
        ->toContain('aria-label="Cerca un animale nel catalogo"');
});

it('links the catalog, the organizations and the organization sign-up', function (): void {
    homePageAnimal($this->organization);

    Livewire::test(Home::class)
        ->assertSeeHtml('href="'.route('catalog').'"')
        ->assertSeeHtml('href="'.route('organizations.index').'"')
        ->assertSeeHtml('href="'.route('filament.shelter.auth.register').'"');
});

/*
 | I chip aggiornano solo l'island `showcase` (wire:click dentro l'island): un ->set() del
 | test è un aggiornamento della radice, che salta le island. Qui si prova quindi il rendering
 | con la specie già scelta; il clic sui chip è coperto dal test browser.
 */
it('renders the showcase for all species with the "all" chip pressed', function (): void {
    homePageAnimal($this->organization, ['name' => 'Rex', 'species' => Species::Dog]);
    homePageAnimal($this->organization, ['name' => 'Micia', 'species' => Species::Cat]);

    Livewire::test(Home::class)
        ->assertSee(['Rex', 'Micia'])
        ->assertSeeHtml('wire:click="$set(\'species\', \'cat\')"')
        ->assertSeeHtmlInOrder(['aria-pressed="true"', 'data-home-filter="all"'])
        ->assertSeeHtml('2 animali in vetrina');
});

it('filters the showcase by species with the matching chip pressed and catalog link', function (): void {
    homePageAnimal($this->organization, ['name' => 'Rex', 'species' => Species::Dog]);
    homePageAnimal($this->organization, ['name' => 'Micia', 'species' => Species::Cat]);

    Livewire::test(Home::class, ['species' => 'cat'])
        ->assertSee('Micia')
        ->assertDontSee('Rex')
        ->assertSeeHtmlInOrder(['aria-pressed="false"', 'data-home-filter="all"', 'aria-pressed="true"', 'data-home-filter="cat"'])
        ->assertSeeHtml('Un animale in vetrina: Gatti')
        ->assertSeeHtml('href="'.route('catalog', ['species' => 'cat']).'"');
});

it('treats an unknown species as all species', function (): void {
    homePageAnimal($this->organization, ['name' => 'Rex', 'species' => Species::Dog]);

    Livewire::test(Home::class, ['species' => 'dragon'])
        ->assertSee('Rex')
        ->assertSeeHtml('href="'.route('catalog').'"');
});

it('explains an empty species filter and keeps the chips to go back', function (): void {
    homePageAnimal($this->organization, ['name' => 'Rex', 'species' => Species::Dog]);

    Livewire::test(Home::class, ['species' => 'other'])
        ->assertSeeHtml('data-home-empty="filtered"')
        ->assertSeeHtml('data-home-filters')
        ->assertDontSeeHtml('data-home-grid')
        ->assertSeeHtml('href="'.route('catalog', ['species' => 'other']).'"');
});

it('shows an honest empty state, without chips, when there are no animals', function (): void {
    Livewire::test(Home::class)
        ->assertSeeHtml('data-home-empty="all"')
        ->assertDontSeeHtml('data-home-filters')
        ->assertDontSeeHtml('data-home-grid');
});

it('keeps the stories section a placeholder and shows the adoptions count only when there are some', function (): void {
    Livewire::test(Home::class)
        ->assertSee('Le prime storie sono ancora da scrivere')
        ->assertDontSeeHtml('data-home-stat="adoptions"');

    $adopted = Animal::factory()->adopted()->forOrganization($this->organization)->create();
    Adoption::factory()->forApplication(AdoptionApplication::factory()->adoptionPending()->forAnimal($adopted)->create())->create();

    Livewire::test(Home::class)
        ->assertSee('Le prime storie sono ancora da scrivere')
        ->assertSeeHtmlInOrder(['data-home-stat="adoptions"', '>1<', 'adozione conclusa su ArkVel']);
});

it('loads the showcase without N+1 queries', function (): void {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Home::class);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    homePageAnimal($this->organization);
    $withOne = $countQueries();

    homePageAnimal(Organization::factory()->verified()->create());
    homePageAnimal(Organization::factory()->verified()->create());
    $withThree = $countQueries();

    expect($withThree)->toBe($withOne);
});

it('picks the hero animal once: the chips neither look it up again nor show it in the showcase', function (): void {
    $luna = homePageAnimal($this->organization, ['name' => 'Luna', 'species' => Species::Dog, 'featured' => true]);
    $luna->addMedia(UploadedFile::fake()->image('luna.jpg', 900, 600))->toMediaCollection('photos');
    homePageAnimal($this->organization, ['name' => 'Argo', 'species' => Species::Dog]);

    $home = Livewire::test(Home::class)->assertSet('spotlightId', $luna->id)->set('species', 'dog');

    // Ciò che l'island `showcase` calcola a ogni chip: la vetrina, senza la hero.
    DB::flushQueryLog();
    DB::enableQueryLog();
    $showcase = $home->instance()->showcase();
    DB::disableQueryLog();

    // La query della hero è l'unica con «esiste una foto» (whereHas media).
    $spotlightQueries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'exists (select * from "media"'));

    expect($spotlightQueries)->toBe([])
        ->and($showcase->animals->pluck('name')->all())->toBe(['Argo']);
});

it('does not let the client change the hero animal left out of the showcase', function (): void {
    Livewire::test(Home::class)->set('spotlightId', 99);
})->throws(CannotUpdateLockedPropertyException::class);

it('asks the database for the favorites of the showcase once', function (): void {
    $animals = collect(range(1, 3))->map(fn (int $number): Animal => homePageAnimal($this->organization, ['name' => 'Cucciolo'.$number]));
    $user = User::factory()->create();
    $user->favorites()->attach($animals[0]->getKey());

    DB::flushQueryLog();
    DB::enableQueryLog();

    $html = Livewire::actingAs($user)->test(Home::class)->html();
    $favoriteQueries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], '"favorites"'));
    DB::disableQueryLog();

    expect($favoriteQueries)->toHaveCount(1)
        ->and(preg_match_all('/aria-pressed="true"\s+aria-label="Salva Cucciolo1 nei preferiti"/', $html))->toBe(1);
});
