<?php

declare(strict_types=1);

namespace App\States\Application;

use App\Enums\ApplicationStatus;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\AdoptionApplication;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Macchina a stati della candidatura di adozione (spatie/laravel-model-states).
 *
 * Questa classe astratta è l'UNICA fonte di verità del grafo delle transizioni:
 * la semantica del ciclo di vita vive interamente in {@see self::config()}. Gli
 * stati NON contengono guardie né side-effect: la persistenza, i controlli di
 * dominio (profilo completo, unicità animale/utente) e l'orchestrazione vivono
 * nelle Action della slice 7, che intercettano l'eccezione spatie e sollevano
 * {@see InvalidApplicationTransition}.
 *
 * Ogni stato concreto ha `$name` uguale al valore string del case
 * {@see ApplicationStatus} corrispondente: così colonna DB, enum PHP e label
 * Filament restano allineati.
 *
 * Grafo (Submitted è lo stato di default):
 *   Submitted        → UnderReview
 *   UnderReview      → Interview | Rejected | Waitlisted
 *   Interview        → Approved | Rejected
 *   Approved         → AdoptionPending
 *   AdoptionPending  → Completed
 *   Waitlisted       → UnderReview
 *   Withdrawn        ← da ogni stato pre-terminale
 *   Terminali (nessun successore): Completed, Rejected, Withdrawn
 *
 * @extends State<AdoptionApplication>
 */
abstract class ApplicationState extends State
{
    /**
     * Enum di dominio corrispondente allo stato concreto.
     */
    abstract public function status(): ApplicationStatus;

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
            ->allowTransition(UnderReview::class, Interview::class)
            ->allowTransition(UnderReview::class, Rejected::class)
            ->allowTransition(UnderReview::class, Waitlisted::class)
            ->allowTransition(Interview::class, Approved::class)
            ->allowTransition(Interview::class, Rejected::class)
            ->allowTransition(Approved::class, AdoptionPending::class)
            ->allowTransition(AdoptionPending::class, Completed::class)
            ->allowTransition(Waitlisted::class, UnderReview::class)
            // Ritiro: raggiungibile da ogni stato pre-terminale (fan-out).
            ->allowTransition([
                Submitted::class,
                UnderReview::class,
                Interview::class,
                Approved::class,
                AdoptionPending::class,
                Waitlisted::class,
            ], Withdrawn::class);
    }
}
