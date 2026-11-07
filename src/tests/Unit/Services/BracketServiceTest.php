<?php

use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('generates a 16-team single-elimination bracket with byes', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $result = (new BracketService)->generate($tournament);

    expect($result['matches'])->toBe(15);
    expect($result['byes'])->toBe(1);
});

it('generates an 8-team round-robin bracket', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $result = (new BracketService)->generate($tournament, 'round-robin');

    expect($result['matches'])->toBe(28);
    expect($result['byes'])->toBe(0);
});

it('generates an 8-team double-elimination bracket', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $result = (new BracketService)->generate($tournament, 'double-elimination');

    expect($result['matches'])->toBe(14);
    expect($result['byes'])->toBe(0);
});

it('throws on invalid format', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    expect(fn () => (new BracketService)->generate($tournament, 'knockout'))
        ->toThrow(InvalidArgumentException::class);
});

it('corrects a score and propagates the changed winner', function () {
    $match = MatchModel::where('round_number', 8)->where('match_number', 2)->firstOrFail();

    (new BracketService)->correctScore($match, 70, 64, 'Clock review');

    $match->refresh();
    expect($match->home_score)->toBe(70);
    expect($match->away_score)->toBe(64);
    expect($match->winner_team_id)->toBe($match->home_team_id);
    expect($match->status)->toBe('final');

    $next = MatchModel::where('round_number', 4)->where('match_number', 1)->firstOrFail();
    expect($next->away_team_id)->toBe($match->home_team_id);

    expect(AuditLog::where('action', 'score.correct')->count())->toBeGreaterThanOrEqual(1);
});

it('finalizes a match with scores and propagates winner', function () {
    $match = MatchModel::where('round_number', 8)->where('match_number', 3)->firstOrFail();
    $match->home_score = 80;
    $match->away_score = 70;
    $match->save();

    (new BracketService)->finalizeMatch($match);

    $match->refresh();
    expect($match->status)->toBe('final');
    expect($match->winner_team_id)->toBe($match->home_team_id);
});
