<?php

declare(strict_types=1);

use App\Actions\Messaging\PostMessage;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Events\MessageSent;
use App\Listeners\BroadcastDatabaseNotificationSent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->organization = Organization::factory()->create();
    $this->adopter = User::factory()->create();
});

/**
 * Membro verificato dell'ente (comparirà in verifiedStaff()).
 */
function bellStaffMember(Organization $organization, MembershipRole $role = MembershipRole::Staff): User
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

it('keeps NewMessageReceived on the database channel that drives the live bell', function () {
    $user = User::factory()->create();
    $thread = MessageThread::factory()->forAdopter($user)->create();
    $message = Message::factory()->forThread($thread)->fromUser($user)->create();

    // Il percorso realtime della campanella passa dal canale `database` (via il
    // listener): la notifica deve continuare a dichiararlo (oltre a `mail`).
    expect((new NewMessageReceived($thread, $message))->via($user))->toContain('database');
});

it('re-emits DatabaseNotificationsSent for a database notification to a user', function () {
    Event::fake([DatabaseNotificationsSent::class]);

    $user = User::factory()->create();
    $thread = MessageThread::factory()->forAdopter($user)->create();
    $message = Message::factory()->forThread($thread)->fromUser($user)->create();

    // La notifica è solo il veicolo del NotificationSent; il canale è `database`.
    (new BroadcastDatabaseNotificationSent)->handle(
        new NotificationSent($user, new NewMessageReceived($thread, $message), 'database'),
    );

    Event::assertDispatched(
        DatabaseNotificationsSent::class,
        // broadcastOn() ritorna la stringa del canale (PrivateChannel::__toString):
        // l'evento Filament trasmette sul canale privato dell'utente destinatario.
        fn (DatabaseNotificationsSent $event): bool => (string) $event->broadcastOn() === 'private-App.Models.User.'.$user->getKey(),
    );
});

it('ignores non-database channels (e.g. mail) for the live bell', function () {
    Event::fake([DatabaseNotificationsSent::class]);

    $user = User::factory()->create();
    $thread = MessageThread::factory()->forAdopter($user)->create();
    $message = $thread->messages()->create(['user_id' => $user->getKey(), 'body' => 'x']);

    (new BroadcastDatabaseNotificationSent)->handle(
        new NotificationSent($user, new NewMessageReceived($thread, $message), 'mail'),
    );

    Event::assertNotDispatched(DatabaseNotificationsSent::class);
});

it('makes the bell live end-to-end when a message is posted', function () {
    // NON fake-iamo le notifiche: il canale `database` deve emettere davvero
    // NotificationSent, così il listener ri-emette DatabaseNotificationsSent. Fake
    // solo gli eventi che asseriamo (e MessageSent per non dipendere dal broadcaster).
    Event::fake([DatabaseNotificationsSent::class, MessageSent::class]);

    $staff = bellStaffMember($this->organization);
    $thread = MessageThread::factory()
        ->forOrganization($this->organization)
        ->forAdopter($this->adopter)
        ->create();

    // L'adottante scrive: la controparte (staff verificato) riceve la notifica DB.
    app(PostMessage::class)->handle($thread, $this->adopter, 'Ciao, novità?');

    // Il badge dello staff va live: DatabaseNotificationsSent sul suo canale utente.
    Event::assertDispatched(
        DatabaseNotificationsSent::class,
        fn (DatabaseNotificationsSent $event): bool => (string) $event->broadcastOn() === 'private-App.Models.User.'.$staff->getKey(),
    );
});
