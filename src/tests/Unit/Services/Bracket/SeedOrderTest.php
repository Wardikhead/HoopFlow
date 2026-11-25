<?php

/** Tests for Seed order test. */

use App\Services\Bracket\SeedOrder;

it('returns the standard bracket seed order', function () {
    expect(SeedOrder::forSize(2))->toBe([1, 2])
        ->and(SeedOrder::forSize(4))->toBe([1, 4, 2, 3])
        ->and(SeedOrder::forSize(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6])
        ->and(SeedOrder::forSize(16))->toBe([1, 16, 8, 9, 4, 13, 5, 12, 2, 15, 7, 10, 3, 14, 6, 11]);
});

it('rejects sizes that are not powers of two', function () {
    expect(fn () => SeedOrder::forSize(6))->toThrow(InvalidArgumentException::class);
});
