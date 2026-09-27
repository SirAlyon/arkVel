<?php

declare(strict_types=1);

use App\Enums\CalendarProvider;
use App\Exceptions\UnregisteredCalendarProvider;
use App\Support\Calendar\CalendarProviderRegistry;
use App\Support\Calendar\Providers\ExternalCalendarProvider;
use App\Support\Calendar\Providers\LogCalendarProvider;

it('resolves the log provider from the container binding', function () {
    $registry = app(CalendarProviderRegistry::class);

    $provider = $registry->for(CalendarProvider::Log);

    expect($provider)->toBeInstanceOf(LogCalendarProvider::class)
        ->and($provider)->toBeInstanceOf(ExternalCalendarProvider::class)
        ->and($provider->provider())->toBe(CalendarProvider::Log)
        ->and($registry->has(CalendarProvider::Log))->toBeTrue();
});

it('throws a clear exception for an unregistered provider', function () {
    $registry = app(CalendarProviderRegistry::class);

    // Google non è ancora registrato in Cal-4.
    expect($registry->has(CalendarProvider::Google))->toBeFalse();

    $registry->for(CalendarProvider::Google);
})->throws(UnregisteredCalendarProvider::class);

it('registers a provider under the enum it declares', function () {
    $registry = new CalendarProviderRegistry;

    expect($registry->has(CalendarProvider::Log))->toBeFalse();

    $registry->register(new LogCalendarProvider);

    expect($registry->has(CalendarProvider::Log))->toBeTrue()
        ->and($registry->for(CalendarProvider::Log))->toBeInstanceOf(LogCalendarProvider::class);
});
