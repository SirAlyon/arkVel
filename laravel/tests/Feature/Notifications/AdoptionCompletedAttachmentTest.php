<?php

declare(strict_types=1);

use App\Actions\Adoptions\FinalizeAdoption;
use App\Enums\AnimalStatus;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use App\Notifications\AdoptionCompleted;
use App\Support\Contracts\ContractGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const SANITY_PDF_BYTES = '%PDF-1.4 sanity-contract-bytes';

beforeEach(function () {
    // NIENTE Notification::fake(): esercitiamo la pipeline reale mail + database.
    // MAIL_MAILER=array (phpunit.xml) → i messaggi finiscono nell'ArrayTransport,
    // ispezionabile. QUEUE_CONNECTION=sync → la notifica after-commit gira inline.
    Storage::fake('local');

    $this->app->bind(ContractGenerator::class, fn (): ContractGenerator => new class implements ContractGenerator
    {
        public function generate(Adoption $adoption): string
        {
            return SANITY_PDF_BYTES;
        }
    });
});

it('sends the AdoptionCompleted mail with the contract PDF attached through the after-commit queue', function () {
    $animal = Animal::factory()->create(['status' => AnimalStatus::AdoptionPending]);
    $application = AdoptionApplication::factory()->forAnimal($animal)->adoptionPending()->create();

    $adoption = app(FinalizeAdoption::class)->handle($application, User::factory()->create());

    // La notifica accodata after-commit ha girato inline (sync): la riga database c'è.
    expect($application->user->notifications()->count())->toBe(1)
        ->and($application->user->notifications()->first()->type)->toBe(AdoptionCompleted::class);

    // La mail è stata inviata attraverso l'ArrayTransport.
    $messages = Mail::mailer()->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);

    // L'allegato PDF è presente: il file è stato letto dal disco privato AL MOMENTO
    // dell'invio (dopo il commit), col contenuto atteso. Verifica che l'after-commit
    // + serializzazione dell'Adoption non abbiano rotto l'allegato.
    $sent = $messages->first()->getOriginalMessage();
    $attachments = collect($sent->getAttachments());

    $filename = __('adoptions.completed.contract_filename', ['id' => $adoption->getKey()]);

    expect($attachments)->toHaveCount(1)
        ->and($attachments->first()->getFilename())->toBe($filename)
        ->and($attachments->first()->getBody())->toBe(SANITY_PDF_BYTES);
});
