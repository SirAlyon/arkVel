<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stato del ciclo di vita di una donazione.
 *
 * Come {@see AppointmentStatus} e {@see AnimalStatus}, il grafo delle transizioni
 * consentite vive NELL'ENUM stesso (non una macchina spatie/laravel-model-states): la
 * semantica del ciclo di vita è una proprietà dello stato e vive qui; l'orchestrazione
 * (persistenza, guardia, activity log, riconciliazione con gli eventi Stripe) sarà
 * responsabilità delle Action in App\Actions\Donations (P4-6/P4-7), non dell'enum.
 *
 * Grafo:
 *  - Pending   → Completed | Failed | Canceled (esito del pagamento Stripe)
 *  - Completed → Refunded (rimborso successivo)
 *  - Failed / Canceled / Refunded sono TERMINALI: nessun successore.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum di colonna DB).
 */
enum DonationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Canceled = 'canceled';

    public function getLabel(): string
    {
        return __('enums.donation_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Refunded => 'info',
            self::Canceled => 'gray',
        };
    }

    /**
     * Grafo delle transizioni di stato consentite.
     *
     * Una donazione in attesa può completarsi, fallire o essere annullata; una donazione
     * completata può essere rimborsata. Failed/Canceled/Refunded sono terminali.
     *
     * @return list<DonationStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Completed, self::Failed, self::Canceled],
            self::Completed => [self::Refunded],
            self::Failed, self::Canceled, self::Refunded => [],
        };
    }

    /**
     * Indica se è consentito passare dallo stato corrente a $to.
     */
    public function canTransitionTo(DonationStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
