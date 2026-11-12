<?php

use App\Models\AuditLog;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tlsUnitTournament(string $status = 'registration-open', array $rules = []): Tournament
{
    return Tournament::factory()->create([
        'status' => $status,
        'rules_json' => array_merge(['format' => 'round-robin'], $rules),
    ]);
}

function tlsUnitGroupBracket(Tournament $tournament, string $phase = 'group'): Bracket
{
    $division = Division::factory()->create(['tournament_id' => $tournament->id]);

    return Bracket::factory()->create([
        'division_id' => $division->id,
        'format' => 'round_robin',
        'phase' => $phase,
    ]);
}

function tlsUnitMatch(Tournament $tournament, Bracket $bracket, string $status): MatchModel
{
    return MatchModel::factory()->create([
        'bracket_id' => $bracket->id,
        'tournament_id' => $tournament->id,
        'stage' => 'round_robin',
        'status' => $status,
    ]);
}

it('activates a pre-start tournament', function (string $status) {
    $tournament = tlsUnitTournament($status);

    $changed = app(TournamentLifecycleService::class)->activate($tournament);

    $log = AuditLog::where('action', 'tournament.activate')->firstOrFail();

    expect($changed)->toBeTrue()
        ->and($tournament->fresh()->status)->toBe('active')
        ->and($log->entity_type)->toBe('tournament')
        ->and($log->entity_id)->toBe($tournament->id)
        ->and($log->before_json)->toBe(['status' => $status])
        ->and($log->after_json)->toBe(['status' => 'active']);
})->with(['draft', 'upcoming', 'registration-open']);

it('does not reactivate an active or completed tournament', function (string $status) {
    $tournament = tlsUnitTournament($status);

    $changed = app(TournamentLifecycleService::class)->activate($tournament);

    expect($changed)->toBeFalse()
        ->and($tournament->fresh()->status)->toBe($status)
        ->and(AuditLog::where('action', 'tournament.activate')->count())->toBe(0);
})->with(['active', 'completed']);

it('completes a tournament when a champion is resolved', function () {
    $tournament = tlsUnitTournament('active', ['format' => 'single-elimination']);
    $champion = Team::factory()->create();

    $changed = app(TournamentLifecycleService::class)->syncCompletion($tournament, $champion);

    $log = AuditLog::where('action', 'tournament.complete')->firstOrFail();

    expect($changed)->toBeTrue()
        ->and($tournament->fresh()->status)->toBe('completed')
        ->and($log->before_json)->toBe(['status' => 'active'])
        ->and($log->after_json)->toBe([
            'status' => 'completed',
            'champion_team_id' => $champion->id,
        ]);
});

it('does not complete a tournament without a champion', function () {
    $tournament = tlsUnitTournament('active', ['format' => 'single-elimination']);

    $changed = app(TournamentLifecycleService::class)->syncCompletion($tournament);

    expect($changed)->toBeFalse()
        ->and($tournament->fresh()->status)->toBe('active')
        ->and(AuditLog::where('action', 'tournament.complete')->count())->toBe(0);
});

it('does not duplicate the completion audit entry', function () {
    $tournament = tlsUnitTournament('active', ['format' => 'single-elimination']);
    $champion = Team::factory()->create();
    $service = app(TournamentLifecycleService::class);

    $service->syncCompletion($tournament, $champion);
    $service->syncCompletion($tournament->fresh(), $champion);

    expect(AuditLog::where('action', 'tournament.complete')->count())->toBe(1);
});

it('completes a round-robin tournament when every group game is resolved', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');
    tlsUnitMatch($tournament, $group, 'forfeit');

    $changed = app(TournamentLifecycleService::class)->syncCompletion($tournament);

    expect($changed)->toBeTrue()
        ->and($tournament->fresh()->status)->toBe('completed');
});

it('waits for playoffs when playoff teams are configured', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 4]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse()
        ->and($tournament->fresh()->status)->toBe('active');
});

it('does not complete a round-robin while a group game is unresolved', function (string $status) {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');
    tlsUnitMatch($tournament, $group, $status);

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
})->with(['scheduled', 'in_progress', 'cancelled']);

it('waits when a playoff bracket already exists', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');
    tlsUnitGroupBracket($tournament, 'playoffs');

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
});

it('does not complete a round-robin without a group bracket', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
});

it('does not complete a round-robin with an empty group bracket', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    tlsUnitGroupBracket($tournament);

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
});
