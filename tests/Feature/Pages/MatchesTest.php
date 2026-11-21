<?php

use App\Livewire\Organizer\MatchesIndex;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Models\User;
use App\Services\SchedulingService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function summerCup(): Tournament
{
    return Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
}

function organizer(): User
{
    return User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
}

function matchFor(int $round, int $number): MatchModel
{
    return MatchModel::where('round_number', $round)->where('match_number', $number)->firstOrFail();
}

it('labels a rescheduled matchup card', function () {
    $slot = MatchSlot::query()
        ->whereHas('match', fn ($query) => $query->where('status', 'scheduled'))
        ->firstOrFail();
    $court = Court::query()->whereKeyNot($slot->court_id)->firstOrFail();

    app(SchedulingService::class)->reschedule($slot->match, $court->id, '2026-05-19 10:00');

    $this->actingAs(organizer())
        ->get('/matches?id='.$slot->tournament_id)
        ->assertOk()
        ->assertSeeHtml('data-testid="match-rescheduled-'.$slot->match_id.'"');
});

it('renders the prototype matchups header and filters', function () {
    $tournament = summerCup();

    $this->actingAs(organizer())
        ->get('/matches?id='.$tournament->id)
        ->assertOk()
        ->assertSee('Matchups')
        ->assertSee('Every bracket game grouped by matchday, with court, status, and a link to the official box score.')
        ->assertSee('Team or round')
        ->assertSee('All courts')
        ->assertSee('All statuses')
        ->assertSeeHtml('data-testid="match-search"')
        ->assertSeeHtml('data-testid="match-date-filter"')
        ->assertSeeHtml('data-testid="match-court-filter"')
        ->assertSeeHtml('data-testid="match-status-filter"')
        ->assertSeeHtml('data-testid="matches-schedule"')
        ->assertSeeHtml('data-testid="matches-bracket"')
        ->assertSee(route('schedule', ['id' => $tournament->id]), false)
        ->assertSee(route('bracket', ['id' => $tournament->id]), false);
});

it('defaults to the first tournament and groups matchups by matchday', function () {
    $match = matchFor(16, 1);

    $this->actingAs(organizer())
        ->get('/matches')
        ->assertOk()
        ->assertSee('HoopFlow Summer Cup')
        ->assertSee('Saturday, May 16, 2026')
        ->assertSee('Monday, May 18, 2026')
        ->assertSeeHtml('data-testid="match-day-2026-05-16"')
        ->assertSeeHtml('data-testid="match-day-2026-05-18"')
        ->assertSeeHtml('data-testid="match-card-'.$match->id.'"')
        ->assertSeeHtml('data-testid="match-list"');
});

it('shows the prototype empty state for an unknown tournament', function () {
    $this->actingAs(organizer())
        ->get('/matches?id=999999')
        ->assertOk()
        ->assertSee('Tournament not found')
        ->assertSee('The matchup link is invalid or the demo has been reset.')
        ->assertSee('Back to tournaments');
});

it('filters matchups by search, day, court, and status', function () {
    $tournament = summerCup();
    $qf1 = matchFor(8, 1);
    $qf2 = matchFor(8, 2);
    $r16 = matchFor(16, 1);
    $forfeit = matchFor(16, 7);
    $courtId = (int) MatchSlot::where('match_id', $qf1->id)->firstOrFail()->court_id;
    $otherCourtId = (int) MatchSlot::where('match_id', $qf2->id)->firstOrFail()->court_id;

    expect($courtId)->not->toBe($otherCourtId);

    $component = Livewire::actingAs(organizer())
        ->test(MatchesIndex::class, ['id' => $tournament->id])
        ->assertSeeHtml('data-testid="match-card-'.$qf1->id.'"')
        ->assertSeeHtml('data-testid="match-card-'.$qf2->id.'"');

    $component
        ->set('court', (string) $courtId)
        ->assertSeeHtml('data-testid="match-card-'.$qf1->id.'"')
        ->assertDontSeeHtml('data-testid="match-card-'.$qf2->id.'"')
        ->set('court', 'all')
        ->set('status', 'forfeit')
        ->assertSeeHtml('data-testid="match-card-'.$forfeit->id.'"')
        ->assertDontSeeHtml('data-testid="match-card-'.$qf1->id.'"')
        ->set('status', 'all')
        ->set('search', 'Vipers')
        ->assertSeeHtml('data-testid="match-card-'.$qf1->id.'"')
        ->assertDontSeeHtml('data-testid="match-card-'.$qf2->id.'"')
        ->set('search', 'quarterfinals')
        ->assertSeeHtml('data-testid="match-card-'.$qf1->id.'"')
        ->assertSeeHtml('data-testid="match-card-'.$qf2->id.'"')
        ->assertDontSeeHtml('data-testid="match-card-'.$r16->id.'"')
        ->set('search', '')
        ->set('day', '2026-05-18')
        ->assertSeeHtml('data-testid="match-card-'.$qf1->id.'"')
        ->assertDontSeeHtml('data-testid="match-card-'.$r16->id.'"');
});

it('clears every matchups filter from the empty state', function () {
    $tournament = summerCup();
    $match = matchFor(8, 1);

    Livewire::actingAs(organizer())
        ->test(MatchesIndex::class, ['id' => $tournament->id])
        ->set('search', 'no-such-team')
        ->set('day', '2030-01-01')
        ->set('court', '1')
        ->set('status', 'forfeit')
        ->assertSee('No matchups match')
        ->assertSee('Clear the day, court, or status filter and try again.')
        ->assertSeeHtml('data-testid="match-clear-filters"')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('day', '')
        ->assertSet('court', 'all')
        ->assertSet('status', 'all')
        ->assertSeeHtml('data-testid="match-card-'.$match->id.'"');
});
