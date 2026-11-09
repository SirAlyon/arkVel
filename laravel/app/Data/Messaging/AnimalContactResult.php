<?php

declare(strict_types=1);

namespace App\Data\Messaging;

use App\Actions\Messaging\ContactOrganizationAboutAnimal;
use App\Models\MessageThread;

/**
 * Esito di {@see ContactOrganizationAboutAnimal}: la conversazione in cui è finito il
 * messaggio e se era già esistente (ricontatto, messaggio accodato) o appena avviata.
 * Il chiamante sceglie il feedback da mostrare in base a `reusedExistingThread`.
 */
final readonly class AnimalContactResult
{
    public function __construct(
        public MessageThread $thread,
        public bool $reusedExistingThread,
    ) {}
}
