<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use App\Models\Adoption;
use App\Support\Contracts\ContractGenerator;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Implementazione di ContractGenerator basata su dompdf (nessun Chromium).
 *
 * Rende la view Blade `pdf.adoption-contract` con i dati dell'adozione e ne
 * restituisce i byte grezzi. Tutta la preparazione dei dati (formattazione
 * di data/valuta, appiattimento delle relazioni) avviene QUI, così la view
 * resta un template privo di logica. Il binding sull'interfaccia vive in
 * App\Providers\AppServiceProvider.
 */
final class DompdfContractGenerator implements ContractGenerator
{
    public function generate(Adoption $adoption): string
    {
        return Pdf::loadView('pdf.adoption-contract', $this->viewData($adoption))
            ->setPaper('a4')
            ->output();
    }

    /**
     * Dati appiattiti per la view (nessuna logica nel template).
     *
     * @return array<string, mixed>
     */
    private function viewData(Adoption $adoption): array
    {
        $animal = $adoption->animal;
        $adopter = $adoption->user;
        $organization = $adoption->organization;

        return [
            'adoption' => $adoption,

            'organizationName' => $organization->name,
            'organizationLegalName' => $organization->legal_name,
            'organizationAddress' => $this->organizationAddress($organization->address_line, $organization->postal_code, $organization->city, $organization->province),
            'organizationEmail' => $organization->email,

            'adopterName' => $adopter->name,
            'adopterEmail' => $adopter->email,

            'animalName' => $animal->name,
            'species' => $animal->species->getLabel(),
            'breed' => $animal->breed,
            'sex' => $animal->sex->getLabel(),
            'microchip' => $animal->microchip_number,

            'adoptedAt' => $adoption->adopted_at->translatedFormat('d F Y'),
            // Il cast `decimal:2` restituisce una stringa: normalizziamo a float.
            'feeLabel' => $this->feeLabel(
                $adoption->fee === null ? null : (float) $adoption->fee,
                $adoption->currency,
            ),
        ];
    }

    private function organizationAddress(?string $line, ?string $postalCode, ?string $city, ?string $province): ?string
    {
        $cityLine = trim(implode(' ', array_filter([
            $postalCode,
            $city,
            $province !== null ? "($province)" : null,
        ], static fn (?string $part): bool => $part !== null && $part !== '')));

        $parts = array_filter([
            $line,
            $cityLine === '' ? null : $cityLine,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function feeLabel(?float $fee, string $currency): string
    {
        if ($fee === null || $fee <= 0.0) {
            return __('adoptions.contract.fee_free');
        }

        return number_format($fee, 2, ',', '.').' '.$currency;
    }
}
