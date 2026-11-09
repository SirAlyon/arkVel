<?php

declare(strict_types=1);

use App\Support\Auth\SuccessNotice;

it('recognizes the URLs of the Filament panels, absolute or relative', function (string $url, bool $panel): void {
    expect(app(SuccessNotice::class)->isPanelUrl($url))->toBe($panel);
})->with([
    'adopter home' => [fn (): string => route('filament.adopter.home'), true],
    'adopter page' => ['/account/preferiti', true],
    'shelter' => [fn (): string => route('filament.shelter.tenant'), true],
    'admin' => ['/admin', true],
    'public page' => [fn (): string => url('/animali'), false],
    'similar prefix' => ['/accounting', false],
    'home' => ['/', false],
]);

it('flashes a toast for a public page', function (): void {
    $session = app('session.store');

    app(SuccessNotice::class)->flash($session, url('/animali'), 'Fatto');

    expect($session->get('toast'))->toBe(['type' => 'success', 'title' => 'Fatto'])
        ->and($session->get('filament.notifications'))->toBeNull();
});

it('sends a Filament notification for a panel page', function (): void {
    $session = app('session.store');

    app(SuccessNotice::class)->flash($session, route('filament.adopter.home'), 'Fatto');

    expect($session->get('toast'))->toBeNull()
        ->and(array_column((array) $session->get('filament.notifications'), 'title'))->toBe(['Fatto'])
        ->and(array_column((array) $session->get('filament.notifications'), 'status'))->toBe(['success']);
});
