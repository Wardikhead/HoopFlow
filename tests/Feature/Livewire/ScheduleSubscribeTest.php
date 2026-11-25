<?php

/** Tests for Schedule subscribe test. */

use App\Livewire\Organizer\ScheduleShow;
use App\Models\Bracket;
use App\Models\Division;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('shows the subscribe dialog and the tournament feed url', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertSeeHtml('data-testid="schedule-subscribe"')
        ->call('openSubscribe')
        ->assertSet('showSubscribe', true)
        ->assertSee(route('calendar.tournament', $tournament));
});

it('lists participating teams and updates the team feed url', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $stranger = Team::factory()->create(['name' => 'Elsewhere Titans']);
    $slot = MatchSlot::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('published_at')
        ->whereHas('match', fn ($query) => $query->whereNotNull('home_team_id'))
        ->with('match.homeTeam')
        ->firstOrFail();
    $team = $slot->match->homeTeam;

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openSubscribe')
        ->assertSee($team->name)
        ->assertDontSee($stranger->name)
        ->assertDontSeeHtml('data-testid="subscribe-team-url"')
        ->set('subscribeTeamId', $team->id)
        ->assertSee(route('calendar.team', ['tournament' => $tournament, 'team' => $team]))
        ->assertSeeHtml('data-testid="subscribe-team-url"');
});

it('hides subscribe when there are no games', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::factory()->create(['name' => 'Empty Cup']);

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertDontSeeHtml('data-testid="schedule-subscribe"');
});

it('hides subscribe when games are not published', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::factory()->create(['name' => 'Draft Cup']);
    $division = Division::factory()->for($tournament)->create();
    $bracket = Bracket::factory()->for($division)->create();
    $match = MatchModel::factory()->for($bracket)->for($tournament)->create([
        'status' => 'scheduled',
    ]);

    MatchSlot::factory()
        ->for($match, 'match')
        ->for($tournament)
        ->create(['published_at' => null]);

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertDontSeeHtml('data-testid="schedule-subscribe"');
});

it('lets coaches and fans open the subscribe dialog', function (string $email) {
    $user = User::where('email', $email)->firstOrFail();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    Livewire::actingAs($user)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openSubscribe')
        ->assertSet('showSubscribe', true)
        ->assertSee(route('calendar.tournament', $tournament));
})->with([
    'coach' => 'coach.marcus@hoopflow.ph',
    'fan' => 'fan.juan@hoopflow.ph',
]);
