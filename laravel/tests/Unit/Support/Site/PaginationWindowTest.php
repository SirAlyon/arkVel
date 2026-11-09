<?php

declare(strict_types=1);

use App\Support\Site\PaginationWindow;

it('shows every page of a short list', function (): void {
    expect(PaginationWindow::pages(1, 1))->toBe([1])
        ->and(PaginationWindow::pages(2, 4))->toBe([1, 2, 3, 4]);
});

it('keeps the first, the last and the neighbours, with gaps for the rest', function (): void {
    expect(PaginationWindow::pages(6, 12))->toBe([1, null, 5, 6, 7, null, 12])
        ->and(PaginationWindow::pages(1, 12))->toBe([1, 2, null, 12])
        ->and(PaginationWindow::pages(12, 12))->toBe([1, null, 11, 12]);
});

it('shows a single missing page instead of an ellipsis', function (): void {
    expect(PaginationWindow::pages(4, 12))->toBe([1, 2, 3, 4, 5, null, 12]);
});

it('treats a range of current pages as one block', function (): void {
    expect(PaginationWindow::pages(1, 10, 3))->toBe([1, 2, 3, 4, null, 10])
        ->and(PaginationWindow::pages(5, 6, 9))->toBe([1, null, 4, 5, 6]);
});

it('clamps out of range values', function (): void {
    expect(PaginationWindow::pages(0, 0))->toBe([1])
        ->and(PaginationWindow::pages(99, 3))->toBe([1, 2, 3])
        ->and(PaginationWindow::pages(5, 20, onEachSide: 0))->toBe([1, null, 5, null, 20]);
});
