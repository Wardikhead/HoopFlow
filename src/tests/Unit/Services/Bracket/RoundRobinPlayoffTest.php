<?php

/** Tests for Round robin playoff test. */

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\StandingsService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

/**
 * Playoff tournament.
 *
 * @param  int  $playoffTeams  The playoff teams.
 * @return Tournament The result.
 */
function playoffTournament(int $playoffTeams = 4): Tournament
{
    $base = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $tournament = $base->replicate();
    $tournament->name = 'Playoff Probe '.uniqid();
    $tournament->slug = 'playoff-probe-'.uniqid();
    $tournament->rules_json = array_merge($base->rules_json ?? [], [
        'format' => 'round-robin',
        'slots' => 4,
        'playoff_teams' => $playoffTeams,
        'round_robin_legs' => 1,
    ]);
    $tournament->save();

    $division = $tournament->divisions()->create([
        'name' => 'Open',
        'max_teams' => 4,
    ]);

    for ($i = 1; $i <= 4; $i++) {
        $team = Team::create([
            'organization_id' => $tournament->organization_id,
            'name' => 'Playoff Team '.$i.' '.uniqid(),
            'slug' => 'playoff-team-'.$i.'-'.uniqid(),
            'city' => 'City',
        ]);

        Registration::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed' => $i,
        ]);
    }

    return $tournament->fresh();
}

/**
 * Play group stage.
 *
 * @param  BracketService  $service  The service.
 * @param  Tournament  $tournament  The tournament.
 * @return void Nothing.
 */
function playGroupStage(BracketService $service, Tournament $tournament): void
{
    $group = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'group')
        ->firstOrFail();

    $guard = 0;

    while ($guard++ < 50) {
        $match = MatchModel::where('bracket_id', $group->id)
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->first();

        if (! $match instanceof MatchModel) {
            break;
        }

        $service->recordScore($match, 80, 70);
    }
}

it('blocks playoff generation until every group game is complete', function () {
    $tournament = playoffTournament();
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');

    expect(fn () => $service->generatePlayoffs($tournament))
        ->toThrow(InvalidArgumentException::class, 'All group games must be final before generating playoffs.');
});

it('seeds the playoff bracket from the final standings and applies series settings', function () {
    $tournament = playoffTournament();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);

    $result = $service->generatePlayoffs($tournament->fresh());
    $playoff = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'playoffs')
        ->firstOrFail();

    $standings = app(StandingsService::class)->compute($tournament->fresh());
    $firstRound = MatchModel::where('bracket_id', $playoff->id)
        ->whereNull('series_id')
        ->where('round_number', 4)
        ->orderBy('match_number')
        ->get();

    expect($playoff->format)->toBe('single_elim')
        ->and($result['matches'])->toBe(3)
        ->and($firstRound)->toHaveCount(2)
        ->and($firstRound[0]->home_team_id)->toBe($standings[0]['team_id'])
        ->and($firstRound[0]->away_team_id)->toBe($standings[3]['team_id'])
        ->and($firstRound[1]->home_team_id)->toBe($standings[1]['team_id'])
        ->and($firstRound[1]->away_team_id)->toBe($standings[2]['team_id']);

    $final = MatchModel::where('bracket_id', $playoff->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    expect($final->best_of)->toBe(3)
        ->and(MatchModel::where('series_id', $final->id)->count())->toBe(2);
});

it('crowns the playoff final winner as champion', function () {
    $tournament = playoffTournament();
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);
    $service->generatePlayoffs($tournament->fresh());

    $playoff = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'playoffs')
        ->firstOrFail();

    foreach ([4, 2] as $round) {
        $matches = MatchModel::where('bracket_id', $playoff->id)
            ->where('round_number', $round)
            ->whereNull('series_id')
            ->orderBy('match_number')
            ->get();

        foreach ($matches as $match) {
            if ($match->home_team_id !== null && $match->away_team_id !== null) {
                $service->recordScore($match->fresh(), 80, 70);
            }
        }
    }

    expect($service->champion($tournament->fresh()))->not->toBeNull();
});

it('rejects playoff generation when playoffs are disabled', function () {
    $tournament = playoffTournament(0);
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);

    expect(fn () => $service->generatePlayoffs($tournament->fresh()))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects regenerating the group stage after a playoff result exists', function () {
    $tournament = playoffTournament();
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);
    $service->generatePlayoffs($tournament->fresh());

    $playoff = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'playoffs')
        ->firstOrFail();
    $match = MatchModel::where('bracket_id', $playoff->id)
        ->where('status', 'scheduled')
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->firstOrFail();
    $service->recordScore($match, 80, 70);

    expect(fn () => $service->generate($tournament->fresh(), 'round-robin'))
        ->toThrow(InvalidArgumentException::class, 'Playoffs already have results');
});
