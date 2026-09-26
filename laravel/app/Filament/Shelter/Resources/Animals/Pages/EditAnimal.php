<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Animals\Pages;

use App\Filament\Shelter\Resources\Animals\AnimalResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAnimal extends EditRecord
{
    protected static string $resource = AnimalResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        // Le azioni onorano AnimalPolicy (delete/forceDelete/restore),
        // tenant-scoped via isVerifiedMemberOf.
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
