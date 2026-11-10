<?php

use App\Support\LandingShowcase;
use Tests\TestCase;

uses(TestCase::class);

it('provides a complete ten-team showcase league', function () {
    $teams = collect((new LandingShowcase)->teams());

    expect($teams)->toHaveCount(10)
        ->and($teams->pluck('id')->unique())->toHaveCount(10)
        ->and($teams->pluck('short')->unique())->toHaveCount(10);
});

it('builds a seven-match bracket from seeds one through eight', function () {
    $showcase = new LandingShowcase;
    $bracket = $showcase->bracket();
    $teams = collect($showcase->teams())->keyBy('id');

    expect($bracket['rounds'])->toHaveCount(3)
        ->and($bracket['rounds'][0]['matches'])->toHaveCount(4)
        ->and($bracket['rounds'][1]['matches'])->toHaveCount(2)
        ->and($bracket['rounds'][2]['matches'])->toHaveCount(1);

    $matches = collect($bracket['rounds'])->flatMap(fn (array $round) => $round['matches']);

    expect($matches)->toHaveCount(7);

    foreach ($matches as $match) {
        foreach (['home', 'away'] as $side) {
            if ($match[$side] !== null) {
                expect($teams->has($match[$side]))->toBeTrue();
            }
        }
    }

    $seeds = $matches
        ->where('round_key', 'quarterfinals')
        ->flatMap(fn (array $match) => [$match['home'], $match['away']])
        ->sort()
        ->values()
        ->all();

    expect($seeds)->toBe([1, 2, 3, 4, 5, 6, 7, 8]);
});

it('keeps completed matches consistent and the live game unfinished', function () {
    $bracket = (new LandingShowcase)->bracket();
    $matches = collect($bracket['rounds'])
        ->flatMap(fn (array $round) => $round['matches'])
        ->keyBy('id');

    foreach ($matches->where('status', 'final') as $match) {
        expect($match['home_score'])->not->toBeNull()
            ->and($match['away_score'])->not->toBeNull()
            ->and($match['winner'])->not->toBeNull();
    }

    $live = $matches->firstWhere('status', 'live');

    expect($live)->not->toBeNull()
        ->and($live['winner'])->toBeNull()
        ->and($live['home_score'])->not->toBeNull()
        ->and($live['away_score'])->not->toBeNull();
});

it('keeps standings balanced and qualifies the top eight', function () {
    $rows = collect((new LandingShowcase)->standings());

    expect($rows)->toHaveCount(10)
        ->and($rows->sum('wins'))->toBe(20)
        ->and($rows->sum('losses'))->toBe(20)
        ->and($rows->sum('point_diff'))->toBe(0)
        ->and($rows->where('qualified', true))->toHaveCount(8)
        ->and($rows->first()['rank'])->toBe(1)
        ->and($rows->last()['rank'])->toBe(10);
});

it('schedules the same seven matches across three days', function () {
    $showcase = new LandingShowcase;
    $scheduledIds = collect($showcase->schedule())
        ->flatMap(fn (array $day) => $day['matches'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $bracketIds = collect($showcase->bracket()['rounds'])
        ->flatMap(fn (array $round) => $round['matches'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($showcase->schedule())->toHaveCount(3)
        ->and($scheduledIds)->toBe($bracketIds);
});

it('ships non-empty marketing content', function () {
    $showcase = new LandingShowcase;

    expect($showcase->announcements())->not->toBeEmpty()
        ->and($showcase->flow())->toHaveCount(5)
        ->and($showcase->roles())->toHaveCount(3)
        ->and($showcase->eligibility()['players'])->toHaveCount(4)
        ->and($showcase->eligibility()['payments'])->toHaveCount(2);
});
