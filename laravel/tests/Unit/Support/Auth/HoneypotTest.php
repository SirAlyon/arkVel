<?php

declare(strict_types=1);

use App\Support\Auth\Honeypot;

it('lets a person through: empty trap and enough time', function (): void {
    expect((new Honeypot)->isBot('', 1_000, 1_000 + Honeypot::MIN_SECONDS))->toBeFalse();
});

it('catches a filled trap field', function (): void {
    expect((new Honeypot)->isBot('https://spam.example', 1_000, 2_000))->toBeTrue();
});

it('catches a submission faster than the minimum time', function (): void {
    expect((new Honeypot)->isBot('', 1_000, 1_000 + Honeypot::MIN_SECONDS - 1))->toBeTrue();
});
