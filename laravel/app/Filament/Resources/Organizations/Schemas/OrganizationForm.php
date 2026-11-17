<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Form Admin di un ente: il solo flag «Ente dimostrativo». Gli altri dati dell'ente sono
 * dello staff (/manage), non del Super Admin.
 */
class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_demo')
                    ->label(__('demo.admin.toggle'))
                    ->helperText(__('demo.admin.toggle_help')),
            ]);
    }
}
