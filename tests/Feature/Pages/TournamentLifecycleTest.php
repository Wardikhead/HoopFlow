<?php

use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function tlsPlayableMatch(Tournament $tournament): ?MatchModel
{
    return MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->where('status', 'scheduled')
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->orderBy('id')
        ->first();
}

function tlsPlayOut(BracketService $service, Tournament $tournament): void
{
    $guard = 0;

    while ($guard++ < 500) {
        $match = tlsPlayableMatch($tournament);

        if (! $match instanceof MatchModel) {
            return;
        }

        $service->recordScore($match, 80, 70);
    }

    throw new RuntimeException('The bracket did not finish within 500 results.');
}

it('activates the tournament when a bracket is generated and closes coach registration', function () {
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="show-register"');

    (new BracketService)->generate($tournament, 'single-elimination');

    expect($tournament->fresh()->status)->toBe('active')
        ->and(AuditLog::where('action', 'tournament.activate')->count())->toBe(1);

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertDontSeeHtml('data-testid="show-register"');
});

it('completes a single-elimination tournament when the final is decided', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');

    tlsPlayOut($service, $tournament);

    expect($tournament->fresh()->status)->toBe('completed')
        ->and(AuditLog::where('action', 'tournament.complete')->count())->toBe(1);
});

it('completes a double-elimination tournament when the grand final is decided', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['format' => 'double-elimination']),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'double-elimination');

    tlsPlayOut($service, $tournament->fresh());

    expect($tournament->fresh()->status)->toBe('completed');
});

it('completes a best-of series final when the series is clinched', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'single-elimination');

    tlsPlayOut($service, $tournament->fresh());

    expect($tournament->fresh()->status)->toBe('completed');
});

it('completes a round-robin tournament without playoffs after the last group game', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 0,
        ]),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'round-robin');

    $matches = MatchModel::where('tournament_id', $tournament->id)->orderBy('id')->get();
    $last = $matches->pop();

    foreach ($matches as $match) {
        $service->recordScore($match->fresh(), 80, 70);
    }

    expect($tournament->fresh()->status)->toBe('active');

    $service->recordScore($last->fresh(), 80, 70);

    expect($tournament->fresh()->status)->toBe('completed');
});

it('waits for the playoff final in a round-robin tournament with playoffs', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 4,
        ]),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'round-robin');

    tlsPlayOut($service, $tournament);

    expect($tournament->fresh()->status)->toBe('active');

    $service->generatePlayoffs($tournament->fresh());

    tlsPlayOut($service, $tournament);

    expect($tournament->fresh()->status)->toBe('completed');
});
