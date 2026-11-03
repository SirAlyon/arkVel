<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Appointments\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Shelter\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista appuntamenti con tab per fase, per una lettura "a board" dell'agenda.
 * Specchio di ListFosterApplications.
 *
 * Nessuna header action Create: gli appuntamenti nascono dagli adottanti. I tab
 * raggruppano per fase del ciclo di vita usando lo stato dell'enum:
 *  - Da confermare: Requested (in attesa dello staff)
 *  - Confermati: Confirmed (in agenda)
 *  - Conclusi: Completed / Cancelled / NoShow (esiti terminali)
 * La query dei tab è applicata SOPRA lo scope del tenant (Resource tenant-scoped),
 * quindi resta isolata per ente. I filtri di data/stato/tipo restano disponibili
 * dentro ogni tab.
 */
class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('appointments.shelter.tabs.all')),

            'to_confirm' => Tab::make(__('appointments.shelter.tabs.to_confirm'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStatuses($query, [
                    AppointmentStatus::Requested,
                ])),

            'confirmed' => Tab::make(__('appointments.shelter.tabs.confirmed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStatuses($query, [
                    AppointmentStatus::Confirmed,
                ])),

            'closed' => Tab::make(__('appointments.shelter.tabs.closed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStatuses($query, [
                    AppointmentStatus::Completed,
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::NoShow,
                ])),
        ];
    }

    /**
     * Filtra per insieme di stati dell'enum. Estratto in un helper tipizzato sul
     * model per far risolvere alla static analysis il Builder<Appointment>.
     *
     * @param  Builder<Appointment>  $query
     * @param  list<AppointmentStatus>  $statuses
     * @return Builder<Appointment>
     */
    private static function filterByStatuses(Builder $query, array $statuses): Builder
    {
        return $query->whereIn('status', $statuses);
    }
}
