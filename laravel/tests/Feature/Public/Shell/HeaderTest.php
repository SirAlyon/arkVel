<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Http\Middleware\SetLocale;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
 | Header della shell pubblica (UI-3): navigazione con stato attivo, menu mobile, selettore
 | lingua, menu account (ospite, adottante, membro di un ente), modalità trasparente.
 */

/** Solo l'header del sito (con il menu mobile) della risposta. */
function siteHeaderOf(TestResponse $response): string
{
    $html = (string) $response->getContent();
    // Fino a <main>: dentro l'header ci sono altri <header> (quello del drawer).
    $start = strpos($html, '<header');
    $end = strpos($html, '<main');

    expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();

    return substr($html, (int) $start, (int) $end - (int) $start);
}

it('renders the logo as the home link and the primary navigation with SPA navigation', function (): void {
    $header = siteHeaderOf(test()->get(route('home')));

    expect($header)
        ->toContain('href="'.route('home').'"')
        ->toContain('aria-label="arkvel, pagina iniziale"')
        ->toContain('aria-label="Navigazione principale"')
        ->toContain('href="'.route('catalog').'"')
        ->toContain('href="'.route('organizations.index').'"')
        ->toContain('wire:navigate.hover')
        ->not->toContain('aria-current');
});

it('marks the active navigation item', function (string $page, string $item, string $current): void {
    $organization = Organization::factory()->verified()->create(['slug' => 'rifugio-test']);
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    $url = match ($page) {
        'catalog' => route('catalog'),
        'animal' => route('animals.show', $animal),
        'organizations' => route('organizations.index'),
        'organization' => route('organizations.show', $organization),
    };

    $header = siteHeaderOf(test()->get($url)->assertOk());

    // Desktop e menu mobile: due link attivi, nessun altro.
    expect(substr_count($header, 'aria-current="'.$current.'"'))->toBe(2)
        ->and(substr_count($header, 'aria-current='))->toBe(2)
        ->and($header)->toMatch('/aria-current="'.$current.'"\s+data-nav-link[^>]*data-nav-item="'.$item.'"/');
})->with([
    'catalog page' => ['catalog', 'catalog', 'page'],
    'animal in the catalog section' => ['animal', 'catalog', 'true'],
    'organizations page' => ['organizations', 'organizations', 'page'],
    'organization in its section' => ['organization', 'organizations', 'true'],
]);

it('moves the navigation into a mobile drawer with the same items, account and language', function (): void {
    $header = siteHeaderOf(test()->get(route('home')));

    expect($header)
        ->toContain('data-site-menu-trigger')
        ->toContain('aria-label="Apri il menu"')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('data-drawer="site-menu"')
        ->and(substr_count($header, 'data-nav-item="catalog"'))->toBe(2)
        ->and(substr_count($header, 'data-language-switcher'))->toBe(2)
        ->and(substr_count($header, 'data-account-login='))->toBe(2);
});

it('offers both languages as POST forms, with the active one pressed', function (string $locale, string $pressed, string $other): void {
    $header = siteHeaderOf(test()->withSession([SetLocale::SESSION_KEY => $locale])->get(route('home')));

    expect($header)
        ->toContain('action="'.route('locale.update', ['locale' => 'it']).'"')
        ->toContain('action="'.route('locale.update', ['locale' => 'en']).'"')
        ->toContain('name="_token"')
        ->toMatch('/lang="'.$pressed.'"\s+aria-pressed="true"/')
        ->toMatch('/lang="'.$other.'"\s+aria-pressed="false"/')
        // Compatto nell'header: codice visibile, endonimo nel nome accessibile.
        ->toContain('IT<span class="sr-only"> Italiano</span>')
        ->toContain('EN<span class="sr-only"> English</span>');
})->with([
    'italian' => ['it', 'it', 'en'],
    'english' => ['en', 'en', 'it'],
]);

it('shows login and sign-up to guests', function (): void {
    $header = siteHeaderOf(test()->get(route('home')));

    expect($header)
        ->toContain('href="'.route('filament.adopter.auth.login').'"')
        ->toContain('href="'.route('filament.adopter.auth.register').'"')
        ->toContain('Accedi')
        ->toContain('Registrati')
        ->not->toContain('data-account-menu')
        ->not->toContain('data-account-logout');
});

it('shows the account menu with the adopter area and logout to a signed-in adopter', function (): void {
    $user = User::factory()->create(['name' => 'Giulia Bianchi']);

    $header = siteHeaderOf(test()->actingAs($user)->get(route('home')));

    expect($header)
        ->toContain('data-account-menu')
        ->toContain('aria-haspopup="menu"')
        // Nome visibile e, dopo, lo scopo per gli screen reader (WCAG 2.5.3).
        ->toContain('Giulia<span class="sr-only">, Il tuo account</span>')
        ->toContain('Giulia Bianchi')
        ->toContain('href="'.route('filament.adopter.home').'"')
        ->toContain('action="'.route('logout.public').'"')
        ->toContain('data-account-logout')
        ->toContain('data-avatar=')
        ->not->toContain('data-account-login')
        ->not->toContain('data-account-manage')
        ->not->toContain(route('filament.shelter.tenant'));
});

it('names the account menu by its purpose when the user has no name', function (): void {
    $header = siteHeaderOf(test()->actingAs(User::factory()->create(['name' => '   ']))->get(route('home')));

    expect($header)
        ->toContain('data-account-menu')
        ->toMatch('/data-dropdown-trigger[^>]*>.*?Il tuo account/s')
        ->not->toContain('<span class="sr-only">, Il tuo account</span>');
});

it('hides the sign-up button below lg with a variant that beats the button display', function (): void {
    expect(siteHeaderOf(test()->get(route('home'))))
        ->toMatch('/class="[^"]*max-lg:hidden[^"]*"[^>]*data-account-register/')
        ->not->toContain('hidden lg:inline-flex');
});

it('adds the organization panel for verified members of an organization', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $member = member(Organization::factory()->verified()->create(), MembershipRole::Volunteer);

    $header = siteHeaderOf(test()->actingAs($member)->get(route('home')));

    expect(substr_count($header, 'data-account-manage='))->toBe(2)
        ->and($header)->toContain('href="'.route('filament.shelter.tenant').'"')
        ->toContain('Gestione ente');
});

it('does not use SPA navigation towards the Filament panels', function (): void {
    $header = siteHeaderOf(test()->actingAs(User::factory()->create())->get(route('home')));

    expect($header)->not->toMatch('/href="'.preg_quote(route('filament.adopter.home'), '/').'"\s+wire:navigate/');
});

it('is solid and sticky by default', function (): void {
    $header = siteHeaderOf(test()->get(route('home')));

    expect($header)
        ->toContain('data-transparent="false"')
        ->toContain('x-data="siteHeader"')
        ->toContain('sticky bg-canvas');
});

it('becomes transparent over the hero when the page opts in', function (): void {
    $html = (string) test()->blade('@component("layouts::app", ["transparentHeader" => true]) <p>Corpo</p> @endcomponent');

    expect($html)
        ->toContain('data-transparent="true"')
        ->toContain('fixed inset-x-0 bg-transparent data-scrolled:bg-canvas')
        ->toContain('data-site-header-sentinel');
});

it('translates the header into English', function (): void {
    $header = siteHeaderOf(test()->withSession([SetLocale::SESSION_KEY => 'en'])->get(route('home')));

    expect($header)
        ->toContain('Primary navigation')
        ->toContain('Catalog')
        ->toContain('Organizations')
        ->toContain('Log in')
        ->toContain('Sign up')
        ->toContain('Open the menu')
        ->toContain('aria-label="arkvel, home page"');
});
