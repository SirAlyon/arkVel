<?php

declare(strict_types=1);

namespace App\Actions\Animals;

use App\Enums\AnimalStatus;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Models\Animal;

/**
 * Applica una transizione di stato a un animale.
 *
 * Single responsibility: e' l'unico punto in cui lo stato viene mutato. La
 * guardia interroga la semantica dell'enum (canTransitionTo); la persistenza e
 * la tracciatura (activity log) sono orchestrate qui, non nel model. Una
 * transizione non ammessa solleva InvalidAnimalStatusTransition e NON tocca lo
 * stato.
 */
final class TransitionAnimalStatus
{
    /**
     * @throws InvalidAnimalStatusTransition
     */
    public function handle(Animal $animal, AnimalStatus $to): Animal
    {
        $from = $animal->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidAnimalStatusTransition::between($from, $to);
        }

        $animal->status = $to;
        $animal->save();

        activity()
            ->performedOn($animal)
            ->withProperties([
                'from' => $from->value,
                'to' => $to->value,
            ])
            ->log('animal.status_changed');

        return $animal;
    }
}
