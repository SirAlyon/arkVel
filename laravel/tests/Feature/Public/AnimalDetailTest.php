<?php

declare(strict_types=1);

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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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
