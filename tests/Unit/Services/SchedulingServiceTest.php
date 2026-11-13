<?php

use App\Models\AuditLog;
use App\Models\Court;
use App\Models\MatchModel;
use App\Models\MatchSlot;
use App\Models\Registration;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Database\Eloquent\Collection;
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

/**
 * @return array{Tournament, Collection<int, MatchModel>, Collection<int, MatchModel>}
 */
function schedulingRoundRobinPlayoffTournament(): array
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

/**
 * @param  array<string, mixed>  $rules
 */
function schedulingConfiguredTournament(
    array $rules,
    string $start = '2026-07-10',
    string $end = '2026-07-31',
): Tournament {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'start_date' => $start,
        'end_date' => $end,
        'rules_json' => array_merge($tournament->rules_json ?? [], $rules),
    ]);

    return $tournament->fresh();
}

it('reserves a four-day footprint for four playoff teams and a best-of-three final', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint)->not->toBeNull()
        ->and($footprint['days'])->toBe(4)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Semifinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Final', 'bestOf' => 3, 'days' => 3],
        ]);
});

it('sums quarterfinal, semifinal, and final best-of games for eight playoff teams', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 8,
        'best_of_quarterfinal' => 3,
        'best_of_semifinal' => 5,
        'best_of_final' => 7,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint['days'])->toBe(15)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Quarterfinal', 'bestOf' => 3, 'days' => 3],
            ['label' => 'Semifinal', 'bestOf' => 5, 'days' => 5],
            ['label' => 'Final', 'bestOf' => 7, 'days' => 7],
        ]);
});

it('includes a single round-of-16 day for sixteen playoff teams', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 16,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint['days'])->toBe(4)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Round of 16', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Quarterfinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Semifinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Final', 'bestOf' => 1, 'days' => 1],
        ]);
});

it('falls back to one day for invalid best-of values', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 2,
        'best_of_final' => 4,
    ]);

    $footprint = (new SchedulingService)->playoffFootprint($tournament);

    expect($footprint['days'])->toBe(1)
        ->and($footprint['rounds'])->toBe([
            ['label' => 'Final', 'bestOf' => 1, 'days' => 1],
        ]);
});

it('does not reserve a footprint without a round-robin playoff field', function () {
    $single = schedulingConfiguredTournament([
        'format' => 'single-elimination',
        'playoff_teams' => 4,
    ]);
    $none = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 0,
    ]);
    $odd = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 6,
    ]);

    $service = new SchedulingService;

    expect($service->playoffFootprint($single))->toBeNull()
        ->and($service->playoffFootprint($none))->toBeNull()
        ->and($service->playoffFootprint($odd))->toBeNull();
});

it('reserves the last eligible days before the tournament ends', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);

    $window = (new SchedulingService)->playoffWindow($tournament);

    expect($window)->not->toBeNull()
        ->and($window['startDate'])->toBe('2026-07-28')
        ->and($window['endDate'])->toBe('2026-07-31')
        ->and($window['groupEndDate'])->toBe('2026-07-27')
        ->and($window['dates'])->toBe([
            '2026-07-28',
            '2026-07-29',
            '2026-07-30',
            '2026-07-31',
        ])
        ->and($window['dayCount'])->toBe(4)
        ->and($window['rounds'])->toBe([
            ['label' => 'Semifinal', 'bestOf' => 1, 'days' => 1],
            ['label' => 'Final', 'bestOf' => 3, 'days' => 3],
        ]);
});

it('reserves only selected weekdays when walking backwards', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 2,
        'best_of_final' => 3,
    ]);

    $window = (new SchedulingService)->playoffWindow($tournament, ['weekdays' => [5, 6]]);

    expect($window)->not->toBeNull()
        ->and($window['dates'])->toBe(['2026-07-24', '2026-07-25', '2026-07-31'])
        ->and($window['groupEndDate'])->toBe('2026-07-18');
});

it('moves the window when the end date is overridden', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);

    $window = (new SchedulingService)->playoffWindow($tournament, ['endDate' => '2026-07-17']);

    expect($window['dates'])->toBe([
        '2026-07-14',
        '2026-07-15',
        '2026-07-16',
        '2026-07-17',
    ])->and($window['groupEndDate'])->toBe('2026-07-13');
});

it('returns no window when the tournament is too short for the playoffs', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-12');

    expect((new SchedulingService)->playoffWindow($tournament))->toBeNull();
});

it('returns no window when the tournament has no end date', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
    ]);
    $tournament->update(['end_date' => null]);

    expect((new SchedulingService)->playoffWindow($tournament->fresh()))->toBeNull();
});

it('plans group rounds before the reserved playoff window', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ]);
    (new BracketService)->generate($tournament, 'round-robin');

    $plan = (new SchedulingService)->plan($tournament);
    $dates = collect($plan['assignments'])
        ->map(fn (array $assignment): string => substr($assignment['scheduledAt'], 0, 10));

    expect($plan['playoffWindow'])->not->toBeNull()
        ->and($plan['playoffWindow']['startDate'])->toBe('2026-07-28')
        ->and($dates->max() <= '2026-07-27')->toBeTrue()
        ->and($plan['endDate'])->toBe('2026-07-31');
});

it('keeps the remaining playoff plan inside the reserved window', function () {
    [$tournament] = schedulingRoundRobinPlayoffTournament();

    $service = new SchedulingService;
    $window = $service->playoffWindow($tournament);
    $plan = $service->planRemaining($tournament);
    $lastDate = collect($plan['assignments'])
        ->map(fn (array $assignment): string => substr($assignment['scheduledAt'], 0, 10))
        ->max();

    expect($window)->not->toBeNull()
        ->and($lastDate <= $window['endDate'])->toBeTrue();
});

it('refuses a group plan when the tournament cannot hold the playoff window', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-12');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'This schedule window has 3 eligible match days, but the playoffs need 4.',
        );

    expect(MatchSlot::query()->where('tournament_id', $tournament->id)->count())->toBe(0);
});

it('refuses a group plan when the playoff window consumes every match day', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-13');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'leaves no match days for the group stage',
        );
});

it('refuses a group plan that runs into the reserved playoff window', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
        'best_of_semifinal' => 1,
        'best_of_final' => 3,
    ], '2026-07-10', '2026-07-17');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'The group stage must finish by Jul 13, 2026 to keep Jul 14, 2026–Jul 17, 2026 free for the playoffs',
        );

    expect(MatchSlot::query()->where('tournament_id', $tournament->id)->count())->toBe(0);
});

it('keeps the existing end date error when no playoff window applies', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 0,
    ], '2026-07-10', '2026-07-12');
    (new BracketService)->generate($tournament, 'round-robin');

    expect(fn () => (new SchedulingService)->plan($tournament))
        ->toThrow(
            InvalidArgumentException::class,
            'The tournament ends Jul 12, 2026, but this plan needs through',
        );
});

it('keeps planning without an end date when no reservation is possible', function () {
    $tournament = schedulingConfiguredTournament([
        'format' => 'round-robin',
        'playoff_teams' => 4,
    ]);
    $tournament->update(['end_date' => null]);
    (new BracketService)->generate($tournament->fresh(), 'round-robin');

    $plan = (new SchedulingService)->plan($tournament->fresh());

    expect($plan['playoffWindow'])->toBeNull()
        ->and($plan['assignments'])->not->toBeEmpty();
});

it('keeps full schedule generation locked once group results exist', function () {
    [$tournament] = schedulingRoundRobinPlayoffTournament();

    expect(fn () => app(SchedulingService::class)->plan($tournament))
        ->toThrow(InvalidArgumentException::class, 'The schedule is locked because results exist.');
});

it('plans and publishes only the unscheduled playoff games after the last group match day', function () {
    [$tournament, $groupMatches, $playoffMatches] = schedulingRoundRobinPlayoffTournament();

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

    $lastGroupDate = substr((string) MatchSlot::query()
        ->whereIn('match_id', $groupMatches->pluck('id'))
        ->max('start_at'), 0, 10);

    $plan = app(SchedulingService::class)->planRemaining($tournament);

    expect($plan['assignments'])->toHaveCount($playoffMatches->count())
        ->and(collect($plan['assignments'])->pluck('matchId')->sort()->values()->all())
        ->toBe($playoffMatches->pluck('id')->sort()->values()->all())
        ->and($playoffMatches->every(fn (MatchModel $match): bool => $match->matchSlots()->doesntExist()))->toBeTrue();

    $firstRemainingDate = collect($plan['assignments'])
        ->map(fn (array $assignment): string => substr((string) $assignment['scheduledAt'], 0, 10))
        ->min();

    expect($firstRemainingDate > $lastGroupDate)->toBeTrue();

    $published = app(SchedulingService::class)->publishRemaining($tournament, $plan);

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

    expect($published)->toBe($playoffMatches->count())
        ->and($afterSnapshot)->toBe($groupSnapshot)
        ->and(MatchSlot::query()->whereIn('match_id', $playoffMatches->pluck('id'))->count())
        ->toBe($playoffMatches->count())
        ->and(MatchSlot::query()->where('tournament_id', $tournament->id)->count())
        ->toBe($groupMatches->count() + $playoffMatches->count())
        ->and(AuditLog::where('action', 'schedule.remaining')->count())->toBe(1);
});

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
