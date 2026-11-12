<?php

use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Registration;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function schedulingTwoTeamTournament(string $start = '2026-07-10', string $end = '2026-07-18'): Tournament
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    Registration::where('tournament_id', $tournament->id)
        ->where('status', 'approved')
        ->orderBy('seed')
        ->get()
        ->slice(2)
        ->each(fn (Registration $registration) => $registration->update(['status' => 'pending']));

    $tournament->update([
        'start_date' => $start,
        'end_date' => $end,
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'slots' => 2,
            'best_of_final' => 3,
        ]),
    ]);

    return $tournament->fresh();
}

function schedulingSeriesGames(Tournament $tournament)
{
    $anchor = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->firstOrFail();

    return MatchModel::where(function ($query) use ($anchor): void {
        $query->where('id', $anchor->id)->orWhere('series_id', $anchor->id);
    })->orderBy('game_number')->get();
}

function schedulingCourtForPlan(Tournament $tournament, int $excludeId): Court
{
    return app(SchedulingService::class)
        ->courts($tournament)
        ->first(fn (Court $court): bool => (int) $court->id !== $excludeId)
        ?? throw new RuntimeException('This test needs two courts.');
}

it('pre-schedules every series game one per eligible day on one court and tip-off', function () {
    $tournament = schedulingTwoTeamTournament();
    (new BracketService)->generate($tournament, 'single-elimination');

    $games = schedulingSeriesGames($tournament);
    $plan = (new SchedulingService)->plan($tournament);
    $rows = collect($plan['assignments'])->keyBy('matchId');

    expect($games)->toHaveCount(3)
        ->and($rows)->toHaveCount(3)
        ->and($games->every(fn (MatchModel $game): bool => $rows->has($game->id)))->toBeTrue();

    $starts = $games->map(fn (MatchModel $game): Carbon => Carbon::parse($rows[$game->id]['scheduledAt']));

    expect($starts->map(fn (Carbon $start): string => $start->format('H:i:s'))->unique())
        ->toHaveCount(1)
        ->and($starts->map(fn (Carbon $start): string => $start->format('Y-m-d'))->unique())
        ->toHaveCount(3)
        ->and(collect($plan['assignments'])->pluck('courtId')->unique())
        ->toHaveCount(1);
});

it('skips excluded weekdays and resumes the series on the next eligible day', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $games = schedulingSeriesGames($tournament);
    $plan = (new SchedulingService)->plan($tournament, ['weekdays' => [5, 6]]);
    $rows = collect($plan['assignments'])->keyBy('matchId');
    $dates = $games->map(fn (MatchModel $game): string => substr($rows[$game->id]['scheduledAt'], 0, 10));

    expect($dates->values()->all())->toBe(['2026-07-10', '2026-07-11', '2026-07-17']);
});

it('fails the plan with a projected end date and writes nothing', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-11');
    (new BracketService)->generate($tournament, 'single-elimination');

    $before = MatchSlot::where('tournament_id', $tournament->id)->count();

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(InvalidArgumentException::class, 'The tournament ends Jul 11, 2026, but this plan needs through Jul 12, 2026.');

    expect(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe($before);
});

it('starts the next round after the last game of the previous round', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-31',
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'slots' => 8,
            'best_of_semifinal' => 3,
            'best_of_final' => 3,
        ]),
    ]);
    $tournament = $tournament->fresh();

    (new BracketService)->generate($tournament, 'single-elimination');

    $plan = (new SchedulingService)->plan($tournament);
    $rows = collect($plan['assignments'])->keyBy('matchId');
    $semifinalAnchors = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 4)
        ->get();
    $finalAnchor = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('stage', 'winners')
        ->where('round_number', 2)
        ->firstOrFail();

    $semifinalGames = MatchModel::whereIn('series_id', $semifinalAnchors->pluck('id'))
        ->orWhereIn('id', $semifinalAnchors->pluck('id'))
        ->orderBy('game_number')
        ->get();
    $finalGames = MatchModel::where(function ($query) use ($finalAnchor): void {
        $query->where('id', $finalAnchor->id)->orWhere('series_id', $finalAnchor->id);
    })->orderBy('game_number')->get();

    $lastSemifinal = $semifinalGames
        ->map(fn (MatchModel $game): Carbon => Carbon::parse($rows[$game->id]['scheduledAt']))
        ->max();
    $firstFinal = Carbon::parse($rows[$finalGames->first()->id]['scheduledAt']);

    expect($firstFinal->greaterThan($lastSemifinal))->toBeTrue()
        ->and($semifinalAnchors->count())->toBe(2);

    $semifinalRows = collect($plan['assignments'])
        ->filter(fn (array $row): bool => $semifinalGames->contains('id', $row['matchId']))
        ->keyBy('matchId');

    $dayOne = $semifinalAnchors->map(
        fn (MatchModel $anchor): array => $semifinalRows[$anchor->id],
    );

    expect($dayOne->pluck('courtId')->unique())->toHaveCount(2)
        ->and($dayOne->map(fn (array $row): string => substr($row['scheduledAt'], 11))->unique())
        ->toHaveCount(1);
});

it('orders round-robin matchdays ascending', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new BracketService)->generate($tournament, 'round-robin');
    $tournament = $tournament->fresh();

    $plan = (new SchedulingService)->plan($tournament);
    $scheduled = MatchModel::whereIn('id', collect($plan['assignments'])->pluck('matchId'))->get()->keyBy('id');
    $rounds = collect($plan['assignments'])
        ->map(fn (array $assignment): int => (int) $scheduled[$assignment['matchId']]->round_number)
        ->all();

    expect($rounds)->toBe(collect($rounds)->sort()->values()->all());
});

it('publishes the exact preview plan, including an edited court', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $plan = $service->plan($tournament);
    $edited = $plan['assignments'][1];
    $otherCourt = schedulingCourtForPlan($tournament, (int) $edited['courtId']);
    $plan['assignments'][1]['courtId'] = (int) $otherCourt->id;

    $count = $service->publish($tournament, $plan);

    $slot = MatchSlot::where('match_id', $edited['matchId'])->firstOrFail();

    expect($count)->toBe(3)
        ->and(MatchSlot::where('tournament_id', $tournament->id)->count())->toBe(3)
        ->and((int) $slot->court_id)->toBe((int) $otherCourt->id)
        ->and($slot->start_at?->format('Y-m-d H:i:s'))->toBe($edited['scheduledAt']);
});

it('rejects a plan row outside the tournament window', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $plan = $service->plan($tournament);
    $matchId = (int) $plan['assignments'][0]['matchId'];
    $plan['assignments'][0]['scheduledAt'] = '2026-07-25 10:00:00';

    $result = $service->validatePlan($tournament, $plan);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->toHaveKey($matchId);
});

it('warns but does not block a manual weekday override', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-24');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $plan = $service->plan($tournament, ['weekdays' => [5, 6]]);
    $matchId = (int) $plan['assignments'][0]['matchId'];
    $plan['assignments'][0]['scheduledAt'] = '2026-07-12 15:00:00';

    $result = $service->validatePlan($tournament, $plan);

    expect($result['warnings'])->toHaveKey($matchId)
        ->and($result['errors'])->not->toHaveKey($matchId);
});

it('rejects a per-game move that breaks series order or the end date', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-18');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $service->generate($tournament);
    $games = schedulingSeriesGames($tournament);
    $game1 = $games->firstWhere('game_number', 1);
    $game2 = $games->firstWhere('game_number', 2);
    $game3 = $games->firstWhere('game_number', 3);
    $game1Start = $game1->matchSlots()->firstOrFail()->start_at;

    expect(fn () => $service->reschedule($game2, null, $game1Start->format('Y-m-d H:i:s')))
        ->toThrow(InvalidArgumentException::class, 'Game 2 must start after Game 1');

    expect(fn () => $service->reschedule($game3, null, '2026-07-19 15:00:00'))
        ->toThrow(InvalidArgumentException::class, 'The tournament ends Jul 18, 2026. Choose an earlier date.');
});

it('refuses to move a game that is already final', function () {
    $tournament = schedulingTwoTeamTournament('2026-07-10', '2026-07-18');
    (new BracketService)->generate($tournament, 'single-elimination');

    $service = new SchedulingService;
    $service->generate($tournament);
    $game1 = schedulingSeriesGames($tournament)->firstWhere('game_number', 1);
    $game1->update(['status' => 'final']);

    expect(fn () => $service->reschedule($game1, null, '2026-07-11 15:00:00'))
        ->toThrow(InvalidArgumentException::class, 'Only scheduled or if-needed games can be moved.');
});
