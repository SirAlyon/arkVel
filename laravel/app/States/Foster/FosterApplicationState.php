<?php

declare(strict_types=1);

namespace App\States\Foster;

use App\Enums\FosterApplicationStatus;
use App\Models\FosterApplication;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Macchina a stati della richiesta di affido (spatie/laravel-model-states).
 *
 * Analoga a App\States\Application\ApplicationState. Questa classe astratta è
 * l'UNICA fonte di verità del grafo delle transizioni: la semantica del ciclo di
 * vita vive interamente in {@see self::config()}. Gli stati NON contengono
 * guardie né side-effect: la persistenza, i controlli di dominio e
 * l'orchestrazione vivono nelle Action delle slice successive.
 *
 * Ogni stato concreto ha `$name` uguale al valore string del case
 * {@see FosterApplicationStatus} corrispondente: così colonna DB, enum PHP e
 * label Filament restano allineati.
 *
 * Grafo (Submitted è lo stato di default):
 *   Submitted   → UnderReview
 *   UnderReview → Approved | Rejected | Waitlisted
 *   Waitlisted  → UnderReview
 *   Approved    → Active
 *   Active      → Completed
 *   Withdrawn   ← da ogni stato pre-terminale
 *   Terminali (nessun successore): Rejected, Completed, Withdrawn
 *
 * @extends State<FosterApplication>
 */
abstract class FosterApplicationState extends State
{
    /**
     * Enum di dominio corrispondente allo stato concreto.
     */
    abstract public function status(): FosterApplicationStatus;

    /**
     * Label localizzata: delega all'enum (unica fonte per l'UI Filament).
     */
    public function label(): string
    {
        return $this->status()->getLabel();
    }

    /**
     * Colore del badge: delega all'enum (unica fonte per l'UI Filament).
     */
    public function color(): string
    {
        return $this->status()->getColor();
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Submitted::class)
            ->allowTransition(Submitted::class, UnderReview::class)
            ->allowTransition(UnderReview::class, Approved::class)
            ->allowTransition(UnderReview::class, Rejected::class)
            ->allowTransition(UnderReview::class, Waitlisted::class)
            ->allowTransition(Waitlisted::class, UnderReview::class)
            ->allowTransition(Approved::class, Active::class)
            ->allowTransition(Active::class, Completed::class)
            // Ritiro: raggiungibile da ogni stato pre-terminale (fan-out).
            ->allowTransition([
                Submitted::class,
                UnderReview::class,
                Approved::class,
                Waitlisted::class,
                Active::class,
            ], Withdrawn::class);
    }
}
