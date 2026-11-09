<?php

use App\Livewire\Public\StandingsShow;
use App\Models\Tournament;
use App\Models\User;
use App\Services\StandingsService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('renders standings with kpis, qualification line, and page links', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/standings?id='.$tournament->id)
        ->assertOk()
        ->assertSee('standings-kpis', false)
        ->assertSee('Top 8')
        ->assertSee('hf-qual-line', false)
        ->assertSee(route('bracket', ['id' => $tournament->id]), false)
        ->assertSee(route('matches', ['id' => $tournament->id]), false)
        ->assertSee('Tiebreakers:');
});

it('defaults to the active tournament when no id is supplied', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $active = Tournament::where('status', 'active')->orderBy('id')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(StandingsShow::class)
        ->assertSet('tournamentId', $active->id)
        ->assertSee($active->name);
});

it('filters the table to winless teams', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $rows = app(StandingsService::class)->compute($tournament);

    $leader = $rows->firstOrFail();
    $winless = $rows->firstWhere('wins', 0);

    expect($leader['wins'])->toBeGreaterThan(0)
        ->and($winless)->not->toBeNull();

    Livewire::actingAs($organizer)
        ->test(StandingsShow::class, ['id' => $tournament->id])
        ->set('filter', 'winless')
        ->assertSeeHtml('data-testid="standings-row-'.$winless['team_id'].'"')
        ->assertDontSeeHtml('data-testid="standings-row-'.$leader['team_id'].'"');
});

it('searches standings by team name', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $row = app(StandingsService::class)->compute($tournament)->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(StandingsShow::class, ['id' => $tournament->id])
        ->set('search', $row['team_name'])
        ->assertSeeHtml('data-testid="standings-row-'.$row['team_id'].'"');
});

it('shows an empty state for an unknown tournament', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/standings?id=999999')
        ->assertOk()
        ->assertSee('Tournament not found');
});
