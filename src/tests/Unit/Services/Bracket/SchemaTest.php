<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds bracket stage and series columns with safe defaults', function () {
    expect(Schema::hasColumn('brackets', 'phase'))->toBeTrue()
        ->and(Schema::hasColumn('bracket_slots', 'stage'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'stage'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_id'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'game_number'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'best_of'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_home_wins'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_away_wins'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_winner_team_id'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'game_winner_team_id'))->toBeTrue();
});

it('exposes series relations and active game helpers', function () {
    $home = \App\Models\Team::factory()->create();
    $away = \App\Models\Team::factory()->create();

    $anchor = MatchModel::factory()->create([
        'best_of' => 3,
        'status' => 'in_progress',
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
    ]);

    $game2 = MatchModel::factory()->create([
        'bracket_id' => $anchor->bracket_id,
        'tournament_id' => $anchor->tournament_id,
        'stage' => $anchor->stage,
        'round_number' => $anchor->round_number,
        'match_number' => $anchor->match_number,
        'series_id' => $anchor->id,
        'game_number' => 2,
        'best_of' => 3,
        'status' => 'scheduled',
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
    ]);

    expect($anchor->isSeriesAnchor())->toBeTrue()
        ->and($game2->isSeriesGame())->toBeTrue()
        ->and($anchor->series)->toHaveCount(1)
        ->and($anchor->activeGame()?->id)->toBe($game2->id)
        ->and($game2->seriesAnchor?->id)->toBe($anchor->id);
});
