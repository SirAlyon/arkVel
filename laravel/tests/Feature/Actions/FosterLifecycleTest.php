<?php

declare(strict_types=1);

use App\Actions\Fostering\AdvanceFosterApplication;
use App\Actions\Fostering\EndFosterPlacement;
use App\Actions\Fostering\StartFosterPlacement;
use App\Actions\Fostering\SubmitFosterApplication;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use App\Enums\MembershipRole;
use App\Models\AdopterProfile;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\FosterApplicationStatusChanged;
use App\Notifications\FosterPlacementEnded;
use App\Notifications\FosterPlacementStarted;
use App\Notifications\NewFosterApplicationReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('walks the full foster lifecycle: submit, advance, start and end', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    // Ente verificato con UN membro verificato (destinatario delle notifiche staff).
    $organization = Organization::factory()->verified()->create();
    $staff = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $staff, MembershipRole::Owner, verified: true);

    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    // Affidatario con profilo di screening completo.
    $carer = User::factory()->create();
    AdopterProfile::factory()->forUser($carer)->complete()->create();
    $carer->load('adopterProfile');

    // 1. L'affidatario invia la richiesta.
    $application = app(SubmitFosterApplication::class)->handle($carer, $animal);
    expect($application->statusEnum())->toBe(FosterApplicationStatus::Submitted);

    // 2. Lo staff la fa avanzare fino ad Approved.
    foreach ([FosterApplicationStatus::UnderReview, FosterApplicationStatus::Approved] as $to) {
        app(AdvanceFosterApplication::class)->handle($application, $to, $staff);
    }
    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Approved);

    // 3. Avvio dell'affido: l'animale viene collocato.
    $placement = app(StartFosterPlacement::class)->handle(
        $application,
        $staff,
        FosterPlacementType::PreAdoption,
    );
    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Active)
        ->and($placement->status)->toBe(FosterPlacementStatus::Active)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Fostered);

    // 4. Conclusione dell'affido: rientro dell'animale.
    app(EndFosterPlacement::class)->handle($placement, $staff);

    // Stati finali.
    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Completed)
        ->and($placement->fresh()->status)->toBe(FosterPlacementStatus::Ended)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Available);

    // Conteggio totale delle notifiche del flusso:
    // - 1 NewFosterApplicationReceived allo staff verificato (submit)
    // - 2 FosterApplicationStatusChanged all'affidatario (UnderReview, Approved)
    // - 1 FosterPlacementStarted all'affidatario (start)
    // - 1 FosterPlacementEnded all'affidatario (end)
    Notification::assertSentTimes(NewFosterApplicationReceived::class, 1);
    Notification::assertSentToTimes($carer, FosterApplicationStatusChanged::class, 2);
    Notification::assertSentToTimes($carer, FosterPlacementStarted::class, 1);
    Notification::assertSentToTimes($carer, FosterPlacementEnded::class, 1);
    Notification::assertCount(5);
});
