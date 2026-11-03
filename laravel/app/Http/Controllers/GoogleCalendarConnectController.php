<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Enums\CalendarSyncDirection;
use App\Enums\PermissionName;
use App\Filament\Shelter\Pages\CalendarConnections;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Models\User;
use App\Support\Calendar\Google\GoogleOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Flusso OAuth per collegare un Google Calendar a un ente (Cal-5).
 *
 * Due tappe:
 *  - {@see redirect()} — autorizza (`calendar.manage` sul tenant corrente), memorizza in
 *    sessione il tenant e un `state` anti-CSRF, poi reindirizza al consenso Google.
 *  - {@see callback()} — verifica lo `state`, scambia il `code`, e crea/aggiorna la
 *    {@see CalendarConnection} Google del tenant con i token cifrati.
 *
 * Il flusso gira FUORI dal contesto URL del pannello (rotte web standard), quindi il
 * tenant è risolto dalla SESSIONE (impostata in redirect), MAI dall'URL — e il permesso
 * `calendar.manage` è ri-verificato in entrambe le tappe con il team id allineato al
 * tenant, così non c'è modo di collegare un calendario a un ente di cui non si è staff.
 *
 * Config-gating: se Google non è configurato ({@see GoogleOAuth::configured()}) la
 * redirect aborta con 404 (la funzione è inerte).
 */
final class GoogleCalendarConnectController extends Controller
{
    private const string SESSION_STATE = 'google_calendar.oauth_state';

    private const string SESSION_TENANT = 'google_calendar.tenant_id';

    public function __construct(
        private readonly GoogleOAuth $oauth,
    ) {}

    /**
     * Avvia il consenso OAuth: autorizza sul tenant corrente, salva state + tenant in
     * sessione, reindirizza a Google.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(GoogleOAuth::configured(), 404);

        $user = $this->currentUser();
        $tenant = $this->resolveTenant($request);

        abort_if($tenant === null, 404);
        $this->authorizeManage($user, $tenant);

        $state = Str::random(40);

        $request->session()->put(self::SESSION_STATE, $state);
        $request->session()->put(self::SESSION_TENANT, $tenant->getKey());

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    /**
     * Callback OAuth: valida lo state, scambia il code, crea/aggiorna la connessione
     * Google del tenant e torna alla pagina di sincronizzazione con un flash localizzato.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleOAuth::configured(), 404);

        $user = $this->currentUser();

        $expectedState = $request->session()->pull(self::SESSION_STATE);
        $tenantId = $request->session()->pull(self::SESSION_TENANT);
        $providedState = $request->query('state');

        // state assente/non combaciante → possibile CSRF: rifiutiamo.
        if (! is_string($expectedState) || ! is_string($providedState) || ! hash_equals($expectedState, $providedState)) {
            return $this->backToConnections($tenantId)
                ->with('calendar_error', __('calendar.connections.google.error'));
        }

        $tenant = is_int($tenantId) || (is_string($tenantId) && ctype_digit($tenantId))
            ? Organization::query()->find((int) $tenantId)
            : null;

        abort_if($tenant === null, 404);
        $this->authorizeManage($user, $tenant);

        // Utente che ha negato il consenso (o errore Google): torniamo con avviso.
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->backToConnections($tenant->getKey())
                ->with('calendar_error', __('calendar.connections.google.error'));
        }

        $tokens = $this->oauth->exchangeCode($code);

        $this->storeConnection($tenant, $tokens);

        return $this->backToConnections($tenant->getKey())
            ->with('calendar_success', __('calendar.connections.google.connected'));
    }

    /**
     * Crea o aggiorna la connessione Google del tenant (una sola per ente): tokens
     * cifrati, calendario 'primary', direzione bidirezionale, stato Attivo.
     *
     * @param  array{access_token: string, refresh_token: string|null, expires_in: int, scope: string|null, token_type: string|null}  $tokens
     */
    private function storeConnection(Organization $tenant, array $tokens): void
    {
        CalendarConnection::query()->updateOrCreate(
            [
                'organization_id' => $tenant->getKey(),
                'provider' => CalendarProvider::Google,
            ],
            [
                'name' => __('calendar.connections.google.name'),
                'external_calendar_id' => 'primary',
                'credentials' => [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_at' => Carbon::now()->addSeconds(max($tokens['expires_in'], 0))->toIso8601String(),
                    'scope' => $tokens['scope'],
                    'token_type' => $tokens['token_type'],
                ],
                'direction' => CalendarSyncDirection::Both,
                'status' => CalendarConnectionStatus::Active,
                'last_error' => null,
            ],
        );
    }

    /**
     * Utente autenticato (garantito dal middleware `auth` sulle rotte).
     */
    private function currentUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Risolve il tenant di partenza dalla query `tenant` (slug o id): è la pagina del
     * pannello a passarlo, così sappiamo a quale ente collegare il calendario.
     */
    private function resolveTenant(Request $request): ?Organization
    {
        $identifier = $request->query('tenant');

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        $organization = Organization::query()->where('slug', $identifier)->first();

        if ($organization === null && ctype_digit($identifier)) {
            $organization = Organization::query()->find((int) $identifier);
        }

        return $organization;
    }

    /**
     * Ri-verifica il permesso `calendar.manage` con il team id allineato al tenant e la
     * membership verificata: nessun collegamento a un ente di cui non si è staff.
     */
    private function authorizeManage(User $user, Organization $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        abort_unless(
            $user->can(PermissionName::CalendarManage->value) && $user->isVerifiedMemberOf($tenant),
            403,
        );
    }

    /**
     * Redirect alla pagina di sincronizzazione del pannello ente per il tenant dato.
     */
    private function backToConnections(int|string|null $tenantId): RedirectResponse
    {
        $tenant = is_int($tenantId) || (is_string($tenantId) && ctype_digit($tenantId))
            ? Organization::query()->find((int) $tenantId)
            : null;

        if ($tenant === null) {
            return redirect()->route('filament.shelter.pages.dashboard');
        }

        return redirect()->to(
            CalendarConnections::getUrl(tenant: $tenant, panel: 'shelter'),
        );
    }
}
