<?php

use App\Livewire\Organizer\GameManage;
use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function gmUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

function gmMatch(int $round, int $number): MatchModel
{
    return MatchModel::where('round_number', $round)->where('match_number', $number)->firstOrFail();
}

function gmPlayerId(MatchModel $match, string $side): int
{
    $team = $side === 'team1' ? $match->homeTeam : $match->awayTeam;

    return (int) $team->players()->orderBy('jersey_number')->value('id');
}

it('redirects guests and blocks non-organizers', function () {
    $this->get('/game-manage')->assertRedirect('/login');

    $this->actingAs(gmUser('coach.marcus@hoopflow.ph'))->get('/game-manage')->assertStatus(403);
    $this->actingAs(gmUser('fan.juan@hoopflow.ph'))->get('/game-manage')->assertStatus(403);
});

it('renders the picker for an organizer', function () {
    $this->actingAs(gmUser('organizer@hoopflow.ph'))
        ->get('/game-manage')
        ->assertOk()
        ->assertSee('Select a match to manage')
        ->assertSeeHtml('data-testid="ingame-tournament-filter"')
        ->assertSeeHtml('data-testid="ingame-status-filter"')
        ->assertSeeHtml('data-testid="ingame-team-search"')
        ->assertSeeHtml('data-testid="ingame-match-list"')
        ->assertSeeHtml('data-testid="ingame-match-card-');
});

it('renders the prototype editor structure', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('matchId', $match->id)
        ->assertSee('In-game score & player stats')
        ->assertSeeHtml('data-testid="ingame-quarter-tabs"')
        ->assertSeeHtml('data-testid="ingame-quarter-tab-0"')
        ->assertSeeHtml('data-testid="ingame-team-panel-team1"')
        ->assertSeeHtml('data-testid="ingame-team-panel-team2"')
        ->assertSeeHtml('data-testid="ingame-add-overtime"')
        ->assertSeeHtml('data-testid="ingame-remove-overtime"')
        ->assertSeeHtml('data-testid="ingame-side-filters"')
        ->assertSeeHtml('data-testid="ingame-player-search-team1"')
        ->assertSeeHtml('data-testid="ingame-player-search-team2"')
        ->assertSeeHtml('data-testid="ingame-save"')
        ->assertSeeHtml('data-testid="ingame-publish"')
        ->assertSeeHtml('data-testid="ingame-cancel"');
});

it('saves a draft without finalizing the match', function () {
    $match = gmMatch(8, 3);
    $playerId = gmPlayerId($match, 'team1');

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set("stats.team1.{$playerId}.quarters.0.fgm", 2)
        ->set("stats.team1.{$playerId}.quarters.0.fga", 4)
        ->set('quarters.team1', [10, 10, 10, 10])
        ->set('quarters.team2', [8, 8, 8, 8])
        ->set('finals.team1', 40)
        ->set('finals.team2', 32)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $match->refresh();
    expect($match->status)->toBe('scheduled')
        ->and($match->quarters_json['team1'])->toBe([10, 10, 10, 10])
        ->and(PlayerStat::where('match_id', $match->id)->count())->toBeGreaterThan(0)
        ->and(PlayerStatQuarter::where('match_id', $match->id)->count())->toBeGreaterThan(0)
        ->and(AuditLog::where('action', 'stats.save')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('recomputes the active quarter and final from stat edits', function () {
    $match = gmMatch(8, 3);
    $playerId = gmPlayerId($match, 'team1');

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set("stats.team1.{$playerId}.quarters.0.fgm", 2)
        ->set("stats.team1.{$playerId}.quarters.0.tpm", 1)
        ->set("stats.team1.{$playerId}.quarters.0.ftm", 1)
        ->assertSet('quarters.team1.0', 6)
        ->assertSet('finals.team1', 6);
});

it('syncs the final when a quarter score changes', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1.0', 12)
        ->assertSet('finals.team1', 12);
});

it('adds and removes overtime across teams and players', function () {
    $match = gmMatch(8, 3);

    $component = Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->call('addOvertime')
        ->assertCount('quarters.team1', 5)
        ->assertCount('quarters.team2', 5);

    $firstPlayer = gmPlayerId($match, 'team1');
    $component->assertCount("stats.team1.{$firstPlayer}.quarters", 5)
        ->call('removeOvertime')
        ->assertCount('quarters.team1', 4);
});

it('caps overtime at nine quarters', function () {
    $match = gmMatch(8, 3);

    $component = Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id]);

    for ($i = 0; $i < 6; $i++) {
        $component->call('addOvertime');
    }

    $component->assertCount('quarters.team1', 9);
});

it('publishes a result and renders the locked state', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1', [10, 10, 10, 10])
        ->set('quarters.team2', [8, 8, 8, 8])
        ->set('finals.team1', 40)
        ->set('finals.team2', 32)
        ->call('requestPublish')
        ->assertSet('showConfirm', true)
        ->call('confirmPublish')
        ->assertSee('Locked result');

    $match->refresh();
    expect($match->status)->toBe('final')
        ->and((int) $match->winner_team_id)->toBe((int) $match->home_team_id)
        ->and(AuditLog::where('action', 'stats.publish')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('rejects publish on a tie and on a final mismatch', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1', [10, 10, 10, 10])
        ->set('quarters.team2', [10, 10, 10, 10])
        ->set('finals.team1', 40)
        ->set('finals.team2', 40)
        ->call('requestPublish')
        ->assertSet('showConfirm', false)
        ->assertSet('errorMessage', 'Elimination games cannot end in a tie.')
        ->set('quarters.team2', [8, 8, 8, 8])
        ->set('finals.team2', 31)
        ->call('requestPublish')
        ->assertSet('errorMessage', 'Final score must equal the sum of the quarters.');
});

it('locks final matches and refuses mutations', function () {
    $match = gmMatch(8, 1);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSee('Locked result')
        ->assertDontSeeHtml('data-testid="ingame-save"')
        ->call('saveDraft')
        ->assertSet('errorMessage', 'This result is already final. Use correction to change it.');

    expect($match->refresh()->status)->toBe('final');
});

it('links to in-game scoring from the nav, bracket detail, and game stats header', function () {
    $organizer = gmUser('organizer@hoopflow.ph');
    $match = gmMatch(8, 3);

    $this->actingAs($organizer)
        ->get('/game-manage')
        ->assertOk()
        ->assertSeeHtml('data-testid="nav-gameManage"');

    $this->get('/bracket?id='.$match->tournament_id.'&match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="bracket-in-game"');

    $this->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-in-game"');
});

it('keys stat rows by the active quarter so bindings cannot leak across quarters', function () {
    $match = gmMatch(8, 3);
    $playerId = gmPlayerId($match, 'team1');

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSeeHtml('wire:key="ingame-stat-row-team1-'.$playerId.'-0"')
        ->call('setActiveQuarter', 1)
        ->assertSeeHtml('wire:key="ingame-stat-row-team1-'.$playerId.'-1"');
});

function gmFinalWithQuarters(): MatchModel
{
    $match = gmMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    PlayerStatQuarter::query()->create([
        'match_id' => $match->id,
        'player_id' => $stat->player_id,
        'team_id' => $stat->team_id,
        'quarter_index' => 0,
        'field_goals_made' => 2,
        'field_goals_attempted' => 4,
        'three_points_made' => 1,
        'three_points_attempted' => 2,
        'free_throws_made' => 1,
        'free_throws_attempted' => 2,
        'points' => 6,
    ]);

    return $match;
}

it('opens correction mode for a final game with an editable box score', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('correctionMode', true)
        ->assertSeeHtml('data-testid="ingame-correction-banner"')
        ->assertSeeHtml('data-testid="ingame-correction-reason"')
        ->assertSeeHtml('data-testid="ingame-save-correction"')
        ->assertSeeHtml('data-testid="ingame-cancel-correction"')
        ->assertDontSeeHtml('data-testid="ingame-save"')
        ->assertDontSeeHtml('data-testid="ingame-publish"')
        ->assertDontSee('Locked result');
});

it('keeps a final game locked without the correction flag', function () {
    $match = gmFinalWithQuarters();

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('correctionMode', false)
        ->assertSee('Locked result')
        ->assertDontSeeHtml('data-testid="ingame-correction-banner"')
        ->assertSeeHtml('data-testid="ingame-correct"');
});

it('links a locked editable game to correction mode', function () {
    $match = gmFinalWithQuarters();

    $this->actingAs(gmUser('organizer@hoopflow.ph'))
        ->get('/game-manage?match='.$match->id)
        ->assertOk()
        ->assertSee(route('gameManage', ['match' => $match->id, 'correct' => 1]));
});

it('redirects correction mode to the score-only dialog when quarter rows are missing', function () {
    $match = gmMatch(8, 1);

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertRedirect(route('gameStats', ['match' => $match->id, 'correct' => 1]));
});

it('ignores the correction flag for a scheduled game', function () {
    $match = gmMatch(8, 3);

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('correctionMode', false)
        ->assertSeeHtml('data-testid="ingame-save"')
        ->assertDontSeeHtml('data-testid="ingame-correction-banner"');
});

it('forbids correction mode to non-organizers', function () {
    $match = gmFinalWithQuarters();

    $this->actingAs(gmUser('coach.marcus@hoopflow.ph'))
        ->get('/game-manage?match='.$match->id.'&correct=1')
        ->assertForbidden();
});

it('shows a mismatch warning in correction mode without blocking the save', function () {
    $match = gmFinalWithQuarters();

    $component = Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSeeHtml('data-testid="ingame-mismatch-team1"')
        ->assertSee('Player points 6 · Final 78 · 72 unassigned');

    $component
        ->set('quarters.team1', [20, 20, 20, 20])
        ->set('quarters.team2', [18, 18, 17, 17])
        ->set('finals.team1', 80)
        ->set('finals.team2', 70)
        ->set('correctionReason', 'Referee review changed the home total.')
        ->call('requestSaveCorrection')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'confirmSaveCorrection')
        ->call('confirmSaveCorrection')
        ->assertRedirect(route('gameStats', ['match' => $match->id]));

    $match->refresh();

    expect((int) $match->home_score)->toBe(80)
        ->and($match->quarters_json['team1'])->toBe([20, 20, 20, 20])
        ->and(AuditLog::where('action', 'score.correct')->where('entity_id', $match->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'stats.correct')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('requires a correction reason', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('correctionReason', 'oops')
        ->call('requestSaveCorrection')
        ->assertHasErrors('correctionReason')
        ->assertSet('showConfirm', false);
});

it('rejects a correction when the final does not equal the quarters', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('correctionReason', 'A valid correction reason.')
        ->set('quarters.team1', [20, 20, 20, 20])
        ->set('finals.team1', 79)
        ->call('requestSaveCorrection')
        ->assertSet('showConfirm', false)
        ->assertSet('errorMessage', 'Final score must equal the sum of the quarters.');
});

it('cancels a correction without writing', function () {
    $match = gmFinalWithQuarters();
    $before = (int) $match->home_score;

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1', [20, 20, 20, 20])
        ->call('cancelCorrection')
        ->assertRedirect(route('gameStats', ['match' => $match->id]));

    expect((int) $match->refresh()->home_score)->toBe($before)
        ->and(AuditLog::where('action', 'stats.correct')->where('entity_id', $match->id)->exists())->toBeFalse();
});

it('ignores draft and publish actions in correction mode', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->call('saveDraft')
        ->call('requestPublish')
        ->assertSet('showConfirm', false);

    expect(AuditLog::where('action', 'stats.save')->where('entity_id', $match->id)->exists())->toBeFalse();
});

function gmSeriesTournament(): Tournament
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);

    return $tournament->fresh();
}

function gmSeriesFinal(Tournament $tournament): MatchModel
{
    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'single_elim')
        ->firstOrFail();

    return MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
}

/**
 * @return array{0: MatchModel, 1: MatchModel}
 */
function gmPrepareSeries(Tournament $tournament, BracketService $service): array
{
    $final = gmSeriesFinal($tournament);
    $teams = $tournament->registrations()
        ->where('status', 'approved')
        ->pluck('team_id')
        ->take(2)
        ->values();

    $final->home_team_id = $teams[0];
    $final->away_team_id = $teams[1];
    $final->save();
    $service->syncSeriesTeams($final);

    $game2 = MatchModel::where('series_id', $final->id)
        ->where('game_number', 2)
        ->firstOrFail();

    return [$final->fresh(), $game2];
}

/**
 * @return array{0: MatchModel, 1: MatchModel}
 */
function gmStartSeries(Tournament $tournament, BracketService $service): array
{
    [$final, $game2] = gmPrepareSeries($tournament, $service);
    $service->recordScore($final->fresh(), 80, 70);

    return [$final->fresh(), $game2->fresh()];
}

it('opens the active series game when the anchor is requested', function () {
    $tournament = gmSeriesTournament();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    [$final, $game2] = gmStartSeries($tournament, $service);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $final->id])
        ->assertSet('matchId', $game2->id)
        ->assertSee('Game 2')
        ->assertDontSee('Game 1 is already recorded');
});

it('locks a published series game and offers the next game', function () {
    $tournament = gmSeriesTournament();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    [$final, $game2] = gmPrepareSeries($tournament, $service);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $final->id])
        ->set('quarters.team1', [20, 20, 20, 20])
        ->set('quarters.team2', [15, 15, 15, 15])
        ->set('finals.team1', 80)
        ->set('finals.team2', 60)
        ->call('requestPublish')
        ->assertSet('showConfirm', true)
        ->call('confirmPublish')
        ->assertSet('matchId', $final->id)
        ->assertSeeHtml('data-testid="ingame-locked"')
        ->assertDontSeeHtml('data-testid="ingame-publish"')
        ->assertSeeHtml('data-testid="ingame-next-game"')
        ->assertSee(route('gameManage', ['match' => $game2->id]));

    expect($final->fresh()->status)->toBe('in_progress')
        ->and($game2->fresh()->status)->toBe('scheduled');
});
