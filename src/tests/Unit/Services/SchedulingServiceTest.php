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

it('auto-schedules the next series game on the same court after rest', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $tournament = $tournament->fresh();

    $bracketService = new BracketService;
    $bracketService->generate($tournament, 'single-elimination');

    $scheduler = new SchedulingService;
    $scheduler->generate($tournament);

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    $teams = $tournament->registrations()
        ->where('status', 'approved')
        ->pluck('team_id')
        ->take(2)
        ->values();

    $final->home_team_id = $teams[0];
    $final->away_team_id = $teams[1];
    $final->save();
    $bracketService->syncSeriesTeams($final);

    $finalSlot = $final->matchSlots()->firstOrFail();
    $bracketService->recordScore($final->fresh(), 80, 70);

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $game2Slot = $game2->matchSlots()->first();
    $earliestStart = $finalSlot->end_at->copy()->addMinutes($scheduler->restMinutes($tournament));

    expect($game2->status)->toBe('scheduled')
        ->and($game2Slot)->not->toBeNull()
        ->and((int) $game2Slot->court_id)->toBe((int) $finalSlot->court_id)
        ->and($game2Slot->start_at?->timestamp)->toBeGreaterThanOrEqual($earliestStart->timestamp);
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
