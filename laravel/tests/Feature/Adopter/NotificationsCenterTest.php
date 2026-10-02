<?php

declare(strict_types=1);

use App\Filament\Adopter\Pages\Notifications;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Centro notifiche del pannello adottante: storico completo (oltre alla campanella),
// scoping per OWNERSHIP (relazione notifications() dell'utente autenticato), lettura
// via markAsRead() nativo di Laravel.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Inserisce una riga di notifica database per l'utente, con payload opzionale.
 *
 * @param  array<string, mixed>  $data
 */
function seedNotification(User $user, array $data = ['message' => 'Ciao!'], ?string $readAt = null): DatabaseNotification
{
    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\ApplicationStatusChanged',
        'data' => $data,
        'read_at' => $readAt,
    ]);

    return $notification;
}

it('lets an authenticated adopter reach the notifications center', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->assertOk();
});

it('lists only the current users notifications, not another users (ownership isolation)', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = seedNotification($owner, ['message' => 'La mia notifica']);
    $theirs = seedNotification($other, ['message' => 'Notifica altrui']);

    Livewire::actingAs($owner)
        ->test(Notifications::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->assertSee('La mia notifica')
        ->assertDontSee('Notifica altrui');
});

it('shows the fallback message when the payload has no message', function () {
    $user = User::factory()->create();
    seedNotification($user, ['foo' => 'bar']);

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->assertSee(__('notifications_center.fallback_message'));
});

it('marks a single notification as read (unread count drops)', function () {
    $user = User::factory()->create();
    $notification = seedNotification($user);

    Auth::login($user);
    expect($user->unreadNotifications()->count())->toBe(1);

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->callAction(TestAction::make('markRead')->table($notification))
        ->assertHasNoActionErrors();

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read (unread count clears)', function () {
    $user = User::factory()->create();
    seedNotification($user, ['message' => 'Uno']);
    seedNotification($user, ['message' => 'Due']);
    seedNotification($user, ['message' => 'Tre']);

    expect($user->unreadNotifications()->count())->toBe(3);

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->callAction('markAllRead')
        ->assertHasNoActionErrors();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('does not touch another users notifications when marking all read', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    seedNotification($owner, ['message' => 'Mia']);
    $theirs = seedNotification($other, ['message' => 'Altrui']);

    Livewire::actingAs($owner)
        ->test(Notifications::class)
        ->callAction('markAllRead')
        ->assertHasNoActionErrors();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(0)
        ->and($theirs->fresh()->read_at)->toBeNull();
});

it('exposes an unread-count navigation badge for the current user', function () {
    $user = User::factory()->create();
    seedNotification($user, ['message' => 'Uno']);
    seedNotification($user, ['message' => 'Due']);

    Auth::login($user);

    expect(Notifications::getNavigationBadge())->toBe('2');

    $user->unreadNotifications->markAsRead();

    expect(Notifications::getNavigationBadge())->toBeNull();
});

it('filters to unread-only notifications', function () {
    $user = User::factory()->create();
    $unread = seedNotification($user, ['message' => 'Non letta']);
    $read = seedNotification($user, ['message' => 'Gia letta'], readAt: now()->toDateTimeString());

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->filterTable('unread', true)
        ->assertCanSeeTableRecords([$unread])
        ->assertCanNotSeeTableRecords([$read]);
});
