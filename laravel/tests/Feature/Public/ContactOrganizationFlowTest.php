<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Livewire\AnimalDetail;
use App\Models\Animal;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Specchio di tests/Feature/Public/BookAppointmentFlowTest.php per il ramo
// "Contatta l'ente": ospite → login; autenticato → delega a StartMessageThread;
// ricontatto → riuso del thread esistente (nessun duplicato).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un ente verificato con almeno un membro verificato (destinatario delle notifiche)
 * e un animale pubblicamente visibile.
 *
 * @return array{0: Animal, 1: User}
 */
function verifiedOrgAnimalWithMessageStaff(): array
{
    $org = Organization::factory()->verified()->create();

    $staff = User::factory()->create();
    app(AddOrganizationMember::class)->handle(
        organization: $org,
        user: $staff,
        role: MembershipRole::Staff,
        verified: true,
    );

    $animal = Animal::factory()->available()->forOrganization($org)->create(['name' => 'Argo']);

    return [$animal, $staff];
}

it('shows the contact CTA on the public animal page', function () {
    [$animal] = verifiedOrgAnimalWithMessageStaff();

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee(__('messaging.contact.cta_title'));
});

it('redirects a guest to the adopter login and creates no thread', function () {
    [$animal] = verifiedOrgAnimalWithMessageStaff();

    Livewire::test(AnimalDetail::class, ['animal' => $animal])
        ->set('contactBody', 'Vorrei saperne di più su Argo.')
        ->call('contact')
        ->assertRedirect(route('login'));

    expect(MessageThread::query()->count())->toBe(0)
        ->and(session('url.intended'))->toBe(route('animals.show', $animal));

    Notification::assertNothingSent();
});

it('starts a conversation and notifies the org staff for an authenticated adopter', function () {
    [$animal, $staff] = verifiedOrgAnimalWithMessageStaff();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->set('contactBody', 'Vorrei saperne di più su Argo.')
        ->call('contact')
        ->assertHasNoErrors()
        ->assertSet('contactMessageIsError', false)
        ->assertSet('contactMessage', __('messaging.contact.flash_success'));

    $threads = MessageThread::query()->get();

    expect($threads)->toHaveCount(1);

    $thread = $threads->first();

    expect($thread->organization_id)->toBe($animal->organization_id)
        ->and($thread->user_id)->toBe($user->getKey())
        ->and($thread->animal_id)->toBe($animal->getKey())
        ->and($thread->messages()->count())->toBe(1)
        ->and($thread->messages()->first()->user_id)->toBe($user->getKey());

    Notification::assertSentTo($staff, NewMessageReceived::class);
});

it('validates the contact form and creates no thread on empty input', function () {
    [$animal] = verifiedOrgAnimalWithMessageStaff();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('contact')
        ->assertHasErrors(['contactBody']);

    expect(MessageThread::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('reuses the existing thread on a second contact (no duplicate)', function () {
    [$animal, $staff] = verifiedOrgAnimalWithMessageStaff();

    $user = User::factory()->create();

    // Primo contatto: crea il thread.
    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->set('contactBody', 'Primo messaggio.')
        ->call('contact')
        ->assertHasNoErrors();

    // Secondo contatto per la stessa terna: posta sul thread esistente.
    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->set('contactBody', 'Secondo messaggio.')
        ->call('contact')
        ->assertHasNoErrors()
        ->assertSet('contactMessageIsError', false)
        ->assertSet('contactMessage', __('messaging.contact.flash_appended'));

    // Un solo thread, due messaggi (entrambi dell'adottante).
    expect(MessageThread::query()->count())->toBe(1);

    $thread = MessageThread::query()->first();

    expect($thread->messages()->count())->toBe(2)
        ->and(Message::query()->where('message_thread_id', $thread->getKey())->where('user_id', $user->getKey())->count())->toBe(2);

    // Due notifiche in totale (una per messaggio) allo staff.
    Notification::assertSentToTimes($staff, NewMessageReceived::class, 2);
});
