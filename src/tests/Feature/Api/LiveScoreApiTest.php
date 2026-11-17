<?php

use App\Models\Bracket;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{Tournament, MatchModel}
 */
function liveTournamentMatch(array $attributes = []): array
{
    $tournament = Tournament::factory()->create([
        'name' => 'Summer Cup',
        'slug' => 'summer-cup',
        'status' => 'active',
    ]);

    $division = Division::factory()->for($tournament)->create();
    $bracket = Bracket::factory()->for($division)->create();

    $home = Team::factory()->create(['name' => 'Home Five', 'short_name' => 'HOM']);
    $away = Team::factory()->create(['name' => 'Away Five', 'short_name' => 'AWY']);

    $match = MatchModel::factory()->create(array_merge([
        'bracket_id' => $bracket->id,
        'tournament_id' => $tournament->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
    ], $attributes));

    return [$tournament, $match];
}

it('returns a public live snapshot for a tournament slug', function () {
    [$tournament, $match] = liveTournamentMatch([
        'home_score' => 21,
        'away_score' => 18,
        'status' => 'in_progress',
        'version' => 3,
    ]);

    $response = $this->getJson('/api/public/tournaments/summer-cup/live');

    $response->assertOk()
        ->assertJsonPath('data.tournament.id', $tournament->id)
        ->assertJsonPath('data.tournament.name', 'Summer Cup')
        ->assertJsonPath('data.tournament.slug', 'summer-cup')
        ->assertJsonPath('data.matches.0.id', $match->id)
        ->assertJsonPath('data.matches.0.status', 'in_progress')
        ->assertJsonPath('data.matches.0.home.name', 'Home Five')
        ->assertJsonPath('data.matches.0.away.name', 'Away Five')
        ->assertJsonPath('data.matches.0.home_score', 21)
        ->assertJsonPath('data.matches.0.away_score', 18)
        ->assertJsonPath('data.matches.0.version', 3);

    expect($response->json('data.server_time'))->toBeString();
});

it('returns 404 for an unknown tournament slug', function () {
    $this->getJson('/api/public/tournaments/missing-cup/live')->assertNotFound();
});

it('renders the public live scoreboard page with a polling endpoint', function () {
    [$tournament] = liveTournamentMatch([
        'home_score' => 7,
        'away_score' => 4,
        'status' => 'in_progress',
    ]);

    $this->get('/live/summer-cup')
        ->assertOk()
        ->assertSee('Summer Cup')
        ->assertSee('data-live-scores', false)
        ->assertSee(route('api.public.tournaments.live', $tournament->slug), false)
        ->assertSee('data-live-list', false);
});

it('updates a live score and bumps the version for an organizer', function () {
    [$tournament, $match] = liveTournamentMatch([
        'home_score' => 10,
        'away_score' => 8,
        'status' => 'scheduled',
        'version' => 1,
    ]);

    $organizer = User::factory()->organizer()->create();

    $response = $this->actingAs($organizer)->patchJson("/api/matches/{$match->id}/score", [
        'home_score' => 24,
        'away_score' => 20,
        'version' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.home_score', 24)
        ->assertJsonPath('data.away_score', 20)
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.version', 2);

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => 24,
        'away_score' => 20,
        'status' => 'in_progress',
        'version' => 2,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'score.live_update',
        'entity_type' => 'match',
        'entity_id' => $match->id,
        'actor_user_id' => $organizer->id,
    ]);

    expect($match->refresh()->score_updated_at)->not->toBeNull();
});

it('rejects stale score updates with a conflict response', function () {
    [, $match] = liveTournamentMatch([
        'home_score' => 12,
        'away_score' => 9,
        'status' => 'in_progress',
        'version' => 4,
    ]);

    $organizer = User::factory()->organizer()->create();

    $this->actingAs($organizer)
        ->patchJson("/api/matches/{$match->id}/score", [
            'home_score' => 30,
            'away_score' => 25,
            'version' => 3,
        ])
        ->assertStatus(409)
        ->assertJsonPath('data.version', 4)
        ->assertJsonPath('data.home_score', 12);

    $this->assertDatabaseHas('matches', [
        'id' => $match->id,
        'home_score' => 12,
        'away_score' => 9,
        'version' => 4,
    ]);
});

it('requires authentication to update a score', function () {
    [, $match] = liveTournamentMatch(['version' => 1]);

    $this->patchJson("/api/matches/{$match->id}/score", [
        'home_score' => 1,
        'away_score' => 0,
        'version' => 1,
    ])->assertUnauthorized();
});

it('forbids non-organizers from updating a score', function () {
    [, $match] = liveTournamentMatch(['version' => 1]);

    $coach = User::factory()->coach()->create();

    $this->actingAs($coach)
        ->patchJson("/api/matches/{$match->id}/score", [
            'home_score' => 1,
            'away_score' => 0,
            'version' => 1,
        ])
        ->assertForbidden();
});

it('refuses live score updates once a result is final', function () {
    [, $match] = liveTournamentMatch([
        'home_score' => 40,
        'away_score' => 35,
        'status' => 'final',
        'version' => 2,
    ]);

    $organizer = User::factory()->organizer()->create();

    $this->actingAs($organizer)
        ->patchJson("/api/matches/{$match->id}/score", [
            'home_score' => 41,
            'away_score' => 35,
            'version' => 2,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This match is no longer accepting live score updates.');
});

it('validates score payloads', function () {
    [, $match] = liveTournamentMatch(['version' => 1]);

    $organizer = User::factory()->organizer()->create();

    $this->actingAs($organizer)
        ->patchJson("/api/matches/{$match->id}/score", [
            'home_score' => -1,
            'away_score' => 'many',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['home_score', 'away_score', 'version']);
});
