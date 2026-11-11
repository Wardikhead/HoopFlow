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

function deTournament(): Tournament
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'double-elimination',
            'best_of_final' => 1,
            'best_of_semifinal' => 1,
            'best_of_quarterfinal' => 1,
        ]),
    ]);

    return $tournament->fresh();
}

function deBracket(Tournament $tournament): Bracket
{
    return Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();
}

function forceGrandFinalTeams(Bracket $bracket): array
{
    $winnersFinal = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'winners')
        ->whereNull('series_id')
        ->orderByDesc('round_number')
        ->firstOrFail();

    $winnersFinal->home_team_id = 1;
    $winnersFinal->away_team_id = 3;
    $winnersFinal->home_score = 80;
    $winnersFinal->away_score = 70;
    $winnersFinal->winner_team_id = 1;
    $winnersFinal->series_winner_team_id = 1;
    $winnersFinal->game_winner_team_id = 1;
    $winnersFinal->status = 'final';
    $winnersFinal->save();

    $grandFinal = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'grand_final')
        ->firstOrFail();
    $reset = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'reset')
        ->firstOrFail();

    $grandFinal->home_team_id = 1;
    $grandFinal->away_team_id = 2;
    $grandFinal->status = 'scheduled';
    $grandFinal->save();

    $reset->home_team_id = 1;
    $reset->away_team_id = 2;
    $reset->status = 'conditional';
    $reset->save();

    return [$grandFinal, $reset];
}

it('crowns the winners-bracket champion when it wins the first grand final', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 80, 70);

    expect($grandFinal->fresh()->status)->toBe('final')
        ->and($reset->fresh()->status)->toBe('cancelled')
        ->and($service->champion($tournament)?->id)->toBe(1);
});

it('requires the reset when the losers-bracket champion wins the first grand final', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 70, 80);

    expect($grandFinal->fresh()->status)->toBe('final')
        ->and($reset->fresh()->status)->toBe('scheduled')
        ->and($reset->fresh()->home_team_id)->toBe(1)
        ->and($reset->fresh()->away_team_id)->toBe(2)
        ->and($service->champion($tournament))->toBeNull();

    $service->recordScore($reset->fresh(), 85, 80);

    expect($reset->fresh()->status)->toBe('final')
        ->and($service->champion($tournament)?->id)->toBe(1);
});

it('re-evaluates the reset when the first grand final is corrected', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 70, 80);
    expect($reset->fresh()->status)->toBe('scheduled');

    $service->correctScore($grandFinal, 80, 70, 'Clock review');

    expect($grandFinal->fresh()->status)->toBe('final')
        ->and($reset->fresh()->status)->toBe('cancelled')
        ->and($service->champion($tournament)?->id)->toBe(1);
});

it('rejects correcting the first grand final after the reset has been played', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 70, 80);
    $service->recordScore($reset->fresh(), 85, 80);

    expect(fn () => $service->correctScore($grandFinal, 80, 70, 'Clock review'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects correcting a series winner when a downstream matchup already has a result', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'best_of_semifinal' => 3,
            'best_of_final' => 1,
        ]),
    ]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'single_elim')
        ->firstOrFail();

    $semi = MatchModel::where('bracket_id', $bracket->id)
        ->where('round_number', 4)
        ->where('match_number', 1)
        ->firstOrFail();
    $semi->home_team_id = 1;
    $semi->away_team_id = 2;
    $semi->save();
    $service->syncSeriesTeams($semi);

    $service->recordScore($semi, 80, 70);

    $game2 = MatchModel::where('series_id', $semi->id)
        ->where('game_number', 2)
        ->firstOrFail();
    $service->recordScore($game2, 80, 70);

    expect($semi->fresh()->status)->toBe('final');

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->where('round_number', 2)
        ->where('match_number', 1)
        ->firstOrFail();
    $final->home_team_id = 1;
    $final->away_team_id = 3;
    $final->save();
    $service->recordScore($final, 80, 70);

    expect(fn () => $service->correctScore($semi, 70, 80, 'Clock review'))
        ->toThrow(InvalidArgumentException::class);
});
