<?php

declare(strict_types=1);

use App\Actions\Auth\VerifyUserEmail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Event::fake([Verified::class]);
});

it('marks the email as verified and fires Verified', function (): void {
    $user = User::factory()->unverified()->create();

    expect(app(VerifyUserEmail::class)->handle($user))->toBeTrue()
        ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatchedTimes(Verified::class, 1);
});

it('leaves an already verified email untouched', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()->subWeek()]);
    $verifiedAt = $user->email_verified_at;

    expect(app(VerifyUserEmail::class)->handle($user))->toBeFalse()
        ->and($user->fresh()->email_verified_at->timestamp)->toBe($verifiedAt->timestamp);
    Event::assertNotDispatched(Verified::class);
});
