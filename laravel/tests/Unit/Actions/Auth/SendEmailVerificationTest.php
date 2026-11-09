<?php

declare(strict_types=1);

use App\Actions\Auth\SendEmailVerification;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
});

it('sends the verification mail to an unverified user', function (): void {
    $user = User::factory()->unverified()->create();

    expect(app(SendEmailVerification::class)->handle($user))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmailAddress::class);
});

it('does not send anything to a verified user', function (): void {
    expect(app(SendEmailVerification::class)->handle(User::factory()->create()))->toBeFalse();
    Notification::assertNothingSent();
});

it('limits the resends per user', function (): void {
    $user = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();
    $action = app(SendEmailVerification::class);

    foreach (range(1, SendEmailVerification::MAX_ATTEMPTS) as $attempt) {
        $action->handle($user);
    }

    expect(fn () => $action->handle($user))->toThrow(ValidationException::class, 'Troppi tentativi')
        ->and($action->handle($other))->toBeTrue();

    $this->travel(SendEmailVerification::DECAY_SECONDS + 1)->seconds();
    expect($action->handle($user))->toBeTrue();
});
