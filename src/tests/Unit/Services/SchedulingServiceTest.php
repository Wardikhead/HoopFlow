<?php

use App\Models\MatchModel;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('schedules double-elimination rounds in stage order and skips conditional games', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new BracketService)->generate($tournament, 'double-elimination');
    $tournament = $tournament->fresh();

    $plan = (new SchedulingService)->plan($tournament);
    $scheduled = MatchModel::whereIn('id', collect($plan['assignments'])->pluck('matchId'))->get();

    expect($plan['assignments'])->toHaveCount(14)
        ->and($scheduled->where('stage', 'winners'))->toHaveCount(7)
        ->and($scheduled->where('stage', 'losers'))->toHaveCount(6)
        ->and($scheduled->where('stage', 'grand_final'))->toHaveCount(1)
        ->and($scheduled->where('stage', 'reset'))->toHaveCount(0)
        ->and($scheduled->contains(fn (MatchModel $match): bool => $match->status === 'conditional'))->toBeFalse();

    $first = $scheduled->firstWhere('id', $plan['assignments'][0]['matchId']);
    $last = $scheduled->firstWhere('id', $plan['assignments'][count($plan['assignments']) - 1]['matchId']);

    expect($first->stage)->toBe('winners')
        ->and($first->round_number)->toBe(1)
        ->and($last->stage)->toBe('grand_final');
});

it('schedules an activated series game and still skips unplayed conditional games', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    (new BracketService)->generate($tournament, 'single-elimination');
    $tournament = $tournament->fresh();

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $game2->status = 'scheduled';
    $game2->home_team_id = 1;
    $game2->away_team_id = 2;
    $game2->save();

    $plan = (new SchedulingService)->plan($tournament);
    $ids = collect($plan['assignments'])->pluck('matchId');

    expect($ids)->toContain($game2->id)
        ->and($ids)->not->toContain(MatchModel::where('series_id', $final->id)->where('game_number', 3)->value('id'));
});

it('orders round-robin matchdays ascending', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new BracketService)->generate($tournament, 'round-robin');
    $tournament = $tournament->fresh();

    $plan = (new SchedulingService)->plan($tournament);
    $scheduled = MatchModel::whereIn('id', collect($plan['assignments'])->pluck('matchId'))->get()->keyBy('id');
    $rounds = collect($plan['assignments'])
        ->map(fn (array $assignment): int => (int) $scheduled[$assignment['matchId']]->round_number)
        ->all();

    expect($rounds)->toBe(collect($rounds)->sort()->values()->all());
});
