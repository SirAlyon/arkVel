<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\FosterApplications\Pages;

use App\Filament\Shelter\Resources\FosterApplications\FosterApplicationResource;
use App\Filament\Shelter\Resources\FosterApplications\Tables\FosterApplicationsTable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * View di una richiesta di affido per lo staff dell'ente. Specchio di
 * ViewAdoptionApplication.
 *
 * Il record binding è tenant-scoped dalla Resource: aprire una richiesta di un
 * altro ente non la risolve (404), coerente con l'isolamento cross-tenant.
 * L'infolist (privacy-aware) è definito sulla Resource; le azioni di gestione
 * riusano le stesse definizioni della table (nessuna duplicazione), tutte gated su
 * FosterApplicationPolicy::update.
 */
class ViewFosterApplication extends ViewRecord
{
    protected static string $resource = FosterApplicationResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            FosterApplicationsTable::advanceAction(),
            FosterApplicationsTable::startPlacementAction(),
            FosterApplicationsTable::endPlacementAction(),
            FosterApplicationsTable::assignAction(),
            FosterApplicationsTable::editNotesAction(),
        ];
    }
}
