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

it('generates match slots for seeded tournament', function () {
    $tournament = Tournament::first();
    $service = new SchedulingService;
    $service->generate($tournament);

    $matchIds = MatchModel::where('tournament_id', $tournament->id)->pluck('id');
    expect(MatchSlot::whereIn('match_id', $matchIds)->count())->toBeGreaterThan(0);
});

it('is idempotent', function () {
    $tournament = Tournament::first();
    $service = new SchedulingService;
    $service->generate($tournament);
    $first = MatchSlot::count();
    $service->generate($tournament);
    expect(MatchSlot::count())->toBe($first);
});
