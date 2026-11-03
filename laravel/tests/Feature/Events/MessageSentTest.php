<?php

declare(strict_types=1);

use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\StartMessageThread;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->organization = Organization::factory()->create();
    $this->adopter = User::factory()->create();
});

/**
 * Membro verificato dell'ente (comparirà in verifiedStaff()).
 */
function eventStaffMember(Organization $organization, MembershipRole $role = MembershipRole::Staff): User
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

// --- Contratto dell'evento ---------------------------------------------------

it('is a broadcast event dispatched after commit', function () {
    expect(is_subclass_of(MessageSent::class, ShouldBroadcast::class))->toBeTrue()
        ->and(is_subclass_of(MessageSent::class, ShouldDispatchAfterCommit::class))->toBeTrue();
});

it('broadcasts on the private per-thread channel', function () {
    $thread = MessageThread::factory()->forAdopter($this->adopter)->create();
    $message = Message::factory()->forThread($thread)->fromUser($this->adopter)->create();

    $event = new MessageSent($thread, $message);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-message-thread.'.$thread->getKey())
        ->and($event->broadcastAs())->toBe('message.sent');
});

it('carries only ids and sender side, no PII', function () {
    $thread = MessageThread::factory()->forAdopter($this->adopter)->create();
    $staff = eventStaffMember($this->organization);
    // Messaggio dello staff (lato "org") con un corpo che NON deve trapelare.
    $message = Message::factory()->forThread($thread)->fromUser($staff)->create([
        'body' => 'Dati sensibili che non devono finire sul websocket.',
    ]);

    $payload = (new MessageSent($thread, $message))->broadcastWith();

    expect($payload)->toBe([
        'thread_id' => $thread->getKey(),
        'message_id' => $message->getKey(),
        'sender_side' => 'org',
    ]);

    // Nessun corpo/nome nel payload.
    $flat = implode('|', array_map('strval', $payload));
    expect($flat)->not->toContain('Dati sensibili')
        ->and($flat)->not->toContain($staff->name);
});

it('labels the sender side as adopter when the adopter is the sender', function () {
    $thread = MessageThread::factory()->forAdopter($this->adopter)->create();
    $message = Message::factory()->forThread($thread)->fromUser($this->adopter)->create();

    expect((new MessageSent($thread, $message))->broadcastWith()['sender_side'])->toBe('adopter');
});

// --- Dispatch dalle Action ---------------------------------------------------

it('is dispatched by PostMessage on the thread channel', function () {
    Notification::fake();
    Event::fake([MessageSent::class]);

    $thread = MessageThread::factory()
        ->forOrganization($this->organization)
        ->forAdopter($this->adopter)
        ->create();

    app(PostMessage::class)->handle($thread, $this->adopter, 'Nuovo messaggio.');

    Event::assertDispatched(
        MessageSent::class,
        fn (MessageSent $event): bool => $event->broadcastOn()[0]->name === 'private-message-thread.'.$thread->getKey()
            && $event->senderSide === 'adopter',
    );
});

it('is dispatched by StartMessageThread on the new thread channel', function () {
    Notification::fake();
    Event::fake([MessageSent::class]);

    app(StartMessageThread::class)->handle(
        author: $this->adopter,
        organization: $this->organization,
        adopter: $this->adopter,
        body: 'Buongiorno, vorrei informazioni.',
    );

    Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
        $thread = MessageThread::query()->findOrFail($event->threadId);

        return $event->broadcastOn()[0]->name === 'private-message-thread.'.$thread->getKey()
            && $event->senderSide === 'adopter';
    });
});

it('is NOT dispatched when the body is blank', function () {
    Notification::fake();
    Event::fake([MessageSent::class]);

    $thread = MessageThread::factory()
        ->forOrganization($this->organization)
        ->forAdopter($this->adopter)
        ->create();

    expect(fn () => app(PostMessage::class)->handle($thread, $this->adopter, '   '))
        ->toThrow(InvalidArgumentException::class);

    Event::assertNotDispatched(MessageSent::class);
});
