<?php

use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Services\SchedulingService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function clearRecordedResults(Tournament $tournament): void
{
    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
            'completed_at' => null,
        ]);
}

it('generates match slots for seeded tournament', function () {
    $tournament = Tournament::first();
    clearRecordedResults($tournament);

    $service = new SchedulingService;
    $service->generate($tournament);

    $matchIds = MatchModel::where('tournament_id', $tournament->id)->pluck('id');
    expect(MatchSlot::whereIn('match_id', $matchIds)->count())->toBeGreaterThan(0);
});

it('is idempotent', function () {
    $tournament = Tournament::first();
    clearRecordedResults($tournament);

    $service = new SchedulingService;
    $service->generate($tournament);
    $first = MatchSlot::count();
    $service->generate($tournament);
    expect(MatchSlot::count())->toBe($first);
});
