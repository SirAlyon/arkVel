<?php

declare(strict_types=1);

use App\Filament\Adopter\Pages\ManageAdopterProfile;
use App\Http\Middleware\AuthenticateAdopter;
use App\Http\Middleware\EnsureAdopterRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

uses(RefreshDatabase::class);

/*
 | Regressione (review di sicurezza UI-5, M1): le richieste /livewire/update dei componenti
 | del pannello /account devono ripassare dall'autenticazione. Livewire riapplica solo i
 | middleware PERSISTENTI (confronto sul nome esatto della classe) e Filament registra il
 | proprio Authenticate, non la sottoclasse AuthenticateAdopter: senza isPersistent un ospite
 | poteva rimandare lo snapshot di una pagina /account e far girare il componente.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Snapshot del componente $component (classe) nella pagina, come lo riceverebbe il browser.
 *
 * @param  class-string  $component
 */
function accountSnapshot(TestResponse $response, string $component): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', (string) $response->getContent(), $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES);
        $name = json_decode($snapshot, true)['memo']['name'] ?? '';

        if ($name === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException("No snapshot for {$component}");
}

/** POST /livewire/update con lo snapshot e un'azione da eseguire, come il client Livewire. */
function replaySnapshot(string $snapshot, bool $json = true): TestResponse
{
    $payload = ['components' => [[
        'snapshot' => $snapshot,
        'updates' => [],
        'calls' => [['method' => '$refresh', 'params' => [], 'metadata' => []]],
    ]]];

    $uri = app(HandleRequests::class)->getUpdateUri();

    if ($json) {
        return test()->postJson($uri, $payload, ['X-Livewire' => '1']);
    }

    return test()->call('POST', $uri, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_LIVEWIRE' => '1',
    ], (string) json_encode($payload));
}

it('registers the adopter auth middleware as persistent for Livewire', function (): void {
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())
        ->toContain(AuthenticateAdopter::class, EnsureAdopterRole::class, EnsureEmailIsVerified::class);
});

it('lets the signed-in adopter use the /account components', function (): void {
    $user = User::factory()->create();
    $page = $this->actingAs($user)->get(route('filament.adopter.pages.manage-adopter-profile'))->assertOk();

    replaySnapshot(accountSnapshot($page, ManageAdopterProfile::class))->assertOk();
});

it('refuses a guest replaying the snapshot of an /account page', function (bool $json): void {
    $user = User::factory()->create();
    $snapshot = accountSnapshot(
        $this->actingAs($user)->get(route('filament.adopter.pages.manage-adopter-profile'))->assertOk(),
        ManageAdopterProfile::class,
    );

    // Un altro browser, senza sessione.
    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $response = replaySnapshot($snapshot, $json);

    $json
        ? $response->assertUnauthorized()
        : $response->assertRedirect(route('login'));
})->with(['json client' => true, 'plain client' => false]);

it('refuses the snapshot of an /account page after signing out', function (): void {
    $user = User::factory()->create();
    $snapshot = accountSnapshot(
        $this->actingAs($user)->get(route('filament.adopter.pages.manage-adopter-profile'))->assertOk(),
        ManageAdopterProfile::class,
    );

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->app['auth']->forgetGuards();

    replaySnapshot($snapshot)->assertUnauthorized();
});

it('sends an unverified user replaying an /account snapshot to the verification notice', function (): void {
    $user = User::factory()->create();
    $snapshot = accountSnapshot(
        $this->actingAs($user)->get(route('filament.adopter.pages.manage-adopter-profile'))->assertOk(),
        ManageAdopterProfile::class,
    );

    $user->forceFill(['email_verified_at' => null])->save();

    replaySnapshot($snapshot)->assertForbidden();
});
