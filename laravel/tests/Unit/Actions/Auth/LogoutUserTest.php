<?php

declare(strict_types=1);

use App\Actions\Auth\LogoutUser;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('logs the user out, invalidates the session and rotates the CSRF token', function (): void {
    $user = User::factory()->create();
    Auth::guard('web')->login($user);

    $session = app('session.store');
    $session->put('secret', 'dati dell\'account');
    $id = $session->getId();
    $token = $session->token();

    app(LogoutUser::class)->handle($session);

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and($session->getId())->not->toBe($id)
        ->and($session->token())->not->toBe($token)
        ->and($session->has('secret'))->toBeFalse();
});

it('keeps only the language chosen with the switcher', function (): void {
    Auth::guard('web')->login(User::factory()->create());

    $session = app('session.store');
    $session->put(SetLocale::SESSION_KEY, 'en');

    app(LogoutUser::class)->handle($session);

    expect($session->get(SetLocale::SESSION_KEY))->toBe('en');
});

it('is a no-op for a guest', function (): void {
    $session = app('session.store');

    app(LogoutUser::class)->handle($session);

    expect(Auth::guard('web')->check())->toBeFalse();
});
