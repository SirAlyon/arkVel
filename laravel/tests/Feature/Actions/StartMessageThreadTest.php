<?php

declare(strict_types=1);

use App\Actions\Messaging\StartMessageThread;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    // I ruoli servono ad AddOrganizationMember (assegnazione ruolo al membro).
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->organization = Organization::factory()->create();
    $this->adopter = User::factory()->create();
});

/**
 * Membro verificato dell'ente (comparirà in verifiedStaff()).
 */
function verifiedStaffMember(Organization $organization, MembershipRole $role = MembershipRole::Staff): User
{
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: $role,
        verified: true,
    );

    return $user;
}

it('creates a thread with its first message, sets last_message_at and logs', function () {
    $thread = app(StartMessageThread::class)->handle(
        author: $this->adopter,
        organization: $this->organization,
        adopter: $this->adopter,
        body: 'Buongiorno, sono interessato ad adottare.',
    );

    expect($thread)->toBeInstanceOf(MessageThread::class)
        ->and($thread->organization_id)->toBe($this->organization->getKey())
        ->and($thread->user_id)->toBe($this->adopter->getKey())
        ->and($thread->last_message_at)->not->toBeNull()
        ->and($thread->messages()->count())->toBe(1)
        ->and($thread->messages()->first()->body)->toBe('Buongiorno, sono interessato ad adottare.')
        ->and($thread->messages()->first()->user_id)->toBe($this->adopter->getKey());

    $activity = Activity::query()->where('description', 'message_thread.started')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->adopter->getKey());
});

it('stores optional subject and context (animal, application)', function () {
    $animal = Animal::factory()->forOrganization($this->organization)->create();

    $thread = app(StartMessageThread::class)->handle(
        author: $this->adopter,
        organization: $this->organization,
        adopter: $this->adopter,
        body: 'Domanda su questo animale.',
        subject: 'Info su Rex',
        animal: $animal,
    );

    expect($thread->subject)->toBe('Info su Rex')
        ->and($thread->animal_id)->toBe($animal->getKey());
});

it('rejects a blank body without creating anything', function () {
    expect(fn () => app(StartMessageThread::class)->handle(
        author: $this->adopter,
        organization: $this->organization,
        adopter: $this->adopter,
        body: "   \n  ",
    ))->toThrow(InvalidArgumentException::class);

    expect(MessageThread::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('marks the author adopter side read on creation (adopter writes)', function () {
    $thread = app(StartMessageThread::class)->handle(
        author: $this->adopter,
        organization: $this->organization,
        adopter: $this->adopter,
        body: 'Primo messaggio.',
    );

    expect($thread->adopter_last_read_at)->not->toBeNull()
        ->and($thread->org_last_read_at)->toBeNull()
        // Il messaggio dell'adottante è non-letto per l'ente, letto per l'adottante.
        ->and($thread->unreadCountForOrg())->toBe(1)
        ->and($thread->unreadCountForAdopter())->toBe(0);
});

it('marks the org side read on creation (staff writes)', function () {
    $staff = verifiedStaffMember($this->organization);

    $thread = app(StartMessageThread::class)->handle(
        author: $staff,
        organization: $this->organization,
        adopter: $this->adopter,
        body: 'Ciao, la contatto io dall\'ente.',
    );

    expect($thread->org_last_read_at)->not->toBeNull()
        ->and($thread->adopter_last_read_at)->toBeNull()
        ->and($thread->unreadCountForAdopter())->toBe(1)
        ->and($thread->unreadCountForOrg())->toBe(0);
});

it('notifies the org verified staff when the adopter starts the thread', function () {
    $staff = verifiedStaffMember($this->organization);
    $otherStaff = verifiedStaffMember($this->organization, MembershipRole::Owner);

    app(StartMessageThread::class)->handle(
        author: $this->adopter,
        organization: $this->organization,
        adopter: $this->adopter,
        body: 'Messaggio dall\'adottante.',
    );

    Notification::assertSentTo($staff, NewMessageReceived::class);
    Notification::assertSentTo($otherStaff, NewMessageReceived::class);
    // L'autore (adottante) non riceve la propria notifica.
    Notification::assertNotSentTo($this->adopter, NewMessageReceived::class);
});

it('notifies the adopter when staff starts the thread', function () {
    $staff = verifiedStaffMember($this->organization);

    app(StartMessageThread::class)->handle(
        author: $staff,
        organization: $this->organization,
        adopter: $this->adopter,
        body: 'Messaggio dallo staff.',
    );

    Notification::assertSentTo($this->adopter, NewMessageReceived::class);
    // L'autore (staff) non riceve la propria notifica.
    Notification::assertNotSentTo($staff, NewMessageReceived::class);
});
