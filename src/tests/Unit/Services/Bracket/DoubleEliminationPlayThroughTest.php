<?php

/** Tests for Double elimination play through test. */

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

/**
 * Play every playable game, home team wins unless $grandFinalAwayWins is true.
 */
function playOutBracket(BracketService $service, Bracket $bracket, bool $grandFinalAwayWins = false): void
{
    $guard = 0;

    while ($guard++ < 500) {
        $anchor = MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('id')
            ->first();

        if ($anchor instanceof MatchModel) {
            $awayWins = $grandFinalAwayWins && $anchor->stage === 'grand_final';
            $service->recordScore($anchor, $awayWins ? 70 : 80, $awayWins ? 80 : 70);

            continue;
        }

        $game = MatchModel::where('bracket_id', $bracket->id)
            ->whereNotNull('series_id')
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('id')
            ->first();

        if (! $game instanceof MatchModel) {
            break;
        }

        $service->recordScore($game, 80, 70);
    }
}

/**
 * Make tournament with teams.
 *
 * @param  int  $teamCount  The team count.
 * @param  array<mixed>  $rules  The rules.
 * @return Tournament The result.
 */
function makeTournamentWithTeams(int $teamCount, array $rules = []): Tournament
{
    $base = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $tournament = $base->replicate();
    $tournament->name = 'Play-through '.$teamCount.' '.uniqid();
    $tournament->slug = 'play-through-'.$teamCount.'-'.uniqid();
    $tournament->rules_json = array_merge($base->rules_json ?? [], [
        'slots' => $teamCount,
    ], $rules, [
        'format' => 'double-elimination',
    ]);
    $tournament->save();

    $division = $tournament->divisions()->create([
        'name' => 'Open',
        'max_teams' => $teamCount,
    ]);

    for ($i = 1; $i <= $teamCount; $i++) {
        $team = Team::create([
            'organization_id' => $tournament->organization_id,
            'name' => 'Team '.$i.' '.uniqid(),
            'slug' => 'team-'.$teamCount.'-'.$i.'-'.uniqid(),
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

it('plays a full double-elimination bracket to one champion', function (int $teamCount) {
    $tournament = makeTournamentWithTeams($teamCount);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    playOutBracket($service, $bracket);

    $champion = $service->champion($tournament);
    expect($champion)->not->toBeNull();

    $losses = [];
    $matches = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->whereIn('status', ['final', 'forfeit'])
        ->get();

    foreach ($matches as $match) {
        if ($match->home_team_id === null || $match->away_team_id === null || $match->isByeGame()) {
            continue;
        }

        $loserId = (int) $match->winner_team_id === (int) $match->home_team_id
            ? (int) $match->away_team_id
            : (int) $match->home_team_id;
        $losses[$loserId] = ($losses[$loserId] ?? 0) + 1;
    }

    $teamIds = Registration::where('tournament_id', $tournament->id)
        ->pluck('team_id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    foreach ($teamIds as $teamId) {
        if ($teamId === $champion->id) {
            expect($losses[$teamId] ?? 0)->toBeLessThanOrEqual(1);
        } else {
            expect($losses[$teamId] ?? 0)->toBe(2);
        }
    }
})->with([4, 8, 16, 32]);

it('plays through bye-heavy fields without leaving a matchup stuck', function (int $teamCount) {
    $tournament = makeTournamentWithTeams($teamCount, ['slots' => 16]);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    playOutBracket($service, $bracket);

    expect($service->champion($tournament))->not->toBeNull()
        ->and(MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->count())->toBe(0);
})->with([5, 6, 7, 9, 15]);

it('plays the reset when the losers-bracket champion wins the first grand final', function () {
    $tournament = makeTournamentWithTeams(8);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    playOutBracket($service, $bracket, grandFinalAwayWins: true);

    $reset = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'reset')
        ->firstOrFail();

    expect($reset->status)->toBe('final')
        ->and($service->champion($tournament))->not->toBeNull();
});
