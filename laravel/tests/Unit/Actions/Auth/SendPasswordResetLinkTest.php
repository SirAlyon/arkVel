<?php

declare(strict_types=1);

use App\Actions\Auth\SendPasswordResetLink;
use App\Data\Auth\ForgotPasswordData;
use App\Exceptions\AlreadyAuthenticated;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

it('stores a reset token and mails the link to a known user', function (): void {
    $user = User::factory()->create();

    app(SendPasswordResetLink::class)->handle(new ForgotPasswordData($user->email), '203.0.113.5');

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
    Notification::assertSentTo($user, ResetPasswordLink::class);
});

it('does nothing visible for an unknown email', function (): void {
    app(SendPasswordResetLink::class)->handle(new ForgotPasswordData('nobody@example.com'), '203.0.113.5');

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

it('limits requests per IP whatever the email', function (): void {
    $action = app(SendPasswordResetLink::class);

    foreach (range(1, SendPasswordResetLink::MAX_ATTEMPTS) as $index) {
        $action->handle(new ForgotPasswordData("p{$index}@example.com"), '203.0.113.5');
    }

    expect(fn () => $action->handle(new ForgotPasswordData('x@example.com'), '203.0.113.5'))
        ->toThrow(ValidationException::class, 'Troppi tentativi');
});

it('does nothing for a session that is already signed in', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(fn () => app(SendPasswordResetLink::class)->handle(new ForgotPasswordData($user->email), '203.0.113.5'))
        ->toThrow(AlreadyAuthenticated::class);
    Notification::assertNothingSent();
});
