<?php

use App\Livewire\Organizer\GameStatsShow;
use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Models\Tournament;
use App\Models\User;
use App\Services\GameStatsService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function gsUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

function gsMatch(int $round, int $number): MatchModel
{
    return MatchModel::where('round_number', $round)->where('match_number', $number)->firstOrFail();
}

function gsPlayerName(PlayerStat $stat): string
{
    return trim(($stat->player?->first_name ?? '').' '.($stat->player?->last_name ?? ''));
}

/**
 * @param  array<string, int>  $values
 */
function gsQuarterStat(MatchModel $match, PlayerStat $stat, int $quarter, array $values = []): PlayerStatQuarter
{
    return PlayerStatQuarter::query()->create([
        'match_id' => $match->id,
        'player_id' => $stat->player_id,
        'team_id' => $stat->team_id,
        'quarter_index' => $quarter,
        'field_goals_made' => $values['fgm'] ?? 0,
        'field_goals_attempted' => $values['fga'] ?? 0,
        'three_points_made' => $values['tpm'] ?? 0,
        'three_points_attempted' => $values['tpa'] ?? 0,
        'free_throws_made' => $values['ftm'] ?? 0,
        'free_throws_attempted' => $values['fta'] ?? 0,
        'rebounds' => $values['reb'] ?? 0,
        'assists' => $values['ast'] ?? 0,
        'steals' => $values['stl'] ?? 0,
        'blocks' => $values['blk'] ?? 0,
        'turnovers' => $values['to'] ?? 0,
        'fouls' => $values['pf'] ?? 0,
        'points' => $values['points'] ?? (($values['fgm'] ?? 0) * 2 + ($values['tpm'] ?? 0) * 3 + ($values['ftm'] ?? 0)),
    ]);
}

it('matches page loads for organizer', function () {
    $this->actingAs(gsUser('organizer@hoopflow.ph'))->get('/matches')->assertStatus(200);
});

it('matches page loads for coach', function () {
    $this->actingAs(gsUser('coach.marcus@hoopflow.ph'))->get('/matches')->assertStatus(200);
});

it('matches page loads for fan', function () {
    $this->actingAs(gsUser('fan.juan@hoopflow.ph'))->get('/matches')->assertStatus(200);
});

it('guest redirected from matches', function () {
    $this->get('/matches')->assertRedirect('/login');
});

it('game stats page loads for organizer', function () {
    $this->actingAs(gsUser('organizer@hoopflow.ph'))->get('/game-stats')->assertStatus(200);
});

it('game stats page loads for coach', function () {
    $this->actingAs(gsUser('coach.marcus@hoopflow.ph'))->get('/game-stats')->assertStatus(200);
});

it('game stats page loads for fan', function () {
    $this->actingAs(gsUser('fan.juan@hoopflow.ph'))->get('/game-stats')->assertStatus(200);
});

it('guest redirected from game stats', function () {
    $this->get('/game-stats')->assertRedirect('/login');
});

it('game stats page loads for a match', function () {
    $match = MatchModel::firstOrFail();
    $this->actingAs(gsUser('organizer@hoopflow.ph'))->get('/game-stats?match='.$match->id)->assertStatus(200);
});

it('shows the prototype game not found state without a match', function () {
    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats')
        ->assertOk()
        ->assertSee('Game not found')
        ->assertSee('Open a game from the bracket, schedule, or matchup list.')
        ->assertSee('Browse matchups');
});

it('renders the prototype game stats header, scoreboard, correction banner, and quarter breakdown', function () {
    $match = gsMatch(8, 1);
    $tournament = Tournament::findOrFail($match->tournament_id);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSee('Game stats')
        ->assertSee($tournament->name)
        ->assertSee('Quarterfinals · Court 1 (Main Gym) · May 18, 2026 at 1:00 PM.')
        ->assertSeeHtml('data-testid="scoreboard-'.$match->id.'"')
        ->assertSeeHtml('data-testid="stats-controls"')
        ->assertSeeHtml('data-testid="stats-correction"')
        ->assertSee('Corrected score')
        ->assertSee('May 18, 2026 · 4:20 PM')
        ->assertSee('Shot-clock correction after referee review.')
        ->assertSee('74–70')
        ->assertSee('78–70')
        ->assertSeeHtml('data-testid="quarter-breakdown"')
        ->assertSeeHtml('data-testid="stats-quarter-breakdown"')
        ->assertSeeHtml('data-testid="stats-quarters-team1"')
        ->assertSeeHtml('data-testid="stats-quarters-team2"')
        ->assertSeeHtml('data-testid="stats-box-score"')
        ->assertSeeHtml('data-testid="stats-side-filters"')
        ->assertSeeHtml('data-testid="stats-side-all"')
        ->assertSeeHtml('data-testid="stats-side-team1"')
        ->assertSeeHtml('data-testid="stats-side-team2"')
        ->assertSee('Search player')
        ->assertSee('Team filter')
        ->assertSeeHtml('data-testid="stats-player-search"')
        ->assertSeeHtml('data-testid="stats-team-filter"')
        ->assertSeeHtml('data-testid="stats-bracket"')
        ->assertSeeHtml('data-testid="stats-schedule"')
        ->assertSeeHtml('data-testid="stats-matches"')
        ->assertSee(route('bracket', ['id' => $match->tournament_id, 'match' => $match->id]), false)
        ->assertSee(route('schedule', ['id' => $match->tournament_id]), false)
        ->assertSee(route('matches', ['id' => $match->tournament_id]), false);
});

it('filters and sorts the box score like the prototype', function () {
    $match = gsMatch(8, 1);
    $homeStat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->with('player')
        ->firstOrFail();
    $awayStat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->away_team_id)
        ->with('player')
        ->firstOrFail();
    $homeName = gsPlayerName($homeStat);
    $awayName = gsPlayerName($awayStat);

    expect($homeName)->not->toBe($awayName);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->assertSeeHtml('data-testid="stats-team-'.$match->home_team_id.'"')
        ->assertSeeHtml('data-testid="stats-team-'.$match->away_team_id.'"')
        ->assertSeeHtml('data-testid="stats-sort-team1-points"')
        ->assertSeeHtml('data-testid="stats-sort-team2-assists"')
        ->assertSeeHtml('data-testid="stats-sort-team1-rebounds"')
        ->set('side', 'team1')
        ->assertSeeHtml('data-testid="stats-team-'.$match->home_team_id.'"')
        ->assertDontSeeHtml('data-testid="stats-team-'.$match->away_team_id.'"')
        ->set('side', 'all')
        ->set('side', 'team2')
        ->assertSeeHtml('data-testid="stats-team-'.$match->away_team_id.'"')
        ->assertDontSeeHtml('data-testid="stats-team-'.$match->home_team_id.'"')
        ->set('side', 'all')
        ->set('search', $homeName)
        ->assertSee($homeName)
        ->assertDontSee($awayName)
        ->set('search', 'no-such-player')
        ->assertSee('No players match')
        ->assertSee('Clear the player search to see the full rotation.')
        ->set('search', '')
        ->call('sortBy', 'points')
        ->assertSet('sortKey', 'points')
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'assists')
        ->assertSet('sortKey', 'assists')
        ->assertSet('sortDirection', 'desc');
});

it('shows score actions to the organizer', function () {
    $organizer = gsUser('organizer@hoopflow.ph');
    $scheduled = MatchModel::where('status', 'scheduled')
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->firstOrFail();
    $final = MatchModel::where('status', 'final')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/game-stats?match='.$scheduled->id)
        ->assertStatus(200)
        ->assertSee('Enter final score')
        ->assertDontSee('Read-only');

    $this->actingAs($organizer)
        ->get('/game-stats?match='.$final->id)
        ->assertStatus(200)
        ->assertSee('Correct score');
});

it('shows a role-aware read-only game stats view to coaches and fans', function (string $email, string $roleLabel) {
    $user = gsUser($email);
    $scheduled = MatchModel::where('status', 'scheduled')
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->firstOrFail();
    $final = MatchModel::where('status', 'final')->firstOrFail();

    foreach ([$scheduled, $final] as $match) {
        $this->actingAs($user)
            ->get('/game-stats?match='.$match->id)
            ->assertStatus(200)
            ->assertDontSee('Enter final score')
            ->assertDontSee('Correct score')
            ->assertSee('Read-only '.$roleLabel.' view');
    }
})->with([
    'coach' => ['coach.marcus@hoopflow.ph', 'coach'],
    'fan' => ['fan.juan@hoopflow.ph', 'fan'],
]);

it('forbids coaches and fans from opening score actions', function (string $email) {
    $user = gsUser($email);
    $scheduled = MatchModel::where('status', 'scheduled')
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->firstOrFail();
    $final = MatchModel::where('status', 'final')->firstOrFail();

    Livewire::actingAs($user)
        ->test(GameStatsShow::class, ['match' => $scheduled->id])
        ->call('openScoreConsole')
        ->assertForbidden();

    Livewire::actingAs($user)
        ->test(GameStatsShow::class, ['match' => $final->id])
        ->call('openCorrection')
        ->assertForbidden();
})->with([
    'coach' => 'coach.marcus@hoopflow.ph',
    'fan' => 'fan.juan@hoopflow.ph',
]);

it('records a final score from the game stats score console', function () {
    $match = gsMatch(8, 3);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openScoreConsole')
        ->assertSet('showScoreConsole', true)
        ->assertSet('score1', 0)
        ->assertSet('score2', 0)
        ->assertSeeHtml('data-testid="score-input-1"')
        ->assertSeeHtml('data-testid="score-add-1-2"')
        ->assertSeeHtml('data-testid="score-forfeit"')
        ->assertSeeHtml('data-testid="score-undo"')
        ->assertSeeHtml('data-testid="score-cancel"')
        ->assertSeeHtml('data-testid="score-finalize"')
        ->call('addScore', 1, 3)
        ->assertSet('score1', 3)
        ->call('addScore', 2, 2)
        ->assertSet('score2', 2)
        ->call('undoScore')
        ->assertSet('score2', 0)
        ->call('requestFinalizeScore')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'finalize')
        ->assertSee('Finalize score?')
        ->call('confirm')
        ->assertSet('showConfirm', false)
        ->assertSet('showScoreConsole', false)
        ->assertDispatched('hf-toast');

    $match->refresh();
    expect($match->home_score)->toBe(3);
    expect($match->away_score)->toBe(0);
    expect($match->status)->toBe('final');
    expect($match->winner_team_id)->toBe($match->home_team_id);
});

it('records a forfeit from the game stats score console', function () {
    $match = gsMatch(8, 4);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openScoreConsole')
        ->set('forfeitSide', 1)
        ->call('requestFinalizeScore')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'forfeit')
        ->assertSee('Record forfeit?')
        ->call('confirm')
        ->assertSet('showScoreConsole', false)
        ->assertDispatched('hf-toast');

    $match->refresh();
    expect($match->home_score)->toBe(0);
    expect($match->away_score)->toBe(20);
    expect($match->status)->toBe('forfeit');
    expect($match->winner_team_id)->toBe($match->away_team_id);
    expect(AuditLog::where('action', 'score.forfeit')->where('entity_id', $match->id)->count())->toBe(1);
});

it('applies a correction from the game stats correction dialog', function () {
    $match = gsMatch(8, 1);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openCorrection')
        ->assertSet('showCorrection', true)
        ->assertSet('score1', 78)
        ->assertSet('score2', 70)
        ->assertSeeHtml('data-testid="correction-score-1"')
        ->assertSeeHtml('data-testid="correction-score-2"')
        ->assertSeeHtml('data-testid="correction-reason"')
        ->set('score1', 80)
        ->set('score2', 70)
        ->set('reason', 'Scorekeeper correction after review')
        ->call('requestCorrection')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'correction')
        ->assertSee('Apply correction?')
        ->call('confirm')
        ->assertSet('showCorrection', false)
        ->assertDispatched('hf-toast');

    $match->refresh();
    expect($match->home_score)->toBe(80);
    expect($match->away_score)->toBe(70);

    $log = AuditLog::where('action', 'score.correct')
        ->where('entity_id', $match->id)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->firstOrFail();

    expect($log->after_json['reason'] ?? null)->toBe('Scorekeeper correction after review');
});

it('rejects tied scores and short correction reasons', function () {
    $scheduled = gsMatch(8, 3);
    $final = gsMatch(8, 1);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $scheduled->id])
        ->call('openScoreConsole')
        ->set('score1', 70)
        ->set('score2', 70)
        ->call('requestFinalizeScore')
        ->assertHasErrors('score2')
        ->assertSet('showConfirm', false);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $final->id])
        ->call('openCorrection')
        ->set('score1', 80)
        ->set('score2', 70)
        ->set('reason', 'oops')
        ->call('requestCorrection')
        ->assertHasErrors('reason')
        ->assertSet('showConfirm', false);
});

it('shows the period nav for a draft box score and not the old quarter accordions', function () {
    $match = gsMatch(8, 3);
    $loaded = app(GameStatsService::class)->load($match);
    $playerId = (int) array_key_first($loaded['stats']['team1']);
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fgm'] = 2;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fga'] = 4;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['ftm'] = 1;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fta'] = 2;

    app(GameStatsService::class)->saveDraft($match, $loaded);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSee('Draft box score')
        ->assertSeeHtml('data-testid="stats-in-game"')
        ->assertSeeHtml('data-testid="stats-period-nav"')
        ->assertSeeHtml('data-testid="stats-period-0"')
        ->assertSeeHtml('data-testid="stats-period-3"')
        ->assertDontSeeHtml('data-testid="stats-player-quarters-team1-'.$playerId.'"');
});

it('shows only the all period when player quarter stats were not recorded', function () {
    $match = gsMatch(8, 1);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-period-all"')
        ->assertDontSeeHtml('data-testid="stats-period-0"')
        ->assertSee('Per-quarter player stats were not recorded for this game.');
});

it('opens the correction dialog from the correct query flag', function () {
    $match = gsMatch(8, 1);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id.'&correct=1')
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-correction-dialog"');
});

it('lists only periods that have per-player quarter rows', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    gsQuarterStat($match, $stat, 0);
    gsQuarterStat($match, $stat, 1);

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);

    expect(array_column($component->instance()->periods(), 'key'))->toBe(['all', '0', '1'])
        ->and(array_column($component->instance()->periods(), 'label'))->toBe(['All', 'Q1', 'Q2'])
        ->and($component->instance()->hasQuarterData())->toBeTrue();
});

it('falls back to all for an unknown or unavailable period', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    Livewire::withQueryParams(['period' => '0'])
        ->actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->assertSet('period', 'all');

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);
    $component->call('setPeriod', '99')->assertSet('period', 'all');

    gsQuarterStat($match, $stat, 0);

    Livewire::withQueryParams(['period' => '0'])
        ->actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->assertSet('period', '0');
});

it('builds the full stat line for the selected period and totals it', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->with('player')
        ->firstOrFail();

    $stat->forceFill([
        'field_goals_made' => 10,
        'field_goals_attempted' => 20,
        'three_points_made' => 2,
        'three_points_attempted' => 6,
        'free_throws_made' => 5,
        'free_throws_attempted' => 6,
        'rebounds' => 9,
        'assists' => 4,
        'steals' => 3,
        'blocks' => 1,
        'turnovers' => 2,
        'fouls' => 3,
        'points' => 29,
    ])->save();

    gsQuarterStat($match, $stat, 0, [
        'fgm' => 3, 'fga' => 7, 'tpm' => 1, 'tpa' => 3, 'ftm' => 2, 'fta' => 2,
        'reb' => 4, 'ast' => 2, 'stl' => 1, 'blk' => 0, 'to' => 1, 'pf' => 2, 'points' => 10,
    ]);
    gsQuarterStat($match, $stat, 1, [
        'fgm' => 2, 'fga' => 5, 'tpm' => 0, 'tpa' => 1, 'ftm' => 1, 'fta' => 2,
        'reb' => 3, 'ast' => 1, 'stl' => 1, 'blk' => 1, 'to' => 0, 'pf' => 1, 'points' => 5,
    ]);

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);
    $allRows = collect($component->instance()->rowsFor($match->homeTeam));
    $allRow = $allRows->firstWhere('playerId', $stat->player_id);

    expect($allRow)->not->toBeNull()
        ->and($allRow['fgm'])->toBe(10)
        ->and($allRow['fga'])->toBe(20)
        ->and($allRow['tpm'])->toBe(2)
        ->and($allRow['tpa'])->toBe(6)
        ->and($allRow['ftm'])->toBe(5)
        ->and($allRow['fta'])->toBe(6)
        ->and($allRow['reb'])->toBe(9)
        ->and($allRow['ast'])->toBe(4)
        ->and($allRow['stl'])->toBe(3)
        ->and($allRow['blk'])->toBe(1)
        ->and($allRow['to'])->toBe(2)
        ->and($allRow['pf'])->toBe(3)
        ->and($allRow['points'])->toBe(29);

    $component->instance()->setPeriod('0');
    $q1Rows = collect($component->instance()->rowsFor($match->homeTeam));
    $q1Row = $q1Rows->firstWhere('playerId', $stat->player_id);

    expect($q1Row)->not->toBeNull()
        ->and($q1Row['fgm'])->toBe(3)
        ->and($q1Row['fga'])->toBe(7)
        ->and($q1Row['points'])->toBe(10);

    $totals = $component->instance()->totalsFor($component->instance()->rowsFor($match->homeTeam));

    expect($totals['fgm'])->toBe(3)
        ->and($totals['fga'])->toBe(7)
        ->and($totals['points'])->toBe(10);
});

it('sorts the table by the selected period stats', function () {
    $match = gsMatch(8, 1);
    $stats = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->with('player')
        ->orderBy('id')
        ->take(2)
        ->get();

    expect($stats)->toHaveCount(2);

    $first = $stats[0];
    $second = $stats[1];

    gsQuarterStat($match, $first, 0, ['fgm' => 1, 'fga' => 1, 'points' => 2]);
    gsQuarterStat($match, $second, 0, ['fgm' => 5, 'fga' => 6, 'points' => 12]);

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);

    $component->instance()->setPeriod('0');
    $component->instance()->sortKey = 'points';
    $component->instance()->sortDirection = 'desc';

    $rows = $component->instance()->rowsFor($match->homeTeam);

    expect($rows[0]['playerId'])->toBe($second->player_id)
        ->and($rows[1]['playerId'])->toBe($first->player_id);
});

it('renders the period navigator and the full stat-line columns', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    gsQuarterStat($match, $stat, 0, ['fgm' => 3, 'fga' => 7, 'tpm' => 1, 'tpa' => 3, 'ftm' => 2, 'fta' => 2, 'reb' => 4, 'ast' => 2, 'stl' => 1, 'to' => 1, 'pf' => 2, 'points' => 10]);
    gsQuarterStat($match, $stat, 1, ['fgm' => 2, 'fga' => 5, 'tpm' => 0, 'tpa' => 1, 'ftm' => 1, 'fta' => 2, 'reb' => 3, 'ast' => 1, 'blk' => 1, 'pf' => 1, 'points' => 5]);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->assertSeeHtml('data-testid="stats-period-nav"')
        ->assertSeeHtml('data-testid="stats-period-all"')
        ->assertSeeHtml('data-testid="stats-period-0"')
        ->assertSeeHtml('data-testid="stats-period-1"')
        ->assertDontSeeHtml('data-testid="stats-period-2"')
        ->assertSeeHtml('data-testid="stats-period-label"')
        ->assertSee('FG')
        ->assertSee('3PT')
        ->assertSee('FT')
        ->assertSee('STL')
        ->assertSee('BLK')
        ->assertSee('TO')
        ->assertSee('PF')
        ->call('setPeriod', '0')
        ->assertSee('3/7')
        ->assertSee('1/3')
        ->assertSee('2/2');
});

function gsMakeEditableFinal(): MatchModel
{
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    gsQuarterStat($match, $stat, 0, ['fgm' => 2, 'fga' => 4, 'points' => 4]);

    return $match;
}

function gsDraftEditable(MatchModel $match): void
{
    $loaded = app(GameStatsService::class)->load($match);
    $playerId = (int) array_key_first($loaded['stats']['team1']);
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fgm'] = 2;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fga'] = 4;

    app(GameStatsService::class)->saveDraft($match, $loaded);
}

it('routes a final editable box score correction to the combined editor', function () {
    $match = gsMakeEditableFinal();

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openCorrection')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));
});

it('redirects a final editable correct deep link to the combined editor', function () {
    $match = gsMakeEditableFinal();

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id.'&correct=1')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));
});

it('keeps the score-only correction dialog for an aggregate-only final', function () {
    $match = gsMatch(8, 1);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openCorrection')
        ->assertNoRedirect()
        ->assertSet('showCorrection', true)
        ->assertSeeHtml('data-testid="stats-correction-dialog"');
});

it('routes final score entry to the editor when draft quarter stats exist', function () {
    $match = gsMatch(8, 3);
    gsDraftEditable($match);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openScoreConsole')
        ->assertRedirect(route('gameManage', ['match' => $match->id]));
});

it('blocks a score-only finalize when draft quarter stats exist', function () {
    $match = gsMatch(8, 3);
    gsDraftEditable($match);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('score1', 10)
        ->set('score2', 8)
        ->call('requestFinalizeScore')
        ->assertRedirect(route('gameManage', ['match' => $match->id]));

    expect($match->refresh()->status)->toBe('scheduled')
        ->and($match->home_score)->toBeNull();
});

it('blocks a direct score-only finalize confirmation when draft quarter stats exist', function () {
    $match = gsMatch(8, 3);
    gsDraftEditable($match);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('confirmAction', 'finalize')
        ->set('score1', 10)
        ->set('score2', 8)
        ->call('confirm')
        ->assertRedirect(route('gameManage', ['match' => $match->id]));

    expect($match->refresh()->status)->toBe('scheduled');
});

it('blocks a score-only correction when quarter stats exist', function () {
    $match = gsMakeEditableFinal();

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('score1', 80)
        ->set('score2', 70)
        ->set('reason', 'Referee review changed the score.')
        ->call('requestCorrection')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));

    expect((int) $match->refresh()->home_score)->toBe(78);
});

it('blocks a direct score-only correction confirmation when quarter stats exist', function () {
    $match = gsMakeEditableFinal();

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('confirmAction', 'correction')
        ->set('score1', 80)
        ->set('score2', 70)
        ->set('reason', 'Referee review changed the score.')
        ->call('confirm')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));

    expect((int) $match->refresh()->home_score)->toBe(78);
});
