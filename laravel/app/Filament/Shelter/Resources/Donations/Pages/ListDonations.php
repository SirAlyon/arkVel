<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Donations\Pages;

use App\Enums\DonationStatus;
use App\Filament\Shelter\Resources\Donations\DonationResource;
use App\Models\Donation;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista donazioni con tab per esito, per una lettura rapida del registro.
 * Specchio di ListAppointments.
 *
 * Nessuna header action Create: le donazioni nascono dal flusso pubblico (P4-6). I tab
 * raggruppano per esito usando lo stato dell'enum:
 *  - Completate: Completed (incassate — i KPI di raccolta)
 *  - In attesa: Pending (checkout avviato, non ancora confermato)
 *  - Non riuscite: Failed / Canceled / Refunded (esiti non positivi/rimborsi)
 * La query dei tab è applicata SOPRA lo scope del tenant (Resource tenant-scoped),
 * quindi resta isolata per ente. Il filtro per stato resta disponibile dentro ogni tab.
 */
class ListDonations extends ListRecords
{
    protected static string $resource = DonationResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('donations.shelter.tabs.all')),

            'completed' => Tab::make(__('donations.shelter.tabs.completed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStatuses($query, [
                    DonationStatus::Completed,
                ])),

            'pending' => Tab::make(__('donations.shelter.tabs.pending'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStatuses($query, [
                    DonationStatus::Pending,
                ])),

            'unsuccessful' => Tab::make(__('donations.shelter.tabs.unsuccessful'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStatuses($query, [
                    DonationStatus::Failed,
                    DonationStatus::Canceled,
                    DonationStatus::Refunded,
                ])),
        ];
    }

    /**
     * Filtra per insieme di stati dell'enum. Estratto in un helper tipizzato sul
     * model per far risolvere alla static analysis il Builder<Donation>.
     *
     * @param  Builder<Donation>  $query
     * @param  list<DonationStatus>  $statuses
     * @return Builder<Donation>
     */
    private static function filterByStatuses(Builder $query, array $statuses): Builder
    {
        return $query->whereIn('status', $statuses);
    }
}
