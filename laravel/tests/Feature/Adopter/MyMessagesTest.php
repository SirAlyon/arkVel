<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Filament\Adopter\Pages\MyMessages;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Specchio di tests/Feature/Adopter/MyAppointmentsTest.php per la messaggistica.
// Scoping per OWNERSHIP (forAdopter); autorizzazione via MessageThreadPolicy.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un thread dell'adottante indicato verso un ente verificato con un membro
 * verificato (destinatario delle notifiche), con un messaggio iniziale dell'ente.
 *
 * @return array{0: MessageThread, 1: User} thread + membro staff verificato
 */
function adopterThreadFor(User $adopter): array
{
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    $thread = MessageThread::factory()->forOrganization($org)->forAdopter($adopter)->create();
    Message::factory()->forThread($thread)->fromUser($staff)->create(['body' => 'Ciao!']);

    return [$thread, $staff];
}

it('lets an adopter reach the my-messages page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyMessages::class)
        ->assertOk();
});

it('lists only the current adopters threads, not another adopters', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    [$mine] = adopterThreadFor($owner);
    [$theirs] = adopterThreadFor($other);

    Livewire::actingAs($owner)
        ->test(MyMessages::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('posts a reply via the open action, delegating to PostMessage and notifying staff', function () {
    $owner = User::factory()->create();
    [$thread, $staff] = adopterThreadFor($owner);

    Livewire::actingAs($owner)
        ->test(MyMessages::class)
        ->callAction(
            TestAction::make('open')->table($thread),
            ['body' => 'Grazie, sono interessato!'],
        )
        ->assertHasNoActionErrors();

    $reply = Message::query()
        ->where('message_thread_id', $thread->getKey())
        ->where('user_id', $owner->getKey())
        ->first();

    expect(Message::query()->where('message_thread_id', $thread->getKey())->where('user_id', $owner->getKey())->count())->toBe(1)
        ->and($reply?->body)->toBe('Grazie, sono interessato!');

    Notification::assertSentTo($staff, NewMessageReceived::class);
});

it('marks the adopter side read when the thread is opened (unread badge clears)', function () {
    $owner = User::factory()->create();
    [$thread] = adopterThreadFor($owner);

    // Il lato adottante parte con 1 non letto (il messaggio dell'ente).
    expect($thread->unreadCountForAdopter())->toBe(1);

    Livewire::actingAs($owner)
        ->test(MyMessages::class)
        ->mountAction(TestAction::make('open')->table($thread))
        ->assertHasNoActionErrors();

    expect($thread->fresh()->unreadCountForAdopter())->toBe(0);
});

it('denies replying to another adopters thread (policy ownership)', function () {
    $owner = User::factory()->create();
    [$thread] = adopterThreadFor($owner);

    expect($owner->can('reply', $thread))->toBeTrue();

    $intruder = User::factory()->create();
    expect($intruder->can('view', $thread))->toBeFalse()
        ->and($intruder->can('reply', $thread))->toBeFalse();
});
