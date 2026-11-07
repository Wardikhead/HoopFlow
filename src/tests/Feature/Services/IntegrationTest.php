<?php

use App\Models\Division;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\EligibilityService;
use App\Services\SchedulingService;
use App\Services\StandingsService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a 16-slot single-elimination bracket on a fresh tournament', function () {
    $tournament = Tournament::factory()->create();
    $division = Division::factory()->create(['tournament_id' => $tournament->id]);

    for ($i = 1; $i <= 16; $i++) {
        $team = Team::factory()->create();
        Registration::factory()->create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed' => $i,
        ]);
    }

    $result = (new BracketService)->generate($tournament);

    expect($result['matches'])->toBe(15);
    expect(MatchModel::where('tournament_id', $tournament->id)->count())->toBe(15);
});

it('generates match slots for a tournament', function () {
    $this->seed(PrototypeSeeder::class);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    (new SchedulingService)->generate($tournament);

    expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe(15);
});

it('computes standings on seeded tournament and returns non-empty collection', function () {
    $this->seed(PrototypeSeeder::class);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $rows = (new StandingsService)->compute($tournament);

    expect($rows)->not->toBeEmpty();
});

it('refreshes eligibility on a team', function () {
    $this->seed(PrototypeSeeder::class);

    $team = Team::firstOrFail();

    (new EligibilityService)->refresh($team);

    expect($team->players()->count())->toBeGreaterThan(0);
});

it('corrects a score and propagates the winner', function () {
    $this->seed(PrototypeSeeder::class);

    $match = MatchModel::where('round_number', 8)->where('match_number', 2)->firstOrFail();

    (new BracketService)->correctScore($match, 70, 64, 'Clock review');

    $match->refresh();
    expect($match->home_score)->toBe(70);
    expect($match->away_score)->toBe(64);
    expect($match->winner_team_id)->toBe($match->home_team_id);

    $next = MatchModel::where('round_number', 4)->where('match_number', 1)->firstOrFail();
    expect($next->away_team_id)->toBe($match->home_team_id);
});
