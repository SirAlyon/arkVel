<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Filament\Adopter\Pages\DeleteAccount;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\ManagesRecurringDonation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeRecurringDonationCheckout;

// "Danger zone" del pannello adottante: conferma esplicita, delega all'Action di dominio,
// logout + redirect, messaggio di guardia per l'unico titolare.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
    app()->instance(ManagesRecurringDonation::class, new FakeRecurringDonationCheckout);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('lets an authenticated user reach the delete-account page', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(DeleteAccount::class)
        ->assertOk();
});

it('requires the typed email to match before deleting', function () {
    $user = User::factory()->create(['email' => 'real@example.com']);

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->callAction('deleteAccount', ['confirmation_email' => 'wrong@example.com'])
        ->assertHasActionErrors(['confirmation_email']);

    expect($user->fresh()->isAnonymized())->toBeFalse();
});

it('anonymizes, logs out and redirects home on valid confirmation', function () {
    $user = User::factory()->create(['email' => 'bye@example.com']);

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->callAction('deleteAccount', ['confirmation_email' => 'bye@example.com'])
        ->assertHasNoActionErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh()->isAnonymized())->toBeTrue()
        ->and(Auth::check())->toBeFalse();
});

it('shows the sole-owner guard message and does not anonymize', function () {
    $org = Organization::factory()->verified()->create();
    $user = User::factory()->create(['email' => 'owner@example.com']);
    app(AddOrganizationMember::class)->handle($org, $user, MembershipRole::Owner, verified: true);
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    Livewire::actingAs($user)
        ->test(DeleteAccount::class)
        ->callAction('deleteAccount', ['confirmation_email' => 'owner@example.com'])
        ->assertNotified(__('account.delete.sole_owner_title'));

    expect($user->fresh()->isAnonymized())->toBeFalse();
});
