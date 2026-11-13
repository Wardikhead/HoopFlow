<?php

use App\Livewire\Organizer\ScheduleShow;
use App\Models\AuditLog;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Database\Eloquent\Collection;
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
        ->assertDontSee('Manual manage')
        ->assertSee('schedule-calendar', false);
});

/**
 * @return array{Tournament, Collection<int, MatchModel>, Collection<int, MatchModel>}
 */
function scheduleRoundRobinPlayoffTournament(): array
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-31',
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 4,
        ]),
    ]);
    $tournament = $tournament->fresh();

    $bracketService = new BracketService;
    $bracketService->generate($tournament, 'round-robin');
    app(SchedulingService::class)->generate($tournament);

    $groupMatches = MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereHas('bracket', fn ($query) => $query->where('phase', 'group'))
        ->orderBy('id')
        ->get();

    foreach ($groupMatches as $match) {
        $bracketService->recordScore($match->fresh(), 80, 70);
    }

    $bracketService->generatePlayoffs($tournament->fresh());

    $playoffMatches = MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereHas('bracket', fn ($query) => $query->where('phase', 'playoffs'))
        ->orderBy('id')
        ->get();

    return [$tournament->fresh(), $groupMatches, $playoffMatches];
}

it('keeps full schedule generation and rebuild locked after group results', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    [$tournament] = scheduleRoundRobinPlayoffTournament();

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openPreview')
        ->assertSet('showPreview', false)
        ->assertDispatched('hf-toast')
        ->call('openRebuild')
        ->assertSet('showPreview', false)
        ->assertDispatched('hf-toast');
});

it('publishes remaining playoff games from the schedule page without moving group slots', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    [$tournament, $groupMatches, $playoffMatches] = scheduleRoundRobinPlayoffTournament();

    $groupSnapshot = MatchSlot::query()
        ->whereIn('match_id', $groupMatches->pluck('id'))
        ->orderBy('id')
        ->get()
        ->map(fn (MatchSlot $slot): array => [
            (int) $slot->id,
            (int) $slot->match_id,
            (int) $slot->court_id,
            $slot->start_at?->format('Y-m-d H:i:s'),
        ])
        ->all();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->assertSeeHtml('data-testid="schedule-remaining-preview"')
        ->assertDontSeeHtml('data-testid="schedule-rebuild-preview"')
        ->call('openRemaining')
        ->assertSet('showPreview', true)
        ->assertSet('previewMode', 'remaining')
        ->assertSee('Schedule remaining games');

    $plan = $component->get('previewPlan');

    expect($plan['assignments'])->toHaveCount($playoffMatches->count());

    $component
        ->call('publishSchedule')
        ->assertSet('showPreview', false)
        ->assertDispatched('hf-toast');

    $afterSnapshot = MatchSlot::query()
        ->whereIn('match_id', $groupMatches->pluck('id'))
        ->orderBy('id')
        ->get()
        ->map(fn (MatchSlot $slot): array => [
            (int) $slot->id,
            (int) $slot->match_id,
            (int) $slot->court_id,
            $slot->start_at?->format('Y-m-d H:i:s'),
        ])
        ->all();

    expect($afterSnapshot)->toBe($groupSnapshot)
        ->and(MatchSlot::query()->whereIn('match_id', $playoffMatches->pluck('id'))->count())
        ->toBe($playoffMatches->count())
        ->and(AuditLog::where('action', 'schedule.remaining')->count())->toBe(1);
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

it('shows a newly activated series game on the schedule', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $tournament = $tournament->fresh();

    $bracketService = new BracketService;
    $bracketService->generate($tournament, 'single-elimination');
    app(SchedulingService::class)->generate($tournament);

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
    $bracketService->syncSeriesTeams($final);
    $bracketService->recordScore($final->fresh(), 80, 70);

    $game2 = MatchModel::where('series_id', $final->id)
        ->where('game_number', 2)
        ->firstOrFail();
    $slot = $game2->matchSlots()->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('selectDay', $slot->start_at->format('Y-m-d'))
        ->assertSeeHtml('data-testid="schedule-match-'.$game2->id.'"');
});

function scheduleReadyTournament(): Tournament
{
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();

    MatchSlot::where('tournament_id', $tournament->id)->delete();
    MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->update([
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
            'series_winner_team_id' => null,
            'game_winner_team_id' => null,
        ]);

    return $tournament->fresh();
}

it('builds a preview from editable settings and publishes the exact edited row', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openPreview')
        ->assertSet('showPreview', true)
        ->assertSet('previewMode', 'generate')
        ->assertSet('previewStartDate', '2026-05-15')
        ->assertSee('Build preview');

    $plan = $component->get('previewPlan');
    $edited = $plan['assignments'][count($plan['assignments']) - 1];
    $court = app(SchedulingService::class)
        ->courts($tournament)
        ->first(fn (Court $option): bool => (int) $option->id !== (int) $edited['courtId'])
        ?? throw new RuntimeException('This test needs another court.');

    $component
        ->call('updatePreviewAssignment', $edited['matchId'], (int) $court->id, null)
        ->call('updatePreviewAssignment', $edited['matchId'], null, $edited['scheduledAt'])
        ->call('publishSchedule')
        ->assertSet('showPreview', false)
        ->assertDispatched('hf-toast');

    $slot = MatchSlot::where('match_id', $edited['matchId'])->firstOrFail();

    expect((int) $slot->court_id)->toBe((int) $court->id)
        ->and($slot->start_at?->format('Y-m-d H:i:s'))->toBe(Carbon::parse($edited['scheduledAt'])->format('Y-m-d H:i:s'))
        ->and(AuditLog::where('action', 'schedule.publish')->count())->toBe(1);
});

it('marks the preview stale when settings change and refuses to publish it', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openPreview')
        ->set('previewStartDate', '2026-05-16')
        ->assertSet('previewDirty', true)
        ->assertSee('Settings changed — rebuild the preview before publishing.')
        ->call('publishSchedule')
        ->assertSet('showPreview', true)
        ->assertDispatched('hf-toast');

    expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe(0);
});

it('shows an actionable error when the preview window is impossible', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openPreview')
        ->set('previewStartDate', '2026-05-23')
        ->call('buildPreview');

    $errors = $component->get('previewErrors');

    expect($errors)->not->toBeEmpty()
        ->and(implode(' ', $errors))->toContain('end date must be on or after the start date');

    $component->assertDispatched('hf-toast');
});

it('prefills rebuild from the published schedule and confirms replacement', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = scheduleReadyTournament();
    app(SchedulingService::class)->generate($tournament);

    $firstDate = substr((string) MatchSlot::where('tournament_id', $tournament->id)->min('start_at'), 0, 10);
    $oldCount = MatchSlot::where('tournament_id', $tournament->id)->count();

    $component = Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('openRebuild')
        ->assertSet('showPreview', true)
        ->assertSet('previewMode', 'rebuild')
        ->assertSet('previewStartDate', $firstDate)
        ->assertSee('Rebuild schedule');

    $component->call('publishSchedule')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'rebuild')
        ->assertSee('replace the current schedule');

    $component->call('confirm')
        ->assertSet('showPreview', false)
        ->assertDispatched('hf-toast');

    expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe($oldCount)
        ->and(AuditLog::where('action', 'schedule.rebuild')->count())->toBe(1);
});

it('removes cancelled series games from the calendar', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $tournament = $tournament->fresh();

    $bracketService = new BracketService;
    $bracketService->generate($tournament, 'single-elimination');
    app(SchedulingService::class)->generate($tournament);

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $teams = $tournament->registrations()->where('status', 'approved')->pluck('team_id')->take(2)->values();
    $final->home_team_id = $teams[0];
    $final->away_team_id = $teams[1];
    $final->save();
    $bracketService->syncSeriesTeams($final);

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $game3 = MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail();
    $game3Date = $game3->matchSlots()->firstOrFail()->start_at->format('Y-m-d');

    $bracketService->recordScore($final->fresh(), 80, 70);
    $bracketService->recordScore($game2->fresh(), 80, 70);

    Livewire::actingAs($organizer)
        ->test(ScheduleShow::class, ['id' => $tournament->id])
        ->call('selectDay', $game3Date)
        ->assertDontSeeHtml('data-testid="schedule-match-'.$game3->id.'"');

    expect(MatchSlot::where('match_id', $game3->id)->exists())->toBeFalse();
});
