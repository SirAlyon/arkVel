<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\AdoptionApplications\Pages;

use App\Filament\Shelter\Resources\AdoptionApplications\AdoptionApplicationResource;
use App\Filament\Shelter\Resources\AdoptionApplications\Tables\AdoptionApplicationsTable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * View di una candidatura per lo staff dell'ente.
 *
 * Il record binding e' tenant-scoped dalla Resource: aprire una candidatura di
 * un altro ente non la risolve (404), coerente con l'isolamento cross-tenant.
 * L'infolist (privacy-aware) e' definito sulla Resource; le azioni di gestione
 * riusano le stesse definizioni della table (nessuna duplicazione), tutte gated
 * su AdoptionApplicationPolicy::update.
 */
class ViewAdoptionApplication extends ViewRecord
{
    protected static string $resource = AdoptionApplicationResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            AdoptionApplicationsTable::advanceAction(),
            AdoptionApplicationsTable::finalizeAction(),
            AdoptionApplicationsTable::assignAction(),
            AdoptionApplicationsTable::editNotesAction(),
        ];
    }
}
