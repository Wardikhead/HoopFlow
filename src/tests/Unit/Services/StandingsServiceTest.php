<?php

use App\Models\Division;
use App\Models\Tournament;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('computes standings for seeded tournament', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $service = new StandingsService;
    $rows = $service->compute($tournament);

    expect($rows)->toHaveCount(15);
    expect($rows->first())->toHaveKeys([
        'team_id', 'team_name', 'played', 'wins', 'losses',
        'points', 'points_for', 'points_against', 'point_diff',
    ]);
});

it('computes standings for seeded division', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $division = Division::where('tournament_id', $tournament->id)->firstOrFail();

    $service = new StandingsService;
    $rows = $service->compute($division);

    expect($rows)->toHaveCount(15);
});

it('sorts by wins, point diff, points for', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $service = new StandingsService;
    $rows = $service->compute($tournament);

    $sorted = $rows->values()->all();
    for ($i = 0; $i < count($sorted) - 1; $i++) {
        $a = $sorted[$i];
        $b = $sorted[$i + 1];
        $cmp = [$b['wins'], $b['point_diff'], $b['points_for']] <=> [$a['wins'], $a['point_diff'], $a['points_for']];
        expect($cmp)->toBeLessThanOrEqual(0);
    }
});

it('excludes non-final matches', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $service = new StandingsService;
    $rows = $service->compute($tournament);

    expect($rows->sum('played'))->toBe(18);
});
