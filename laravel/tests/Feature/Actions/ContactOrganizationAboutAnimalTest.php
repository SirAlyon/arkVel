<?php

declare(strict_types=1);

use App\Actions\Messaging\ContactOrganizationAboutAnimal;
use App\Models\Animal;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->organization = Organization::factory()->verified()->create();
    $this->animal = Animal::factory()->available()->forOrganization($this->organization)->create();
    $this->adopter = User::factory()->create();
});

it('starts a conversation about the animal when none exists', function () {
    $result = app(ContactOrganizationAboutAnimal::class)
        ->handle($this->adopter, $this->animal, 'Vorrei conoscerlo.');

    expect($result->reusedExistingThread)->toBeFalse()
        ->and(MessageThread::query()->count())->toBe(1)
        ->and($result->thread->organization_id)->toBe($this->organization->getKey())
        ->and($result->thread->user_id)->toBe($this->adopter->getKey())
        ->and($result->thread->animal_id)->toBe($this->animal->getKey())
        ->and($result->thread->messages()->sole()->body)->toBe('Vorrei conoscerlo.');
});

it('appends to the existing conversation for the same adopter, organization and animal', function () {
    $action = app(ContactOrganizationAboutAnimal::class);

    $first = $action->handle($this->adopter, $this->animal, 'Primo messaggio.');
    $second = $action->handle($this->adopter, $this->animal, 'Secondo messaggio.');

    expect($second->reusedExistingThread)->toBeTrue()
        ->and($second->thread->is($first->thread))->toBeTrue()
        ->and(MessageThread::query()->count())->toBe(1)
        ->and($first->thread->messages()->count())->toBe(2);
});

it('appends to the most recent conversation when several exist for the same animal', function () {
    $older = MessageThread::factory()->forAnimal($this->animal)->forAdopter($this->adopter)
        ->create(['last_message_at' => now()->subDays(5)]);
    $newer = MessageThread::factory()->forAnimal($this->animal)->forAdopter($this->adopter)
        ->create(['last_message_at' => now()->subDay()]);

    $result = app(ContactOrganizationAboutAnimal::class)
        ->handle($this->adopter, $this->animal, 'Ancora io.');

    expect($result->thread->is($newer))->toBeTrue()
        ->and($newer->messages()->count())->toBe(1)
        ->and($older->messages()->count())->toBe(0);
});

it('never reuses a conversation of another adopter or about another animal', function () {
    $otherAnimal = Animal::factory()->available()->forOrganization($this->organization)->create();

    MessageThread::factory()->forAnimal($this->animal)->forAdopter(User::factory()->create())->create();
    MessageThread::factory()->forAnimal($otherAnimal)->forAdopter($this->adopter)->create();

    $result = app(ContactOrganizationAboutAnimal::class)
        ->handle($this->adopter, $this->animal, 'Una domanda.');

    expect($result->reusedExistingThread)->toBeFalse()
        ->and(MessageThread::query()->count())->toBe(3)
        ->and($result->thread->user_id)->toBe($this->adopter->getKey())
        ->and($result->thread->animal_id)->toBe($this->animal->getKey());
});

it('rejects a blank body', function () {
    app(ContactOrganizationAboutAnimal::class)->handle($this->adopter, $this->animal, '   ');
})->throws(InvalidArgumentException::class);
