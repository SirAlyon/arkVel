<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\AdoptionApplications\Pages;

use App\Filament\Shelter\Resources\AdoptionApplications\AdoptionApplicationResource;
use App\Models\AdoptionApplication;
use App\States\Application\AdoptionPending;
use App\States\Application\ApplicationState;
use App\States\Application\Approved;
use App\States\Application\Completed;
use App\States\Application\Interview;
use App\States\Application\Rejected;
use App\States\Application\Submitted;
use App\States\Application\UnderReview;
use App\States\Application\Waitlisted;
use App\States\Application\Withdrawn;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista candidature con tab per fase, per una lettura "a board" del pipeline.
 *
 * Nessuna header action Create: le candidature nascono dagli adottanti. I tab
 * raggruppano per fase del ciclo di vita usando lo scope spatie whereState:
 *  - Da valutare: Submitted / UnderReview / Waitlisted (in attesa di lavorazione)
 *  - In corso: Interview / Approved / AdoptionPending (in lavorazione)
 *  - Decise: Rejected / Completed / Withdrawn (esiti terminali)
 * La query dei tab e' applicata SOPRA lo scope del tenant (Resource
 * tenant-scoped), quindi resta isolata per ente.
 */
class ListAdoptionApplications extends ListRecords
{
    protected static string $resource = AdoptionApplicationResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('applications.shelter.tabs.all')),

            'to_review' => Tab::make(__('applications.shelter.tabs.to_review'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStates($query, [
                    Submitted::class,
                    UnderReview::class,
                    Waitlisted::class,
                ])),

            'in_progress' => Tab::make(__('applications.shelter.tabs.in_progress'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStates($query, [
                    Interview::class,
                    Approved::class,
                    AdoptionPending::class,
                ])),

            'decided' => Tab::make(__('applications.shelter.tabs.decided'))
                ->modifyQueryUsing(fn (Builder $query): Builder => self::filterByStates($query, [
                    Rejected::class,
                    Completed::class,
                    Withdrawn::class,
                ])),
        ];
    }

    /**
     * Filtra per insieme di stati usando lo scope spatie whereState. Estratto in
     * un helper tipizzato sul model per far risolvere lo scope alla static
     * analysis (whereState vive sul Builder<AdoptionApplication>).
     *
     * @param  Builder<AdoptionApplication>  $query
     * @param  list<class-string<ApplicationState>>  $states
     * @return Builder<AdoptionApplication>
     */
    private static function filterByStates(Builder $query, array $states): Builder
    {
        return $query->whereState('status', $states);
    }
}
