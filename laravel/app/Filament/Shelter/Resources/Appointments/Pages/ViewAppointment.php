<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Appointments\Pages;

use App\Filament\Shelter\Resources\Appointments\AppointmentResource;
use App\Filament\Shelter\Resources\Appointments\Support\StaffAppointmentActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * View di un appuntamento per lo staff dell'ente. Specchio di ViewFosterApplication.
 *
 * Il record binding è tenant-scoped dalla Resource: aprire un appuntamento di un
 * altro ente non lo risolve (404), coerente con l'isolamento cross-tenant.
 * L'infolist (privacy-aware) è definito sulla Resource; le azioni di gestione
 * riusano le stesse definizioni della table (nessuna duplicazione), gated sulla
 * AppointmentPolicy (update per confirm/complete/no-show, cancel per l'annullamento).
 */
class ViewAppointment extends ViewRecord
{
    protected static string $resource = AppointmentResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            StaffAppointmentActions::confirmAction(),
            StaffAppointmentActions::completeAction(),
            StaffAppointmentActions::noShowAction(),
            StaffAppointmentActions::cancelAction(),
        ];
    }
}
