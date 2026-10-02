<?php

declare(strict_types=1);

use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('casts the three *_at columns to Carbon', function () {
    $thread = MessageThread::factory()->create([
        'last_message_at' => now(),
        'adopter_last_read_at' => now(),
        'org_last_read_at' => now(),
    ]);

    expect($thread->last_message_at)->toBeInstanceOf(Carbon::class)
        ->and($thread->adopter_last_read_at)->toBeInstanceOf(Carbon::class)
        ->and($thread->org_last_read_at)->toBeInstanceOf(Carbon::class);
});

it('links to organization, adopter, animal and adoption application', function () {
    $organization = Organization::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->forOrganization($organization)
        ->create();

    $thread = MessageThread::factory()
        ->forOrganization($organization)
        ->forAdopter($adopter)
        ->forAnimal($animal)
        ->withApplication($application)
        ->create();

    expect($thread->organization->is($organization))->toBeTrue()
        ->and($thread->user->is($adopter))->toBeTrue()
        ->and($thread->animal->is($animal))->toBeTrue()
        ->and($thread->adoptionApplication->is($application))->toBeTrue();
});

it('allows null optional context (animal and application)', function () {
    $thread = MessageThread::factory()->create();

    expect($thread->animal_id)->toBeNull()
        ->and($thread->adoption_application_id)->toBeNull()
        ->and($thread->animal)->toBeNull()
        ->and($thread->adoptionApplication)->toBeNull();
});

it('orders messages chronologically by id', function () {
    $thread = MessageThread::factory()->create();
    $first = Message::factory()->forThread($thread)->create();
    $second = Message::factory()->forThread($thread)->create();
    $third = Message::factory()->forThread($thread)->create();

    expect($thread->messages->pluck('id')->all())
        ->toBe([$first->getKey(), $second->getKey(), $third->getKey()]);
});

it('scopes forAdopter to the adopter participant threads', function () {
    $adopter = User::factory()->create();
    $mine = MessageThread::factory()->forAdopter($adopter)->create();
    MessageThread::factory()->create();

    $threads = MessageThread::query()->forAdopter($adopter)->get();

    expect($threads)->toHaveCount(1)
        ->and($threads->first()->is($mine))->toBeTrue();
});

it('scopes forOrganization to the tenant threads', function () {
    $org = Organization::factory()->create();
    $ours = MessageThread::factory()->forOrganization($org)->create();
    MessageThread::factory()->create();

    $threads = MessageThread::query()->forOrganization($org)->get();

    expect($threads)->toHaveCount(1)
        ->and($threads->first()->is($ours))->toBeTrue();
});

it('counts unread messages per side as those from the other side after that side last_read_at', function () {
    $adopter = User::factory()->create();
    $staff = User::factory()->create();

    // Entrambi i lati hanno letto un'ora fa.
    $thread = MessageThread::factory()
        ->forAdopter($adopter)
        ->create([
            'adopter_last_read_at' => now()->subHour(),
            'org_last_read_at' => now()->subHour(),
        ]);

    // Dopo le letture: 2 dall'adottante, 1 dallo staff.
    Message::factory()->forThread($thread)->fromUser($adopter)->create(['created_at' => now()]);
    Message::factory()->forThread($thread)->fromUser($adopter)->create(['created_at' => now()]);
    Message::factory()->forThread($thread)->fromUser($staff)->create(['created_at' => now()]);

    // Non-letto per l'ente = messaggi dell'adottante dopo org_last_read_at = 2.
    // Non-letto per l'adottante = messaggi dello staff dopo adopter_last_read_at = 1.
    expect($thread->unreadCountForOrg())->toBe(2)
        ->and($thread->unreadCountForAdopter())->toBe(1);
});

it('treats a null last_read_at as never read (all counterparty messages unread)', function () {
    $adopter = User::factory()->create();
    $staff = User::factory()->create();

    $thread = MessageThread::factory()
        ->forAdopter($adopter)
        ->create([
            'adopter_last_read_at' => null,
            'org_last_read_at' => null,
        ]);

    Message::factory()->forThread($thread)->fromUser($staff)->create();
    Message::factory()->forThread($thread)->fromUser($staff)->create();
    Message::factory()->forThread($thread)->fromUser($adopter)->create();

    expect($thread->unreadCountForAdopter())->toBe(2)
        ->and($thread->unreadCountForOrg())->toBe(1);
});

it('does not count a side own messages as unread for that side', function () {
    $adopter = User::factory()->create();

    $thread = MessageThread::factory()
        ->forAdopter($adopter)
        ->create([
            'adopter_last_read_at' => now()->subHour(),
            'org_last_read_at' => now()->subHour(),
        ]);

    // Solo messaggi dell'adottante: non contano come non-letti PER l'adottante.
    Message::factory()->forThread($thread)->fromUser($adopter)->create();
    Message::factory()->forThread($thread)->fromUser($adopter)->create();

    expect($thread->unreadCountForAdopter())->toBe(0)
        ->and($thread->unreadCountForOrg())->toBe(2);
});
