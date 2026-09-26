<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Enums\FollowUpStatus;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use App\Notifications\AdoptionCompleted;
use App\Notifications\ApplicationStatusChanged;
use App\Notifications\ApplicationWithdrawn;
use App\Notifications\NewApplicationReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

/**
 * Testo di tutte le righe (greeting + intro + outro) di una MailMessage,
 * concatenato: comodo per asserire la presenza di frasi tradotte senza dipendere
 * dalla posizione esatta della riga.
 */
function mailBody(MailMessage $mail): string
{
    return implode("\n", [
        (string) $mail->greeting,
        ...$mail->introLines,
        ...$mail->outroLines,
        (string) $mail->salutation,
    ]);
}

// --- ApplicationStatusChanged (all'adottante) -----------------------------------

it('ApplicationStatusChanged is queued after commit and uses mail + database', function () {
    $application = AdoptionApplication::factory()->submitted()->create();
    $notification = new ApplicationStatusChanged(
        $application,
        ApplicationStatus::Submitted,
        ApplicationStatus::UnderReview,
    );

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($application->user))->toBe(['mail', 'database']);
});

it('ApplicationStatusChanged mail carries the translated subject/body and animal name', function () {
    $animal = Animal::factory()->create(['name' => 'Fido']);
    $application = AdoptionApplication::factory()->forAnimal($animal)->submitted()->create();

    $mail = (new ApplicationStatusChanged(
        $application,
        ApplicationStatus::Submitted,
        ApplicationStatus::UnderReview,
    ))->toMail($application->user);

    $status = ApplicationStatus::UnderReview->getLabel();

    expect($mail->subject)->toBe(__('applications.status_changed.subject', ['animal' => 'Fido']))
        ->and(mailBody($mail))
        ->toContain('Fido')
        ->toContain($status)
        ->toContain($application->user->name);
});

it('ApplicationStatusChanged database payload carries ids, states and a message', function () {
    $animal = Animal::factory()->create(['name' => 'Fido']);
    $application = AdoptionApplication::factory()->forAnimal($animal)->submitted()->create();

    $payload = (new ApplicationStatusChanged(
        $application,
        ApplicationStatus::Submitted,
        ApplicationStatus::UnderReview,
    ))->toArray($application->user);

    expect($payload)
        ->toHaveKeys(['application_id', 'animal', 'from', 'to', 'message'])
        ->and($payload['application_id'])->toBe($application->getKey())
        ->and($payload['animal'])->toBe('Fido')
        ->and($payload['from'])->toBe(ApplicationStatus::Submitted->value)
        ->and($payload['to'])->toBe(ApplicationStatus::UnderReview->value)
        ->and($payload['message'])->toContain('Fido');
});

// --- NewApplicationReceived (allo staff dell'ente) ------------------------------

it('NewApplicationReceived is queued after commit and uses mail + database', function () {
    $application = AdoptionApplication::factory()->submitted()->create();
    $notification = new NewApplicationReceived($application);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($application->organization))->toBe(['mail', 'database']);
});

it('NewApplicationReceived mail carries the translated subject/body, adopter and animal name', function () {
    $animal = Animal::factory()->create(['name' => 'Luna']);
    $adopter = User::factory()->create(['name' => 'Mario Rossi']);
    $application = AdoptionApplication::factory()->forAnimal($animal)->forAdopter($adopter)->submitted()->create();

    $mail = (new NewApplicationReceived($application))->toMail($application->organization);

    expect($mail->subject)->toBe(__('applications.new_received.subject', ['animal' => 'Luna']))
        ->and(mailBody($mail))
        ->toContain('Luna')
        ->toContain('Mario Rossi');
});

it('NewApplicationReceived database payload carries ids, adopter, animal and a message', function () {
    $animal = Animal::factory()->create(['name' => 'Luna']);
    $adopter = User::factory()->create(['name' => 'Mario Rossi']);
    $application = AdoptionApplication::factory()->forAnimal($animal)->forAdopter($adopter)->submitted()->create();

    $payload = (new NewApplicationReceived($application))->toArray($application->organization);

    expect($payload)
        ->toHaveKeys(['application_id', 'animal_id', 'organization_id', 'animal', 'adopter', 'message'])
        ->and($payload['application_id'])->toBe($application->getKey())
        ->and($payload['animal_id'])->toBe($application->animal_id)
        ->and($payload['organization_id'])->toBe($application->organization_id)
        ->and($payload['animal'])->toBe('Luna')
        ->and($payload['adopter'])->toBe('Mario Rossi')
        ->and($payload['message'])->toContain('Luna')
        ->and($payload['message'])->toContain('Mario Rossi');
});

// --- AdoptionCompleted (all'adottante) ------------------------------------------

it('AdoptionCompleted is queued after commit and uses mail + database', function () {
    $adoption = makeAdoption();
    $notification = new AdoptionCompleted($adoption);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($adoption->user))->toBe(['mail', 'database']);
});

it('AdoptionCompleted mail carries the translated subject/body and animal name', function () {
    $adoption = makeAdoption('Argo');

    $mail = (new AdoptionCompleted($adoption))->toMail($adoption->user);

    expect($mail->subject)->toBe(__('adoptions.completed.subject', ['animal' => 'Argo']))
        ->and(mailBody($mail))
        ->toContain('Argo')
        ->toContain($adoption->user->name);
});

it('AdoptionCompleted database payload carries adoption id, animal and a message', function () {
    $adoption = makeAdoption('Argo');

    $payload = (new AdoptionCompleted($adoption))->toArray($adoption->user);

    expect($payload)
        ->toHaveKeys(['adoption_id', 'animal_id', 'animal', 'message'])
        ->and($payload['adoption_id'])->toBe($adoption->getKey())
        ->and($payload['animal_id'])->toBe($adoption->animal_id)
        ->and($payload['animal'])->toBe('Argo')
        ->and($payload['message'])->toContain('Argo');
});

// --- ApplicationWithdrawn (allo staff dell'ente) --------------------------------

it('ApplicationWithdrawn is queued after commit and uses mail + database', function () {
    $application = AdoptionApplication::factory()->withdrawn()->create();
    $notification = new ApplicationWithdrawn($application);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->afterCommit)->toBeTrue()
        ->and($notification->via($application->organization))->toBe(['mail', 'database']);
});

it('ApplicationWithdrawn mail carries the translated subject/body, adopter and animal name', function () {
    $animal = Animal::factory()->create(['name' => 'Zoe']);
    $adopter = User::factory()->create(['name' => 'Anna Bianchi']);
    $application = AdoptionApplication::factory()->forAnimal($animal)->forAdopter($adopter)->withdrawn()->create();

    $mail = (new ApplicationWithdrawn($application))->toMail($application->organization);

    expect($mail->subject)->toBe(__('applications.withdrawn.subject', ['animal' => 'Zoe']))
        ->and(mailBody($mail))
        ->toContain('Zoe')
        ->toContain('Anna Bianchi');
});

it('ApplicationWithdrawn database payload carries ids, adopter, animal and a message', function () {
    $animal = Animal::factory()->create(['name' => 'Zoe']);
    $adopter = User::factory()->create(['name' => 'Anna Bianchi']);
    $application = AdoptionApplication::factory()->forAnimal($animal)->forAdopter($adopter)->withdrawn()->create();

    $payload = (new ApplicationWithdrawn($application))->toArray($application->organization);

    expect($payload)
        ->toHaveKeys(['application_id', 'animal_id', 'organization_id', 'animal', 'adopter', 'message'])
        ->and($payload['application_id'])->toBe($application->getKey())
        ->and($payload['animal'])->toBe('Zoe')
        ->and($payload['adopter'])->toBe('Anna Bianchi')
        ->and($payload['message'])->toContain('Zoe')
        ->and($payload['message'])->toContain('Anna Bianchi');
});

/**
 * Adozione finalizzata "a mano" per i test di contenuto: non passa da
 * FinalizeAdoption (nessun contratto/notifica), serve solo come dato per costruire
 * la notifica e asserirne il contenuto.
 */
function makeAdoption(string $animalName = 'Rex'): Adoption
{
    $animal = Animal::factory()->create(['name' => $animalName, 'status' => AnimalStatus::Adopted]);
    $application = AdoptionApplication::factory()->forAnimal($animal)->completed()->create();

    return Adoption::query()->create([
        'animal_id' => $animal->getKey(),
        'user_id' => $application->user_id,
        'organization_id' => $application->organization_id,
        'application_id' => $application->getKey(),
        'adopted_at' => now(),
        'fee' => null,
        'currency' => 'EUR',
        'follow_up_status' => FollowUpStatus::Pending,
    ]);
}
