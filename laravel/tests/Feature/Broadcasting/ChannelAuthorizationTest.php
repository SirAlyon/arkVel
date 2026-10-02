<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // In test l'ambiente gira su BROADCAST_CONNECTION=null (phpunit.xml): il
    // NullBroadcaster ha un auth() no-op e NON esegue le callback di autorizzazione.
    // Il vero routes/channels.php si è però già registrato su QUEL broadcaster al boot.
    // Per testare il CONFINE reale ci serve un broadcaster il cui auth() valuti davvero
    // le callback: la connessione `reverb` (driver Pusher-compatibile). Catturiamo le
    // callback di produzione dal broadcaster di boot e le copiamo su `reverb` — stessi
    // callback (policy + model binding), zero duplicazione di logica.
    $bootBroadcaster = Broadcast::connection();
    $readChannels = Closure::bind(
        fn (): array => $this->channels,
        $bootBroadcaster,
        $bootBroadcaster::class,
    );
    $productionChannels = $readChannels();

    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb', [
        'driver' => 'reverb',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'app_id' => 'test-app',
        'options' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
    ]);

    // Nessuna rete: PusherBroadcaster valida la callback e genera una firma HMAC locale.
    $reverb = Broadcast::connection('reverb');
    foreach ($productionChannels as $pattern => $callback) {
        $reverb->channel($pattern, $callback);
    }
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Membro verificato dell'ente col ruolo indicato, con il team id impostato sul
 * registrar così che i check `can()` (dentro la policy) valutino i permessi
 * team-scoped dell'ente. Rispecchia l'helper del MessageThreadPolicyTest.
 */
function channelMemberOf(Organization $organization, MembershipRole $role): User
{
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: $role,
        verified: true,
    );

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($organization->getKey());
    $registrar->forgetCachedPermissions();

    return $user;
}

/**
 * Esegue l'autorizzazione reale di un canale per un utente: costruisce la richiesta
 * di auth come farebbe il client (channel_name + socket_id), autentica l'utente e
 * delega al PusherBroadcaster, che esegue la callback registrata in routes/channels.php
 * (model binding incluso). Ritorna true se autorizzato; false se negato
 * (AccessDeniedHttpException). È il CONFINE reale del websocket.
 */
function authorizesChannel(User $user, string $channel): bool
{
    /** @var PusherBroadcaster $broadcaster */
    $broadcaster = Broadcast::connection('reverb');

    $request = Request::create('/broadcasting/auth', 'POST', [
        'channel_name' => $channel,
        'socket_id' => '1234.5678',
    ]);
    $request->setUserResolver(fn (): User => $user);

    try {
        $broadcaster->auth($request);

        return true;
    } catch (AccessDeniedHttpException) {
        return false;
    }
}

// --- Canale per-utente (campanella notifiche Filament) -----------------------

it('authorizes a user on their own notification channel', function () {
    $user = User::factory()->create();

    expect(authorizesChannel($user, 'private-App.Models.User.'.$user->getKey()))->toBeTrue();
});

it('denies a user on someone else notification channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect(authorizesChannel($user, 'private-App.Models.User.'.$other->getKey()))->toBeFalse();
});

// --- Canale per-conversazione (message-thread.{id}) --------------------------

it('authorizes the adopter participant on their thread channel', function () {
    $adopter = User::factory()->create();
    $thread = MessageThread::factory()->forAdopter($adopter)->create();

    expect(authorizesChannel($adopter, 'private-message-thread.'.$thread->getKey()))->toBeTrue();
});

it('authorizes verified org staff on a thread of their organization', function () {
    $org = Organization::factory()->create();
    $staff = channelMemberOf($org, MembershipRole::Staff);
    $thread = MessageThread::factory()->forOrganization($org)->create();

    expect(authorizesChannel($staff, 'private-message-thread.'.$thread->getKey()))->toBeTrue();
});

it('authorizes a volunteer (view-only) on a thread of their organization', function () {
    $org = Organization::factory()->create();
    $volunteer = channelMemberOf($org, MembershipRole::Volunteer);
    $thread = MessageThread::factory()->forOrganization($org)->create();

    expect(authorizesChannel($volunteer, 'private-message-thread.'.$thread->getKey()))->toBeTrue();
});

it('denies a different adopter on a thread they do not own', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $thread = MessageThread::factory()->forAdopter($owner)->create();

    expect(authorizesChannel($intruder, 'private-message-thread.'.$thread->getKey()))->toBeFalse();
});

it('denies a non-member outsider on a thread channel', function () {
    $org = Organization::factory()->create();
    $outsider = User::factory()->create();
    $thread = MessageThread::factory()->forOrganization($org)->create();

    expect(authorizesChannel($outsider, 'private-message-thread.'.$thread->getKey()))->toBeFalse();
});

it('denies cross-tenant staff on a thread of another organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staffA = channelMemberOf($orgA, MembershipRole::Staff);
    $threadB = MessageThread::factory()->forOrganization($orgB)->create();

    expect(authorizesChannel($staffA, 'private-message-thread.'.$threadB->getKey()))->toBeFalse();
});

it('authorizes verified staff even when NO spatie team is set (real /broadcasting/auth condition)', function () {
    $org = Organization::factory()->create();
    $staff = channelMemberOf($org, MembershipRole::Staff);
    $thread = MessageThread::factory()->forOrganization($org)->create();

    // CONDIZIONE REALE: la rotta /broadcasting/auth è fuori dai pannelli Filament, quindi
    // il team spatie NON è impostato. Azzeriamolo: il callback del canale deve allinearlo
    // da sé all'ente del thread, altrimenti il permesso team-scoped MessageView manca e lo
    // staff verrebbe negato (era il bug).
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(authorizesChannel($staff, 'private-message-thread.'.$thread->getKey()))->toBeTrue();
});
