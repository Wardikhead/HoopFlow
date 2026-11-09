<?php

use App\Livewire\Organizer\ScheduleShow;
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

it('schedule page loads for organizer', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $this->actingAs($user)->get('/schedule')->assertStatus(200);
});

it('schedule page loads for coach', function () {
    $user = User::where('email', 'coach.marcus@hoopflow.ph')->first();
    $this->actingAs($user)->get('/schedule')->assertStatus(200);
});

it('schedule page loads for fan', function () {
    $user = User::where('email', 'fan.juan@hoopflow.ph')->first();
    $this->actingAs($user)->get('/schedule')->assertStatus(200);
});

it('guest redirected from schedule', function () {
    $this->get('/schedule')->assertRedirect('/login');
});

it('renders the prototype schedule controls', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();

    $this->actingAs($user)
        ->get('/schedule')
        ->assertOk()
        ->assertSee('Court schedule')
        ->assertSee('Court filters')
        ->assertSee('Matchups')
        ->assertSee('Reschedule match')
        ->assertSee('Manual manage')
        ->assertSee('schedule-calendar', false);
});

it('toggles court filters and restores all courts', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $court = app(SchedulingService::class)->courts($tournament)->firstOrFail();

    Livewire::actingAs($user)
        ->test(ScheduleShow::class)
        ->assertSet('selectedCourtIds', fn (array $ids): bool => in_array((int) $court->id, $ids, true))
        ->call('toggleCourt', $court->id)
        ->assertSet('selectedCourtIds', fn (array $ids): bool => ! in_array((int) $court->id, $ids, true))
        ->call('allCourts')
        ->assertSet('selectedCourtIds', fn (array $ids): bool => in_array((int) $court->id, $ids, true));
});

it('labels reschedule options with team names', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();

    Livewire::actingAs($user)
        ->test(ScheduleShow::class)
        ->call('openReschedule')
        ->assertSee('Round of 16 · Pinagbuhatan Warriors vs Bagong Ilog Falcons');
});

it('reschedules a match from the schedule dialog', function () {
    $user = User::where('email', 'organizer@hoopflow.ph')->first();
    $slot = MatchSlot::query()
        ->whereHas('match', fn ($query) => $query->where('status', 'scheduled'))
        ->firstOrFail();
    $court = Court::query()->whereKeyNot($slot->court_id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(ScheduleShow::class)
        ->call('openReschedule')
        ->set('rescheduleMatchId', $slot->match_id)
        ->set('rescheduleCourtId', $court->id)
        ->set('rescheduleScheduledAt', '2026-05-19T10:00')
        ->call('saveReschedule')
        ->assertHasNoErrors();

    $slot->refresh();

    expect((int) $slot->court_id)->toBe((int) $court->id);
    expect($slot->start_at?->format('Y-m-d H:i'))->toBe('2026-05-19 10:00');
});

it('plans a schedule across the tournament courts', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
        ]);

    $plan = app(SchedulingService::class)->plan($tournament);

    expect($plan['assignments'])->not->toBeEmpty();
    expect($plan['courts'])->toHaveCount(4);
    expect($plan['startDate'])->toBe('2026-05-15');
});
