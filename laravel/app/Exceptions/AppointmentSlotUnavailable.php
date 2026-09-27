<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Animal;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Sollevata quando lo slot richiesto per un appuntamento si sovrappone a un altro
 * appuntamento ATTIVO (Richiesto/Confermato) dello stesso animale o dello stesso
 * richiedente.
 *
 * È una guardia di dominio di RequestAppointment, valutata dentro la transazione
 * PRIMA di scrivere: un doppio-booking non deve mai andare a buon fine. Porta con
 * sé il contesto (animale/utente + orario) per messaggi parlanti.
 */
final class AppointmentSlotUnavailable extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAnimal(Animal $animal, CarbonInterface $scheduledAt): self
    {
        return new self(
            __(':name ha già un appuntamento che si sovrappone a :when.', [
                'name' => $animal->name,
                'when' => $scheduledAt->toDateTimeString(),
            ]),
        );
    }

    public static function forUser(User $user, CarbonInterface $scheduledAt): self
    {
        return new self(
            __(':name ha già un appuntamento che si sovrappone a :when.', [
                'name' => $user->name,
                'when' => $scheduledAt->toDateTimeString(),
            ]),
        );
    }
}
