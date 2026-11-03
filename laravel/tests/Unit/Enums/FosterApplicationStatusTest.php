<?php

declare(strict_types=1);

use App\Enums\FosterApplicationStatus;

it('is backed by the expected string values', function () {
    expect(FosterApplicationStatus::Submitted->value)->toBe('submitted')
        ->and(FosterApplicationStatus::UnderReview->value)->toBe('under_review')
        ->and(FosterApplicationStatus::Approved->value)->toBe('approved')
        ->and(FosterApplicationStatus::Rejected->value)->toBe('rejected')
        ->and(FosterApplicationStatus::Waitlisted->value)->toBe('waitlisted')
        ->and(FosterApplicationStatus::Active->value)->toBe('active')
        ->and(FosterApplicationStatus::Completed->value)->toBe('completed')
        ->and(FosterApplicationStatus::Withdrawn->value)->toBe('withdrawn');
});

it('defines exactly eight statuses', function () {
    expect(FosterApplicationStatus::cases())->toHaveCount(8);
});

it('exposes a non-empty italian label for every case', function () {
    foreach (FosterApplicationStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});

it('exposes a non-empty color for every case', function () {
    foreach (FosterApplicationStatus::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBe('');
    }
});
