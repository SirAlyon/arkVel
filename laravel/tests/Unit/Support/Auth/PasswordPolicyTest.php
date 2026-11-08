<?php

declare(strict_types=1);

use App\Support\Auth\PasswordPolicy;
use Illuminate\Validation\Rules\Password;

afterEach(function (): void {
    Password::$defaultCallback = null;
});

it('reads the minimum length from Password::defaults()', function (): void {
    expect(PasswordPolicy::minLength())->toBe(8);

    Password::defaults(fn (): Password => Password::min(12)->mixedCase());

    expect(PasswordPolicy::minLength())->toBe(12);
});

it('reads the link lifetimes from config/auth.php', function (): void {
    config(['auth.passwords.users.expire' => 45, 'auth.verification.expire' => 90]);

    expect(PasswordPolicy::resetLinkExpiryMinutes())->toBe(45)
        ->and(PasswordPolicy::verificationLinkExpiryMinutes())->toBe(90);
});
