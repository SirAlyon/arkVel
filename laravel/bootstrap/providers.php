<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;
use App\Providers\CalendarAssetServiceProvider;
use App\Providers\CalendarSyncServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AdopterPanelProvider;
use App\Providers\Filament\ShelterPanelProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    CalendarAssetServiceProvider::class,
    CalendarSyncServiceProvider::class,
    AdminPanelProvider::class,
    ShelterPanelProvider::class,
    AdopterPanelProvider::class,
];
