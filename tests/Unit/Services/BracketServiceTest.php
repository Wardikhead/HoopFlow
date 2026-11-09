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

it('records a score for a scheduled match and propagates the winner', function () {
    $match = MatchModel::where('round_number', 8)->where('match_number', 3)->firstOrFail();
    $homeTeamId = $match->home_team_id;
    $awayTeamId = $match->away_team_id;

    (new BracketService)->recordScore($match, 81, 74);

    $match->refresh();
    expect($match->home_score)->toBe(81);
    expect($match->away_score)->toBe(74);
    expect($match->winner_team_id)->toBe($homeTeamId);
    expect($match->status)->toBe('final');
    expect($match->forfeit_reason)->toBeNull();

    $next = MatchModel::where('bracket_id', $match->bracket_id)
        ->where('round_number', 4)
        ->where('match_number', 2)
        ->firstOrFail();
    expect($next->home_team_id)->toBe($homeTeamId);
    expect($next->away_team_id)->not->toBe($homeTeamId);

    expect(AuditLog::where('action', 'score.save')->where('entity_id', $match->id)->count())->toBe(1);
    expect($awayTeamId)->not->toBe($homeTeamId);
});

it('rejects recording a score on a completed match', function () {
    $match = MatchModel::where('round_number', 8)->where('match_number', 2)->firstOrFail();

    expect(fn () => (new BracketService)->recordScore($match, 70, 60))
        ->toThrow(InvalidArgumentException::class, 'Use correction to change a recorded result.');

    expect(AuditLog::where('action', 'score.save')->where('entity_id', $match->id)->count())->toBe(0);
});

it('rejects a tie when recording a score', function () {
    $match = MatchModel::where('round_number', 8)->where('match_number', 3)->firstOrFail();

    expect(fn () => (new BracketService)->recordScore($match, 70, 70))
        ->toThrow(InvalidArgumentException::class, 'Elimination games cannot end in a tie.');
});

it('records a forfeit with a 20-0 score and propagates the winner', function () {
    $match = MatchModel::where('round_number', 8)->where('match_number', 4)->firstOrFail();
    $homeTeamId = $match->home_team_id;
    $awayTeamId = $match->away_team_id;

    (new BracketService)->recordForfeit($match, 1);

    $match->refresh();
    expect($match->home_score)->toBe(0);
    expect($match->away_score)->toBe(20);
    expect($match->winner_team_id)->toBe($awayTeamId);
    expect($match->status)->toBe('forfeit');
    expect($match->forfeit_reason)->not->toBeNull();

    $next = MatchModel::where('bracket_id', $match->bracket_id)
        ->where('round_number', 4)
        ->where('match_number', 2)
        ->firstOrFail();
    expect($next->away_team_id)->toBe($awayTeamId);
    expect($homeTeamId)->not->toBe($awayTeamId);

    $audit = AuditLog::where('action', 'score.forfeit')->where('entity_id', $match->id)->firstOrFail();
    expect($audit->after_json['forfeit_side'])->toBe(1);
});

it('rejects a forfeit on a completed match or invalid side', function () {
    $completed = MatchModel::where('round_number', 8)->where('match_number', 2)->firstOrFail();
    $scheduled = MatchModel::where('round_number', 8)->where('match_number', 3)->firstOrFail();

    expect(fn () => (new BracketService)->recordForfeit($completed, 1))
        ->toThrow(InvalidArgumentException::class, 'Only a scheduled game can be recorded as a forfeit.');
    expect(fn () => (new BracketService)->recordForfeit($scheduled, 3))
        ->toThrow(InvalidArgumentException::class, 'Choose which team forfeited.');
});

it('clears the forfeit reason when a forfeit result is corrected', function () {
    $match = MatchModel::where('status', 'forfeit')->firstOrFail();

    (new BracketService)->correctScore($match, 70, 60, 'Score entered after review');

    $match->refresh();
    expect($match->status)->toBe('final');
    expect($match->forfeit_reason)->toBeNull();
    expect($match->home_score)->toBe(70);
    expect($match->away_score)->toBe(60);
});
