<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Segna come verificato l'indirizzo email dell'utente (link firmato di
 * `verification.verify`) ed emette l'evento Verified, come
 * EmailVerificationRequest::fulfill() usato prima dal pannello Filament.
 * Idempotente: un indirizzo già verificato resta com'è e l'evento non si ripete.
 */
final readonly class VerifyUserEmail
{
    public function __construct(private Dispatcher $events) {}

    /** @return bool true se l'indirizzo è stato verificato adesso */
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();
        $this->events->dispatch(new Verified($user));

        return true;
    }
}
