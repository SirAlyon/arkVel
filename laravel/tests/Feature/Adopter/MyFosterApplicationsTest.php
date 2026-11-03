<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Filament\Adopter\Pages\MyFosterApplications;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

// Specchio di tests/Feature/Adopter/MyApplicationsTest.php per il ramo affido.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Una richiesta di affido in uno stato di factory dato, legata a un animale
 * pubblicamente visibile e all'affidatario indicato.
 */
function fosterApplicationFor(User $carer, string $factoryState = 'submitted'): FosterApplication
{
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    return FosterApplication::factory()
        ->{$factoryState}()
        ->forAnimal($animal)
        ->forAdopter($carer)
        ->create();
}

it('lets a carer reach the my-foster-applications page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyFosterApplications::class)
        ->assertOk();
});

it('lists only the current users foster requests, not another users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = fosterApplicationFor($owner);
    $theirs = fosterApplicationFor($other);

    Livewire::actingAs($owner)
        ->test(MyFosterApplications::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('withdraws a pre-terminal foster request via the record action', function (string $factoryState) {
    $owner = User::factory()->create();
    $application = fosterApplicationFor($owner, $factoryState);

    Livewire::actingAs($owner)
        ->test(MyFosterApplications::class)
        ->assertActionVisible(TestAction::make('withdraw')->table($application))
        ->callAction(TestAction::make('withdraw')->table($application))
        ->assertHasNoActionErrors();

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Withdrawn)
        ->and(Activity::query()->where('description', 'foster_application.withdrawn')->count())->toBe(1);
})->with([
    'submitted' => ['submitted'],
    'under review' => ['underReview'],
    'approved' => ['approved'],
    'waitlisted' => ['waitlisted'],
]);

it('withdrawing an active foster request closes the placement and returns the animal to available', function () {
    $owner = User::factory()->create();

    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->forOrganization($org)->create(['status' => AnimalStatus::Fostered]);

    $application = FosterApplication::factory()
        ->active()
        ->forAnimal($animal)
        ->forAdopter($owner)
        ->create();

    $placement = FosterPlacement::factory()
        ->active()
        ->forApplication($application)
        ->create();

    Livewire::actingAs($owner)
        ->test(MyFosterApplications::class)
        ->callAction(TestAction::make('withdraw')->table($application))
        ->assertHasNoActionErrors();

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Withdrawn)
        ->and($placement->fresh()->status)->toBe(FosterPlacementStatus::Ended)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Available);
});

it('hides the withdraw action for a terminal foster request', function (string $factoryState) {
    $owner = User::factory()->create();
    $application = fosterApplicationFor($owner, $factoryState);

    Livewire::actingAs($owner)
        ->test(MyFosterApplications::class)
        ->assertActionHidden(TestAction::make('withdraw')->table($application));

    expect(Activity::query()->where('description', 'foster_application.withdrawn')->count())->toBe(0);
})->with([
    'completed' => ['completed'],
    'rejected' => ['rejected'],
    'withdrawn' => ['withdrawn'],
]);

it('denies withdrawing another users foster request (policy ownership)', function () {
    $owner = User::factory()->create();
    $application = fosterApplicationFor($owner);

    // Un utente diverso non è il proprietario: la policy `withdraw` nega e l'azione
    // non è nemmeno visibile nella tabella (che comunque non contiene la riga altrui).
    expect($owner->can('withdraw', $application))->toBeTrue();

    $intruder = User::factory()->create();
    expect($intruder->can('withdraw', $application))->toBeFalse();
});
