<?php

declare(strict_types=1);

use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Http\Middleware\SetLocale;
use App\Livewire\AnimalDetail;
use App\Livewire\AnimalDetail\AdoptionApplicationForm;
use App\Livewire\AnimalDetail\AppointmentBooking;
use App\Livewire\AnimalDetail\ContactOrganizationForm;
use App\Livewire\AnimalDetail\FosterRequestForm;
use App\Livewire\FavoriteButton;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Un animale pubblico, con il suo ente verificato. */
function detailPageAnimal(array $attributes = []): Animal
{
    $organization = Organization::factory()->verified()->create(['name' => 'Rifugio Coda Felice', 'city' => 'Bologna', 'region' => 'Emilia-Romagna']);

    return Animal::factory()->available()->forOrganization($organization)->create([
        'name' => 'Argo',
        'species' => Species::Dog,
        'sex' => Sex::Male,
        'size' => Size::Medium,
        'estimated_age_months' => 30,
        ...$attributes,
    ]);
}

it('returns 200 for an available animal of a verified organization', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create(['name' => 'Argo']);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('Argo');
});

it('returns 404 for a draft animal', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->draft()->forOrganization($org)->create();

    $this->get(route('animals.show', $animal))->assertNotFound();
});

it('returns 404 for an animal whose organization is not verified', function () {
    $org = Organization::factory()->pending()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    $this->get(route('animals.show', $animal))->assertNotFound();
});

it('shows the description in the current locale', function () {
    app()->setLocale('it');

    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create([
        'description' => ['it' => 'Cagnolino dolcissimo', 'en' => 'A very sweet dog'],
    ]);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('Cagnolino dolcissimo')
        ->assertDontSee('A very sweet dog');
});

it('shows the adoption CTA to a guest and sends them to the adopter login', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee(__('public.detail.cta_button'))
        ->assertSee('wire:click="apply"', false);

    Livewire::test(AdoptionApplicationForm::class, ['animal' => $animal])
        ->call('apply')
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('animals.show', $animal));
});

it('composes one child component per call to action', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    Livewire::test(AnimalDetail::class, ['animal' => $animal])
        ->assertSeeLivewire(FavoriteButton::class)
        ->assertSeeLivewire(AdoptionApplicationForm::class)
        ->assertSeeLivewire(FosterRequestForm::class)
        ->assertSeeLivewire(AppointmentBooking::class)
        ->assertSeeLivewire(ContactOrganizationForm::class);
});

it('renders the call-to-action forms on the public page', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('wire:click="requestFoster"', false)
        ->assertSee('wire:submit="book"', false)
        ->assertSee('wire:submit="contact"', false);
});

it('shows the sent state to a user who already applied and requested a foster', function () {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();
    $user = User::factory()->create();

    AdoptionApplication::factory()->submitted()->forAnimal($animal)->forAdopter($user)->create();
    FosterApplication::factory()->submitted()->forAnimal($animal)->forAdopter($user)->create();

    $this->actingAs($user)
        ->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee(__('applications.apply.applied'))
        ->assertSee(__('fostering.request.requested'))
        ->assertDontSee('wire:click="apply"', false)
        ->assertDontSee('wire:click="requestFoster"', false);
});

it('does not let the client retarget a child component to another animal', function (string $component) {
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();
    $other = Animal::factory()->available()->forOrganization($org)->create();

    Livewire::test($component, ['animal' => $animal])
        ->set('animalId', $other->getKey());
})->with([
    'adoption' => AdoptionApplicationForm::class,
    'foster' => FosterRequestForm::class,
    'booking' => AppointmentBooking::class,
    'contact' => ContactOrganizationForm::class,
])->throws(CannotUpdateLockedPropertyException::class);

it('renders the page with the themeable layout', function () {
    $this->get(route('animals.show', detailPageAnimal()))
        ->assertOk()
        ->assertDontSee('data-theme-forced', false)
        ->assertSee('data-animal-detail', false);
});

it('shows the header with the name as the only h1, the traits, the location and the organization link', function () {
    app()->setLocale('it');
    $animal = detailPageAnimal(['breed' => 'Meticcio']);

    $response = $this->get(route('animals.show', $animal))->assertOk();

    expect(substr_count($response->getContent(), '<h1'))->toBe(1)
        ->and($response->getContent())->toMatch('/<h1[^>]*>\s*Argo\s*<\/h1>/');

    $response
        ->assertSeeInOrder(['data-animal-traits', 'Cane', 'Maschio', '2 anni', 'Taglia media'], false)
        ->assertSee('Meticcio')
        ->assertSee('Bologna, Emilia-Romagna')
        ->assertSee('href="'.route('organizations.show', $animal->organization).'"', false)
        ->assertSee(__('ui_domain.animal_card.favorite', ['name' => 'Argo']));
});

it('shows the photos in the gallery with the first one as the LCP image', function () {
    Storage::fake('public');
    Queue::fake();

    $animal = detailPageAnimal();
    $animal->addMedia(UploadedFile::fake()->image('a.jpg', 900, 600))->toMediaCollection('photos');
    $animal->addMedia(UploadedFile::fake()->image('b.jpg', 900, 600))->toMediaCollection('photos');

    $html = $this->get(route('animals.show', $animal))->assertOk()->getContent();

    expect($html)->toContain('data-ui-gallery')
        ->toContain('id="animal-gallery"')
        ->not->toContain('data-animal-photo-fallback')
        ->and(substr_count($html, 'fetchpriority="high"'))->toBe(1);
});

it('falls back to the profile photo, then to a paw with the same proportions, without photos', function () {
    Storage::fake('public');
    Queue::fake();

    $withAvatar = detailPageAnimal();
    $withAvatar->addMedia(UploadedFile::fake()->image('avatar.jpg', 900, 600))->toMediaCollection('avatar');

    $this->get(route('animals.show', $withAvatar))
        ->assertOk()
        ->assertDontSee('data-ui-gallery', false)
        ->assertSee('data-animal-photo-fallback', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertDontSee('data-picture-fallback', false);

    $this->get(route('animals.show', detailPageAnimal()))
        ->assertOk()
        ->assertSee('data-picture-fallback', false)
        ->assertSee('aspect-ratio: 1536 / 1024', false);
});

it('falls back to another language for the description and marks its language', function () {
    app()->setLocale('it');
    $animal = detailPageAnimal(['description' => ['en' => 'A very sweet dog']]);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('A very sweet dog');
});

it('says so when the organization has not written a description yet', function () {
    app()->setLocale('it');

    $this->get(route('animals.show', detailPageAnimal(['description' => null])))
        ->assertOk()
        ->assertSee(__('page_animal.about.empty', ['name' => 'Argo']));
});

it('shows the information tabs with the compatibility and health facts', function (string $locale) {
    $animal = detailPageAnimal([
        'temperament' => 'Giocherellone',
        'good_with_kids' => true,
        'good_with_dogs' => false,
        'good_with_cats' => null,
        'vaccinated' => true,
        'sterilized' => false,
    ]);

    app()->setLocale($locale);
    $response = $this->withSession([SetLocale::SESSION_KEY => $locale])->get(route('animals.show', $animal))->assertOk();

    expect(substr_count($response->getContent(), 'role="tab"'))->toBe(4)
        ->and(substr_count($response->getContent(), 'role="tabpanel"'))->toBe(4);

    $response
        ->assertSee(__('page_animal.facts.title', ['name' => 'Argo']))
        ->assertSeeInOrder([
            __('page_animal.facts.tab.profile'),
            __('page_animal.facts.tab.character'),
            __('page_animal.facts.tab.compatibility'),
            __('page_animal.facts.tab.health'),
        ])
        ->assertSee('Giocherellone')
        ->assertSeeInOrder([
            __('page_animal.facts.kids'), __('page_animal.facts.compatible.yes'),
            __('page_animal.facts.dogs'), __('page_animal.facts.compatible.no'),
            __('page_animal.facts.cats'), __('page_animal.facts.compatible.unknown'),
        ])
        ->assertSeeInOrder([
            __('page_animal.facts.vaccinated'), __('page_animal.facts.done'),
            __('page_animal.facts.sterilized'), __('page_animal.facts.not_done'),
        ])
        ->assertDontSee('text-stone-400', false);
})->with(['it', 'en']);

it('renders the action panel, the booking and contact drawers and the mobile bar', function (string $locale) {
    $animal = detailPageAnimal();

    app()->setLocale($locale);

    $this->withSession([SetLocale::SESSION_KEY => $locale])->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSeeInOrder([
            'data-animal-actions-dock',
            'data-animal-actions',
            __('public.detail.cta_title', ['name' => 'Argo']),
            'wire:click="apply"',
            'wire:click="requestFoster"',
            'data-animal-open="booking"',
            'data-animal-open="contact"',
        ], false)
        ->assertSee('data-drawer="animal-booking"', false)
        ->assertSee('data-drawer="animal-contact"', false)
        ->assertSee('data-drawer="animal-actions"', false)
        ->assertSee('data-animal-actions-sheet', false)
        ->assertSee('data-animal-actions-bar', false)
        ->assertSee(__('page_animal.actions.bar_open'))
        ->assertSee(__('page_animal.booking.when'))
        ->assertSee('id="appointment-date"', false)
        ->assertSee('id="contact-body"', false);
})->with(['it', 'en']);

it('lets the visitor type any visit time next to the day picker, with no slots imposed by the view', function () {
    $html = $this->get(route('animals.show', detailPageAnimal()))->assertOk()->getContent();

    // Orario libero come il datetime-local di prima: le guardie (slot nel passato, overlap)
    // restano solo nell'Action.
    expect($html)->toMatch('/<input\s[^>]*type="time"[^>]*id="appointment-time"|<input\s[^>]*id="appointment-time"[^>]*type="time"/')
        ->not->toMatch('/<option value="\d{2}:\d{2}"/');
});

it('shows the guests a sign-in note in the booking and contact drawers only', function () {
    $animal = detailPageAnimal();

    $guest = $this->get(route('animals.show', $animal))->assertOk();

    expect(substr_count($guest->getContent(), 'data-animal-guest-note'))->toBe(2);

    $guest->assertSeeInOrder(['data-drawer="animal-booking"', __('page_animal.booking.guest_note'), 'wire:submit="book"'], false)
        ->assertSeeInOrder(['data-drawer="animal-contact"', __('page_animal.contact.guest_note'), 'wire:submit="contact"'], false)
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('wire:click.prevent="signIn"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('animals.show', $animal))
        ->assertOk()
        ->assertDontSee('data-animal-guest-note', false);
});

it('disables the apply and foster buttons while their request runs', function () {
    $html = $this->get(route('animals.show', detailPageAnimal()))->assertOk()->getContent();

    expect($html)
        ->toMatch('/wire:click="apply"\s+wire:loading\.attr="disabled"\s+wire:target="apply"\s+data-animal-apply/')
        ->toMatch('/wire:click="requestFoster"\s+wire:loading\.attr="disabled"\s+wire:target="requestFoster"\s+data-animal-foster/');
});

it('suggests similar public animals with their cards', function () {
    $animal = detailPageAnimal();
    Animal::factory()->available()->forOrganization($animal->organization)->create(['name' => 'Fido', 'species' => Species::Cat]);
    Animal::factory()->draft()->forOrganization($animal->organization)->create(['name' => 'Bozza']);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('data-animal-similar', false)
        ->assertSee(__('page_animal.similar.title'))
        ->assertSee('data-animal-card', false)
        ->assertSee('Fido')
        ->assertDontSee('Bozza');
});

it('shows the similar animals with the site card: link, public location and favorite button', function () {
    $animal = detailPageAnimal();
    $other = Organization::factory()->verified()->create(['city' => 'Torino', 'region' => 'Piemonte']);
    $fido = Animal::factory()->available()->forOrganization($other)->create(['name' => 'Fido', 'species' => Species::Dog]);

    $html = (string) $this->get(route('animals.show', $animal))->assertOk()->getContent();
    $similar = substr($html, (int) strpos($html, 'data-animal-similar'));

    expect($similar)
        ->toContain('href="'.route('animals.show', $fido).'"')
        ->toContain('Torino, Piemonte')
        ->toContain(__('ui_domain.animal_card.favorite', ['name' => 'Fido']))
        ->toMatch('/<h3[^>]*>\s*(<a[^>]*>\s*)?Fido/');
});

it('asks the database for the favorites of the page once, for the animal and the similar ones', function () {
    $animal = detailPageAnimal();
    $similar = Animal::factory()->available()->count(3)->forOrganization($animal->organization)->create(['species' => Species::Dog]);
    $user = User::factory()->create();
    $user->favorites()->attach([$animal->getKey(), $similar[1]->getKey()]);

    DB::enableQueryLog();

    $html = (string) $this->actingAs($user)->get(route('animals.show', $animal))->assertOk()->getContent();

    $favoriteQueries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], '"favorites"'));

    expect($favoriteQueries)->toHaveCount(1)
        ->and(preg_match_all('/aria-pressed="true"\s+aria-label="Salva /', $html))->toBe(2)
        ->and(preg_match_all('/aria-pressed="false"\s+aria-label="Salva /', $html))->toBe(2);
});

it('asks nothing about favorites for a guest', function () {
    $animal = detailPageAnimal();
    Animal::factory()->available()->forOrganization($animal->organization)->create(['species' => Species::Dog]);

    DB::enableQueryLog();

    $this->get(route('animals.show', $animal))->assertOk();

    expect(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], '"favorites"')))->toBe([]);
});

it('hides the similar animals section when nothing is related', function () {
    $this->get(route('animals.show', detailPageAnimal()))
        ->assertOk()
        ->assertDontSee('data-animal-similar', false);
});
