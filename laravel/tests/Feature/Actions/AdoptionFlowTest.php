<?php

declare(strict_types=1);

use App\Actions\Adoptions\FinalizeAdoption;
use App\Actions\Applications\AdvanceApplication;
use App\Actions\Applications\SubmitApplication;
use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Models\AdopterProfile;
use App\Models\Adoption;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Support\Contracts\ContractGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const FLOW_PDF_BYTES = '%PDF-1.4 end-to-end-contract';

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');

    // Fake del generatore PDF (Dependency Inversion): il flusso non deve
    // dipendere dal motore reale, solo dall'interfaccia.
    $this->app->bind(ContractGenerator::class, fn (): ContractGenerator => new class implements ContractGenerator
    {
        public function generate(Adoption $adoption): string
        {
            return FLOW_PDF_BYTES;
        }
    });
});

it('walks the full submitted -> completed flow: submit, advance to AdoptionPending (reserving the animal), then finalize', function () {
    // Attore staff dell'ente e adottante con profilo completo.
    $staff = User::factory()->create();
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    $adopter = User::factory()->create();
    AdopterProfile::factory()->forUser($adopter)->complete()->create();
    $adopter->load('adopterProfile');

    // 1. L'adottante invia la candidatura.
    $application = app(SubmitApplication::class)->handle($adopter, $animal);
    expect($application->statusEnum())->toBe(ApplicationStatus::Submitted);

    // 2. Lo staff la fa avanzare fino ad AdoptionPending.
    foreach ([
        ApplicationStatus::UnderReview,
        ApplicationStatus::Interview,
        ApplicationStatus::Approved,
        ApplicationStatus::AdoptionPending,
    ] as $to) {
        app(AdvanceApplication::class)->handle($application, $to, $staff);
    }

    // Entrando in AdoptionPending l'animale è stato RISERVATO.
    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::AdoptionPending)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::AdoptionPending);

    // 3. Finalizzazione: chiude il flusso submitted -> completed.
    $adoption = app(FinalizeAdoption::class)->handle($application, $staff, fee: 80.0);

    // Candidatura completata, adozione creata, animale adottato.
    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Completed)
        ->and($adoption->exists)->toBeTrue()
        ->and($adoption->application_id)->toBe($application->getKey())
        ->and($adoption->organization_id)->toBe($organization->getKey())
        ->and((float) $adoption->fee)->toBe(80.0)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Adopted);

    // Contratto archiviato sul disco privato.
    Storage::disk('local')->assertExists("contracts/adoption-{$adoption->getKey()}.pdf");

    // La relazione 1:1 candidatura -> adozione è popolata.
    expect($application->fresh()->adoption?->getKey())->toBe($adoption->getKey());
});
