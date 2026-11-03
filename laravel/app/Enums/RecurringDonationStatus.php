<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stato del ciclo di vita di una donazione RICORRENTE (abbonamento Stripe), P4-7.
 *
 * Come {@see DonationStatus} e {@see AppointmentStatus}, il grafo delle transizioni
 * consentite vive NELL'ENUM stesso (non una macchina spatie/laravel-model-states): la
 * semantica del ciclo di vita è una proprietà dello stato e vive qui; l'orchestrazione
 * (avvio Checkout, riconciliazione con gli eventi subscription/invoice di Stripe,
 * disdetta) è responsabilità delle Action in App\Actions\Donations, non dell'enum.
 *
 * Grafo:
 *  - Pending  → Active | Canceled   (abbonamento creato, in attesa della conferma webhook)
 *  - Active   → PastDue | Canceled  (addebito mensile riuscito / insoluto / disdetta)
 *  - PastDue  → Active | Canceled   (insoluto poi recuperato, o disdetto)
 *  - Canceled                        (TERMINALE: nessun successore)
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum di colonna DB).
 */
enum RecurringDonationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';

    public function getLabel(): string
    {
        return __('enums.recurring_donation_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::PastDue => 'danger',
            self::Canceled => 'gray',
        };
    }

    /**
     * Grafo delle transizioni di stato consentite.
     *
     * In attesa → può attivarsi o essere disdetta; attiva → può diventare insoluta o
     * essere disdetta; insoluta → può tornare attiva (recupero) o essere disdetta.
     * Canceled è terminale.
     *
     * @return list<RecurringDonationStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Canceled],
            self::Active => [self::PastDue, self::Canceled],
            self::PastDue => [self::Active, self::Canceled],
            self::Canceled => [],
        };
    }

    /**
     * Indica se è consentito passare dallo stato corrente a $to.
     */
    public function canTransitionTo(RecurringDonationStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
