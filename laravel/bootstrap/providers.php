<?php

use App\Providers\AppServiceProvider;
use App\Providers\CalendarAssetServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AdopterPanelProvider;
use App\Providers\Filament\ShelterPanelProvider;

return [
    AppServiceProvider::class,
    CalendarAssetServiceProvider::class,
    AdminPanelProvider::class,
    ShelterPanelProvider::class,
    AdopterPanelProvider::class,
];
