<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Animals\Pages;

use App\Filament\Shelter\Resources\Animals\AnimalResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnimals extends ListRecords
{
    protected static string $resource = AnimalResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // CreateAction onora AnimalPolicy::create: non visibile al volontario.
        return [
            CreateAction::make(),
        ];
    }
}
