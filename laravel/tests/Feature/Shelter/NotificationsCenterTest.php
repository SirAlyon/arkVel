<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Filament\Shelter\Pages\Notifications;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Centro notifiche del pannello ente: stesso comportamento del pannello adottante
// (trait condiviso). Lo scoping è per OWNERSHIP (persona), NON per tenant: ogni
// membro vede solo le proprie notifiche anche dentro il contesto tenant.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Inserisce una riga di notifica database per l'utente indicato.
 *
 * @param  array<string, mixed>  $data
 */
function seedShelterNotification(User $user, array $data = ['message' => 'Nuova candidatura']): DatabaseNotification
{
    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\NewApplicationReceived',
        'data' => $data,
        'read_at' => null,
    ]);

    return $notification;
}

it('lets a verified staff member reach the notifications center in the tenant', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(Notifications::class)->assertOk();
});

it('lists only the current users notifications even inside a tenant (ownership, not tenant)', function () {
    $org = Organization::factory()->verified()->create();

    $staff = member($org, MembershipRole::Staff);
    $colleague = member($org, MembershipRole::Staff);

    $mine = seedShelterNotification($staff, ['message' => 'La mia notifica staff']);
    $theirs = seedShelterNotification($colleague, ['message' => 'Notifica del collega']);

    actAsInTenant($staff, $org);

    Livewire::test(Notifications::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->assertSee('La mia notifica staff')
        ->assertDontSee('Notifica del collega');
});

it('marks a single notification as read for a staff member', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    $notification = seedShelterNotification($staff);

    actAsInTenant($staff, $org);

    expect($staff->unreadNotifications()->count())->toBe(1);

    Livewire::test(Notifications::class)
        ->callAction(TestAction::make('markRead')->table($notification))
        ->assertHasNoActionErrors();

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($staff->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read for a staff member', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    seedShelterNotification($staff, ['message' => 'Uno']);
    seedShelterNotification($staff, ['message' => 'Due']);

    actAsInTenant($staff, $org);

    expect($staff->unreadNotifications()->count())->toBe(2);

    Livewire::test(Notifications::class)
        ->callAction('markAllRead')
        ->assertHasNoActionErrors();

    expect($staff->fresh()->unreadNotifications()->count())->toBe(0);
});

it('exposes an unread-count navigation badge for the staff member', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    seedShelterNotification($staff);

    actAsInTenant($staff, $org);

    expect(Notifications::getNavigationBadge())->toBe('1');
});
