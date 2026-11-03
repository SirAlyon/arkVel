<?php

declare(strict_types=1);

use App\Enums\RecurringDonationStatus;
use App\Filament\Adopter\Pages\MyDonations;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\ManagesRecurringDonation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeRecurringDonationCheckout;

// Pagina "Le mie donazioni" del pannello adottante (P4-7): ownership-scoped (NON tenancy),
// disdetta via Action col seam FAKE (nessuna rete), isolamento cross-utente.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function bindFakeManagerForPage(): FakeRecurringDonationCheckout
{
    $fake = new FakeRecurringDonationCheckout;
    app()->instance(ManagesRecurringDonation::class, $fake);

    return $fake;
}

it('lets an authenticated user reach the my-donations page', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(MyDonations::class)
        ->assertOk();
});

it('lists only the current users recurring donations, not another users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = RecurringDonation::factory()->forDonor($owner)->active()->create();
    $theirs = RecurringDonation::factory()->forDonor($other)->active()->create();

    Livewire::actingAs($owner)
        ->test(MyDonations::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('shows the cancel action on active donations and cancels via the record action', function () {
    $fake = bindFakeManagerForPage();
    $owner = User::factory()->create();
    $recurring = RecurringDonation::factory()
        ->forDonor($owner)
        ->active()
        ->withSubscriptionId('sub_PAGE')
        ->create();

    Livewire::actingAs($owner)
        ->test(MyDonations::class)
        ->assertActionVisible(TestAction::make('cancel')->table($recurring))
        ->callAction(TestAction::make('cancel')->table($recurring))
        ->assertHasNoActionErrors();

    expect($recurring->fresh()->status)->toBe(RecurringDonationStatus::Canceled)
        ->and($fake->canceledSubscriptionId)->toBe('sub_PAGE');
});

it('hides the cancel action for an already canceled donation', function () {
    $owner = User::factory()->create();
    $recurring = RecurringDonation::factory()->forDonor($owner)->canceled()->create();

    Livewire::actingAs($owner)
        ->test(MyDonations::class)
        ->assertActionHidden(TestAction::make('cancel')->table($recurring));
});

it('shows the charge history of the current users recurring donations', function () {
    $owner = User::factory()->create();
    $org = Organization::factory()->verified()->create(['name' => 'Rifugio Ricorrente']);
    $recurring = RecurringDonation::factory()->forDonor($owner)->forOrganization($org)->active()->create();

    Donation::factory()
        ->forRecurringDonation($recurring)
        ->completed()
        ->create(['amount' => 1_500, 'currency' => 'eur']);

    Livewire::actingAs($owner)
        ->test(MyDonations::class)
        ->assertOk()
        ->assertSee(__('donations.my.charges_heading'));
});

it('denies cancelling another users recurring donation (policy ownership)', function () {
    $owner = User::factory()->create();
    $recurring = RecurringDonation::factory()->forDonor($owner)->active()->create();

    expect($owner->can('cancel', $recurring))->toBeTrue();

    $intruder = User::factory()->create();
    expect($intruder->can('cancel', $recurring))->toBeFalse();
});
