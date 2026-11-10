# In-game Score & Player Stats Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Port the prototype "In-game score & player stats" workflow to Laravel with normalized per-quarter player stats, draft save, publish, and a locked final state.

**Architecture:** Add a `player_stat_quarters` table and extend `player_stats` with full box-score totals. A plain `GameStatsService` validates payloads, derives points/totals, writes drafts, and publishes through the existing `BracketService`. One organizer-only Livewire page (`/game-manage`) renders the prototype picker, quarter editor, player tables, overtime controls, and publish confirmation. The read-only game-stats page gains per-player quarter breakdowns and deep links.

**Tech Stack:** Laravel 13, PHP 8.4+, Livewire 4, Alpine 3, Bootstrap 5.3 SCSS, Pest, Larastan, Pint, SQLite tests, PostgreSQL production.

## Global Constraints

- Stack is fixed: no Tailwind, no Vue/React, no Bootstrap JavaScript.
- `player_stats` is hash-partitioned in PostgreSQL; `player_stat_quarters` intentionally has no foreign key to it.
- Points formula is `fgm * 2 + tpm + ftm` where `fgm` includes 3PT makes.
- Final/forfeit games are read-only on `/game-manage` and link to the existing correction workflow.
- Every mutation runs inside `DB::transaction` and is audited.
- `composer quality` must pass before claiming completion; report actual output.
- Commit only the files named in each task. The repository has unrelated dirty files (`logo/`, `.obsidian/`, `.kilo/`).

---

## File Structure

**Create**

- `src/database/migrations/2026_11_10_000001_add_box_score_columns_to_player_stats_table.php` — totals columns.
- `src/database/migrations/2026_11_10_000002_create_player_stat_quarters_table.php` — normalized quarter rows.
- `src/app/Models/PlayerStatQuarter.php` — quarter-row model.
- `src/app/Services/GameStatsService.php` — validation, load, draft save, publish, totals, audit.
- `src/app/Livewire/Organizer/GameManage.php` — picker + editor component.
- `src/resources/views/livewire/organizer/game-manage.blade.php` — prototype markup.
- `src/routes/features/game-management.php` — `gameManage` route.
- `src/tests/Feature/Services/GameStatsServiceTest.php` — service coverage.
- `src/tests/Feature/Pages/GameManageTest.php` — page coverage.

**Modify**

- `src/app/Models/PlayerStat.php` — fillable columns.
- `src/app/Models/MatchModel.php` — `playerQuarterStats()` relation.
- `src/routes/web.php` — load `routes/features/*.php`.
- `src/app/Livewire/Organizer/GameStatsShow.php` — dynamic quarters, per-player rows, correction deep link, draft label.
- `src/resources/views/livewire/organizer/game-stats-show.blade.php` — dynamic quarter headers, per-player quarter details, in-game link, draft label.
- `src/resources/views/livewire/organizer/bracket-show.blade.php` — in-game link in match dialog.
- `src/resources/views/layouts/public.blade.php` — organizer `gameManage` nav item.

---

## Task 1: Schema and models

**Files**

- Create: `src/database/migrations/2026_11_10_000001_add_box_score_columns_to_player_stats_table.php`
- Create: `src/database/migrations/2026_11_10_000002_create_player_stat_quarters_table.php`
- Create: `src/app/Models/PlayerStatQuarter.php`
- Modify: `src/app/Models/PlayerStat.php`
- Modify: `src/app/Models/MatchModel.php`

- [ ] **Step 1: Add the totals migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->unsignedInteger('field_goals_made')->default(0);
            $table->unsignedInteger('field_goals_attempted')->default(0);
            $table->unsignedInteger('three_points_made')->default(0);
            $table->unsignedInteger('three_points_attempted')->default(0);
            $table->unsignedInteger('free_throws_made')->default(0);
            $table->unsignedInteger('free_throws_attempted')->default(0);
            $table->unsignedInteger('turnovers')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropColumn([
                'field_goals_made',
                'field_goals_attempted',
                'three_points_made',
                'three_points_attempted',
                'free_throws_made',
                'free_throws_attempted',
                'turnovers',
            ]);
        });
    }
};
```

- [ ] **Step 2: Add the quarter table migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stat_quarters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter_index');
            $table->unsignedInteger('field_goals_made')->default(0);
            $table->unsignedInteger('field_goals_attempted')->default(0);
            $table->unsignedInteger('three_points_made')->default(0);
            $table->unsignedInteger('three_points_attempted')->default(0);
            $table->unsignedInteger('free_throws_made')->default(0);
            $table->unsignedInteger('free_throws_attempted')->default(0);
            $table->unsignedInteger('rebounds')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('steals')->default(0);
            $table->unsignedInteger('blocks')->default(0);
            $table->unsignedInteger('turnovers')->default(0);
            $table->unsignedInteger('fouls')->default(0);
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->unique(['match_id', 'player_id', 'quarter_index']);
            $table->index(['match_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stat_quarters');
    }
};
```

- [ ] **Step 3: Add the new columns to `PlayerStat` fillable**

Replace the `#[Fillable(...)]` attribute in `src/app/Models/PlayerStat.php` with:

```php
#[Fillable([
    'match_id',
    'player_id',
    'team_id',
    'tournament_id',
    'points',
    'assists',
    'rebounds',
    'steals',
    'blocks',
    'fouls',
    'field_goals_made',
    'field_goals_attempted',
    'three_points_made',
    'three_points_attempted',
    'free_throws_made',
    'free_throws_attempted',
    'turnovers',
])]
```

- [ ] **Step 4: Create the `PlayerStatQuarter` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'match_id',
    'player_id',
    'team_id',
    'quarter_index',
    'field_goals_made',
    'field_goals_attempted',
    'three_points_made',
    'three_points_attempted',
    'free_throws_made',
    'free_throws_attempted',
    'rebounds',
    'assists',
    'steals',
    'blocks',
    'turnovers',
    'fouls',
    'points',
])]
class PlayerStatQuarter extends Model
{
    /**
     * @return BelongsTo<MatchModel, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
```

- [ ] **Step 5: Add the match relation**

In `src/app/Models/MatchModel.php`, ensure `HasMany` is imported, then add before the closing brace:

```php
/**
 * @return HasMany<PlayerStatQuarter, $this>
 */
public function playerQuarterStats(): HasMany
{
    return $this->hasMany(PlayerStatQuarter::class, 'match_id')
        ->orderBy('player_id')
        ->orderBy('quarter_index');
}
```

- [ ] **Step 6: Run migrations against SQLite tests and the local database**

Run:

```powershell
cd src
php artisan migrate --force
php artisan test --filter=GameStatsTest
```

Expected: migrations apply; existing game-stats tests still pass. On Postgres, verify the partition migration still runs after the totals migration (columns are added to the partitioned parent, which is supported).

- [ ] **Step 7: Commit**

```powershell
git add src/database/migrations/2026_11_10_000001_add_box_score_columns_to_player_stats_table.php src/database/migrations/2026_11_10_000002_create_player_stat_quarters_table.php src/app/Models/PlayerStatQuarter.php src/app/Models/PlayerStat.php src/app/Models/MatchModel.php
git commit -m "feat(stats): add box score totals and quarter rows"
```

---

## Task 2: `GameStatsService`

**Files**

- Create: `src/app/Services/GameStatsService.php`
- Create: `src/tests/Feature/Services/GameStatsServiceTest.php`

**Design notes**

- `load()` reads `matches.quarters_json`, roster players, `player_stats`, and `player_stat_quarters`.
- `saveDraft()` and `publish()` normalize and validate the same payload shape. `publish()` additionally checks finals against quarter sums and delegates the official result to `BracketService::recordScore()`.
- The service maps short UI keys to long column names and derives `points`; `points` is never accepted from input.
- Roster order is jersey number then first name.
- `stats` includes every roster player. Missing players or missing quarters are zero-filled.
- Audit writes use `AuditLogger` and set `organization_id` from the tournament.

- [ ] **Step 1: Write the service tests first**

Create `src/tests/Feature/Services/GameStatsServiceTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Services\GameStatsService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function gssMatch(int $round, int $number): MatchModel
{
    return MatchModel::where('round_number', $round)->where('match_number', $number)->firstOrFail();
}

function gssPlayerId(MatchModel $match, string $side, int $index = 0): int
{
    $team = $side === 'team1' ? $match->homeTeam : $match->awayTeam;

    return (int) $team->players()->orderBy('jersey_number')->pluck('id')->get($index);
}

function gssPayload(MatchModel $match, array $overrides = []): array
{
    $p1 = gssPlayerId($match, 'team1');
    $p2 = gssPlayerId($match, 'team2');

    return array_replace([
        'quarters' => [
            'team1' => [10, 10, 10, 10],
            'team2' => [8, 8, 8, 8],
        ],
        'finals' => ['team1' => 40, 'team2' => 32],
        'stats' => [
            'team1' => [
                $p1 => ['quarters' => [
                    ['fgm' => 2, 'fga' => 4, 'tpm' => 1, 'tpa' => 2, 'ftm' => 1, 'fta' => 2],
                ]],
            ],
            'team2' => [
                $p2 => ['quarters' => [
                    ['fgm' => 1, 'fga' => 3, 'tpm' => 0, 'tpa' => 1, 'ftm' => 0, 'fta' => 0],
                ]],
            ],
        ],
    ], $overrides);
}

it('loads a scheduled match with a full roster, padded quarters, and zero-filled stats', function () {
    $match = gssMatch(8, 3);
    $loaded = app(GameStatsService::class)->load($match);

    expect($loaded['quarters']['team1'])->toHaveCount(4)
        ->and($loaded['quarters']['team1'])->toBe([0, 0, 0, 0])
        ->and($loaded['quarters']['team2'])->toBe([0, 0, 0, 0])
        ->and($loaded['stats']['team1'])->toHaveCount($match->homeTeam->players()->count())
        ->and($loaded['stats']['team2'])->toHaveCount($match->awayTeam->players()->count());

    $firstPlayer = array_key_first($loaded['stats']['team1']);
    expect($loaded['stats']['team1'][$firstPlayer]['quarters'])->toHaveCount(4)
        ->and($loaded['stats']['team1'][$firstPlayer]['quarters'][0]['fgm'])->toBe(0);
});

it('saves a draft, derives points and totals, and leaves the match scheduled', function () {
    $match = gssMatch(8, 3);
    $payload = gssPayload($match);

    app(GameStatsService::class)->saveDraft($match, $payload);

    $match->refresh();
    expect($match->status)->toBe('scheduled')
        ->and($match->quarters_json)->toBe(['team1' => [10, 10, 10, 10], 'team2' => [8, 8, 8, 8]]);

    $stat = PlayerStat::where('match_id', $match->id)
        ->where('player_id', gssPlayerId($match, 'team1'))
        ->firstOrFail();

    expect((int) $stat->points)->toBe(6)
        ->and((int) $stat->field_goals_made)->toBe(2)
        ->and((int) $stat->three_points_made)->toBe(1);

    expect(PlayerStatQuarter::where('match_id', $match->id)->count())
        ->toBe(($match->homeTeam->players()->count() + $match->awayTeam->players()->count()) * 4);

    expect(AuditLog::where('action', 'stats.save')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('sums quarter rows into player totals', function () {
    $match = gssMatch(8, 3);
    $playerId = gssPlayerId($match, 'team1');

    $payload = gssPayload($match, [
        'stats' => [
            'team1' => [
                $playerId => ['quarters' => [
                    ['fgm' => 1, 'fga' => 2, 'tpm' => 0, 'tpa' => 0, 'ftm' => 1, 'fta' => 1, 'reb' => 2, 'ast' => 1],
                    ['fgm' => 2, 'fga' => 3, 'tpm' => 1, 'tpa' => 1, 'ftm' => 0, 'fta' => 0, 'reb' => 3, 'ast' => 2, 'stl' => 1],
                ]],
            ],
        ],
    ]);

    app(GameStatsService::class)->saveDraft($match, $payload);

    $stat = PlayerStat::where('match_id', $match->id)->where('player_id', $playerId)->firstOrFail();

    expect((int) $stat->points)->toBe(8)
        ->and((int) $stat->field_goals_made)->toBe(3)
        ->and((int) $stat->field_goals_attempted)->toBe(5)
        ->and((int) $stat->rebounds)->toBe(5)
        ->and((int) $stat->assists)->toBe(3)
        ->and((int) $stat->steals)->toBe(1);
});

it('rejects unknown players', function () {
    $match = gssMatch(8, 3);
    $payload = gssPayload($match, ['stats' => ['team1' => [999999 => ['quarters' => []]]]]);

    app(GameStatsService::class)->saveDraft($match, $payload);
})->throws(InvalidArgumentException::class, 'is not on the roster');

it('rejects made shots greater than attempts', function () {
    $match = gssMatch(8, 3);
    $playerId = gssPlayerId($match, 'team1');
    $payload = gssPayload($match, [
        'stats' => ['team1' => [$playerId => ['quarters' => [['fgm' => 5, 'fga' => 2]]]]],
    ]);

    app(GameStatsService::class)->saveDraft($match, $payload);
})->throws(InvalidArgumentException::class, 'cannot exceed');

it('pads missing quarters and caps the number of quarters at nine', function () {
    $match = gssMatch(8, 3);
    $service = app(GameStatsService::class);

    $service->saveDraft($match, gssPayload($match, [
        'quarters' => ['team1' => [1, 2], 'team2' => [3]],
    ]));

    expect($match->refresh()->quarters_json['team1'])->toBe([1, 2, 0, 0]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot have more than 9 quarters');

    $service->saveDraft($match, gssPayload($match, [
        'quarters' => ['team1' => array_fill(0, 10, 1), 'team2' => array_fill(0, 10, 1)],
    ]));
});

it('publishes a result, advances the winner, and writes audits', function () {
    $match = gssMatch(8, 3);
    $away = $match->awayTeam;
    $next = MatchModel::where('round_number', 4)->where('match_number', 2)->firstOrFail();

    app(GameStatsService::class)->publish($match, gssPayload($match));

    $match->refresh();
    $next->refresh();

    expect($match->status)->toBe('final')
        ->and((int) $match->home_score)->toBe(40)
        ->and((int) $match->away_score)->toBe(32)
        ->and((int) $match->winner_team_id)->toBe((int) $match->home_team_id)
        ->and($next->home_team_id)->toBe((int) $match->home_team_id);

    expect(AuditLog::where('action', 'score.save')->where('entity_id', $match->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'stats.publish')->where('entity_id', $match->id)->exists())->toBeTrue();

    expect($away->id)->not->toBe($match->winner_team_id);
});

it('rejects publish when finals do not equal the quarter sums', function () {
    $match = gssMatch(8, 3);

    app(GameStatsService::class)->publish($match, gssPayload($match, [
        'finals' => ['team1' => 41, 'team2' => 32],
    ]));
})->throws(InvalidArgumentException::class, 'Final score must equal the sum of the quarters.');

it('rejects publish on a tie', function () {
    $match = gssMatch(8, 3);
    $payload = gssPayload($match, [
        'quarters' => ['team1' => [10, 10, 10, 10], 'team2' => [10, 10, 10, 10]],
        'finals' => ['team1' => 40, 'team2' => 40],
    ]);

    app(GameStatsService::class)->publish($match, $payload);
})->throws(InvalidArgumentException::class, 'Elimination games cannot end in a tie.');

it('rejects mutations on a final match', function () {
    $match = gssMatch(8, 1);

    app(GameStatsService::class)->saveDraft($match, gssPayload($match));
})->throws(InvalidArgumentException::class, 'This result is already final. Use correction to change it.');
```

- [ ] **Step 2: Run the new tests and confirm they fail**

Run:

```powershell
cd src
php artisan test --filter=GameStatsServiceTest
```

Expected: failure because `App\Services\GameStatsService` does not exist.

- [ ] **Step 3: Implement `GameStatsService`**

Create `src/app/Services/GameStatsService.php`:

```php
<?php

namespace App\Services;

use App\Models\MatchModel;
use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameStatsService
{
    public const MIN_QUARTERS = 4;

    public const MAX_QUARTERS = 9;

    public const MAX_VALUE = 199;

    /** @var array<string, string> */
    public const FIELDS = [
        'fgm' => 'field_goals_made',
        'fga' => 'field_goals_attempted',
        'tpm' => 'three_points_made',
        'tpa' => 'three_points_attempted',
        'ftm' => 'free_throws_made',
        'fta' => 'free_throws_attempted',
        'reb' => 'rebounds',
        'ast' => 'assists',
        'stl' => 'steals',
        'blk' => 'blocks',
        'to' => 'turnovers',
        'pf' => 'fouls',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BracketService $bracketService,
    ) {
    }

    /**
     * @return array{
     *   quarters: array{team1: list<int>, team2: list<int>},
     *   finals: array{team1: int, team2: int},
     *   stats: array{
     *     team1: array<int, array{quarters: list<array<string, int>>}>,
     *     team2: array<int, array{quarters: list<array<string, int>>}>
     *   }
     * }
     */
    public function load(MatchModel $match): array
    {
        $raw = is_array($match->quarters_json) ? $match->quarters_json : [];
        $team1 = $this->cleanQuarterList($raw['team1'] ?? []);
        $team2 = $this->cleanQuarterList($raw['team2'] ?? []);
        $count = max(self::MIN_QUARTERS, count($team1), count($team2));
        $count = min(self::MAX_QUARTERS, $count);

        $team1 = $this->pad($team1, $count);
        $team2 = $this->pad($team2, $count);

        $statsRows = $match->playerStats->keyBy('player_id');
        $quarterRows = $match->playerQuarterStats->groupBy('player_id');
        $stats = ['team1' => [], 'team2' => []];

        foreach (['team1' => $match->homeTeam, 'team2' => $match->awayTeam] as $side => $team) {
            if (! $team instanceof Team) {
                continue;
            }

            foreach ($this->roster($team) as $player) {
                $quarters = [];
                $rows = $quarterRows->get($player->id, collect())->keyBy('quarter_index');

                for ($index = 0; $index < $count; $index++) {
                    $row = $rows->get($index);
                    $quarters[] = $row instanceof PlayerStatQuarter
                        ? $this->shortFields($row->getAttributes())
                        : $this->emptyQuarter();
                }

                $stats[$side][(int) $player->id] = ['quarters' => $quarters];
            }
        }

        return [
            'quarters' => ['team1' => $team1, 'team2' => $team2],
            'finals' => [
                'team1' => $match->home_score !== null ? (int) $match->home_score : array_sum($team1),
                'team2' => $match->away_score !== null ? (int) $match->away_score : array_sum($team2),
            ],
            'stats' => $stats,
        ];
    }

    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, stats?: array<string, mixed>}  $payload
     */
    public function saveDraft(MatchModel $match, array $payload): void
    {
        $this->guardEditable($match);
        $normalized = $this->normalize($match, $payload);

        DB::transaction(function () use ($match, $normalized): void {
            $this->write($match, $normalized);
        });
    }

    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, finals?: array{team1?: mixed, team2?: mixed}, stats?: array<string, mixed>}  $payload
     */
    public function publish(MatchModel $match, array $payload): MatchModel
    {
        $this->guardEditable($match);
        $normalized = $this->normalize($match, $payload);

        $final1 = $this->intValue($payload['finals']['team1'] ?? null, 'Final score must be a whole number.');
        $final2 = $this->intValue($payload['finals']['team2'] ?? null, 'Final score must be a whole number.');

        if ($final1 !== array_sum($normalized['quarters']['team1']) || $final2 !== array_sum($normalized['quarters']['team2'])) {
            throw new \InvalidArgumentException('Final score must equal the sum of the quarters.');
        }

        if ($final1 === $final2) {
            throw new \InvalidArgumentException('Elimination games cannot end in a tie.');
        }

        $previous = is_array($match->quarters_json) ? $match->quarters_json : null;

        DB::transaction(function () use ($match, $normalized, $final1, $final2, $previous): void {
            $this->write($match, $normalized, false);
            $this->bracketService->recordScore($match->refresh(), $final1, $final2);
            $this->writeAudit($match, 'stats.publish', $previous, [
                'quarters' => $normalized['quarters'],
                'finals' => ['team1' => $final1, 'team2' => $final2],
            ]);
        });

        return $match->refresh();
    }

    public function pointsFor(array $fields): int
    {
        return ((int) ($fields['fgm'] ?? $fields['field_goals_made'] ?? 0)) * 2
            + (int) ($fields['tpm'] ?? $fields['three_points_made'] ?? 0)
            + (int) ($fields['ftm'] ?? $fields['free_throws_made'] ?? 0);
    }

    /**
     * @return Collection<int, Player>
     */
    public function roster(Team $team): Collection
    {
        return $team->players()
            ->orderBy('jersey_number')
            ->orderBy('first_name')
            ->get();
    }

    protected function guardEditable(MatchModel $match): void
    {
        if (in_array($match->status, ['final', 'forfeit'], true)) {
            throw new \InvalidArgumentException('This result is already final. Use correction to change it.');
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            throw new \InvalidArgumentException('Both teams must be known before entering stats.');
        }
    }

    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, stats?: array<string, mixed>}  $payload
     * @return array{
     *   quarters: array{team1: list<int>, team2: list<int>},
     *   stats: array{team1: array<int, array{quarters: list<array<string, int>>}>, team2: array<int, array{quarters: list<array<string, int>>}>}
     * }
     */
    protected function normalize(MatchModel $match, array $payload): array
    {
        $home = $match->homeTeam;
        $away = $match->awayTeam;

        if (! $home instanceof Team || ! $away instanceof Team) {
            throw new \InvalidArgumentException('Both teams must be known before entering stats.');
        }

        $team1 = $this->quarterInput($payload['quarters']['team1'] ?? []);
        $team2 = $this->quarterInput($payload['quarters']['team2'] ?? []);

        if (count($team1) > self::MAX_QUARTERS || count($team2) > self::MAX_QUARTERS) {
            throw new \InvalidArgumentException('A game cannot have more than 9 quarters.');
        }

        $count = max(self::MIN_QUARTERS, count($team1), count($team2));
        $team1 = $this->pad($team1, $count);
        $team2 = $this->pad($team2, $count);

        $rosters = [
            'team1' => $this->roster($home)->keyBy('id'),
            'team2' => $this->roster($away)->keyBy('id'),
        ];

        $inputStats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
        $stats = ['team1' => [], 'team2' => []];

        foreach (['team1', 'team2'] as $side) {
            $provided = is_array($inputStats[$side] ?? null) ? $inputStats[$side] : [];

            foreach (array_keys($provided) as $playerId) {
                if (! $rosters[$side]->has((int) $playerId)) {
                    throw new \InvalidArgumentException('Player '.$playerId.' is not on the roster for this game.');
                }
            }

            foreach ($rosters[$side] as $playerId => $player) {
                $entry = is_array($provided[$playerId] ?? null) ? $provided[$playerId] : [];
                $quarters = is_array($entry['quarters'] ?? null) ? array_values($entry['quarters']) : [];

                if (count($quarters) > $count) {
                    throw new \InvalidArgumentException('A player cannot have more quarters than the game.');
                }

                $normalizedQuarters = [];

                for ($index = 0; $index < $count; $index++) {
                    $normalizedQuarters[] = $this->normalizeQuarter(
                        is_array($quarters[$index] ?? null) ? $quarters[$index] : []
                    );
                }

                $stats[$side][(int) $playerId] = ['quarters' => $normalizedQuarters];
            }
        }

        return ['quarters' => ['team1' => $team1, 'team2' => $team2], 'stats' => $stats];
    }

    /**
     * @param  array{
     *   quarters: array{team1: list<int>, team2: list<int>},
     *   stats: array{team1: array<int, array{quarters: list<array<string, int>>}>, team2: array<int, array{quarters: list<array<string, int>>}>}
     * }  $normalized
     */
    protected function write(MatchModel $match, array $normalized, bool $audit = true): void
    {
        $previous = is_array($match->quarters_json) ? $match->quarters_json : null;
        $match->quarters_json = $normalized['quarters'];
        $match->save();

        $teamIds = [
            'team1' => (int) $match->home_team_id,
            'team2' => (int) $match->away_team_id,
        ];

        $rosterIds = [];

        foreach ($normalized['stats'] as $side => $players) {
            foreach ($players as $playerId => $entry) {
                $rosterIds[] = (int) $playerId;
                $totals = $this->sumQuarters($entry['quarters']);
                $values = $totals;
                $values['points'] = $this->pointsFor($totals);
                $values['team_id'] = $teamIds[$side];

                if (DB::getDriverName() === 'pgsql') {
                    $values['tournament_id'] = (int) $match->tournament_id;
                }

                PlayerStat::updateOrCreate(
                    ['match_id' => $match->id, 'player_id' => $playerId],
                    $values,
                );

                foreach ($entry['quarters'] as $index => $quarter) {
                    $row = [];
                    foreach (self::FIELDS as $short => $column) {
                        $row[$column] = $quarter[$short] ?? 0;
                    }
                    $row['points'] = $this->pointsFor($quarter);
                    $row['team_id'] = $teamIds[$side];

                    PlayerStatQuarter::updateOrCreate(
                        ['match_id' => $match->id, 'player_id' => $playerId, 'quarter_index' => $index],
                        $row,
                    );
                }
            }
        }

        PlayerStatQuarter::where('match_id', $match->id)
            ->whereNotIn('player_id', $rosterIds)
            ->delete();

        PlayerStatQuarter::where('match_id', $match->id)
            ->where('quarter_index', '>=', count($normalized['quarters']['team1']))
            ->delete();

        if ($audit) {
            $this->writeAudit($match, 'stats.save', $previous, [
                'quarters' => $normalized['quarters'],
                'players' => count($rosterIds),
            ]);
        }
    }

    /**
     * @param  list<array<string, int>>  $quarters
     * @return array<string, int>
     */
    protected function sumQuarters(array $quarters): array
    {
        $totals = array_fill_keys(array_values(self::FIELDS), 0);

        foreach ($quarters as $quarter) {
            foreach (self::FIELDS as $short => $column) {
                $totals[$column] += (int) ($quarter[$short] ?? 0);
            }
        }

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, int>
     */
    protected function normalizeQuarter(array $values): array
    {
        $out = [];

        foreach (self::FIELDS as $short => $column) {
            $value = $values[$short] ?? 0;
            $out[$short] = $this->intValue($value, 'Player stats must be whole numbers.');
        }

        foreach ([['fgm', 'fga'], ['tpm', 'tpa'], ['ftm', 'fta'], ['tpm', 'fgm'], ['tpa', 'fga']] as [$made, $attempted]) {
            if ($out[$made] > $out[$attempted]) {
                throw new \InvalidArgumentException('Made shots cannot exceed attempts.');
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<int>
     */
    protected function quarterInput(array $values): array
    {
        return array_map(
            fn (mixed $value): int => $this->intValue($value, 'Quarter scores must be whole numbers.'),
            array_values($values),
        );
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<int>
     */
    protected function cleanQuarterList(array $values): array
    {
        return array_map(
            fn (mixed $value): int => is_numeric($value) ? max(0, min(self::MAX_VALUE, (int) $value)) : 0,
            array_values($values),
        );
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    protected function pad(array $values, int $count): array
    {
        $values = array_values($values);

        while (count($values) < $count) {
            $values[] = 0;
        }

        return array_slice($values, 0, $count);
    }

    protected function intValue(mixed $value, string $message): int
    {
        if (! is_numeric($value) || (float) $value !== (float) (int) $value) {
            throw new \InvalidArgumentException($message);
        }

        $int = (int) $value;

        if ($int < 0 || $int > self::MAX_VALUE) {
            throw new \InvalidArgumentException($message);
        }

        return $int;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    protected function shortFields(array $attributes): array
    {
        $out = [];

        foreach (self::FIELDS as $short => $column) {
            $out[$short] = (int) ($attributes[$column] ?? 0);
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    protected function emptyQuarter(): array
    {
        return array_fill_keys(array_keys(self::FIELDS), 0);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    protected function writeAudit(MatchModel $match, string $action, ?array $before, array $after): void
    {
        $this->auditLogger->log(
            $action,
            'match',
            $match->id,
            $before,
            $after,
            $match->tournament?->organization_id !== null ? (int) $match->tournament->organization_id : null,
            auth()->id(),
        );
    }
}
```

- [ ] **Step 4: Run the service tests**

Run:

```powershell
cd src
php artisan test --filter=GameStatsServiceTest
```

Expected: all service tests pass. If SQLite rejects `unsignedTinyInteger` defaults, adjust only the migration (it supports it) rather than the service.

- [ ] **Step 5: Run Pint and Larastan on the new files**

```powershell
cd src
vendor/bin/pint app/Services/GameStatsService.php app/Models/PlayerStatQuarter.php
vendor/bin/phpstan analyse app/Services/GameStatsService.php app/Models/PlayerStatQuarter.php --level=6
```

Fix any type complaints before moving on.

- [ ] **Step 6: Commit**

```powershell
git add src/app/Services/GameStatsService.php src/tests/Feature/Services/GameStatsServiceTest.php
git commit -m "feat(stats): add game stats service"
```

---

## Task 3: Livewire page, route, and feature tests

**Files**

- Create: `src/app/Livewire/Organizer/GameManage.php`
- Create: `src/resources/views/livewire/organizer/game-manage.blade.php`
- Create: `src/routes/features/game-management.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Feature/Pages/GameManageTest.php`

- [ ] **Step 1: Register the feature route**

Create `src/routes/features/game-management.php`:

```php
<?php

use App\Livewire\Organizer\GameManage;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:organizer'])->group(function () {
    Route::get('/game-manage', GameManage::class)->name('gameManage');
});
```

At the bottom of `src/routes/web.php`, after the closing `});` of the existing auth group, add:

```php
foreach (glob(__DIR__.'/features/*.php') ?: [] as $featureRoutes) {
    require $featureRoutes;
}
```

- [ ] **Step 2: Create the `GameManage` component**

Create `src/app/Livewire/Organizer/GameManage.php`:

```php
<?php

namespace App\Livewire\Organizer;

use App\Models\MatchModel;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\GameStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('In-game score & player stats')]
class GameManage extends Component
{
    public ?int $matchId = null;

    public bool $requestedMatchMissing = false;

    public string $tournamentFilter = 'all';

    public string $statusFilter = 'all';

    public string $teamSearch = '';

    /** @var array{team1: list<int>, team2: list<int>} */
    public array $quarters = ['team1' => [0, 0, 0, 0], 'team2' => [0, 0, 0, 0]];

    /** @var array{team1: int, team2: int} */
    public array $finals = ['team1' => 0, 'team2' => 0];

    /** @var array{team1: array<int, array{quarters: list<array<string, int>>}>, team2: array<int, array{quarters: list<array<string, int>>}>} */
    public array $stats = ['team1' => [], 'team2' => []];

    public int $activeQuarter = 0;

    public string $side = 'all';

    public string $search1 = '';

    public string $search2 = '';

    public bool $showConfirm = false;

    public string $confirmTitle = 'Confirm action';

    public string $confirmMessage = '';

    public string $confirmLabel = 'Confirm';

    public string $confirmTone = 'primary';

    public string $confirmAction = '';

    public string $errorMessage = '';

    public function mount(int|string|null $match = null): void
    {
        abort_unless(auth()->user()?->hasRole('organizer') ?? false, 403);

        $candidate = $match ?? request()->query('match');

        if (! is_numeric($candidate)) {
            return;
        }

        $found = MatchModel::find((int) $candidate);

        if (! $found instanceof MatchModel) {
            $this->requestedMatchMissing = true;

            return;
        }

        $this->selectMatch($found);
    }

    #[Computed]
    public function match(): ?MatchModel
    {
        if ($this->matchId === null) {
            return null;
        }

        return MatchModel::query()
            ->with([
                'homeTeam.players',
                'awayTeam.players',
                'tournament',
                'matchSlots.court',
                'playerStats',
                'playerQuarterStats',
            ])
            ->find($this->matchId);
    }

    #[Computed]
    public function homeTeam(): ?Team
    {
        return $this->match?->homeTeam;
    }

    #[Computed]
    public function awayTeam(): ?Team
    {
        return $this->match?->awayTeam;
    }

    #[Computed]
    public function isLocked(): bool
    {
        return in_array($this->match?->status, ['final', 'forfeit'], true);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function quarterLabels(): array
    {
        return array_map(
            fn (int $index): string => $index < 4 ? 'Q'.($index + 1) : 'OT'.($index - 3),
            array_keys($this->quarters['team1']),
        );
    }

    /**
     * @return Collection<int, Tournament>
     */
    #[Computed]
    public function tournaments(): Collection
    {
        return Tournament::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, MatchModel>
     */
    #[Computed]
    public function matches(): Collection
    {
        $query = MatchModel::query()->with(['homeTeam', 'awayTeam', 'tournament', 'matchSlots.court']);

        if ($this->tournamentFilter !== 'all' && is_numeric($this->tournamentFilter)) {
            $query->where('tournament_id', (int) $this->tournamentFilter);
        }

        if (in_array($this->statusFilter, ['scheduled', 'final', 'forfeit'], true)) {
            $query->where('status', $this->statusFilter);
        }

        $term = trim($this->teamSearch);

        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder->whereHas('homeTeam', fn ($team) => $team->where('name', 'like', '%'.$term.'%'))
                    ->orWhereHas('awayTeam', fn ($team) => $team->where('name', 'like', '%'.$term.'%'));
            });
        }

        return $query->get()
            ->sortBy(fn (MatchModel $match): int => $match->matchSlots->first()?->start_at?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @return array<int, array{id: int, name: string, number: int|string|null, position: string|null, totals: array<string, int>}>
     */
    public function playersFor(string $side): array
    {
        $match = $this->match;
        $team = $side === 'team1' ? $match?->homeTeam : $match?->awayTeam;

        if (! $team instanceof Team) {
            return [];
        }

        $term = mb_strtolower(trim($side === 'team1' ? $this->search1 : $this->search2));

        return $this->sortedPlayers($team)
            ->map(function (Player $player) use ($side): array {
                $quarters = $this->stats[$side][(int) $player->id]['quarters'] ?? [];
                $name = trim(($player->first_name ?? '').' '.($player->last_name ?? ''));

                return [
                    'id' => (int) $player->id,
                    'name' => $name !== '' ? $name : 'Player',
                    'number' => $player->jersey_number,
                    'position' => $player->position,
                    'totals' => $this->sumStatQuarters(is_array($quarters) ? $quarters : []),
                ];
            })
            ->filter(fn (array $row): bool => $term === '' || str_contains(mb_strtolower($row['name']), $term))
            ->values()
            ->all();
    }

    public function statPoints(array $quarter): int
    {
        return app(GameStatsService::class)->pointsFor($quarter);
    }

    public function setActiveQuarter(int $index): void
    {
        $this->activeQuarter = max(0, min($index, count($this->quarters['team1']) - 1));
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'quarters.')) {
            $parts = explode('.', $name);

            if (count($parts) === 3 && in_array($parts[1], ['team1', 'team2'], true)) {
                $side = $parts[1];
                $index = (int) $parts[2];
                $this->quarters[$side][$index] = $this->clampInt($this->quarters[$side][$index] ?? 0);
                $this->syncFinal($side);
            }

            return;
        }

        if (preg_match('/^stats\.(team1|team2)\.(\d+)\.quarters\.(\d+)\.([a-z]+)$/', $name, $match) !== 1) {
            return;
        }

        $side = $match[1];
        $playerId = (int) $match[2];
        $quarter = (int) $match[3];
        $field = $match[4];

        if (! array_key_exists($field, GameStatsService::FIELDS)) {
            return;
        }

        $value = $this->stats[$side][$playerId]['quarters'][$quarter][$field] ?? 0;
        $this->stats[$side][$playerId]['quarters'][$quarter][$field] = $this->clampInt($value);

        if ($quarter === $this->activeQuarter) {
            $this->syncTeamQuarter($side);
        }
    }

    public function addOvertime(): void
    {
        if ($this->editableMatch() === null) {
            return;
        }

        if (count($this->quarters['team1']) >= GameStatsService::MAX_QUARTERS) {
            $this->toast('A game can have at most 9 quarters.', 'Overtime limit', 'warning');

            return;
        }

        foreach (['team1', 'team2'] as $side) {
            $this->quarters[$side][] = 0;
            $this->finals[$side] = array_sum($this->quarters[$side]);

            foreach (array_keys($this->stats[$side]) as $playerId) {
                $this->stats[$side][$playerId]['quarters'][] = $this->emptyQuarter();
            }
        }

        $this->activeQuarter = count($this->quarters['team1']) - 1;
    }

    public function removeOvertime(): void
    {
        if ($this->editableMatch() === null) {
            return;
        }

        $count = count($this->quarters['team1']);

        if ($count <= GameStatsService::MIN_QUARTERS) {
            $this->toast('Regulation has four quarters. There is no overtime to remove.', 'Nothing to remove', 'warning');

            return;
        }

        $last = $count - 1;
        $dirty = ($this->quarters['team1'][$last] ?? 0) !== 0 || ($this->quarters['team2'][$last] ?? 0) !== 0;

        foreach (['team1', 'team2'] as $side) {
            foreach ($this->stats[$side] as $entry) {
                foreach (($entry['quarters'][$last] ?? []) as $value) {
                    if ((int) $value !== 0) {
                        $dirty = true;
                    }
                }
            }
        }

        if ($dirty) {
            $this->openConfirm(
                'Remove overtime?',
                'The last overtime period has scores or player stats. Removing it deletes that data.',
                'Remove OT',
                'danger',
                'doRemoveOvertime',
            );

            return;
        }

        $this->doRemoveOvertime();
    }

    public function doRemoveOvertime(): void
    {
        $this->showConfirm = false;

        if (count($this->quarters['team1']) <= GameStatsService::MIN_QUARTERS) {
            return;
        }

        foreach (['team1', 'team2'] as $side) {
            array_pop($this->quarters[$side]);
            $this->finals[$side] = array_sum($this->quarters[$side]);

            foreach (array_keys($this->stats[$side]) as $playerId) {
                array_pop($this->stats[$side][$playerId]['quarters']);
            }
        }

        $this->activeQuarter = min($this->activeQuarter, count($this->quarters['team1']) - 1);
    }

    public function setSide(string $side): void
    {
        if (in_array($side, ['all', 'team1', 'team2'], true)) {
            $this->side = $side;
        }
    }

    public function clearFilters(): void
    {
        $this->tournamentFilter = 'all';
        $this->statusFilter = 'all';
        $this->teamSearch = '';
    }

    public function saveDraft(): void
    {
        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        try {
            app(GameStatsService::class)->saveDraft($match, $this->payload());
        } catch (\InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $this->errorMessage = '';
        $this->toast('Draft saved. The game stays scheduled until you publish.', 'Saved');
    }

    public function requestPublish(): void
    {
        if ($this->editableMatch() === null) {
            return;
        }

        $sum1 = array_sum($this->quarters['team1']);
        $sum2 = array_sum($this->quarters['team2']);

        if ((int) $this->finals['team1'] !== $sum1 || (int) $this->finals['team2'] !== $sum2) {
            $this->fail('Final score must equal the sum of the quarters.');

            return;
        }

        if ($sum1 === $sum2) {
            $this->fail('Elimination games cannot end in a tie.');

            return;
        }

        $this->errorMessage = '';
        $this->openConfirm(
            'Publish final result?',
            'Publish '.$sum1.'–'.$sum2.'? The winner advances, standings update, and the game locks. Corrections will require a reason.',
            'Publish result',
            'primary',
            'confirmPublish',
        );
    }

    public function confirmPublish(): void
    {
        $this->showConfirm = false;
        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        try {
            app(GameStatsService::class)->publish($match, $this->payload());
        } catch (\InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $this->loadEditor($match->refresh());
        $this->errorMessage = '';
        $this->toast('Result published. The winner advances to the next round.', 'Result published');
    }

    public function closeConfirm(): void
    {
        $this->showConfirm = false;
    }

    public function render(): View
    {
        return view('livewire.organizer.game-manage');
    }

    protected function selectMatch(MatchModel $match): void
    {
        $this->matchId = (int) $match->id;
        $this->requestedMatchMissing = false;
        $this->loadEditor($match);
    }

    protected function loadEditor(MatchModel $match): void
    {
        $loaded = app(GameStatsService::class)->load($match);

        $this->quarters = $loaded['quarters'];
        $this->finals = $loaded['finals'];
        $this->stats = $loaded['stats'];
        $this->activeQuarter = 0;
        $this->side = 'all';
        $this->search1 = '';
        $this->search2 = '';
        $this->errorMessage = '';
        $this->showConfirm = false;
    }

    /**
     * @return array{quarters: array{team1: list<int>, team2: list<int>}, finals: array{team1: int, team2: int}, stats: array<string, mixed>}
     */
    protected function payload(): array
    {
        return [
            'quarters' => $this->quarters,
            'finals' => $this->finals,
            'stats' => $this->stats,
        ];
    }

    protected function editableMatch(): ?MatchModel
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return null;
        }

        if (in_array($match->status, ['final', 'forfeit'], true)) {
            $this->fail('This result is already final. Use correction to change it.');

            return null;
        }

        Gate::authorize('enterStats', $match);

        if ($match->home_team_id === null || $match->away_team_id === null) {
            $this->fail('Both teams must be known before entering stats.');

            return null;
        }

        return $match;
    }

    protected function syncTeamQuarter(string $side): void
    {
        $sum = 0;

        foreach ($this->stats[$side] as $entry) {
            $quarter = $entry['quarters'][$this->activeQuarter] ?? [];

            if (is_array($quarter)) {
                $sum += app(GameStatsService::class)->pointsFor($quarter);
            }
        }

        $this->quarters[$side][$this->activeQuarter] = $sum;
        $this->syncFinal($side);
    }

    protected function syncFinal(string $side): void
    {
        $this->finals[$side] = array_sum($this->quarters[$side]);
    }

    /**
     * @return Collection<int, Player>
     */
    protected function sortedPlayers(Team $team): Collection
    {
        return $team->players
            ->sortBy(fn (Player $player): string => sprintf(
                '%05d-%s',
                (int) ($player->jersey_number ?? 999),
                mb_strtolower(($player->first_name ?? '').' '.($player->last_name ?? '')),
            ))
            ->values();
    }

    /**
     * @param  list<array<string, int>>  $quarters
     * @return array<string, int>
     */
    protected function sumStatQuarters(array $quarters): array
    {
        $totals = array_fill_keys(array_keys(GameStatsService::FIELDS), 0);

        foreach ($quarters as $quarter) {
            foreach (array_keys(GameStatsService::FIELDS) as $field) {
                $totals[$field] += (int) ($quarter[$field] ?? 0);
            }
        }

        $totals['pts'] = app(GameStatsService::class)->pointsFor($totals);

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    protected function emptyQuarter(): array
    {
        return array_fill_keys(array_keys(GameStatsService::FIELDS), 0);
    }

    protected function clampInt(mixed $value): int
    {
        return max(0, min(GameStatsService::MAX_VALUE, is_numeric($value) ? (int) $value : 0));
    }

    protected function fail(string $message): void
    {
        $this->errorMessage = $message;
        $this->toast($message, 'Unable to save', 'danger');
    }

    protected function openConfirm(string $title, string $message, string $label, string $tone, string $action): void
    {
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmLabel = $label;
        $this->confirmTone = $tone;
        $this->confirmAction = $action;
        $this->showConfirm = true;
    }

    protected function toast(string $message, string $title, string $tone = 'success'): void
    {
        $this->dispatch('hf-toast', title: $title, message: $message, tone: $tone);
    }
}
```

- [ ] **Step 3: Create the Blade view**

Create `src/resources/views/livewire/organizer/game-manage.blade.php`. Keep the prototype structure and test IDs exactly. The main blocks are:

```blade
<div>
    @if (! $this->match)
        @if ($requestedMatchMissing)
            <div class="hf-empty" data-testid="ingame-missing-match">
                <i class="bi bi-search" aria-hidden="true"></i>
                <h3>Match not found</h3>
                <p>That game is not in this organization, or the link is out of date.</p>
                <a class="hf-btn hf-btn--primary" href="{{ route('gameManage') }}" data-testid="manage-select-match">Browse matchups</a>
            </div>
        @else
            <header class="hf-page-head hf-mb-3">
                <div>
                    <span class="hf-eyebrow">In-game management</span>
                    <h1 class="hf-page-title">Select a match to manage</h1>
                    <p class="hf-page-desc">Filter matchups by tournament, status, and team, then choose one to enter quarter scores and player stats.</p>
                </div>
            </header>

            <section class="hf-card hf-mb-3" data-testid="ingame-picker-filters">
                <div class="hf-grid hf-grid--3">
                    <div class="hf-field">
                        <label class="hf-label" for="ingameTournamentFilter">Tournament</label>
                        <select class="hf-select" id="ingameTournamentFilter" wire:model.live="tournamentFilter" data-testid="ingame-tournament-filter">
                            <option value="all">All tournaments</option>
                            @foreach ($this->tournaments as $tournament)
                                <option value="{{ $tournament->id }}">{{ $tournament->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="ingameStatusFilter">Status</label>
                        <select class="hf-select" id="ingameStatusFilter" wire:model.live="statusFilter" data-testid="ingame-status-filter">
                            <option value="all">All statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="final">Final</option>
                        </select>
                    </div>
                    <div class="hf-field">
                        <label class="hf-label" for="ingameTeamSearch">Team search</label>
                        <input class="hf-input" id="ingameTeamSearch" type="search" placeholder="Search teams" wire:model.live.debounce.300ms="teamSearch" data-testid="ingame-team-search">
                    </div>
                </div>
            </section>

            <div class="hf-grid hf-grid--2" data-testid="ingame-match-list">
                @forelse ($this->matches as $match)
                    <x-match-card :match="$match" :href="route('gameManage', ['match' => $match->id])" :testid="'ingame-match-card-'.$match->id" />
                @empty
                    <div class="hf-empty" data-testid="ingame-no-matches">
                        <i class="bi bi-calendar-x" aria-hidden="true"></i>
                        <h3>No matches match</h3>
                        <p>Clear the filters or choose another tournament.</p>
                        <button type="button" class="hf-btn" wire:click="clearFilters" data-testid="ingame-clear-filters">Clear filters</button>
                    </div>
                @endforelse
            </div>
        @endif
    @else
        @php
            $match = $this->match;
            $home = $this->homeTeam;
            $away = $this->awayTeam;
            $locked = $this->isLocked;
            $labels = $this->quarterLabels;
            $teamsReady = $home !== null && $away !== null;
        @endphp

        <header class="hf-page-head hf-mb-3">
            <div>
                <span class="hf-eyebrow">{{ $home?->name ?? 'TBD' }} vs {{ $away?->name ?? 'TBD' }}</span>
                <h1 class="hf-page-title">In-game score &amp; player stats</h1>
                <p class="hf-page-desc">{{ $match->roundLabel() }} · Slot {{ $match->match_number }} · {{ $match->status }}</p>
            </div>
            <div class="hf-page-actions">
                <a class="hf-btn" href="{{ route('bracket', ['id' => $match->tournament_id, 'match' => $match->id]) }}" data-testid="ingame-bracket"><i class="bi bi-diagram-3"></i> Bracket</a>
                <a class="hf-btn" href="{{ route('matches', ['id' => $match->tournament_id]) }}" data-testid="ingame-matches"><i class="bi bi-list-ul"></i> Matchups</a>
                <a class="hf-btn" href="{{ route('gameManage') }}" data-testid="ingame-change-match"><i class="bi bi-arrow-left-right"></i> Change match</a>
            </div>
        </header>

        @if (! $teamsReady)
            <div class="hf-empty" data-testid="ingame-teams-pending">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <h3>Teams still pending</h3>
                <p>Both teams must be decided before quarter scores and player stats can be entered.</p>
                <a class="hf-btn" href="{{ route('gameManage') }}">Change match</a>
            </div>
        @elseif ($locked)
            <section class="hf-card" data-testid="ingame-locked">
                <div class="hf-between hf-wrap">
                    <div>
                        <span class="hf-eyebrow">Locked result</span>
                        <h2 class="hf-section-title hf-mb-0">{{ $match->home_score ?? '–' }}–{{ $match->away_score ?? '–' }}</h2>
                        <p class="hf-small hf-muted hf-mb-0">Recorded as {{ $match->status }}. In-game edits are closed.</p>
                    </div>
                    <span class="hf-chip hf-chip--slate">{{ $match->status }}</span>
                </div>
                <div class="hf-btn-row hf-mt-2">
                    <a class="hf-btn hf-btn--primary" href="{{ route('gameStats', ['match' => $match->id]) }}" data-testid="ingame-view-stats">View box score</a>
                    <a class="hf-btn hf-btn--danger" href="{{ route('gameStats', ['match' => $match->id, 'correct' => 1]) }}" data-testid="ingame-correct">Correct score</a>
                    <a class="hf-btn" href="{{ route('gameManage') }}">Change match</a>
                </div>
            </section>
        @else
            <section class="hf-card hf-mb-3" data-testid="ingame-quarter-tabs">
                <div class="hf-between hf-wrap hf-mb-2">
                    <div>
                        <span class="hf-eyebrow">Game clock</span>
                        <h2 class="hf-section-title hf-mb-0">Quarter</h2>
                    </div>
                    <div class="hf-btn-row">
                        @foreach ($labels as $index => $label)
                            <button type="button" class="hf-btn hf-btn--sm {{ $activeQuarter === $index ? 'hf-btn--primary' : '' }}" wire:click="setActiveQuarter({{ $index }})" aria-pressed="{{ $activeQuarter === $index ? 'true' : 'false' }}" data-testid="ingame-quarter-tab-{{ $index }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="hf-btn-row">
                    <button type="button" class="hf-btn" wire:click="addOvertime" data-testid="ingame-add-overtime"><i class="bi bi-plus-lg"></i> Add overtime</button>
                    <button type="button" class="hf-btn" wire:click="removeOvertime" data-testid="ingame-remove-overtime"><i class="bi bi-dash-lg"></i> Remove last OT</button>
                </div>
            </section>

            <div class="hf-grid hf-grid--2 hf-mb-3">
                @foreach ([['side' => 'team1', 'team' => $home], ['side' => 'team2', 'team' => $away]] as $panel)
                    <section class="hf-card" data-testid="ingame-team-panel-{{ $panel['side'] }}">
                        <div class="hf-flex hf-items-center hf-gap-2 hf-mb-2">
                            <x-team-shield :team="$panel['team']" />
                            <strong>{{ $panel['team']?->name ?? 'TBD' }}</strong>
                        </div>
                        <div class="hf-grid hf-grid--3">
                            @foreach ($this->quarters[$panel['side']] as $index => $value)
                                <div class="hf-field">
                                    <label class="hf-label" for="ingameQ{{ $panel['side'] }}_{{ $index }}">{{ $labels[$index] ?? 'Q'.($index + 1) }}</label>
                                    <input class="hf-input" id="ingameQ{{ $panel['side'] }}_{{ $index }}" type="number" min="0" max="199" wire:model.live="quarters.{{ $panel['side'] }}.{{ $index }}" data-testid="ingame-q-{{ $panel['side'] }}-{{ $index }}">
                                </div>
                            @endforeach
                        </div>
                        <p class="hf-small hf-mt-1 hf-mb-1">Running total: <strong>{{ array_sum($this->quarters[$panel['side']]) }}</strong></p>
                        <label class="hf-label" for="ingameFinal{{ $panel['side'] }}">Final score</label>
                        <input class="hf-input" id="ingameFinal{{ $panel['side'] }}" type="number" min="0" max="199" wire:model.live="finals.{{ $panel['side'] }}" data-testid="ingame-final-{{ $panel['side'] }}">
                    </section>
                @endforeach
            </div>

            <section class="hf-card hf-card--flat hf-mb-3" data-testid="ingame-side-filters">
                <div class="hf-between hf-wrap">
                    <div class="hf-filter-row">
                        @foreach ([['key' => 'all', 'label' => 'All'], ['key' => 'team1', 'label' => 'Team 1'], ['key' => 'team2', 'label' => 'Team 2']] as $filter)
                            <button type="button" class="hf-chip hf-chip--{{ $side === $filter['key'] ? 'primary' : 'slate' }}" wire:click="setSide('{{ $filter['key'] }}')" aria-pressed="{{ $side === $filter['key'] ? 'true' : 'false' }}" data-testid="ingame-side-{{ $filter['key'] }}">{{ $filter['label'] }}</button>
                        @endforeach
                    </div>
                    <select class="hf-select" wire:model.live="side" data-testid="ingame-team-filter" aria-label="Team filter">
                        <option value="all">Both teams</option>
                        <option value="team1">{{ $home?->name ?? 'Team 1' }}</option>
                        <option value="team2">{{ $away?->name ?? 'Team 2' }}</option>
                    </select>
                </div>
            </section>

            @foreach ([['side' => 'team1', 'team' => $home, 'search' => 'search1'], ['side' => 'team2', 'team' => $away, 'search' => 'search2']] as $block)
                @continue($side !== 'all' && $side !== $block['side'])
                @php $rows = $this->playersFor($block['side']); @endphp
                <section class="hf-card hf-mb-3" data-testid="ingame-stats-{{ $block['side'] }}">
                    <div class="hf-between hf-wrap hf-mb-2">
                        <div class="hf-flex hf-items-center hf-gap-2">
                            <x-team-shield :team="$block['team']" />
                            <h2 class="hf-section-title hf-mb-0">{{ $block['team']?->name ?? 'TBD' }}</h2>
                        </div>
                        <input class="hf-input" type="search" placeholder="Search player" wire:model.live.debounce.300ms="{{ $block['search'] }}" data-testid="ingame-player-search-{{ $block['side'] }}">
                    </div>
                    <div class="hf-table-wrap">
                        <table class="hf-table">
                            <thead>
                                <tr>
                                    <th scope="col">Player</th>
                                    <th scope="col" class="is-num">FG M</th>
                                    <th scope="col" class="is-num">FG A</th>
                                    <th scope="col" class="is-num">3PT M</th>
                                    <th scope="col" class="is-num">3PT A</th>
                                    <th scope="col" class="is-num">FT M</th>
                                    <th scope="col" class="is-num">FT A</th>
                                    <th scope="col" class="is-num">REB</th>
                                    <th scope="col" class="is-num">AST</th>
                                    <th scope="col" class="is-num">STL</th>
                                    <th scope="col" class="is-num">BLK</th>
                                    <th scope="col" class="is-num">TO</th>
                                    <th scope="col" class="is-num">PF</th>
                                    <th scope="col" class="is-num">PTS</th>
                                    <th scope="col" class="is-num">Totals</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $quarterStats = $this->stats[$block['side']][$row['id']]['quarters'][$activeQuarter] ?? [];
                                    @endphp
                                    <tr data-testid="ingame-stat-row-{{ $block['side'] }}-{{ $loop->index }}" data-player-name="{{ mb_strtolower($row['name']) }}">
                                        <td>
                                            <strong>#{{ $row['number'] ?? '–' }} {{ $row['name'] }}</strong>
                                            <span class="hf-small hf-muted" style="display: block">{{ $row['position'] ?: 'Player' }}</span>
                                        </td>
                                        @foreach (array_keys(\App\Services\GameStatsService::FIELDS) as $field)
                                            <td class="is-num">
                                                <input class="hf-input" type="number" min="0" max="199" aria-label="{{ $row['name'] }} {{ $field }} {{ $labels[$activeQuarter] ?? '' }}" wire:model.live="stats.{{ $block['side'] }}.{{ $row['id'] }}.quarters.{{ $activeQuarter }}.{{ $field }}" data-testid="ingame-stat-{{ $block['side'] }}-{{ $row['id'] }}-{{ $field }}">
                                            </td>
                                        @endforeach
                                        <td class="is-num"><strong>{{ $this->statPoints($quarterStats) }}</strong></td>
                                        <td class="is-num">{{ $row['totals']['pts'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="15">
                                            <div class="hf-empty">
                                                <h3>No players match</h3>
                                                <p>Clear the player search to see the full rotation.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="hf-small hf-muted hf-mt-1 hf-mb-0">Totals are the sum of all quarters. Editing a stat recomputes the active quarter and final score.</p>
                </section>
            @endforeach

            <section class="hf-card hf-card--flat" data-testid="ingame-actions">
                @if ($errorMessage !== '')
                    <p class="hf-field-error hf-mb-2" data-testid="ingame-error">{{ $errorMessage }}</p>
                @endif
                <div class="hf-btn-row">
                    <button type="button" class="hf-btn" wire:click="saveDraft" wire:loading.attr="disabled" data-testid="ingame-save"><i class="bi bi-save"></i> Save</button>
                    <button type="button" class="hf-btn hf-btn--primary" wire:click="requestPublish" wire:loading.attr="disabled" data-testid="ingame-publish"><i class="bi bi-check2-circle"></i> Publish result</button>
                    <a class="hf-btn" href="{{ route('bracket', ['id' => $match->tournament_id]) }}" data-testid="ingame-cancel">Cancel</a>
                </div>
            </section>
        @endif
    @endif

    @if ($showConfirm)
        <dialog
            class="hf-dialog"
            x-data
            x-init="if (! $el.open) $el.showModal()"
            x-on:cancel.prevent="$wire.closeConfirm()"
            x-on:click="if ($event.target === $el) $wire.closeConfirm()"
            wire:key="ingame-confirm-dialog"
            data-testid="ingame-confirm-dialog"
        >
            <div class="hf-dialog__head">
                <h2 class="hf-dialog__title">{{ $confirmTitle }}</h2>
                <button type="button" class="hf-icon-btn" wire:click="closeConfirm" aria-label="Close dialog" data-testid="dialog-close"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <div class="hf-dialog__body">
                <p class="hf-small hf-muted">{{ $confirmMessage }}</p>
            </div>
            <div class="hf-dialog__foot">
                <button type="button" class="hf-btn" wire:click="closeConfirm" data-testid="ingame-confirm-cancel">Cancel</button>
                <button type="button" class="hf-btn hf-btn--{{ $confirmTone }}" wire:click="{{ $confirmAction }}" data-testid="ingame-confirm">{{ $confirmLabel }}</button>
            </div>
        </dialog>
    @endif
</div>
```

- [ ] **Step 4: Write the page tests**

Create `src/tests/Feature/Pages/GameManageTest.php`:

```php
<?php

use App\Livewire\Organizer\GameManage;
use App\Models\AuditLog;
use App\Models\MatchModel;
use App\Models\PlayerStat;
use App\Models\PlayerStatQuarter;
use App\Models\User;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function gmUser(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

function gmMatch(int $round, int $number): MatchModel
{
    return MatchModel::where('round_number', $round)->where('match_number', $number)->firstOrFail();
}

function gmPlayerId(MatchModel $match, string $side): int
{
    $team = $side === 'team1' ? $match->homeTeam : $match->awayTeam;

    return (int) $team->players()->orderBy('jersey_number')->value('id');
}

it('redirects guests and blocks non-organizers', function () {
    $this->get('/game-manage')->assertRedirect('/login');

    $this->actingAs(gmUser('coach.marcus@hoopflow.ph'))->get('/game-manage')->assertStatus(403);
    $this->actingAs(gmUser('fan.juan@hoopflow.ph'))->get('/game-manage')->assertStatus(403);
});

it('renders the picker for an organizer', function () {
    $this->actingAs(gmUser('organizer@hoopflow.ph'))
        ->get('/game-manage')
        ->assertOk()
        ->assertSee('Select a match to manage')
        ->assertSeeHtml('data-testid="ingame-tournament-filter"')
        ->assertSeeHtml('data-testid="ingame-status-filter"')
        ->assertSeeHtml('data-testid="ingame-team-search"')
        ->assertSeeHtml('data-testid="ingame-match-list"')
        ->assertSeeHtml('data-testid="ingame-match-card-');
});

it('renders the prototype editor structure', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('matchId', $match->id)
        ->assertSee('In-game score & player stats')
        ->assertSeeHtml('data-testid="ingame-quarter-tabs"')
        ->assertSeeHtml('data-testid="ingame-quarter-tab-0"')
        ->assertSeeHtml('data-testid="ingame-team-panel-team1"')
        ->assertSeeHtml('data-testid="ingame-team-panel-team2"')
        ->assertSeeHtml('data-testid="ingame-add-overtime"')
        ->assertSeeHtml('data-testid="ingame-remove-overtime"')
        ->assertSeeHtml('data-testid="ingame-side-filters"')
        ->assertSeeHtml('data-testid="ingame-player-search-team1"')
        ->assertSeeHtml('data-testid="ingame-player-search-team2"')
        ->assertSeeHtml('data-testid="ingame-save"')
        ->assertSeeHtml('data-testid="ingame-publish"')
        ->assertSeeHtml('data-testid="ingame-cancel"');
});

it('saves a draft without finalizing the match', function () {
    $match = gmMatch(8, 3);
    $playerId = gmPlayerId($match, 'team1');

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set("stats.team1.{$playerId}.quarters.0.fgm", 2)
        ->set("stats.team1.{$playerId}.quarters.0.fga", 4)
        ->set('quarters.team1', [10, 10, 10, 10])
        ->set('quarters.team2', [8, 8, 8, 8])
        ->set('finals.team1', 40)
        ->set('finals.team2', 32)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $match->refresh();
    expect($match->status)->toBe('scheduled')
        ->and($match->quarters_json['team1'])->toBe([10, 10, 10, 10])
        ->and(PlayerStat::where('match_id', $match->id)->count())->toBeGreaterThan(0)
        ->and(PlayerStatQuarter::where('match_id', $match->id)->count())->toBeGreaterThan(0)
        ->and(AuditLog::where('action', 'stats.save')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('recomputes the active quarter and final from stat edits', function () {
    $match = gmMatch(8, 3);
    $playerId = gmPlayerId($match, 'team1');

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set("stats.team1.{$playerId}.quarters.0.fgm", 2)
        ->set("stats.team1.{$playerId}.quarters.0.tpm", 1)
        ->set("stats.team1.{$playerId}.quarters.0.ftm", 1)
        ->assertSet('quarters.team1.0', 6)
        ->assertSet('finals.team1', 6);
});

it('syncs the final when a quarter score changes', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1.0', 12)
        ->assertSet('finals.team1', 12);
});

it('adds and removes overtime across teams and players', function () {
    $match = gmMatch(8, 3);

    $component = Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->call('addOvertime')
        ->assertCount('quarters.team1', 5)
        ->assertCount('quarters.team2', 5);

    $firstPlayer = gmPlayerId($match, 'team1');
    $component->assertCount("stats.team1.{$firstPlayer}.quarters", 5)
        ->call('removeOvertime')
        ->assertCount('quarters.team1', 4);
});

it('caps overtime at nine quarters', function () {
    $match = gmMatch(8, 3);

    $component = Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id]);

    for ($i = 0; $i < 6; $i++) {
        $component->call('addOvertime');
    }

    $component->assertCount('quarters.team1', 9);
});

it('publishes a result and renders the locked state', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1', [10, 10, 10, 10])
        ->set('quarters.team2', [8, 8, 8, 8])
        ->set('finals.team1', 40)
        ->set('finals.team2', 32)
        ->call('requestPublish')
        ->assertSet('showConfirm', true)
        ->call('confirmPublish')
        ->assertSee('Locked result');

    $match->refresh();
    expect($match->status)->toBe('final')
        ->and((int) $match->winner_team_id)->toBe((int) $match->home_team_id)
        ->and(AuditLog::where('action', 'stats.publish')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('rejects publish on a tie and on a final mismatch', function () {
    $match = gmMatch(8, 3);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1', [10, 10, 10, 10])
        ->set('quarters.team2', [10, 10, 10, 10])
        ->set('finals.team1', 40)
        ->set('finals.team2', 40)
        ->call('requestPublish')
        ->assertSet('showConfirm', false)
        ->assertSet('errorMessage', 'Elimination games cannot end in a tie.')
        ->set('quarters.team2', [8, 8, 8, 8])
        ->set('finals.team2', 31)
        ->call('requestPublish')
        ->assertSet('errorMessage', 'Final score must equal the sum of the quarters.');
});

it('locks final matches and refuses mutations', function () {
    $match = gmMatch(8, 1);

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSee('Locked result')
        ->assertDontSeeHtml('data-testid="ingame-save"')
        ->call('saveDraft')
        ->assertSet('errorMessage', 'This result is already final. Use correction to change it.');

    expect($match->refresh()->status)->toBe('final');
});
```

- [ ] **Step 5: Run the page tests**

```powershell
cd src
php artisan test --filter=GameManageTest
```

Expected: all pass. If Livewire reports a missing property path, confirm the `stats` array is initialized with both sides before editing and that `playersFor()` returns roster rows.

- [ ] **Step 6: Commit**

```powershell
git add src/routes/features/game-management.php src/routes/web.php src/app/Livewire/Organizer/GameManage.php src/resources/views/livewire/organizer/game-manage.blade.php src/tests/Feature/Pages/GameManageTest.php
git commit -m "feat(stats): add in-game scoring page"
```

---

## Task 4: Read-only game-stats page

**Files**

- Modify: `src/app/Livewire/Organizer/GameStatsShow.php`
- Modify: `src/resources/views/livewire/organizer/game-stats-show.blade.php`
- Modify: `src/tests/Feature/Pages/GameStatsTest.php`

- [ ] **Step 1: Add imports and eager loading**

At the top of `src/app/Livewire/Organizer/GameStatsShow.php`, add:

```php
use App\Models\PlayerStatQuarter;
use App\Services\GameStatsService;
```

In `match()`, add `'playerQuarterStats',` after `'playerStats.team',`.

- [ ] **Step 2: Make the quarter computed dynamic**

Replace the whole `quarters()` method (and its docblock) with:

```php
/**
 * @return array{team1: list<int>, team2: list<int>}|null
 */
#[Computed]
public function quarters(): ?array
{
    $raw = $this->match?->quarters_json;

    if (! is_array($raw) || ! is_array($raw['team1'] ?? null) || ! is_array($raw['team2'] ?? null)) {
        return null;
    }

    $team1 = array_values($raw['team1']);
    $team2 = array_values($raw['team2']);
    $count = min(9, max(4, count($team1), count($team2)));

    $normalize = function (array $values) use ($count): array {
        $values = array_values($values);
        $out = [];

        for ($index = 0; $index < $count; $index++) {
            $out[] = is_numeric($values[$index] ?? null) ? (int) $values[$index] : 0;
        }

        return $out;
    };

    return [
        'team1' => $normalize($team1),
        'team2' => $normalize($team2),
    ];
}
```

- [ ] **Step 3: Include quarter rows in `rowsFor()`**

Replace `rowsFor()` with:

```php
/**
 * @return array<int, array{playerId: int, name: string, number: int|string|null, position: string|null, points: int, assists: int, rebounds: int, hasQuarters: bool, quarters: array<int, array<string, int>>}>
 */
public function rowsFor(?Team $team): array
{
    $match = $this->match;

    if (! $team instanceof Team || ! $match instanceof MatchModel) {
        return [];
    }

    $term = mb_strtolower(trim($this->search));
    $key = in_array($this->sortKey, ['points', 'assists', 'rebounds'], true) ? $this->sortKey : 'points';
    $factor = $this->sortDirection === 'asc' ? 1 : -1;
    $quarterRows = $match->playerQuarterStats->groupBy('player_id');
    $quarterData = $this->quarters;
    $count = is_array($quarterData) ? count($quarterData['team1']) : 4;

    return $match->playerStats
        ->where('team_id', $team->id)
        ->map(function (PlayerStat $stat) use ($quarterRows, $count): array {
            $player = $stat->player;
            $name = trim(($player->first_name ?? '').' '.($player->last_name ?? ''));
            $rows = $quarterRows->get((int) $stat->player_id, collect());
            $quarters = [];

            for ($index = 0; $index < $count; $index++) {
                $row = $rows->firstWhere('quarter_index', $index);
                $quarters[$index] = $this->shortQuarter($row instanceof PlayerStatQuarter ? $row : null);
            }

            return [
                'playerId' => (int) $stat->player_id,
                'name' => $name !== '' ? $name : 'Player',
                'number' => $player->jersey_number,
                'position' => $player->position,
                'points' => (int) ($stat->points ?? 0),
                'assists' => (int) ($stat->assists ?? 0),
                'rebounds' => (int) ($stat->rebounds ?? 0),
                'hasQuarters' => $rows->isNotEmpty(),
                'quarters' => $quarters,
            ];
        })
        ->filter(fn (array $row): bool => $term === '' || str_contains(mb_strtolower($row['name']), $term))
        ->sort(fn (array $a, array $b): int => (($a[$key] <=> $b[$key]) * $factor) ?: strcasecmp($a['name'], $b['name']))
        ->values()
        ->all();
}
```

Add these helpers before `sortBy()`:

```php
/**
 * @return array<string, int>
 */
protected function shortQuarter(?PlayerStatQuarter $row): array
{
    $out = [];

    foreach (GameStatsService::FIELDS as $short => $column) {
        $out[$short] = (int) ($row?->getAttribute($column) ?? 0);
    }

    $out['pts'] = (int) ($row?->getAttribute('points') ?? 0);

    return $out;
}
```

- [ ] **Step 4: Open the correction dialog from `?correct=1`**

At the end of `mount()`, after the existing query-string match lookup, add:

```php
if ($this->matchId !== null && request()->boolean('correct') && $this->isOrganizer) {
    $this->openCorrection();
}
```

- [ ] **Step 5: Update the header action and box-score label**

In `src/resources/views/livewire/organizer/game-stats-show.blade.php`, inside `hf-page-actions` before the `@if ($canScore)` button, add:

```blade
@if ($canScore)
    <a class="hf-btn" href="{{ route('gameManage', ['match' => $match->id]) }}" data-testid="stats-in-game"><i class="bi bi-clipboard-pulse"></i> In-game scoring</a>
@endif
```

Change the box-score eyebrow line to:

```blade
<span class="hf-eyebrow">{{ $match->status === 'scheduled' && $hasStats ? 'Draft box score' : 'Official box score' }}</span>
```

- [ ] **Step 6: Render dynamic quarter headers**

Replace the four fixed `Q1`–`Q4` header cells with:

```blade
@foreach (array_keys($quarters['team1']) as $index)
    <th scope="col" class="is-num">{{ $index < 4 ? 'Q'.($index + 1) : 'OT'.($index - 3) }}</th>
@endforeach
```

- [ ] **Step 7: Render per-player quarter details**

Inside the `@else` branch of each team section, immediately after the closing `</div>` of `.hf-table-wrap` and before `</section>`, add:

```blade
@foreach ($rows as $row)
    @if ($row['hasQuarters'])
        <details class="hf-card hf-card--flat hf-mt-2" data-testid="stats-player-quarters-{{ $sideKey }}-{{ $row['playerId'] }}">
            <summary><strong>{{ $row['name'] }}</strong> — by quarter</summary>
            <div class="hf-table-wrap hf-mt-2">
                <table class="hf-table">
                    <thead>
                        <tr>
                            <th scope="col">Q</th>
                            <th scope="col" class="is-num">PTS</th>
                            <th scope="col" class="is-num">FG</th>
                            <th scope="col" class="is-num">3PT</th>
                            <th scope="col" class="is-num">FT</th>
                            <th scope="col" class="is-num">REB</th>
                            <th scope="col" class="is-num">AST</th>
                            <th scope="col" class="is-num">STL</th>
                            <th scope="col" class="is-num">BLK</th>
                            <th scope="col" class="is-num">TO</th>
                            <th scope="col" class="is-num">PF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($row['quarters'] as $index => $quarter)
                            <tr data-testid="stats-player-quarter-{{ $sideKey }}-{{ $row['playerId'] }}-{{ $index }}">
                                <td>{{ $index < 4 ? 'Q'.($index + 1) : 'OT'.($index - 3) }}</td>
                                <td class="is-num">{{ $quarter['pts'] }}</td>
                                <td class="is-num">{{ $quarter['fgm'] }}/{{ $quarter['fga'] }}</td>
                                <td class="is-num">{{ $quarter['tpm'] }}/{{ $quarter['tpa'] }}</td>
                                <td class="is-num">{{ $quarter['ftm'] }}/{{ $quarter['fta'] }}</td>
                                <td class="is-num">{{ $quarter['reb'] }}</td>
                                <td class="is-num">{{ $quarter['ast'] }}</td>
                                <td class="is-num">{{ $quarter['stl'] }}</td>
                                <td class="is-num">{{ $quarter['blk'] }}</td>
                                <td class="is-num">{{ $quarter['to'] }}</td>
                                <td class="is-num">{{ $quarter['pf'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
@endforeach
```

- [ ] **Step 8: Add the game-stats test**

Append to `src/tests/Feature/Pages/GameStatsTest.php`:

```php
it('shows per-player quarter details, the draft label, and the in-game link', function () {
    $match = gsMatch(8, 3);
    $loaded = app(\App\Services\GameStatsService::class)->load($match);
    $playerId = (int) array_key_first($loaded['stats']['team1']);
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fgm'] = 2;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fga'] = 4;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['ftm'] = 1;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fta'] = 2;

    app(\App\Services\GameStatsService::class)->saveDraft($match, $loaded);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSee('Draft box score')
        ->assertSeeHtml('data-testid="stats-in-game"')
        ->assertSeeHtml('data-testid="stats-player-quarters-team1-'.$playerId.'"')
        ->assertSee('— by quarter');
});

it('opens the correction dialog from the correct query flag', function () {
    $match = gsMatch(8, 1);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id.'&correct=1')
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-correction-dialog"');
});
```

- [ ] **Step 9: Run the game-stats tests**

```powershell
cd src
php artisan test --filter=GameStatsTest
```

Expected: existing assertions still pass. If an existing test expected exactly four quarter headers, update only that assertion to use the new dynamic count.

- [ ] **Step 10: Commit**

```powershell
git add src/app/Livewire/Organizer/GameStatsShow.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/tests/Feature/Pages/GameStatsTest.php
git commit -m "feat(stats): show per-player quarter details"
```

---

## Task 5: Navigation and bracket entry points

**Files**

- Modify: `src/resources/views/layouts/public.blade.php`
- Modify: `src/resources/views/livewire/organizer/bracket-show.blade.php`
- Modify: `src/tests/Feature/Pages/GameManageTest.php`

- [ ] **Step 1: Add the organizer nav item**

In `src/resources/views/layouts/public.blade.php`, in `$navItems`, add after `gameStats`:

```php
'gameManage' => ['label' => 'Game scoring', 'icon' => 'clipboard-pulse', 'route' => 'gameManage'],
```

Change the organizer role tools line to:

```php
'organizer' => ['submissions', 'payments', 'audit', 'createTournament', 'gameStats', 'gameManage'],
```

In `$groupedActive['schedule']`, add `'gameManage'`:

```php
'schedule' => ['schedule', 'matches', 'gameStats', 'game-stats', 'gameManage'],
```

- [ ] **Step 2: Add the bracket match-detail link**

In `src/resources/views/livewire/organizer/bracket-show.blade.php`, inside the match detail dialog footer, before the existing `Game stats` link, add:

```blade
@if ($isOrganizer)
    <a class="hf-btn" href="{{ route('gameManage', ['match' => $detailMatch->id]) }}" data-testid="bracket-in-game"><i class="bi bi-clipboard-pulse"></i> In-game score &amp; player stats</a>
@endif
```

- [ ] **Step 3: Add the entry-point test**

Append to `src/tests/Feature/Pages/GameManageTest.php`:

```php
it('links to in-game scoring from the nav, bracket detail, and game stats header', function () {
    $organizer = gmUser('organizer@hoopflow.ph');
    $match = gmMatch(8, 3);

    $this->actingAs($organizer)
        ->get('/game-manage')
        ->assertOk()
        ->assertSeeHtml('data-testid="nav-gameManage"');

    $this->get('/bracket?id='.$match->tournament_id.'&match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="bracket-in-game"');

    $this->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-in-game"');
});
```

- [ ] **Step 4: Run the link tests**

```powershell
cd src
php artisan test --filter=GameManageTest
php artisan test --filter=GameStatsTest
```

- [ ] **Step 5: Commit**

```powershell
git add src/resources/views/layouts/public.blade.php src/resources/views/livewire/organizer/bracket-show.blade.php src/tests/Feature/Pages/GameManageTest.php
git commit -m "feat(stats): link in-game scoring from navigation"
```

---

## Task 6: Quality gate and manual verification

- [ ] **Step 1: Run the full quality suite**

```powershell
cd src
composer quality
```

This runs Pint, Larastan level 6, and Pest. Fix every failure. Do not claim completion until it exits successfully.

- [ ] **Step 2: Re-run the two feature suites explicitly**

```powershell
cd src
php artisan test --filter=GameManageTest
php artisan test --filter=GameStatsTest
php artisan test --filter=GameStatsServiceTest
```

- [ ] **Step 3: Verify the page in a browser**

```powershell
cd src
php artisan serve
```

Sign in as `organizer@hoopflow.ph` / `password`, then:

1. Open `/game-manage`; confirm the picker filters and match cards render.
2. Open a scheduled game; confirm quarter tabs, team panels, overtime buttons, side filters, player searches, and Save/Publish/Cancel are visible.
3. Enter quarter scores; confirm the running total and final update.
4. Enter a player stat; confirm the active-quarter score and final update.
5. Add overtime; confirm Q/OT tabs and every roster row gain a quarter.
6. Save; reload and confirm quarters and stats persist while the game stays scheduled.
7. Publish; confirm the winner advances on `/bracket`, the match becomes final, and `/game-manage` shows the locked card.
8. Open `/game-stats?match={id}`; confirm the per-player `— by quarter` details and dynamic OT headers.
9. Open the bracket match dialog; confirm `In-game score & player stats` opens the locked screen for a final and the editor for a scheduled game.
10. Open the locked screen's `Correct score`; confirm `/game-stats?match={id}&correct=1` opens the correction dialog with the reason field.

- [ ] **Step 4: Report the actual output**

Paste the `composer quality` summary (test count and assertions, Pint result, PHPStan result) in the final message. Include any manual-verification notes, especially if PostgreSQL-specific behavior was not exercised locally.

- [ ] **Step 5: Final commit (only if manual fixes were needed)**

```powershell
git add src/app src/database src/routes src/resources src/tests
git commit -m "fix(stats): polish in-game scoring"
```

Never stage `logo/`, `.obsidian/`, `.kilo/`, or other unrelated dirty files.

---

## Risks and mitigations

- **PostgreSQL partition insert:** `player_stats.tournament_id` is NOT NULL only on Postgres. `GameStatsService` sets it only when `DB::getDriverName() === 'pgsql'`; keep that branch when refactoring.
- **Livewire nested updates:** `updated()` receives dotted paths. The regex in `GameManage` is intentionally strict; if a field is renamed, update the regex and `GameStatsService::FIELDS` together.
- **Quarter/stat auto-sync:** editing a stat overwrites the active team quarter score by design. If a scorekeeper needs a manual correction, they can clear or adjust the stat inputs; the final score always follows the quarter sums on publish.
- **Duplicate route loading:** the `glob()` loop in `web.php` must be added once, after the existing route groups. Feature files must not register the same route name twice.
- **Final-game lock:** both the component and the service reject final/forfeit matches. Do not relax only one layer; the service is the source of truth for other callers.

