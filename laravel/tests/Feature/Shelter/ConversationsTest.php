<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Filament\Shelter\Pages\Conversations;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\FosterApplication;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Gli helper member() e actAsInTenant() sono in tests/Pest.php (condivisi dallo
// shelter). Specchio di AppointmentCalendarTest.php per la messaggistica.
// Scoping TENANT (forOrganization); autorizzazione via MessageThreadPolicy.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un thread dell'ente indicato con un adottante dato e un messaggio iniziale
 * dell'adottante (così il lato ente ha 1 non letto).
 */
function orgThreadFor(Organization $organization, ?User $adopter = null): MessageThread
{
    $adopter ??= User::factory()->create();

    $thread = MessageThread::factory()->forOrganization($organization)->forAdopter($adopter)->create();
    Message::factory()->forThread($thread)->fromUser($adopter)->create(['body' => 'Buongiorno!']);

    return $thread;
}

it('renders the conversations page for a verified staff member of the tenant', function () {
    $orgA = Organization::factory()->verified()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(Conversations::class)->assertOk();
});

it('lists only the current tenants threads (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $mine = orgThreadFor($orgA);
    $theirs = orgThreadFor($orgB);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(Conversations::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('does not resolve a thread of another tenant (404 backstop)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $threadB = orgThreadFor($orgB);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $instance = Livewire::test(Conversations::class)->instance();

    expect(fn () => $instance->resolveTenantThread($threadB->getKey()))
        ->toThrow(ModelNotFoundException::class);
});

it('posts a reply as staff via the open action, delegating to PostMessage and notifying the adopter', function () {
    $orgA = Organization::factory()->verified()->create();
    $adopter = User::factory()->create();
    $thread = orgThreadFor($orgA, $adopter);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(Conversations::class)
        ->callAction(
            TestAction::make('open')->table($thread),
            ['body' => 'Le rispondiamo volentieri.'],
        )
        ->assertHasNoActionErrors();

    expect(Message::query()->where('message_thread_id', $thread->getKey())->where('user_id', $staff->getKey())->count())->toBe(1);

    Notification::assertSentTo($adopter, NewMessageReceived::class);
});

it('marks the org side read when the thread is opened (unread badge clears)', function () {
    $orgA = Organization::factory()->verified()->create();
    $thread = orgThreadFor($orgA);

    expect($thread->unreadCountForOrg())->toBe(1);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(Conversations::class)
        ->mountAction(TestAction::make('open')->table($thread))
        ->assertHasNoActionErrors();

    expect($thread->fresh()->unreadCountForOrg())->toBe(0);
});

it('lets a volunteer open a thread read-only but denies replying server-side', function () {
    $orgA = Organization::factory()->verified()->create();
    $thread = orgThreadFor($orgA);

    // Il volontario ha solo MessageView (no MessageManage).
    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    // Può aprire (view) senza errori...
    Livewire::test(Conversations::class)
        ->mountAction(TestAction::make('open')->table($thread))
        ->assertHasNoActionErrors();

    // ... ma non può gestire né rispondere (difesa in profondità sull'ability).
    expect($volunteer->can('view', $thread))->toBeTrue()
        ->and($volunteer->can('reply', $thread))->toBeFalse()
        ->and(Livewire::test(Conversations::class)->instance()->canManageMessages())->toBeFalse();

    // Il box di risposta è disabilitato per il volontario.
    Livewire::test(Conversations::class)
        ->mountAction(TestAction::make('open')->table($thread))
        ->assertFormFieldDisabled('body');
});

it('hides the new-conversation action from a volunteer', function () {
    $orgA = Organization::factory()->verified()->create();

    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    Livewire::test(Conversations::class)
        ->assertActionHidden(TestAction::make('newThread'));
});

it('starts a new conversation with a related adopter and notifies them', function () {
    $orgA = Organization::factory()->verified()->create();

    // Adottante in relazione con l'ente (ha una candidatura).
    $adopter = User::factory()->create();
    AdoptionApplication::factory()->forOrganization($orgA)->forAdopter($adopter)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(Conversations::class)
        ->callAction('newThread', data: [
            'user_id' => $adopter->getKey(),
            'subject' => 'Aggiornamento',
            'body' => 'La aggiorniamo sulla pratica.',
        ])
        ->assertHasNoActionErrors();

    $thread = MessageThread::query()
        ->where('organization_id', $orgA->getKey())
        ->where('user_id', $adopter->getKey())
        ->first();

    expect($thread)->not->toBeNull()
        ->and($thread->subject)->toBe('Aggiornamento')
        ->and($thread->messages()->where('user_id', $staff->getKey())->count())->toBe(1);

    Notification::assertSentTo($adopter, NewMessageReceived::class);
});

it('scopes the new-conversation adopter options to org-related users only (GDPR)', function () {
    $orgA = Organization::factory()->verified()->create();

    // Quattro relazioni diverse con l'ente → tutti selezionabili.
    $viaAdoption = User::factory()->create();
    AdoptionApplication::factory()->forOrganization($orgA)->forAdopter($viaAdoption)->create();

    $viaFoster = User::factory()->create();
    FosterApplication::factory()->forOrganization($orgA)->forAdopter($viaFoster)->create();

    $viaAppointment = User::factory()->create();
    $animalA = Animal::factory()->available()->forOrganization($orgA)->create();
    Appointment::factory()->forAnimal($animalA)->forUser($viaAppointment)->completed()->create();

    $viaThread = User::factory()->create();
    orgThreadFor($orgA, $viaThread);

    // Utente TOTALMENTE estraneo all'ente → NON selezionabile.
    $unrelated = User::factory()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $reflection = new ReflectionMethod(Conversations::class, 'relatedAdopterOptions');
    $reflection->setAccessible(true);
    $options = $reflection->invoke(Livewire::test(Conversations::class)->instance());

    expect(array_keys($options))
        ->toContain($viaAdoption->getKey())
        ->toContain($viaFoster->getKey())
        ->toContain($viaAppointment->getKey())
        ->toContain($viaThread->getKey())
        ->not->toContain($unrelated->getKey());
});

it('rejects starting a conversation with an unrelated user (GDPR backstop)', function () {
    $orgA = Organization::factory()->verified()->create();

    // Utente NON in relazione con l'ente: fuori dall'allow-list.
    $unrelated = User::factory()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(Conversations::class)
        ->callAction('newThread', data: [
            'user_id' => $unrelated->getKey(),
            'body' => 'Tentativo di contatto non pertinente.',
        ]);

    // Backstop server-side: nessun thread creato per un utente estraneo.
    expect(MessageThread::query()->where('user_id', $unrelated->getKey())->count())->toBe(0);
});

it('denies access to a non-member of the tenant', function () {
    $orgA = Organization::factory()->verified()->create();

    $outsider = User::factory()->create();
    actAsInTenant($outsider, $orgA);

    expect(Conversations::canAccess())->toBeFalse();
});
