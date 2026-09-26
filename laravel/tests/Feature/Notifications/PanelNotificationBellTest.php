<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enables the database notification bell on the adopter panel with a polling interval', function () {
    $panel = Filament::getPanel('adopter');

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});

it('enables the database notification bell on the shelter panel with a polling interval', function () {
    $panel = Filament::getPanel('shelter');

    expect($panel->hasDatabaseNotifications())->toBeTrue()
        ->and($panel->getDatabaseNotificationsPollingInterval())->toBe('30s');
});

it('leaves the admin panel database notifications untouched (default off)', function () {
    $panel = Filament::getPanel('admin');

    // L'admin resta invariato: la campanella è attiva solo su adopter/shelter.
    expect($panel->hasDatabaseNotifications())->toBeFalse();
});
