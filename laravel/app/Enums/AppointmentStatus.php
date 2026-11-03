<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stato del ciclo di vita di un appuntamento (request → confirm → complete).
 *
 * A differenza della richiesta di affido/adozione (che usano
 * spatie/laravel-model-states), qui il grafo delle transizioni consentite vive
 * NELL'ENUM stesso — stesso pattern di App\Enums\AnimalStatus. La semantica del
 * ciclo di vita (quali passaggi hanno senso) è una proprietà dello stato e vive
 * qui; l'orchestrazione (persistenza, guardia, activity log, notifiche) è
 * responsabilità delle Action in App\Actions\Appointments, non dell'enum.
 *
 * Completed/Cancelled/NoShow sono TERMINALI: non ammettono successori.
 */
enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return __('enums.appointment_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'gray',
            self::Confirmed => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::NoShow => 'warning',
        };
    }

    /**
     * Grafo delle transizioni di stato consentite.
     *
     * Un appuntamento richiesto può essere confermato o annullato; una volta
     * confermato può concludersi (Completed), essere annullato (Cancelled) o
     * registrare l'assenza (NoShow). Completed/Cancelled/NoShow sono terminali.
     *
     * @return list<AppointmentStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled, self::NoShow],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    /**
     * Indica se è consentito passare dallo stato corrente a $to.
     */
    public function canTransitionTo(AppointmentStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
