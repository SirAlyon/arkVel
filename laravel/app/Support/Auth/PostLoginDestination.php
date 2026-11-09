<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Site\AccountLinks;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Dove va un utente autenticato quando non c'è un URL "intended": dopo l'accesso da
 * /accedi, dopo la verifica dell'email, e quando apre una pagina per soli ospiti (middleware
 * `guest`, redirectUsersTo in bootstrap/app.php). Un solo punto per tutti questi casi.
 *
 * - Super Admin → pannello di amministrazione (/admin);
 * - membro verificato di un ente operabile → pannello degli enti (/manage), con la stessa
 *   regola del link "Gestione ente" della shell ({@see AccountLinks::canManage()});
 * - tutti gli altri → area adottante (/account).
 *
 * Perché: /account assegna il ruolo Adopter a chiunque ci entri (EnsureAdopterRole).
 * Mandarci di default lo staff degli enti lo segnerebbe come adottante.
 */
final readonly class PostLoginDestination
{
    public function __construct(private AccountLinks $accountLinks) {}

    public function for(?Authenticatable $user): string
    {
        if ($user instanceof User && $user->isSuperAdmin()) {
            return route('filament.admin.pages.dashboard');
        }

        if ($this->accountLinks->canManage($user)) {
            return $this->accountLinks->manage();
        }

        return $this->accountLinks->account();
    }
}
