<?php

use App\Livewire\Organizer\GameManage;
use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Models\User;
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
