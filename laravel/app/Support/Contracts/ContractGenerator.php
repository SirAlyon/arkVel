<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use App\Models\Adoption;

/**
 * Contratto (Interface Segregation + Dependency Inversion) per la generazione
 * del PDF del contratto di adozione.
 *
 * L'unica responsabilità è produrre i BYTE grezzi del PDF a partire da
 * un'adozione: dove/come viene reso (motore PDF, template) è dettaglio
 * dell'implementazione. FinalizeAdoption dipende da QUESTA interfaccia, non
 * dall'implementazione concreta: il binding vive in un Service Provider e i test
 * possono sostituire un fake deterministico senza avviare un motore PDF reale.
 */
interface ContractGenerator
{
    /**
     * Genera il PDF del contratto e ne restituisce i byte grezzi.
     */
    public function generate(Adoption $adoption): string;
}
