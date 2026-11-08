<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Exceptions\AlreadyAuthenticated;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Le Action dei moduli per ospiti (accesso, registrazione, richiesta e reimpostazione della
 * password) partono solo senza una sessione autenticata.
 *
 * Il middleware `guest` delle route vale per la GET della pagina, non per le richieste
 * Livewire che seguono (/livewire/update): chi accede in un'altra scheda e poi invia il
 * modulo rimasto aperto arriverebbe all'Action da autenticato. Qui l'Action si ferma con
 * {@see AlreadyAuthenticated}, che porta la destinazione dell'utente
 * ({@see PostLoginDestination}).
 */
final readonly class GuestOnly
{
    public function __construct(
        private AuthFactory $auth,
        private PostLoginDestination $destination,
    ) {}

    /**
     * @throws AlreadyAuthenticated
     */
    public function ensure(): void
    {
        $guard = $this->auth->guard('web');

        if ($guard->check()) {
            throw AlreadyAuthenticated::redirectTo($this->destination->for($guard->user()));
        }
    }
}
