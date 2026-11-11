<?php

use App\Services\Bracket\RoundRobinBuilder;

it('builds a complete single round robin for an even field', function () {
    $plan = (new RoundRobinBuilder)->build([1, 2, 3, 4]);

    $pairs = collect($plan->matchups)
        ->map(fn ($matchup): string => implode('-', [
            min($matchup->home->teamId, $matchup->away->teamId),
            max($matchup->home->teamId, $matchup->away->teamId),
        ]))
        ->sort()
        ->values()
        ->all();

    expect($plan->matchups)->toHaveCount(6)
        ->and(collect($plan->matchups)->pluck('roundNumber')->unique()->values()->all())->toBe([1, 2, 3])
        ->and($pairs)->toBe(['1-2', '1-3', '1-4', '2-3', '2-4', '3-4']);
});

it('creates byes without phantom matches for an odd field', function () {
    $plan = (new RoundRobinBuilder)->build([1, 2, 3, 4, 5]);

    expect($plan->matchups)->toHaveCount(10)
        ->and(collect($plan->matchups)->pluck('roundNumber')->unique()->count())->toBe(5)
        ->and(collect($plan->matchups)->contains(
            fn ($matchup): bool => $matchup->home->teamId === null || $matchup->away->teamId === null,
        ))->toBeFalse();
});

it('supports multiple legs and swaps home and away on even legs', function () {
    $plan = (new RoundRobinBuilder)->build([1, 2, 3, 4], 2);

    expect($plan->matchups)->toHaveCount(12)
        ->and(collect($plan->matchups)->pluck('roundNumber')->unique()->count())->toBe(6);

    $legOne = collect($plan->matchups)->first(
        fn ($matchup): bool => $matchup->roundNumber === 1,
    );
    $legTwo = collect($plan->matchups)->first(
        fn ($matchup): bool => $matchup->roundNumber === 4,
    );

    expect($legOne->home->teamId)->toBe($legTwo->away->teamId)
        ->and($legOne->away->teamId)->toBe($legTwo->home->teamId);
});

it('builds the correct number of games for large odd fields and multiple legs', function () {
    expect((new RoundRobinBuilder)->build(range(1, 15))->matchups)->toHaveCount(105)
        ->and((new RoundRobinBuilder)->build(range(1, 16), 3)->matchups)->toHaveCount(360);
});

it('rejects invalid fields and leg counts', function () {
    expect(fn () => (new RoundRobinBuilder)->build([1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new RoundRobinBuilder)->build(range(1, 17)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new RoundRobinBuilder)->build([1, 2, 3], 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new RoundRobinBuilder)->build([1, 2, 3], 11))->toThrow(InvalidArgumentException::class);
});
