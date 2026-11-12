<?php

use App\Livewire\Organizer\BracketShow;
use App\Livewire\Organizer\TournamentShow;
use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('bracket page loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/bracket')->assertStatus(200);
});

it('bracket page loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->first();
    $this->actingAs($user)->get('/bracket')->assertStatus(200);
});

it('bracket page loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/bracket')->assertStatus(200);
});

it('guest redirected from bracket', function () {
    $this->get('/bracket')->assertRedirect('/login');
});

it('opens the requested match from the match query parameter', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $match = MatchModel::where('round_number', 8)->where('match_number', 3)->firstOrFail();

    $this->actingAs($organizer)
        ->get('/bracket?id='.$match->tournament_id.'&match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="bracket-match-detail-dialog"')
        ->assertSee('Kapitolyo Knights')
        ->assertSee('Kalawaan Comets');
});

it('renders bracket KPIs, team shields, and the mobile round pager', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(BracketShow::class)
        ->assertSee('Round of 16')
        ->assertSee('Quarterfinals')
        ->assertSee('Championship Final')
        ->assertSee('VPR')
        ->assertSee('10')
        ->assertSee('5 scheduled')
        ->assertSeeHtml('data-testid="bracket-kpi-played"')
        ->assertSeeHtml('data-testid="bracket-tree"')
        ->assertSeeHtml('data-testid="bracket-mobile"')
        ->assertSeeHtml('data-testid="bracket-round-tab-0"')
        ->assertDontSeeHtml('data-testid="bracket-regenerate"')
        ->assertDontSeeHtml('data-testid="bracket-edit-slots"');
});

it('opens the match detail and score console for a scheduled game', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(BracketShow::class)
        ->call('openMatchDetail', 11)
        ->assertSet('showMatchDetail', true)
        ->assertSet('selectedMatchId', 11)
        ->assertSee('Kapitolyo Knights')
        ->call('openScoreConsole', 11)
        ->assertSet('showScoreConsole', true)
        ->assertSet('showMatchDetail', false)
        ->assertSee('Use the score buttons or a forfeit')
        ->call('addScore', 1, 2)
        ->assertSet('score1', 2)
        ->call('addScore', 2, 3)
        ->assertSet('score2', 3)
        ->call('undoScore')
        ->assertSet('score2', 0)
        ->call('undoScore')
        ->assertSet('score1', 0)
        ->call('undoScore')
        ->assertSet('score1', 0);
});

it('finalizes a score through the confirmation dialog', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(BracketShow::class)
        ->call('openScoreConsole', 11)
        ->set('score1', 77)
        ->set('score2', 70)
        ->call('requestFinalizeScore')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'finalize')
        ->assertSee('Finalize score?')
        ->call('confirm')
        ->assertSet('showConfirm', false)
        ->assertSet('showScoreConsole', false)
        ->assertDispatched('hf-toast');

    $match = MatchModel::findOrFail(11);
    expect($match->home_score)->toBe(77);
    expect($match->away_score)->toBe(70);
    expect($match->status)->toBe('final');
    expect($match->winner_team_id)->toBe($match->home_team_id);
});

it('records a forfeit through the confirmation dialog', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(BracketShow::class)
        ->call('openScoreConsole', 12)
        ->set('forfeitSide', 1)
        ->call('requestFinalizeScore')
        ->assertSet('confirmAction', 'forfeit')
        ->assertSee('Record forfeit?')
        ->call('confirm')
        ->assertSet('showScoreConsole', false)
        ->assertDispatched('hf-toast');

    $match = MatchModel::findOrFail(12);
    expect($match->home_score)->toBe(0);
    expect($match->away_score)->toBe(20);
    expect($match->status)->toBe('forfeit');
    expect($match->winner_team_id)->toBe($match->away_team_id);
    expect($match->forfeit_reason)->not->toBeNull();
    expect(AuditLog::where('action', 'score.forfeit')->where('entity_id', 12)->count())->toBe(1);
});

it('applies a correct score through the confirmation dialog', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $match = MatchModel::findOrFail(5);
    $previousWinner = $match->winner_team_id;

    Livewire::actingAs($organizer)
        ->test(BracketShow::class)
        ->call('openCorrection', 5)
        ->assertSet('showCorrection', true)
        ->set('score1', 61)
        ->set('score2', 66)
        ->set('reason', 'Referee review of the final possession')
        ->call('requestCorrection')
        ->assertSet('confirmAction', 'correction')
        ->assertSee('Apply correction?')
        ->call('confirm')
        ->assertSet('showCorrection', false)
        ->assertDispatched('hf-toast');

    $match->refresh();
    expect($match->home_score)->toBe(61);
    expect($match->away_score)->toBe(66);
    expect($match->winner_team_id)->toBe($match->away_team_id);
    expect($match->winner_team_id)->not->toBe($previousWinner);
    expect(AuditLog::where('action', 'score.correct')->where('entity_id', 5)->count())->toBe(1);
});

it('generates a bracket through the confirmation dialog', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => 2])
        ->call('requestGenerate')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'generate')
        ->assertSee('Generate bracket?')
        ->call('confirm')
        ->assertSet('showConfirm', false)
        ->assertDispatched('hf-toast');

    expect(MatchModel::where('tournament_id', 2)->count())->toBe(15);
});

it('forbids non-organizers from generating or scoring', function () {
    $fan = User::where('email', 'fan.juan@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($fan)
        ->test(BracketShow::class, ['id' => 2])
        ->call('requestGenerate')
        ->assertForbidden();

    Livewire::actingAs($fan)
        ->test(BracketShow::class)
        ->call('openScoreConsole', 11)
        ->assertForbidden();
});

it('renders winners, losers, and grand final sections for double elimination', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new BracketService)->generate($tournament, 'double-elimination');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertSee('Winners bracket')
        ->assertSee('Losers bracket')
        ->assertSee('Grand final')
        ->assertSee('Winners Quarterfinal')
        ->assertSee('Losers Round 1')
        ->assertDontSeeHtml('data-testid="bracket-champion"');
});

it('shows the best-of length and series score on a playoff card', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);

    (new BracketService)->generate($tournament, 'single-elimination');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertSee('Best of 3')
        ->assertSee('Game 1');
});

it('points a series bracket at the active game and shows its state', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $tournament = $tournament->fresh();

    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    (new SchedulingService)->generate($tournament);

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    $teams = $tournament->registrations()
        ->where('status', 'approved')
        ->pluck('team_id')
        ->take(2)
        ->values();

    $final->home_team_id = $teams[0];
    $final->away_team_id = $teams[1];
    $final->save();
    $service->syncSeriesTeams($final);
    $service->recordScore($final->fresh(), 80, 70);

    $game2 = MatchModel::where('series_id', $final->id)
        ->where('game_number', 2)
        ->firstOrFail();

    Livewire::actingAs($organizer)
        ->withQueryParams(['match' => $final->id])
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertSet('selectedMatchId', $final->id)
        ->assertSeeHtml('data-testid="bracket-series-active-'.$game2->id.'"')
        ->assertSeeHtml('href="'.route('gameManage', ['match' => $game2->id]).'"')
        ->assertSee('Next')
        ->assertSee('In progress');
});

it('targets the seed order when editing slots', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new BracketService)->generate($tournament, 'double-elimination');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->call('openEditSlots')
        ->assertSet('showEditSlots', true)
        ->assertCount('slotAssignments', 8)
        ->assertSet('slotAssignments.0.seed', 1)
        ->assertSee('Seed 1');
});

it('shows the generate-playoffs action only when the round-robin group is complete', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 4,
        ]),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'round-robin');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertDontSeeHtml('data-testid="bracket-generate-playoffs"');

    $group = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'group')
        ->firstOrFail();

    foreach (MatchModel::where('bracket_id', $group->id)->get() as $match) {
        if ($match->home_team_id !== null && $match->away_team_id !== null) {
            $service->recordScore($match->fresh(), 80, 70);
        }
    }

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertSeeHtml('data-testid="bracket-generate-playoffs"');
});

it('uses the bracket service champion resolution on the tournament page', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentShow::class, ['tournament' => $tournament->id])
        ->assertSet('champion', null);
});
