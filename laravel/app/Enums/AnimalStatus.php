<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AnimalStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Available = 'available';
    case Reserved = 'reserved';
    case AdoptionPending = 'adoption_pending';
    case Adopted = 'adopted';
    case Fostered = 'fostered';
    case MedicalHold = 'medical_hold';
    case NotAvailable = 'not_available';

    public function getLabel(): string
    {
        return __('enums.animal_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Available => 'success',
            self::Reserved, self::AdoptionPending => 'warning',
            self::Adopted => 'info',
            self::Fostered => 'primary',
            self::MedicalHold => 'danger',
            self::NotAvailable => 'gray',
        };
    }

    /**
     * Grafo delle transizioni di stato consentite.
     *
     * La semantica del ciclo di vita (quali passaggi hanno senso) è una
     * proprietà dello stato stesso e vive qui. L'orchestrazione (persistenza,
     * guardia, activity log) è responsabilità di
     * App\Actions\Animals\TransitionAnimalStatus, non dell'enum.
     *
     * `Adopted` è terminale: non ammette successori.
     *
     * @return list<AnimalStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Available, self::NotAvailable],
            self::Available => [
                self::Reserved,
                self::AdoptionPending,
                self::MedicalHold,
                self::Fostered,
                self::NotAvailable,
            ],
            self::Reserved => [self::Available, self::AdoptionPending, self::NotAvailable],
            self::AdoptionPending => [self::Adopted, self::Available, self::NotAvailable],
            self::MedicalHold => [self::Available, self::NotAvailable],
            self::Fostered => [self::Available, self::Adopted, self::NotAvailable],
            // `Draft` e' pre-pubblicazione: da `NotAvailable` la ripubblicazione
            // passa per `Available`, non c'e' ritorno a bozza.
            self::NotAvailable => [self::Available],
            self::Adopted => [],
        };
    }

    /**
     * Indica se è consentito passare dallo stato corrente a $to.
     */
    public function canTransitionTo(AnimalStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
