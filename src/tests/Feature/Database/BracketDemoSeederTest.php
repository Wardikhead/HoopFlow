<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'BracketDemoSeeder'])->assertExitCode(0);
});

it('seeds three demo tournaments with teams and approved registrations', function () {
    expect(Tournament::count())->toBe(3)
        ->and(Team::count())->toBe(16)
        ->and(Player::count())->toBe(160);

    $counts = Tournament::query()
        ->withCount(['registrations as approved_registrations_count' => fn ($query) => $query->where('status', 'approved')])
        ->get()
        ->pluck('approved_registrations_count', 'slug');

    expect($counts['demo-single-elimination'])->toBe(16)
        ->and($counts['demo-double-elimination'])->toBe(16)
        ->and($counts['demo-round-robin'])->toBe(8);
});

it('generates a 15-match single-elimination bracket', function () {
    $tournament = Tournament::where('slug', 'demo-single-elimination')->firstOrFail();
    $bracket = Bracket::where('division_id', $tournament->divisions()->value('id'))->firstOrFail();

    expect($bracket->format)->toBe('single_elim')
        ->and($bracket->phase)->toBe('main')
        ->and(MatchModel::where('bracket_id', $bracket->id)->count())->toBe(15)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('status', 'scheduled')->count())->toBe(15);
});

it('generates a 30-anchor double-elimination bracket with best-of-three series and no reset', function () {
    $tournament = Tournament::where('slug', 'demo-double-elimination')->firstOrFail();
    $bracket = Bracket::where('division_id', $tournament->divisions()->value('id'))->firstOrFail();

    expect($bracket->format)->toBe('double_elim')
        ->and(MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->count())->toBe(30)
        ->and(MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->where('best_of', 3)->count())->toBe(8)
        ->and(MatchModel::where('bracket_id', $bracket->id)->whereNotNull('series_id')->count())->toBe(16)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'reset')->count())->toBe(0)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'grand_final')->whereNull('series_id')->value('best_of'))->toBe(3);
});

it('generates a complete 7-round round-robin group', function () {
    $tournament = Tournament::where('slug', 'demo-round-robin')->firstOrFail();
    $bracket = Bracket::where('division_id', $tournament->divisions()->value('id'))->firstOrFail();
    $matches = MatchModel::where('bracket_id', $bracket->id)->get();

    expect($bracket->format)->toBe('round_robin')
        ->and($bracket->phase)->toBe('group')
        ->and($matches)->toHaveCount(28)
        ->and($matches->pluck('round_number')->unique()->sort()->values()->all())->toBe(range(1, 7))
        ->and($matches->whereNull('home_team_id'))->toHaveCount(0)
        ->and($matches->whereNull('away_team_id'))->toHaveCount(0)
        ->and($matches->where('status', 'scheduled'))->toHaveCount(28);
});

it('leaves round-robin playoffs ungenerated', function () {
    $tournament = Tournament::where('slug', 'demo-round-robin')->firstOrFail();

    expect($tournament->ruleValue('playoff_teams'))->toBe(4)
        ->and(Bracket::where('division_id', $tournament->divisions()->value('id'))->where('phase', 'playoffs')->exists())->toBeFalse();
});
