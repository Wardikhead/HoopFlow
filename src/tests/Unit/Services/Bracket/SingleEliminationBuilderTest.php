<?php

use App\Services\Bracket\SingleEliminationBuilder;

it('builds an eight-team single-elimination plan with the standard pairings', function () {
    $plan = (new SingleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8]);

    expect($plan->matchups)->toHaveCount(7);

    $firstRound = array_values(array_filter(
        $plan->matchups,
        fn ($matchup): bool => $matchup->roundNumber === 8,
    ));

    expect($firstRound)->toHaveCount(4)
        ->and($firstRound[0]->home->teamId)->toBe(1)
        ->and($firstRound[0]->away->teamId)->toBe(8)
        ->and($firstRound[1]->home->teamId)->toBe(4)
        ->and($firstRound[1]->away->teamId)->toBe(5)
        ->and($firstRound[2]->home->teamId)->toBe(2)
        ->and($firstRound[2]->away->teamId)->toBe(7)
        ->and($firstRound[3]->home->teamId)->toBe(3)
        ->and($firstRound[3]->away->teamId)->toBe(6);
});

it('routes winners forward and applies per-round best-of lengths', function () {
    $plan = (new SingleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8], [
        0 => 7,
        1 => 5,
        2 => 3,
    ]);

    $final = collect($plan->matchups)->firstWhere('roundNumber', 2);
    $semi = collect($plan->matchups)->firstWhere('roundNumber', 4);
    $quarter = collect($plan->matchups)->firstWhere('roundNumber', 8);

    expect($final->bestOf)->toBe(7)
        ->and($semi->bestOf)->toBe(5)
        ->and($quarter->bestOf)->toBe(3)
        ->and($final->home->type)->toBe('winner')
        ->and($final->home->sourceStage)->toBe('winners')
        ->and($final->home->sourceRound)->toBe(4)
        ->and($final->away->sourceMatch)->toBe(2);
});

it('keeps missing teams as empty seed sources for bye resolution', function () {
    $plan = (new SingleEliminationBuilder)->build(8, [10, 20, 30]);

    $firstRound = array_values(array_filter(
        $plan->matchups,
        fn ($matchup): bool => $matchup->roundNumber === 8,
    ));

    expect($firstRound[0]->home->teamId)->toBe(10)
        ->and($firstRound[0]->away->teamId)->toBeNull()
        ->and($firstRound[2]->home->teamId)->toBe(20)
        ->and($firstRound[3]->home->teamId)->toBe(30)
        ->and($firstRound[3]->away->teamId)->toBeNull();
});

it('rejects invalid sizes and best-of values', function () {
    expect(fn () => (new SingleEliminationBuilder)->build(6, [1, 2, 3, 4, 5, 6]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new SingleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8], [0 => 4]))
        ->toThrow(InvalidArgumentException::class);
});
