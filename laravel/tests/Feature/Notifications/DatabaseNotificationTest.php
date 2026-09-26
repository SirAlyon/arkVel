<?php

declare(strict_types=1);

use App\Actions\Applications\AdvanceApplication;
use App\Actions\Applications\SubmitApplication;
use App\Actions\Applications\WithdrawApplication;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\ApplicationStatus;
use App\Enums\MembershipRole;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use App\Notifications\ApplicationWithdrawn;
use App\Notifications\NewApplicationReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Adottante con profilo COMPLETO (questionario + consenso GDPR): condizione per
 * candidarsi.
 */
function adopterWithProfile(): User
{
    $adopter = User::factory()->create();
    AdopterProfile::factory()->forUser($adopter)->complete()->create();
    $adopter->load('adopterProfile');

    return $adopter;
}

// --- integrazione: la riga finisce davvero nella tabella `notifications` --------
// QUEUE_CONNECTION=sync (phpunit.xml) → le notifiche accodate girano inline, così
// il canale database scrive la riga entro la richiesta di test (senza worker).

it('writes a database notification row for the adopter on a real status change', function () {
    // NIENTE Notification::fake(): esercitiamo il canale database reale.
    $application = AdoptionApplication::factory()->submitted()->create();
    $adopter = $application->user;
    $actor = User::factory()->create();

    app(AdvanceApplication::class)->handle($application, ApplicationStatus::UnderReview, $actor);

    expect($adopter->notifications()->count())->toBe(1);

    $row = $adopter->notifications()->first();
    expect($row->type)->toBe(ApplicationStatusChanged::class)
        ->and($row->data['application_id'])->toBe($application->getKey())
        ->and($row->data['to'])->toBe(ApplicationStatus::UnderReview->value)
        ->and($row->data)->toHaveKey('message');
});

it('writes a database notification row for each verified org member on a new application', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $organization = Organization::factory()->verified()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $owner, MembershipRole::Owner, verified: true);
    app(AddOrganizationMember::class)->handle($organization, $staff, MembershipRole::Staff, verified: true);

    $unverified = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $unverified, MembershipRole::Staff, verified: false);

    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = adopterWithProfile();

    app(SubmitApplication::class)->handle($adopter, $animal);

    // I due membri verificati hanno la riga; il non verificato no.
    expect($owner->notifications()->count())->toBe(1)
        ->and($staff->notifications()->count())->toBe(1)
        ->and($unverified->notifications()->count())->toBe(0);

    expect($owner->notifications()->first()->type)->toBe(NewApplicationReceived::class);
});

// --- ritiro: notifica lo staff dell'ente ----------------------------------------

it('notifies the assigned staff member on withdrawal when the application is assigned', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    $organization = Organization::factory()->verified()->create();
    $assignee = User::factory()->create();
    $other = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $assignee, MembershipRole::Staff, verified: true);
    app(AddOrganizationMember::class)->handle($organization, $other, MembershipRole::Owner, verified: true);

    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->submitted()
        ->create(['assigned_to' => $assignee->getKey()]);

    app(WithdrawApplication::class)->handle($application, $adopter);

    // Solo l'operatore in carico riceve la notifica; gli altri membri no.
    Notification::assertSentTo($assignee, ApplicationWithdrawn::class);
    Notification::assertNotSentTo($other, ApplicationWithdrawn::class);
    Notification::assertSentTimes(ApplicationWithdrawn::class, 1);
});

it('notifies all verified org members on withdrawal when the application is unassigned', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    $organization = Organization::factory()->verified()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $owner, MembershipRole::Owner, verified: true);
    app(AddOrganizationMember::class)->handle($organization, $staff, MembershipRole::Staff, verified: true);

    $unverified = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $unverified, MembershipRole::Staff, verified: false);

    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->submitted()
        ->create(); // assigned_to null

    app(WithdrawApplication::class)->handle($application, $adopter);

    Notification::assertSentTo($owner, ApplicationWithdrawn::class);
    Notification::assertSentTo($staff, ApplicationWithdrawn::class);
    Notification::assertNotSentTo($unverified, ApplicationWithdrawn::class);
    Notification::assertSentTimes(ApplicationWithdrawn::class, 2);
});

it('does not notify the adopter (org-facing) on withdrawal', function () {
    Notification::fake();

    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()->forAdopter($adopter)->submitted()->create();

    app(WithdrawApplication::class)->handle($application, $adopter);

    // La notifica di ritiro è per l'ente, non per l'adottante che ha ritirato.
    Notification::assertNotSentTo($adopter, ApplicationWithdrawn::class);
});
