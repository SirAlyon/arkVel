<?php

declare(strict_types=1);

use App\Actions\Messaging\PostMessage;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Message;
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
    $this->thread = MessageThread::factory()
        ->forOrganization($this->organization)
        ->forAdopter($this->adopter)
        ->create([
            'last_message_at' => now()->subDay(),
            'adopter_last_read_at' => now()->subDay(),
            'org_last_read_at' => now()->subDay(),
        ]);
});

/**
 * Membro verificato dell'ente (comparirà in verifiedStaff()).
 */
function postStaffMember(Organization $organization, MembershipRole $role = MembershipRole::Staff): User
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

it('posts a message, bumps last_message_at and logs', function () {
    $before = $this->thread->last_message_at;

    $message = app(PostMessage::class)->handle($this->thread, $this->adopter, 'Un altro messaggio.');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->body)->toBe('Un altro messaggio.')
        ->and($message->user_id)->toBe($this->adopter->getKey())
        ->and($this->thread->fresh()->last_message_at->gt($before))->toBeTrue();

    $activity = Activity::query()->where('description', 'message.posted')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->adopter->getKey());
});

it('rejects a blank body without creating a message', function () {
    expect(fn () => app(PostMessage::class)->handle($this->thread, $this->adopter, '   '))
        ->toThrow(InvalidArgumentException::class);

    expect($this->thread->messages()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('marks the adopter side read when the adopter posts', function () {
    app(PostMessage::class)->handle($this->thread, $this->adopter, 'Scrivo io adottante.');

    $fresh = $this->thread->fresh();

    // Il messaggio dell'adottante è letto per l'adottante, non-letto per l'ente.
    expect($fresh->unreadCountForAdopter())->toBe(0)
        ->and($fresh->unreadCountForOrg())->toBe(1);
});

it('marks the org side read when staff posts', function () {
    $staff = postStaffMember($this->organization);

    app(PostMessage::class)->handle($this->thread, $staff, 'Rispondo io dell\'ente.');

    $fresh = $this->thread->fresh();

    expect($fresh->unreadCountForOrg())->toBe(0)
        ->and($fresh->unreadCountForAdopter())->toBe(1);
});

it('notifies the org verified staff when the adopter posts', function () {
    $staff = postStaffMember($this->organization);

    app(PostMessage::class)->handle($this->thread, $this->adopter, 'Messaggio adottante.');

    Notification::assertSentTo($staff, NewMessageReceived::class);
    Notification::assertNotSentTo($this->adopter, NewMessageReceived::class);
});

it('notifies the adopter when staff posts', function () {
    $staff = postStaffMember($this->organization);

    app(PostMessage::class)->handle($this->thread, $staff, 'Risposta dello staff.');

    Notification::assertSentTo($this->adopter, NewMessageReceived::class);
    Notification::assertNotSentTo($staff, NewMessageReceived::class);
});

it('computes unread counts correctly across a back-and-forth exchange', function () {
    $staff = postStaffMember($this->organization);

    // Facciamo avanzare il tempo fra i post così che i marcatori di lettura e i
    // timestamp dei messaggi siano confrontabili in modo deterministico (in
    // produzione i post avvengono in richieste distinte, con istanti diversi).
    Carbon\Carbon::setTestNow(now()->startOfMinute());

    // Adottante scrive 2, staff scrive 1 (nessuna lettura esplicita nel mezzo).
    Carbon\Carbon::setTestNow(now()->addMinute());
    app(PostMessage::class)->handle($this->thread, $this->adopter, 'Uno.');

    Carbon\Carbon::setTestNow(now()->addMinute());
    app(PostMessage::class)->handle($this->thread, $this->adopter, 'Due.');

    Carbon\Carbon::setTestNow(now()->addMinute());
    app(PostMessage::class)->handle($this->thread, $staff, 'Tre.');

    $fresh = $this->thread->fresh();

    // Scrivendo "Tre" l'ente ha "letto" fino a quel momento (org_last_read_at
    // dopo i due dell'adottante) → 0 non-letti per l'ente. L'adottante non legge
    // dopo "Due", quindi "Tre" (dello staff) è non-letto per l'adottante → 1.
    expect($fresh->unreadCountForOrg())->toBe(0)
        ->and($fresh->unreadCountForAdopter())->toBe(1);

    Carbon\Carbon::setTestNow();
});
