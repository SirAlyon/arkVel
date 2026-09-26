<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ShelterPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ShelterPanelProvider::class,
];
