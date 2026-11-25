<?php

/** Tests for Standings service test. */

use App\Models\Division;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
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

it('sorts by wins descending', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $service = new StandingsService;
    $rows = $service->compute($tournament);

    $sorted = $rows->values()->all();
    for ($i = 0; $i < count($sorted) - 1; $i++) {
        expect($sorted[$i]['wins'])->toBeGreaterThanOrEqual($sorted[$i + 1]['wins']);
    }
});

it('excludes non-final matches', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $service = new StandingsService;
    $rows = $service->compute($tournament);

    expect($rows->sum('played'))->toBe(18);
});

it('assigns rank, seed, and playoff qualification for the seeded tournament', function () {
    $this->artisan('db:seed', ['--class' => 'PrototypeSeeder'])->assertExitCode(0);

    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $service = new StandingsService;
    $rows = $service->compute($tournament);
    $cut = $service->playoffCut($tournament);

    expect($cut)->toBe(8)
        ->and($rows->pluck('rank')->all())->toBe(range(1, $rows->count()))
        ->and($rows->where('qualified', true))->toHaveCount($cut)
        ->and($rows->where('qualified', false))->toHaveCount($rows->count() - $cut)
        ->and($rows->every(fn (array $row): bool => array_key_exists('seed', $row)))->toBeTrue();
});

/**
 * Standings tournament.
 *
 * @param  array<mixed>  $rules  The rules.
 * @return array<mixed> The result.
 */
function standingsTournament(array $rules = []): array
{
    $tournament = Tournament::factory()->create([
        'rules_json' => array_merge(['format' => 'round-robin', 'slots' => 4], $rules),
    ]);
    $division = $tournament->divisions()->create([
        'name' => 'Open',
        'max_teams' => 4,
    ]);

    $teams = collect(range(1, 4))->map(function (int $seed) use ($tournament, $division): Team {
        $team = Team::factory()->create([
            'organization_id' => $tournament->organization_id,
            'name' => 'Standings Team '.$seed,
        ]);

        Registration::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed' => $seed,
        ]);

        return $team;
    });

    $bracket = $tournament->divisions()->first()->brackets()->create([
        'name' => 'Group',
        'format' => 'round_robin',
        'phase' => 'group',
        'seeding_method' => 'manual',
        'status' => 'generated',
        'generated_at' => now(),
    ]);

    return [$tournament, $division, $teams, $bracket];
}

it('ranks tied teams by head-to-head before point differential', function () {
    [$tournament, , $teams, $bracket] = standingsTournament();

    [$a, $b, $c, $d] = $teams->all();

    $makeMatch = function (Team $home, Team $away, int $homeScore, int $awayScore) use ($tournament, $bracket): void {
        MatchModel::factory()->create([
            'bracket_id' => $bracket->id,
            'tournament_id' => $tournament->id,
            'stage' => 'round_robin',
            'round_number' => 1,
            'match_number' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'winner_team_id' => $homeScore > $awayScore ? $home->id : $away->id,
            'game_winner_team_id' => $homeScore > $awayScore ? $home->id : $away->id,
            'status' => 'final',
        ]);
    };

    // A is 2-0. B and C are both 1-1, but C beat B head-to-head.
    // B has the better point differential (+40 vs -10).
    $makeMatch($a, $b, 60, 50);
    $makeMatch($a, $c, 60, 40);
    $makeMatch($b, $d, 100, 40);
    $makeMatch($c, $b, 60, 50);

    $rows = (new StandingsService)->compute($tournament)->keyBy('team_id');

    expect($rows[$a->id]['rank'])->toBe(1)
        ->and($rows[$c->id]['rank'])->toBe(2)
        ->and($rows[$b->id]['rank'])->toBe(3)
        ->and($rows[$d->id]['rank'])->toBe(4);
});

it('uses point differential when tied teams have no head-to-head result', function () {
    [$tournament, , $teams, $bracket] = standingsTournament();

    [$a, $b, $c, $d] = $teams->all();

    $makeMatch = function (Team $home, Team $away, int $homeScore, int $awayScore) use ($tournament, $bracket): void {
        MatchModel::factory()->create([
            'bracket_id' => $bracket->id,
            'tournament_id' => $tournament->id,
            'stage' => 'round_robin',
            'round_number' => 1,
            'match_number' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'winner_team_id' => $homeScore > $awayScore ? $home->id : $away->id,
            'game_winner_team_id' => $homeScore > $awayScore ? $home->id : $away->id,
            'status' => 'final',
        ]);
    };

    // B and C are both 1-1 and never played each other. B has the better
    // point differential (+50 vs -10), so B ranks above C.
    $makeMatch($a, $b, 60, 50);
    $makeMatch($a, $c, 60, 40);
    $makeMatch($b, $d, 100, 40);
    $makeMatch($c, $d, 50, 40);

    $rows = (new StandingsService)->compute($tournament)->keyBy('team_id');

    expect($rows[$a->id]['rank'])->toBe(1)
        ->and($rows[$b->id]['rank'])->toBe(2)
        ->and($rows[$c->id]['rank'])->toBe(3)
        ->and($rows[$d->id]['rank'])->toBe(4);
});

it('uses playoff teams for the qualification cut', function () {
    [$tournament] = standingsTournament(['playoff_teams' => 2]);

    $rows = (new StandingsService)->compute($tournament);

    expect($rows->where('qualified', true))->toHaveCount(2)
        ->and($rows->first()['qualified'])->toBeTrue();
});

it('excludes playoff games from round-robin group standings', function () {
    [$tournament, , $teams, $bracket] = standingsTournament();

    [$a, $b] = $teams->all();

    MatchModel::factory()->create([
        'bracket_id' => $bracket->id,
        'tournament_id' => $tournament->id,
        'stage' => 'round_robin',
        'round_number' => 1,
        'match_number' => 1,
        'home_team_id' => $a->id,
        'away_team_id' => $b->id,
        'home_score' => 60,
        'away_score' => 50,
        'winner_team_id' => $a->id,
        'game_winner_team_id' => $a->id,
        'status' => 'final',
    ]);

    $playoffBracket = $tournament->divisions()->first()->brackets()->create([
        'name' => 'Playoffs',
        'format' => 'single_elim',
        'phase' => 'playoffs',
        'seeding_method' => 'manual',
        'status' => 'generated',
        'generated_at' => now(),
    ]);

    MatchModel::factory()->create([
        'bracket_id' => $playoffBracket->id,
        'tournament_id' => $tournament->id,
        'stage' => 'winners',
        'round_number' => 1,
        'match_number' => 1,
        'home_team_id' => $b->id,
        'away_team_id' => $a->id,
        'home_score' => 80,
        'away_score' => 70,
        'winner_team_id' => $b->id,
        'game_winner_team_id' => $b->id,
        'status' => 'final',
    ]);

    $rows = (new StandingsService)->compute($tournament)->keyBy('team_id');

    expect($rows[$a->id]['wins'])->toBe(1)
        ->and($rows[$b->id]['wins'])->toBe(0)
        ->and($rows[$a->id]['point_diff'])->toBe(10);
});
