<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\FosterApplications\Pages;

use App\Filament\Shelter\Resources\FosterApplications\FosterApplicationResource;
use App\Models\FosterApplication;
use App\States\Foster\Active;
use App\States\Foster\Approved;
use App\States\Foster\Completed;
use App\States\Foster\FosterApplicationState;
use App\States\Foster\Rejected;
use App\States\Foster\Submitted;
use App\States\Foster\UnderReview;
use App\States\Foster\Waitlisted;
use App\States\Foster\Withdrawn;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista richieste di affido con tab per fase, per una lettura "a board" del
 * pipeline. Specchio di ListAdoptionApplications.
 *
 * Nessuna header action Create: le richieste nascono dagli affidatari. I tab
 * raggruppano per fase del ciclo di vita usando lo scope spatie whereState:
 *  - Da valutare: Submitted / UnderReview / Waitlisted (in attesa di lavorazione)
 *  - In corso: Approved / Active (in lavorazione / affido attivo)
 *  - Decise: Rejected / Completed / Withdrawn (esiti terminali)
 * La query dei tab è applicata SOPRA lo scope del tenant (Resource tenant-scoped),
 * quindi resta isolata per ente.
 */
class ListFosterApplications extends ListRecords
{
    protected static string $resource = FosterApplicationResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('fostering.shelter.tabs.all')),

            'to_review' => Tab::make(__('fostering.shelter.tabs.to_review'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStates($query, [
                    Submitted::class,
                    UnderReview::class,
                    Waitlisted::class,
                ])),

            'in_progress' => Tab::make(__('fostering.shelter.tabs.in_progress'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStates($query, [
                    Approved::class,
                    Active::class,
                ])),

            'decided' => Tab::make(__('fostering.shelter.tabs.decided'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStates($query, [
                    Rejected::class,
                    Completed::class,
                    Withdrawn::class,
                ])),
        ];
    }

    /**
     * Filtra per insieme di stati usando lo scope spatie whereState. Estratto in un
     * helper tipizzato sul model per far risolvere lo scope alla static analysis
     * (whereState vive sul Builder<FosterApplication>).
     *
     * @param  Builder<FosterApplication>  $query
     * @param  list<class-string<FosterApplicationState>>  $states
     * @return Builder<FosterApplication>
     */
    private static function filterByStates(Builder $query, array $states): Builder
    {
        return $query->whereState('status', $states);
    }
}
