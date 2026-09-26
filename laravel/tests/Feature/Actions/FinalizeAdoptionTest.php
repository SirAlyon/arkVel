<?php

declare(strict_types=1);

use App\Actions\Adoptions\FinalizeAdoption;
use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Enums\FollowUpStatus;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use App\Notifications\AdoptionCompleted;
use App\Notifications\ApplicationStatusChanged;
use App\Support\Contracts\ContractGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const FAKE_PDF_BYTES = '%PDF-1.4 fake-contract-bytes';

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->actor = User::factory()->create();

    // Fake deterministico del generatore PDF: nessun motore reale, byte noti.
    // Dependency Inversion: FinalizeAdoption dipende dall'interfaccia, qui la
    // rimpiazziamo nel container.
    $this->app->bind(ContractGenerator::class, fn (): ContractGenerator => new class implements ContractGenerator
    {
        public function generate(Adoption $adoption): string
        {
            return FAKE_PDF_BYTES;
        }
    });
});

/**
 * Candidatura in AdoptionPending con animale RISERVATO (AdoptionPending), come
 * lo lascia AdvanceApplication: è il presupposto della finalizzazione.
 */
function pendingApplicationReadyToFinalize(): AdoptionApplication
{
    $animal = Animal::factory()->create(['status' => AnimalStatus::AdoptionPending]);

    return AdoptionApplication::factory()
        ->forAnimal($animal)
        ->adoptionPending()
        ->create();
}

it('throws InvalidApplicationTransition when the application is not AdoptionPending and creates nothing', function () {
    $application = AdoptionApplication::factory()->approved()->create();

    expect(fn () => app(FinalizeAdoption::class)->handle($application, $this->actor))
        ->toThrow(InvalidApplicationTransition::class);

    expect(Adoption::query()->count())->toBe(0)
        ->and($application->fresh()->statusEnum())->toBe(ApplicationStatus::Approved);

    Notification::assertNothingSent();
});

it('finalizes the adoption: completes the application, creates the adoption and marks the animal Adopted', function () {
    $application = pendingApplicationReadyToFinalize();
    $animal = $application->animal;

    $adoption = app(FinalizeAdoption::class)->handle($application, $this->actor, fee: 120.50);

    // Candidatura completata.
    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Completed)
        ->and($application->fresh()->decided_at)->not->toBeNull();

    // Adozione creata con i campi corretti.
    expect($adoption->animal_id)->toBe($animal->getKey())
        ->and($adoption->user_id)->toBe($application->user_id)
        ->and($adoption->organization_id)->toBe($application->organization_id)
        ->and($adoption->application_id)->toBe($application->getKey())
        ->and((float) $adoption->fee)->toBe(120.50)
        ->and($adoption->currency)->toBe('EUR')
        ->and($adoption->follow_up_status)->toBe(FollowUpStatus::Pending)
        ->and($adoption->adopted_at)->not->toBeNull();

    // Animale adottato + log della transizione animale.
    expect($animal->fresh()->status)->toBe(AnimalStatus::Adopted);
    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(1);
});

it('defaults the currency to EUR and leaves fee null when not provided', function () {
    $application = pendingApplicationReadyToFinalize();

    $adoption = app(FinalizeAdoption::class)->handle($application, $this->actor);

    expect($adoption->fee)->toBeNull()
        ->and($adoption->currency)->toBe('EUR');
});

it('stores the contract PDF on the private local disk and sets contract_path', function () {
    $application = pendingApplicationReadyToFinalize();

    $adoption = app(FinalizeAdoption::class)->handle($application, $this->actor);

    $expectedPath = "contracts/adoption-{$adoption->getKey()}.pdf";

    expect($adoption->fresh()->contract_path)->toBe($expectedPath);
    Storage::disk('local')->assertExists($expectedPath);
    expect(Storage::disk('local')->get($expectedPath))->toBe(FAKE_PDF_BYTES);
});

it('notifies the adopter with AdoptionCompleted and logs adoption.finalized caused by the actor', function () {
    $application = pendingApplicationReadyToFinalize();

    $adoption = app(FinalizeAdoption::class)->handle($application, $this->actor);

    Notification::assertSentTo(
        $application->user,
        AdoptionCompleted::class,
        fn (AdoptionCompleted $notification): bool => $notification->adoption->is($adoption),
    );

    $activity = Activity::query()->where('description', 'adoption.finalized')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->subject_id)->toBe($adoption->getKey());
});

it('closes sibling open applications for the same animal as Rejected and notifies their adopters', function () {
    $application = pendingApplicationReadyToFinalize();
    $animal = $application->animal;

    // Candidature SORELLE aperte per lo stesso animale (stati chiudibili).
    $siblingUnderReview = AdoptionApplication::factory()->forAnimal($animal)->underReview()->create();
    $siblingInterview = AdoptionApplication::factory()->forAnimal($animal)->interview()->create();

    // Una candidatura già terminale non deve essere toccata.
    $alreadyRejected = AdoptionApplication::factory()->forAnimal($animal)->rejected()->create();

    app(FinalizeAdoption::class)->handle($application, $this->actor);

    expect($siblingUnderReview->fresh()->statusEnum())->toBe(ApplicationStatus::Rejected)
        ->and($siblingUnderReview->fresh()->decision_reason)->toBe(__('adoptions.sibling_rejected_reason'))
        ->and($siblingInterview->fresh()->statusEnum())->toBe(ApplicationStatus::Rejected);

    // Ciascun adottante sorella riceve la notifica di cambio stato.
    Notification::assertSentTo($siblingUnderReview->user, ApplicationStatusChanged::class);
    Notification::assertSentTo($siblingInterview->user, ApplicationStatusChanged::class);

    // La candidatura già respinta resta invariata e il suo adottante NON viene notificato.
    expect($alreadyRejected->fresh()->statusEnum())->toBe(ApplicationStatus::Rejected);
    Notification::assertNotSentTo($alreadyRejected->user, ApplicationStatusChanged::class);
});

it('rolls back everything when the animal is not in a finalizable state', function () {
    // Candidatura in AdoptionPending ma animale ancora Available (non riservato):
    // AdoptionPending non è raggiungibile da... anzi Available -> Adopted NON è
    // ammesso, quindi la transizione animale fallisce e fa rollback.
    $animal = Animal::factory()->available()->create();
    $application = AdoptionApplication::factory()->forAnimal($animal)->adoptionPending()->create();

    expect(fn () => app(FinalizeAdoption::class)->handle($application, $this->actor))
        ->toThrow(InvalidAnimalStatusTransition::class);

    // Nulla è stato persistito: né adozione, né completamento, né contratto.
    expect(Adoption::query()->count())->toBe(0)
        ->and($application->fresh()->statusEnum())->toBe(ApplicationStatus::AdoptionPending)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Available);

    Storage::disk('local')->assertDirectoryEmpty('contracts');
    Notification::assertNothingSent();
});
