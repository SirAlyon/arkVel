<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Auth\AssignAdopterRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantisce che ogni utente autenticato del pannello adopter abbia il ruolo
 * `Adopter` (team-independent): la logica di rilevamento e assegnazione è in
 * {@see AssignAdopterRole}, condivisa con la registrazione pubblica.
 *
 * Registrato nell'authMiddleware del pannello DOPO l'autenticazione (l'utente
 * esiste) e PRIMA della verifica email: come quando la prompt di verifica era una
 * pagina del pannello, anche un utente non ancora verificato riceve il ruolo al
 * primo accesso a /account. Idempotente ed economico.
 */
class EnsureAdopterRole
{
    public function __construct(
        private readonly AssignAdopterRole $assignAdopterRole,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $this->assignAdopterRole->handle($user);
        }

        return $next($request);
    }
}
