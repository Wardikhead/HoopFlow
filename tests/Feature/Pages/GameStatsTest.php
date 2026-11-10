<?php

use App\Livewire\Organizer\GameStatsShow;
use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\Tournament;
use App\Models\User;
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

it('shows per-player quarter details, the draft label, and the in-game link', function () {
    $match = gsMatch(8, 3);
    $loaded = app(\App\Services\GameStatsService::class)->load($match);
    $playerId = (int) array_key_first($loaded['stats']['team1']);
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fgm'] = 2;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fga'] = 4;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['ftm'] = 1;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fta'] = 2;

    app(\App\Services\GameStatsService::class)->saveDraft($match, $loaded);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSee('Draft box score')
        ->assertSeeHtml('data-testid="stats-in-game"')
        ->assertSeeHtml('data-testid="stats-player-quarters-team1-'.$playerId.'"')
        ->assertSee('— by quarter');
});

it('opens the correction dialog from the correct query flag', function () {
    $match = gsMatch(8, 1);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id.'&correct=1')
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-correction-dialog"');
});
