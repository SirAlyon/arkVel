<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Filament\Adopter\Pages\MyApplications;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Una candidatura in uno stato di factory dato, legata a un animale pubblicamente
 * visibile e all'adottante indicato.
 */
function applicationFor(User $adopter, string $factoryState = 'submitted'): AdoptionApplication
{
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    return AdoptionApplication::factory()
        ->{$factoryState}()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->create();
}

it('lets an adopter reach the my-applications page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyApplications::class)
        ->assertOk();
});

it('lists only the current users applications, not another users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = applicationFor($owner);
    $theirs = applicationFor($other);

    Livewire::actingAs($owner)
        ->test(MyApplications::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('withdraws a pre-terminal application via the record action', function (string $factoryState) {
    $owner = User::factory()->create();
    $application = applicationFor($owner, $factoryState);

    Livewire::actingAs($owner)
        ->test(MyApplications::class)
        ->assertActionVisible(TestAction::make('withdraw')->table($application))
        ->callAction(TestAction::make('withdraw')->table($application))
        ->assertHasNoActionErrors();

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Withdrawn)
        ->and(Activity::query()->where('description', 'application.withdrawn')->count())->toBe(1);
})->with([
    'submitted' => ['submitted'],
    'under review' => ['underReview'],
]);

it('hides the withdraw action for a terminal application', function (string $factoryState) {
    $owner = User::factory()->create();
    $application = applicationFor($owner, $factoryState);

    Livewire::actingAs($owner)
        ->test(MyApplications::class)
        ->assertActionHidden(TestAction::make('withdraw')->table($application));

    expect(Activity::query()->where('description', 'application.withdrawn')->count())->toBe(0);
})->with([
    'completed' => ['completed'],
    'rejected' => ['rejected'],
    'withdrawn' => ['withdrawn'],
]);

it('denies withdrawing another users application (policy ownership)', function () {
    $owner = User::factory()->create();
    $application = applicationFor($owner);

    // Un utente diverso non è il proprietario: la policy `withdraw` nega e l'azione
    // non è nemmeno visibile nella tabella (che comunque non contiene la riga altrui).
    expect($owner->can('withdraw', $application))->toBeTrue();

    $intruder = User::factory()->create();
    expect($intruder->can('withdraw', $application))->toBeFalse();
});
