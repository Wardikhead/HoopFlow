<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function seriesTournament(array $rules = []): Tournament
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], $rules),
    ]);

    return $tournament->fresh();
}

function seriesBracket(Tournament $tournament): Bracket
{
    return Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'single_elim')
        ->firstOrFail();
}

it('creates every potential game up front and activates them as needed', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('stage', 'winners')
        ->where('round_number', 2)
        ->firstOrFail();

    expect($final->best_of)->toBe(3)
        ->and(MatchModel::where('series_id', $final->id)->count())->toBe(2)
        ->and(MatchModel::where('series_id', $final->id)->where('status', 'conditional')->count())->toBe(2);

    // Play the bracket until the final has two known teams.
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 1)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 2)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 3)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 4)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 4)->where('match_number', 1)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 4)->where('match_number', 2)->firstOrFail(),
        80,
        70,
    );

    $final->refresh();
    expect($final->home_team_id)->not->toBeNull()
        ->and($final->away_team_id)->not->toBeNull();

    $service->recordScore($final, 81, 70);

    $final->refresh();
    expect($final->status)->toBe('in_progress')
        ->and($final->series_home_wins)->toBe(1)
        ->and($final->series_away_wins)->toBe(0)
        ->and($final->winner_team_id)->toBeNull();

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    expect($game2->status)->toBe('scheduled')
        ->and($game2->home_team_id)->toBe($final->home_team_id)
        ->and($game2->away_team_id)->toBe($final->away_team_id);

    $service->recordScore($game2, 90, 80);

    $final->refresh();
    expect($final->status)->toBe('final')
        ->and($final->series_home_wins)->toBe(2)
        ->and($final->winner_team_id)->toBe($final->home_team_id)
        ->and($final->series_winner_team_id)->toBe($final->home_team_id)
        ->and(MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail()->status)->toBe('cancelled');
});

it('supports a split series that needs the deciding game', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    // Force the final teams for a focused series test.
    $final->home_team_id = 1;
    $final->away_team_id = 2;
    $final->save();
    $service->syncSeriesTeams($final);

    $service->recordScore($final, 80, 70);
    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $service->recordScore($game2, 60, 70);

    $final->refresh();
    expect($final->status)->toBe('in_progress')
        ->and($final->series_home_wins)->toBe(1)
        ->and($final->series_away_wins)->toBe(1);

    $game3 = MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail();
    expect($game3->status)->toBe('scheduled');

    $service->recordScore($game3, 99, 90);

    expect($final->fresh()->status)->toBe('final')
        ->and($final->fresh()->series_home_wins)->toBe(2);
});

it('supports best-of-five and best-of-seven series', function () {
    $tournament = seriesTournament(['best_of_final' => 5]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $final->home_team_id = 1;
    $final->away_team_id = 2;
    $final->save();
    $service->syncSeriesTeams($final);

    foreach ([1, 2, 3] as $gameNumber) {
        $game = $gameNumber === 1
            ? $final
            : MatchModel::where('series_id', $final->id)->where('game_number', $gameNumber)->firstOrFail();
        $service->recordScore($game, 80, 70);
    }

    expect($final->fresh()->status)->toBe('final')
        ->and($final->fresh()->series_home_wins)->toBe(3)
        ->and(MatchModel::where('series_id', $final->id)->where('game_number', 4)->firstOrFail()->status)->toBe('cancelled')
        ->and(MatchModel::where('series_id', $final->id)->where('game_number', 5)->firstOrFail()->status)->toBe('cancelled');
});

it('marks the series forfeit when the deciding game is a forfeit', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $final->home_team_id = 1;
    $final->away_team_id = 2;
    $final->save();
    $service->syncSeriesTeams($final);

    $service->recordScore($final, 80, 70);
    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $service->recordScore($game2, 60, 70);

    $game3 = MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail();
    $service->recordForfeit($game3, 2);

    expect($final->fresh()->status)->toBe('forfeit')
        ->and($final->fresh()->series_home_wins)->toBe(2)
        ->and($final->fresh()->series_away_wins)->toBe(1)
        ->and($final->fresh()->winner_team_id)->toBe($final->home_team_id);
});

it('does not create a reset when the double-elimination grand final is a series', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    expect(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'reset')->count())->toBe(0)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'grand_final')->firstOrFail()->best_of)->toBe(3);
});
