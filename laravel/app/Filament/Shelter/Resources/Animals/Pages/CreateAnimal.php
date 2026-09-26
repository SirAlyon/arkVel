<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Animals\Pages;

use App\Filament\Shelter\Resources\Animals\AnimalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnimal extends CreateRecord
{
    protected static string $resource = AnimalResource::class;
}
