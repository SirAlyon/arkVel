<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns 200 and lists available animals for a verified organization', function () {
    $org = Organization::factory()->verified()->create(['name' => 'Rifugio Speranza']);
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Milo']);

    $this->get(route('organizations.show', $org))
        ->assertOk()
        ->assertSee('Rifugio Speranza')
        ->assertSee('Milo');
});

it('returns 404 for a non-verified organization', function () {
    $org = Organization::factory()->pending()->create();

    $this->get(route('organizations.show', $org))->assertNotFound();
});

it('does not list the organization\'s draft animals', function () {
    $org = Organization::factory()->verified()->create();
    Animal::factory()->available()->forOrganization($org)->create(['name' => 'Disponibile']);
    Animal::factory()->draft()->forOrganization($org)->create(['name' => 'BozzaEnte']);

    $this->get(route('organizations.show', $org))
        ->assertOk()
        ->assertSee('Disponibile')
        ->assertDontSee('BozzaEnte');
});

it('renders the organizations index listing verified organizations only', function () {
    Organization::factory()->verified()->create(['name' => 'Ente Verificato']);
    Organization::factory()->pending()->create(['name' => 'Ente In Attesa']);

    $this->get(route('organizations.index'))
        ->assertOk()
        ->assertSee('Ente Verificato')
        ->assertDontSee('Ente In Attesa');
});

/*
 | Redesign della pagina ente (UI-6d): tema, intestazione, numeri, animali con x-ui.animal-card
 | in eager loading, stato vuoto, paginazione; pagine di ritorno dal Checkout.
 */

it('renders the redesigned header with the theme, in Italian and English', function (): void {
    $org = Organization::factory()->verified()->create([
        'name' => 'Rifugio Il Faro',
        'city' => 'Genova',
        'region' => 'Liguria',
        'email' => 'info@ilfaro.example',
        'phone' => '+39 010 123456',
        'website' => 'https://ilfaro.example',
        'description' => ['it' => 'Ci prendiamo cura dei cani del porto.', 'en' => 'We look after the harbour dogs.'],
    ]);

    $this->get(route('organizations.show', $org))
        ->assertOk()
        ->assertDontSee('data-theme-forced', escape: false)
        ->assertSee('<h1 class="text-h1 [overflow-wrap:anywhere]">Rifugio Il Faro</h1>', escape: false)
        ->assertSee($org->type->getLabel())
        ->assertSee('Ente verificato')
        ->assertSee('Genova, Liguria')
        ->assertSee('href="mailto:info@ilfaro.example"', escape: false)
        ->assertSee('href="tel:+39 010 123456"', escape: false)
        ->assertSee('href="https://ilfaro.example"', escape: false)
        ->assertSee('Chi siamo')
        ->assertSee('Ci prendiamo cura dei cani del porto.');

    $this->withSession([SetLocale::SESSION_KEY => 'en'])->get(route('organizations.show', $org))
        ->assertOk()
        ->assertSee('Verified organization')
        ->assertSee('About us')
        ->assertSee('We look after the harbour dogs.')
        ->assertSee('Their animals');
});

it('shows the organization numbers, with completed adoptions only when there are some', function (): void {
    $org = Organization::factory()->verified()->create();
    Animal::factory()->available()->count(2)->forOrganization($org)->create();

    $this->get(route('organizations.show', $org))
        ->assertSee('data-stat="leaf"', escape: false)
        ->assertSee('animali in cerca di casa')
        ->assertDontSee('adozioni concluse');

    // Segnato adottato a mano: non è un'adozione conclusa sulla piattaforma.
    Animal::factory()->adopted()->forOrganization($org)->create();

    $this->get(route('organizations.show', $org))->assertDontSee('adozione conclusa');

    $adopted = Animal::factory()->adopted()->forOrganization($org)->create();
    Adoption::factory()->forApplication(AdoptionApplication::factory()->adoptionPending()->forAnimal($adopted)->create())->create();

    $this->get(route('organizations.show', $org))
        ->assertSee('data-stat="mint"', escape: false)
        ->assertSee('adozione conclusa');
});

it('lists the animals with the new card, eager loading their photos', function (): void {
    $org = Organization::factory()->verified()->create();
    Animal::factory()->available()->count(3)->forOrganization($org)->create();
    Animal::factory()->adopted()->forOrganization($org)->create(['name' => 'GiaAdottato']);

    Model::preventLazyLoading();

    try {
        $this->get(route('organizations.show', $org))
            ->assertOk()
            ->assertSee('data-organization-animal-grid', escape: false)
            ->assertSee('<h3 class="text-h4">', escape: false)
            ->assertSee('3 animali disponibili')
            ->assertDontSee('GiaAdottato');
    } finally {
        Model::preventLazyLoading(false);
    }
});

it('shows an empty state when the organization has no animals up for adoption', function (): void {
    $org = Organization::factory()->verified()->create();

    $this->get(route('organizations.show', $org))
        ->assertOk()
        ->assertSee('Nessun animale in adozione, per ora')
        ->assertSee('Questo ente al momento non ha animali disponibili.')
        ->assertDontSee('data-organization-animal-grid', escape: false);
});

it('paginates the animals with absolute links', function (): void {
    $org = Organization::factory()->verified()->create();
    Animal::factory()->available()->count(13)->forOrganization($org)->create();

    $this->get(route('organizations.show', $org))
        ->assertSee('data-pagination', escape: false)
        ->assertSee('Pagina 1 di 2')
        ->assertSee('href="'.route('organizations.show', [$org, 'page' => 2]).'"', escape: false)
        ->assertSee('wire:click.prevent="gotoPage(2, \'page\')"', escape: false);
});

it('shows the description in another language when the current one is missing, marked with lang', function (): void {
    $org = Organization::factory()->verified()->create(['description' => ['en' => 'We look after the harbour dogs.']]);

    $italian = (string) $this->get(route('organizations.show', $org))->assertOk()->assertSee('Chi siamo')->getContent();
    $english = (string) $this->withSession([SetLocale::SESSION_KEY => 'en'])->get(route('organizations.show', $org))->getContent();

    expect($italian)->toMatch('/<p [^>]*lang="en"\s+data-organization-description>We look after the harbour dogs\.<\/p>/')
        ->and($english)->toMatch('/<p [^>]*data-organization-description>We look after the harbour dogs\.<\/p>/')
        ->and($english)->not->toMatch('/<p [^>]*lang="en"\s+data-organization-description>/');
});

it('links the website only when it is an http or https address', function (string $website, bool $linked): void {
    $org = Organization::factory()->verified()->create(['website' => $website, 'email' => null, 'phone' => null]);

    $response = $this->get(route('organizations.show', $org))->assertOk();

    $linked
        ? $response->assertSee('href="'.$website.'"', escape: false)->assertSee('data-organization-website', escape: false)
        : $response->assertDontSee('data-organization-website', escape: false)->assertDontSee('data-organization-contacts', escape: false);
})->with([
    'https' => ['https://ilfaro.example', true],
    'http' => ['http://ilfaro.example', true],
    'javascript' => ['javascript:alert(1)', false],
    'data' => ['data:text/html,ciao', false],
    'not a url' => ['il nostro sito', false],
]);

it('browses the catalog filtered on the organization, under a heading sized like «Chi siamo»', function (): void {
    $org = Organization::factory()->verified()->create(['slug' => 'il-faro', 'description' => ['it' => 'Chi siamo, in breve.']]);
    Animal::factory()->available()->forOrganization($org)->create();

    $this->get(route('organizations.show', $org))
        ->assertSee('href="'.route('catalog', ['organization' => 'il-faro']).'"', escape: false)
        ->assertSee('<h2 id="organization-about-title" class="text-h3 text-fg">', escape: false)
        ->assertSee('<h2 id="organization-animals-title" class="text-fg text-h3">', escape: false);
});

it('asks the database for the favorites of the page once, not once per card', function (): void {
    $org = Organization::factory()->verified()->create();
    $animals = Animal::factory()->available()->count(5)->forOrganization($org)->create();
    $user = User::factory()->create();
    $user->favorites()->attach($animals[1]->getKey());

    DB::enableQueryLog();

    $html = (string) $this->actingAs($user)->get(route('organizations.show', $org))->assertOk()->getContent();

    $favoriteQueries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], '"favorites"'));

    expect($favoriteQueries)->toHaveCount(1)
        ->and(preg_match_all('/aria-pressed="true"\s+aria-label="Salva /', $html))->toBe(1)
        ->and(preg_match_all('/aria-pressed="false"\s+aria-label="Salva /', $html))->toBe(4);
});

it('renders the donation landings with the theme, in Italian and English', function (string $route, string $it, string $en): void {
    $org = Organization::factory()->verified()->create(['name' => 'Rifugio Grazie']);

    $this->get(route($route, $org))
        ->assertOk()
        ->assertDontSee('data-theme-forced', escape: false)
        ->assertSee('<meta name="robots" content="noindex', escape: false)
        ->assertSee('<h1 id="', escape: false)
        ->assertSee($it)
        ->assertSee('href="'.route('organizations.show', $org).'"', escape: false)
        ->assertSee('href="'.route('catalog').'"', escape: false);

    $this->withSession([SetLocale::SESSION_KEY => 'en'])->get(route($route, $org))
        ->assertOk()
        ->assertSee($en);
})->with([
    'thanks' => ['organizations.donate.thanks', 'Grazie per la tua donazione', 'Thank you for your donation'],
    'cancelled' => ['organizations.donate.cancel', 'Donazione annullata', 'Donation cancelled'],
]);

it('returns 404 on the cancel landing for a non-verified organization', function (): void {
    $org = Organization::factory()->suspended()->create();

    $this->get(route('organizations.donate.cancel', $org))->assertNotFound();
});
