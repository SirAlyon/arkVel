<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Scheda animale nel browser: le CTA vivono in componenti Livewire figli
 | (candidatura, affido, prenotazione, contatto) e devono funzionare montate nella
 | pagina, ognuna con il proprio stato.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function detailAnimal(): Animal
{
    $organization = Organization::factory()->verified()->create();

    return Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Argo']);
}

it('sends a guest who wants to adopt to the login page', function (): void {
    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->click('[wire\\:click="apply"]')
        ->assertPathIs('/accedi');
});

it('shows the booking validation errors inside the booking form only', function (): void {
    $this->actingAs(User::factory()->create());

    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->click('form[wire\\:submit="book"] button[type="submit"]')
        ->assertSee(__('appointments.booking.validation.type_required'))
        ->assertSee(__('appointments.booking.validation.scheduled_at_required'))
        ->assertDontSee(__('messaging.contact.empty'));
});

it('contacts the organization from its own form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    visit(route('animals.show', detailAnimal()))->withLocale('it-IT')
        ->type('#contact-body', 'Vorrei saperne di più su Argo.')
        ->click('form[wire\\:submit="contact"] button[type="submit"]')
        ->assertSee(__('messaging.contact.flash_success'))
        ->assertNoJavaScriptErrors();

    expect(MessageThread::query()->where('user_id', $user->getKey())->count())->toBe(1);
});
