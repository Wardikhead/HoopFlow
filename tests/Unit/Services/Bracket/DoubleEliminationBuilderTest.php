<?php

/** Tests for Double elimination builder test. */

use App\Services\Bracket\DoubleEliminationBuilder;
use App\Services\Bracket\MatchupPlan;

/**
 * De matchup.
 *
 * @param  array<mixed>  $matchups  The matchups.
 * @param  string  $stage  The stage.
 * @param  int  $round  The round.
 * @param  int  $match  The match.
 * @return MatchupPlan The result.
 */
function deMatchup(array $matchups, string $stage, int $round, int $match): MatchupPlan
{
    $key = $stage.':'.$round.':'.$match;

    foreach ($matchups as $matchup) {
        if ($matchup->key() === $key) {
            return $matchup;
        }
    }

    throw new RuntimeException('Missing matchup '.$key);
}

it('builds the standard double-elimination shape for every supported size', function (int $size, int $total, int $winners, int $losers) {
    $plan = (new DoubleEliminationBuilder)->build($size, range(1, $size));

    $counts = collect($plan->matchups)->countBy('stage');

    expect($plan->matchups)->toHaveCount($total)
        ->and($counts->get('winners'))->toBe($winners)
        ->and($counts->get('losers'))->toBe($losers)
        ->and($counts->get('grand_final'))->toBe(1)
        ->and($counts->get('reset'))->toBe(1);
})->with([
    [4, 7, 3, 2],
    [8, 15, 7, 6],
    [16, 31, 15, 14],
    [32, 63, 31, 30],
]);

it('routes winners losers and losers-bracket winners correctly', function () {
    $plan = (new DoubleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8]);
    $matchups = $plan->matchups;

    $lb1m1 = deMatchup($matchups, 'losers', 1, 1);
    expect($lb1m1->home->type)->toBe('loser')
        ->and($lb1m1->home->sourceStage)->toBe('winners')
        ->and($lb1m1->home->sourceRound)->toBe(1)
        ->and($lb1m1->home->sourceMatch)->toBe(1)
        ->and($lb1m1->away->sourceMatch)->toBe(2);

    $lb2m1 = deMatchup($matchups, 'losers', 2, 1);
    expect($lb2m1->home->type)->toBe('winner')
        ->and($lb2m1->home->sourceStage)->toBe('losers')
        ->and($lb2m1->home->sourceRound)->toBe(1)
        ->and($lb2m1->away->type)->toBe('loser')
        ->and($lb2m1->away->sourceStage)->toBe('winners')
        ->and($lb2m1->away->sourceRound)->toBe(2);

    $lb3m1 = deMatchup($matchups, 'losers', 3, 1);
    expect($lb3m1->home->type)->toBe('winner')
        ->and($lb3m1->home->sourceRound)->toBe(2)
        ->and($lb3m1->away->sourceRound)->toBe(2)
        ->and($lb3m1->away->sourceMatch)->toBe(2);

    $lb4m1 = deMatchup($matchups, 'losers', 4, 1);
    expect($lb4m1->home->sourceRound)->toBe(3)
        ->and($lb4m1->away->sourceStage)->toBe('winners')
        ->and($lb4m1->away->sourceRound)->toBe(3);

    $gf = deMatchup($matchups, 'grand_final', 1, 1);
    expect($gf->home->sourceStage)->toBe('winners')
        ->and($gf->home->sourceRound)->toBe(3)
        ->and($gf->away->sourceStage)->toBe('losers')
        ->and($gf->away->sourceRound)->toBe(4);

    $reset = deMatchup($matchups, 'reset', 1, 1);
    expect($reset->home->sourceStage)->toBe('winners')
        ->and($reset->away->sourceStage)->toBe('losers');
});

it('uses the configured best-of for winners rounds and the grand final', function () {
    $plan = (new DoubleEliminationBuilder)->build(8, range(1, 8), [
        0 => 5,
        1 => 3,
        2 => 3,
    ]);

    expect(deMatchup($plan->matchups, 'winners', 1, 1)->bestOf)->toBe(3)
        ->and(deMatchup($plan->matchups, 'winners', 2, 1)->bestOf)->toBe(3)
        ->and(deMatchup($plan->matchups, 'winners', 3, 1)->bestOf)->toBe(5)
        ->and(deMatchup($plan->matchups, 'grand_final', 1, 1)->bestOf)->toBe(5)
        ->and(deMatchup($plan->matchups, 'losers', 2, 1)->bestOf)->toBe(1);
});

it('omits the reset when the grand final is a series', function () {
    $plan = (new DoubleEliminationBuilder)->build(8, range(1, 8), [0 => 3]);

    expect(collect($plan->matchups)->where('stage', 'reset'))->toHaveCount(0);
});

it('rejects invalid sizes', function () {
    expect(fn () => (new DoubleEliminationBuilder)->build(2, [1, 2]))
        ->toThrow(InvalidArgumentException::class);
});
