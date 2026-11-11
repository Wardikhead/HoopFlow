# Bracket engine and playoff series — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make double elimination and round robin fully playable and accurate in `src/`, add best-of playoff series, add an optional round-robin playoff stage, and fix the bracket UI, standings, and scheduling integration.

**Architecture:** Extract pure-PHP plan builders (`SingleEliminationBuilder`, `DoubleEliminationBuilder`, `RoundRobinBuilder`) under `app/Services/Bracket/`. `BracketService` persists a plan, resolves structural byes, runs the series lifecycle, propagates results, and resolves champions. Every matchup is a series anchored by its game 1 match; child games reference the anchor through `matches.series_id`. Livewire and Blade consume stage-aware sections and series cards.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4, Blade, Pest, SQLite for tests, PostgreSQL 17 in production.

**Source of truth:** `docs/superpowers/specs/2026-11-11-bracket-engine-playoff-series-design.md`

## Global Constraints

- Work inside `src/`. Do not modify `prototype/`.
- No Tailwind, Vue, React, or Bootstrap JavaScript.
- Best-of values are only `1, 3, 5, 7, 9`.
- `matches` is hash-partitioned by `tournament_id` in PostgreSQL. Do **not** add a self-referencing foreign key for `series_id`; use an indexed `unsignedBigInteger` and application-level integrity. Do not add FKs to the new team ID columns either, because the partition migration excluded existing constraints; keep the new columns indexed.
- Existing single-elimination behavior and tests must remain green.
- Every task ends with the listed tests and a commit.
- `composer quality` must pass before the work is declared complete.
- If the shell tool still fails with “The system cannot find the path specified”, stop and report the blocker. Do not claim tests passed without real output.

## File map

Create:

- `src/app/Services/Bracket/SeedOrder.php`
- `src/app/Services/Bracket/SlotSource.php`
- `src/app/Services/Bracket/MatchupPlan.php`
- `src/app/Services/Bracket/BracketPlan.php`
- `src/app/Services/Bracket/SingleEliminationBuilder.php`
- `src/app/Services/Bracket/DoubleEliminationBuilder.php`
- `src/app/Services/Bracket/RoundRobinBuilder.php`
- `src/database/migrations/2026_11_11_000002_add_bracket_stage_and_series.php`
- `src/tests/Unit/Services/Bracket/SeedOrderTest.php`
- `src/tests/Unit/Services/Bracket/SingleEliminationBuilderTest.php`
- `src/tests/Unit/Services/Bracket/DoubleEliminationBuilderTest.php`
- `src/tests/Unit/Services/Bracket/RoundRobinBuilderTest.php`
- `src/tests/Unit/Services/Bracket/SeriesLifecycleTest.php`
- `src/tests/Unit/Services/Bracket/DoubleEliminationPlayThroughTest.php`
- `src/tests/Unit/Services/Bracket/RoundRobinPlayoffTest.php`

Modify:

- `src/app/Services/BracketService.php`
- `src/app/Models/MatchModel.php`
- `src/app/Models/Bracket.php`
- `src/app/Models/BracketSlot.php`
- `src/app/Services/StandingsService.php`
- `src/app/Services/SchedulingService.php`
- `src/app/Livewire/Organizer/BracketShow.php`
- `src/app/Livewire/Organizer/TournamentShow.php`
- `src/app/Livewire/Organizer/TournamentCreate.php`
- `src/resources/views/livewire/organizer/bracket-show.blade.php`
- `src/resources/views/livewire/organizer/tournament-create.blade.php`
- `src/resources/views/components/bracket-match.blade.php`
- `src/tests/Unit/Services/BracketServiceTest.php`
- `src/tests/Unit/Services/StandingsServiceTest.php`
- `src/tests/Feature/Pages/BracketTest.php`
- `src/tests/Feature/Pages/ScheduleTest.php` (only if an assertion depends on old round ordering)
- `src/tests/Feature/Pages/TournamentCreateTest.php` (or the existing create-page test file)

---

## Task 1: Schema, model relations, and compatibility defaults

**Files:**
- Create: `src/database/migrations/2026_11_11_000002_add_bracket_stage_and_series.php`
- Modify: `src/app/Models/MatchModel.php`
- Modify: `src/app/Models/Bracket.php`
- Modify: `src/app/Models/BracketSlot.php`
- Create: `src/tests/Unit/Services/Bracket/SchemaTest.php`

**Interfaces:**
- Produces: `matches.stage`, `matches.series_id`, `matches.game_number`, `matches.best_of`, `matches.series_home_wins`, `matches.series_away_wins`, `matches.series_winner_team_id`, `matches.game_winner_team_id`.
- Produces: `bracket_slots.stage`, `brackets.phase`.
- Produces: `MatchModel::series()`, `seriesAnchor()`, `seriesWinnerTeam()`, `gameWinnerTeam()`, `isSeriesAnchor()`, `isSeriesGame()`, `activeGame()`.
- Defaults keep existing rows working: `stage = winners`, `game_number = 1`, `best_of = 1`, `phase = main`.

- [ ] **Step 1: Write the failing schema/model test**

Create `src/tests/Unit/Services/Bracket/SchemaTest.php`:

```php
<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds bracket stage and series columns with safe defaults', function () {
    expect(Schema::hasColumn('brackets', 'phase'))->toBeTrue()
        ->and(Schema::hasColumn('bracket_slots', 'stage'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'stage'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_id'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'game_number'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'best_of'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_home_wins'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_away_wins'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'series_winner_team_id'))->toBeTrue()
        ->and(Schema::hasColumn('matches', 'game_winner_team_id'))->toBeTrue();
});

it('exposes series relations and active game helpers', function () {
    $home = \App\Models\Team::factory()->create();
    $away = \App\Models\Team::factory()->create();

    $anchor = MatchModel::factory()->create([
        'best_of' => 3,
        'status' => 'in_progress',
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
    ]);

    $game2 = MatchModel::factory()->create([
        'bracket_id' => $anchor->bracket_id,
        'tournament_id' => $anchor->tournament_id,
        'stage' => $anchor->stage,
        'round_number' => $anchor->round_number,
        'match_number' => $anchor->match_number,
        'series_id' => $anchor->id,
        'game_number' => 2,
        'best_of' => 3,
        'status' => 'scheduled',
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
    ]);

    expect($anchor->isSeriesAnchor())->toBeTrue()
        ->and($game2->isSeriesGame())->toBeTrue()
        ->and($anchor->series)->toHaveCount(1)
        ->and($anchor->activeGame()?->id)->toBe($game2->id)
        ->and($game2->seriesAnchor?->id)->toBe($anchor->id);
});
```

Run it and confirm it fails because the columns/relations do not exist:

```powershell
cd src
php artisan test --filter="adds bracket stage and series columns with safe defaults"
```

- [ ] **Step 2: Create the migration**

Create `src/database/migrations/2026_11_11_000002_add_bracket_stage_and_series.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brackets', function (Blueprint $table): void {
            $table->string('phase', 20)->default('main');
            $table->index('phase');
        });

        Schema::table('bracket_slots', function (Blueprint $table): void {
            $table->string('stage', 20)->default('winners');
            $table->index(['bracket_id', 'stage', 'round_number', 'match_number'], 'bracket_slots_stage_identity_idx');
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->string('stage', 20)->default('winners');
            $table->unsignedBigInteger('series_id')->nullable();
            $table->unsignedSmallInteger('game_number')->default(1);
            $table->unsignedSmallInteger('best_of')->default(1);
            $table->unsignedSmallInteger('series_home_wins')->default(0);
            $table->unsignedSmallInteger('series_away_wins')->default(0);
            $table->unsignedBigInteger('series_winner_team_id')->nullable();
            $table->unsignedBigInteger('game_winner_team_id')->nullable();
            $table->index(['bracket_id', 'stage', 'round_number', 'match_number'], 'matches_stage_identity_idx');
            $table->index('series_id');
            $table->index('series_winner_team_id');
            $table->index('game_winner_team_id');
        });

        $roundRobinBracketIds = DB::table('brackets')->where('format', 'round_robin')->select('id');
        DB::table('brackets')->where('format', 'round_robin')->update(['phase' => 'group']);
        DB::table('matches')->whereIn('bracket_id', $roundRobinBracketIds)->update(['stage' => 'round_robin']);
        DB::table('bracket_slots')->whereIn('bracket_id', $roundRobinBracketIds)->update(['stage' => 'round_robin']);

        $doubleElimBracketIds = DB::table('brackets')->where('format', 'double_elim')->select('id');
        DB::table('matches')
            ->whereIn('bracket_id', $doubleElimBracketIds)
            ->where('round_number', '>=', 1000)
            ->update(['stage' => 'losers']);
        DB::table('bracket_slots')
            ->whereIn('bracket_id', $doubleElimBracketIds)
            ->where('round_number', '>=', 1000)
            ->update(['stage' => 'losers']);
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table): void {
            $table->dropIndex('matches_stage_identity_idx');
            $table->dropIndex(['series_id']);
            $table->dropIndex(['series_winner_team_id']);
            $table->dropIndex(['game_winner_team_id']);
            $table->dropColumn([
                'stage',
                'series_id',
                'game_number',
                'best_of',
                'series_home_wins',
                'series_away_wins',
                'series_winner_team_id',
                'game_winner_team_id',
            ]);
        });

        Schema::table('bracket_slots', function (Blueprint $table): void {
            $table->dropIndex('bracket_slots_stage_identity_idx');
            $table->dropColumn('stage');
        });

        Schema::table('brackets', function (Blueprint $table): void {
            $table->dropIndex(['phase']);
            $table->dropColumn('phase');
        });
    }
};
```

- [ ] **Step 3: Update the models**

`MatchModel` — add to `#[Fillable(...)]`:

```php
'stage', 'series_id', 'game_number', 'best_of',
'series_home_wins', 'series_away_wins',
'series_winner_team_id', 'game_winner_team_id',
```

Add to `casts()`:

```php
'series_id' => 'integer',
'game_number' => 'integer',
'best_of' => 'integer',
'series_home_wins' => 'integer',
'series_away_wins' => 'integer',
'series_winner_team_id' => 'integer',
'game_winner_team_id' => 'integer',
```

Add relations and helpers:

```php
public function isSeriesAnchor(): bool
{
    return $this->series_id === null;
}

public function isSeriesGame(): bool
{
    return $this->series_id !== null;
}

public function isBye(): bool
{
    return $this->isByeGame();
}

/**
 * @return HasMany<MatchModel, $this>
 */
public function series(): HasMany
{
    return $this->hasMany(MatchModel::class, 'series_id')->orderBy('game_number');
}

/**
 * @return BelongsTo<MatchModel, $this>
 */
public function seriesAnchor(): BelongsTo
{
    return $this->belongsTo(MatchModel::class, 'series_id');
}

/**
 * @return BelongsTo<Team, $this>
 */
public function seriesWinnerTeam(): BelongsTo
{
    return $this->belongsTo(Team::class, 'series_winner_team_id');
}

/**
 * @return BelongsTo<Team, $this>
 */
public function gameWinnerTeam(): BelongsTo
{
    return $this->belongsTo(Team::class, 'game_winner_team_id');
}

public function activeGame(): ?MatchModel
{
    if ($this->best_of <= 1) {
        return $this->status === 'scheduled' ? $this : null;
    }

    if ($this->status === 'scheduled') {
        return $this;
    }

    if ($this->status !== 'in_progress') {
        return null;
    }

    return $this->series()
        ->where('status', 'scheduled')
        ->orderBy('game_number')
        ->first();
}
```

`Bracket` — add `phase` to fillable, add a cached stage round count:

```php
/** @var array<string, int> */
private array $roundCountCache = [];

public function roundCount(string $stage): int
{
    if (! array_key_exists($stage, $this->roundCountCache)) {
        $this->roundCountCache[$stage] = (int) $this->matches()
            ->where('stage', $stage)
            ->whereNull('series_id')
            ->distinct()
            ->count('round_number');
    }

    return $this->roundCountCache[$stage];
}
```

`BracketSlot` — add `stage` to fillable.

- [ ] **Step 4: Run the schema/model test and the existing bracket test**

```powershell
cd src
php artisan test --filter="adds bracket stage and series columns with safe defaults"
php artisan test --filter="exposes series relations and active game helpers"
php artisan test --filter="generates a 16-team single-elimination bracket with byes"
```

The existing single-elimination test must still pass because the new columns have defaults.

- [ ] **Step 5: Commit**

```powershell
git add src/database/migrations/2026_11_11_000002_add_bracket_stage_and_series.php src/app/Models/MatchModel.php src/app/Models/Bracket.php src/app/Models/BracketSlot.php src/tests/Unit/Services/Bracket/SchemaTest.php
git commit -m "feat(bracket): add stage, phase, and series columns"
```

---

## Task 2: Pure plan value objects and seed order

**Files:**
- Create: `src/app/Services/Bracket/SeedOrder.php`
- Create: `src/app/Services/Bracket/SlotSource.php`
- Create: `src/app/Services/Bracket/MatchupPlan.php`
- Create: `src/app/Services/Bracket/BracketPlan.php`
- Create: `src/tests/Unit/Services/Bracket/SeedOrderTest.php`

**Interfaces:**
- Produces: `SeedOrder::forSize(int $size): list<int>` — returns the 1-based seed numbers in bracket-slot order.
- Produces: `SlotSource::seed(?int $teamId)`, `SlotSource::winner(string $stage, int $round, int $match)`, `SlotSource::loser(...)`.
- Produces: `MatchupPlan` with `stage`, `roundNumber`, `matchNumber`, `home`, `away`, `bestOf`, and `key()`.
- Produces: `BracketPlan` with a `list<MatchupPlan>` and `keyed()` lookup.

- [ ] **Step 1: Write the failing seed-order test**

Create `src/tests/Unit/Services/Bracket/SeedOrderTest.php`:

```php
<?php

use App\Services\Bracket\SeedOrder;

it('returns the standard bracket seed order', function () {
    expect(SeedOrder::forSize(2))->toBe([1, 2])
        ->and(SeedOrder::forSize(4))->toBe([1, 4, 2, 3])
        ->and(SeedOrder::forSize(8))->toBe([1, 8, 4, 5, 2, 7, 3, 6])
        ->and(SeedOrder::forSize(16))->toBe([1, 16, 8, 9, 4, 13, 5, 12, 2, 15, 7, 10, 3, 14, 6, 11]);
});

it('rejects sizes that are not powers of two', function () {
    expect(fn () => SeedOrder::forSize(6))->toThrow(InvalidArgumentException::class);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="returns the standard bracket seed order"
```

- [ ] **Step 2: Implement the value objects**

`SeedOrder.php`:

```php
<?php

namespace App\Services\Bracket;

final class SeedOrder
{
    /**
     * @return list<int>
     */
    public static function forSize(int $size): array
    {
        if ($size < 2 || ($size & ($size - 1)) !== 0) {
            throw new \InvalidArgumentException('Bracket size must be a power of two.');
        }

        $order = [1, 2];

        while (count($order) < $size) {
            $complement = count($order) * 2 + 1;
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $complement - $seed;
            }

            $order = $next;
        }

        return $order;
    }
}
```

`SlotSource.php`:

```php
<?php

namespace App\Services\Bracket;

final class SlotSource
{
    private function __construct(
        public readonly string $type,
        public readonly ?int $teamId = null,
        public readonly ?string $sourceStage = null,
        public readonly ?int $sourceRound = null,
        public readonly ?int $sourceMatch = null,
    ) {
    }

    public static function seed(?int $teamId): self
    {
        return new self('seed', $teamId);
    }

    public static function winner(string $stage, int $round, int $match): self
    {
        return new self('winner', null, $stage, $round, $match);
    }

    public static function loser(string $stage, int $round, int $match): self
    {
        return new self('loser', null, $stage, $round, $match);
    }

    public function key(): ?string
    {
        return $this->sourceStage === null
            ? null
            : $this->sourceStage.':'.$this->sourceRound.':'.$this->sourceMatch;
    }
}
```

`MatchupPlan.php`:

```php
<?php

namespace App\Services\Bracket;

final class MatchupPlan
{
    public function __construct(
        public readonly string $stage,
        public readonly int $roundNumber,
        public readonly int $matchNumber,
        public readonly SlotSource $home,
        public readonly SlotSource $away,
        public readonly int $bestOf = 1,
    ) {
        if (! in_array($bestOf, [1, 3, 5, 7, 9], true)) {
            throw new \InvalidArgumentException('Best-of must be 1, 3, 5, 7, or 9.');
        }
    }

    public function key(): string
    {
        return $this->stage.':'.$this->roundNumber.':'.$this->matchNumber;
    }
}
```

`BracketPlan.php`:

```php
<?php

namespace App\Services\Bracket;

final class BracketPlan
{
    /**
     * @param  list<MatchupPlan>  $matchups
     */
    public function __construct(public readonly array $matchups)
    {
    }

    /**
     * @return array<string, MatchupPlan>
     */
    public function keyed(): array
    {
        $keyed = [];

        foreach ($this->matchups as $matchup) {
            $keyed[$matchup->key()] = $matchup;
        }

        return $keyed;
    }
}
```

- [ ] **Step 3: Run the test**

```powershell
cd src
php artisan test --filter="returns the standard bracket seed order"
php artisan test --filter="rejects sizes that are not powers of two"
```

- [ ] **Step 4: Commit**

```powershell
git add src/app/Services/Bracket src/tests/Unit/Services/Bracket/SeedOrderTest.php
git commit -m "feat(bracket): add plan value objects and seed order"
```

## Task 3: Single-elimination plan builder (shared by playoffs)

**Files:**
- Create: `src/app/Services/Bracket/SingleEliminationBuilder.php`
- Create: `src/tests/Unit/Services/Bracket/SingleEliminationBuilderTest.php`

**Interfaces:**
- Consumes: `SeedOrder`, `SlotSource`, `MatchupPlan`, `BracketPlan`.
- Produces: `build(int $size, array $orderedTeamIds, array $bestOfByRound = []): BracketPlan`.
- `$orderedTeamIds` is a list in seed order: index 0 is seed 1, index 1 is seed 2, and so on. Missing entries are byes.
- `$bestOfByRound` keys: `0` final, `1` semifinal, `2` quarterfinal.

- [ ] **Step 1: Write the failing builder test**

Create `src/tests/Unit/Services/Bracket/SingleEliminationBuilderTest.php`:

```php
<?php

use App\Services\Bracket\SingleEliminationBuilder;

it('builds an eight-team single-elimination plan with the standard pairings', function () {
    $plan = (new SingleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8]);

    expect($plan->matchups)->toHaveCount(7);

    $firstRound = array_values(array_filter(
        $plan->matchups,
        fn ($matchup): bool => $matchup->roundNumber === 8,
    ));

    expect($firstRound)->toHaveCount(4)
        ->and($firstRound[0]->home->teamId)->toBe(1)
        ->and($firstRound[0]->away->teamId)->toBe(8)
        ->and($firstRound[1]->home->teamId)->toBe(4)
        ->and($firstRound[1]->away->teamId)->toBe(5)
        ->and($firstRound[2]->home->teamId)->toBe(2)
        ->and($firstRound[2]->away->teamId)->toBe(7)
        ->and($firstRound[3]->home->teamId)->toBe(3)
        ->and($firstRound[3]->away->teamId)->toBe(6);
});

it('routes winners forward and applies per-round best-of lengths', function () {
    $plan = (new SingleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8], [
        0 => 7,
        1 => 5,
        2 => 3,
    ]);

    $final = collect($plan->matchups)->firstWhere('roundNumber', 2);
    $semi = collect($plan->matchups)->firstWhere('roundNumber', 4);
    $quarter = collect($plan->matchups)->firstWhere('roundNumber', 8);

    expect($final->bestOf)->toBe(7)
        ->and($semi->bestOf)->toBe(5)
        ->and($quarter->bestOf)->toBe(3)
        ->and($final->home->type)->toBe('winner')
        ->and($final->home->sourceStage)->toBe('winners')
        ->and($final->home->sourceRound)->toBe(4)
        ->and($final->away->sourceMatch)->toBe(2);
});

it('keeps missing teams as empty seed sources for bye resolution', function () {
    $plan = (new SingleEliminationBuilder)->build(8, [10, 20, 30]);

    $firstRound = array_values(array_filter(
        $plan->matchups,
        fn ($matchup): bool => $matchup->roundNumber === 8,
    ));

    expect($firstRound[0]->home->teamId)->toBe(10)
        ->and($firstRound[0]->away->teamId)->toBeNull()
        ->and($firstRound[1]->home->teamId)->toBe(30)
        ->and($firstRound[1]->away->teamId)->toBeNull();
});

it('rejects invalid sizes and best-of values', function () {
    expect(fn () => (new SingleEliminationBuilder)->build(6, [1, 2, 3, 4, 5, 6]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new SingleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8], [0 => 4]))
        ->toThrow(InvalidArgumentException::class);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="builds an eight-team single-elimination plan"
```

- [ ] **Step 2: Implement the builder**

Create `src/app/Services/Bracket/SingleEliminationBuilder.php`:

```php
<?php

namespace App\Services\Bracket;

final class SingleEliminationBuilder
{
    /**
     * @param  list<int|null>  $orderedTeamIds  Seed order: index 0 is seed 1.
     * @param  array<int, int>  $bestOfByRound  Keys: 0 final, 1 semifinal, 2 quarterfinal.
     */
    public function build(int $size, array $orderedTeamIds, array $bestOfByRound = []): BracketPlan
    {
        if (! in_array($size, [2, 4, 8, 16, 32], true)) {
            throw new \InvalidArgumentException('Bracket size must be 2, 4, 8, 16, or 32.');
        }

        $seedOrder = SeedOrder::forSize($size);
        $rounds = (int) log($size, 2);
        $matchups = [];
        $previousRoundSize = null;

        for ($roundIndex = 0; $roundIndex < $rounds; $roundIndex++) {
            $roundSize = intdiv($size, 2 ** $roundIndex);
            $count = intdiv($roundSize, 2);
            $fromFinal = $rounds - $roundIndex - 1;
            $bestOf = $bestOfByRound[$fromFinal] ?? 1;

            for ($match = 1; $match <= $count; $match++) {
                if ($roundIndex === 0) {
                    $homeSeed = $seedOrder[($match - 1) * 2];
                    $awaySeed = $seedOrder[($match - 1) * 2 + 1];
                    $home = SlotSource::seed($orderedTeamIds[$homeSeed - 1] ?? null);
                    $away = SlotSource::seed($orderedTeamIds[$awaySeed - 1] ?? null);
                } else {
                    $home = SlotSource::winner('winners', (int) $previousRoundSize, $match * 2 - 1);
                    $away = SlotSource::winner('winners', (int) $previousRoundSize, $match * 2);
                }

                $matchups[] = new MatchupPlan('winners', $roundSize, $match, $home, $away, $bestOf);
            }

            $previousRoundSize = $roundSize;
        }

        return new BracketPlan($matchups);
    }
}
```

- [ ] **Step 3: Run the builder tests**

```powershell
cd src
php artisan test --filter="builds an eight-team single-elimination plan"
php artisan test --filter="routes winners forward and applies per-round best-of lengths"
php artisan test --filter="keeps missing teams as empty seed sources"
php artisan test --filter="rejects invalid sizes and best-of values"
```

- [ ] **Step 4: Commit**

```powershell
git add src/app/Services/Bracket/SingleEliminationBuilder.php src/tests/Unit/Services/Bracket/SingleEliminationBuilderTest.php
git commit -m "feat(bracket): add single-elimination plan builder"
```

---

## Task 4: Double-elimination plan builder

**Files:**
- Create: `src/app/Services/Bracket/DoubleEliminationBuilder.php`
- Create: `src/tests/Unit/Services/Bracket/DoubleEliminationBuilderTest.php`

**Interfaces:**
- Consumes: `SeedOrder`, `SlotSource`, `MatchupPlan`, `BracketPlan`.
- Produces: `build(int $size, array $orderedTeamIds, array $bestOfByRound = []): BracketPlan`.
- Winners rounds are numbered `1..k`; losers rounds are numbered `1..2(k-1)`; grand final and reset are round `1`.
- Totals including reset: 4 teams → 7, 8 → 15, 16 → 31, 32 → 63.

- [ ] **Step 1: Write the failing builder test**

Create `src/tests/Unit/Services/Bracket/DoubleEliminationBuilderTest.php`:

```php
<?php

use App\Services\Bracket\DoubleEliminationBuilder;
use App\Services\Bracket\MatchupPlan;

function deMatchup(array $matchups, string $stage, int $round, int $match): MatchupPlan
{
    $key = $stage.':'.$round.':'.$match;

    foreach ($matchups as $matchup) {
        if ($matchup->key() === $key) {
            return $matchup;
        }
    }

    throw new RuntimeException('Missing matchup '.$key);
}

it('builds the standard double-elimination shape for every supported size', function (int $size, int $total, int $winners, int $losers) {
    $plan = (new DoubleEliminationBuilder)->build($size, range(1, $size));

    $counts = collect($plan->matchups)->countBy('stage');

    expect($plan->matchups)->toHaveCount($total)
        ->and($counts->get('winners'))->toBe($winners)
        ->and($counts->get('losers'))->toBe($losers)
        ->and($counts->get('grand_final'))->toBe(1)
        ->and($counts->get('reset'))->toBe(1);
})->with([
    [4, 7, 3, 2],
    [8, 15, 7, 6],
    [16, 31, 15, 14],
    [32, 63, 31, 30],
]);

it('routes winners losers and losers-bracket winners correctly', function () {
    $plan = (new DoubleEliminationBuilder)->build(8, [1, 2, 3, 4, 5, 6, 7, 8]);
    $matchups = $plan->matchups;

    $lb1m1 = deMatchup($matchups, 'losers', 1, 1);
    expect($lb1m1->home->type)->toBe('loser')
        ->and($lb1m1->home->sourceStage)->toBe('winners')
        ->and($lb1m1->home->sourceRound)->toBe(1)
        ->and($lb1m1->home->sourceMatch)->toBe(1)
        ->and($lb1m1->away->sourceMatch)->toBe(2);

    $lb2m1 = deMatchup($matchups, 'losers', 2, 1);
    expect($lb2m1->home->type)->toBe('winner')
        ->and($lb2m1->home->sourceStage)->toBe('losers')
        ->and($lb2m1->home->sourceRound)->toBe(1)
        ->and($lb2m1->away->type)->toBe('loser')
        ->and($lb2m1->away->sourceStage)->toBe('winners')
        ->and($lb2m1->away->sourceRound)->toBe(2);

    $lb3m1 = deMatchup($matchups, 'losers', 3, 1);
    expect($lb3m1->home->type)->toBe('winner')
        ->and($lb3m1->home->sourceRound)->toBe(2)
        ->and($lb3m1->away->sourceRound)->toBe(2)
        ->and($lb3m1->away->sourceMatch)->toBe(2);

    $lb4m1 = deMatchup($matchups, 'losers', 4, 1);
    expect($lb4m1->home->sourceRound)->toBe(3)
        ->and($lb4m1->away->sourceStage)->toBe('winners')
        ->and($lb4m1->away->sourceRound)->toBe(3);

    $gf = deMatchup($matchups, 'grand_final', 1, 1);
    expect($gf->home->sourceStage)->toBe('winners')
        ->and($gf->home->sourceRound)->toBe(3)
        ->and($gf->away->sourceStage)->toBe('losers')
        ->and($gf->away->sourceRound)->toBe(4);

    $reset = deMatchup($matchups, 'reset', 1, 1);
    expect($reset->home->sourceStage)->toBe('winners')
        ->and($reset->away->sourceStage)->toBe('losers');
});

it('uses the configured best-of for winners rounds and the grand final', function () {
    $plan = (new DoubleEliminationBuilder)->build(8, range(1, 8), [
        0 => 5,
        1 => 3,
        2 => 3,
    ]);

    expect(deMatchup($plan->matchups, 'winners', 1, 1)->bestOf)->toBe(3)
        ->and(deMatchup($plan->matchups, 'winners', 2, 1)->bestOf)->toBe(3)
        ->and(deMatchup($plan->matchups, 'winners', 3, 1)->bestOf)->toBe(5)
        ->and(deMatchup($plan->matchups, 'grand_final', 1, 1)->bestOf)->toBe(5)
        ->and(deMatchup($plan->matchups, 'losers', 2, 1)->bestOf)->toBe(1);
});

it('omits the reset when the grand final is a series', function () {
    $plan = (new DoubleEliminationBuilder)->build(8, range(1, 8), [0 => 3]);

    expect(collect($plan->matchups)->where('stage', 'reset'))->toHaveCount(0);
});

it('rejects invalid sizes', function () {
    expect(fn () => (new DoubleEliminationBuilder)->build(2, [1, 2]))
        ->toThrow(InvalidArgumentException::class);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="builds the standard double-elimination shape"
```

- [ ] **Step 2: Implement the builder**

Create `src/app/Services/Bracket/DoubleEliminationBuilder.php`:

```php
<?php

namespace App\Services\Bracket;

final class DoubleEliminationBuilder
{
    /**
     * @param  list<int|null>  $orderedTeamIds  Seed order: index 0 is seed 1.
     * @param  array<int, int>  $bestOfByRound  Keys: 0 final, 1 semifinal, 2 quarterfinal.
     */
    public function build(int $size, array $orderedTeamIds, array $bestOfByRound = []): BracketPlan
    {
        if (! in_array($size, [4, 8, 16, 32], true)) {
            throw new \InvalidArgumentException('Double elimination requires 4, 8, 16, or 32 slots.');
        }

        $seedOrder = SeedOrder::forSize($size);
        $rounds = (int) log($size, 2);
        $matchups = [];

        // Winners bracket: round 1..k, round k is the winners final.
        for ($round = 1; $round <= $rounds; $round++) {
            $count = intdiv($size, 2 ** $round);
            $fromFinal = $rounds - $round;
            $bestOf = $bestOfByRound[$fromFinal] ?? 1;

            for ($match = 1; $match <= $count; $match++) {
                if ($round === 1) {
                    $homeSeed = $seedOrder[($match - 1) * 2];
                    $awaySeed = $seedOrder[($match - 1) * 2 + 1];
                    $home = SlotSource::seed($orderedTeamIds[$homeSeed - 1] ?? null);
                    $away = SlotSource::seed($orderedTeamIds[$awaySeed - 1] ?? null);
                } else {
                    $home = SlotSource::winner('winners', $round - 1, $match * 2 - 1);
                    $away = SlotSource::winner('winners', $round - 1, $match * 2);
                }

                $matchups[] = new MatchupPlan('winners', $round, $match, $home, $away, $bestOf);
            }
        }

        // Losers round 1 receives the losers of winners round 1, paired adjacent.
        $firstLosersCount = intdiv($size, 4);
        for ($match = 1; $match <= $firstLosersCount; $match++) {
            $matchups[] = new MatchupPlan(
                'losers',
                1,
                $match,
                SlotSource::loser('winners', 1, $match * 2 - 1),
                SlotSource::loser('winners', 1, $match * 2),
                1,
            );
        }

        // For each winners round r >= 2:
        //   drop round 2r-2: winners of the previous losers round vs losers of winners round r.
        //   minor round 2r-1: winners of the drop round pair adjacent.
        for ($round = 2; $round <= $rounds; $round++) {
            $dropRound = 2 * $round - 2;
            $count = intdiv($size, 2 ** $round);

            for ($match = 1; $match <= $count; $match++) {
                $matchups[] = new MatchupPlan(
                    'losers',
                    $dropRound,
                    $match,
                    SlotSource::winner('losers', $dropRound - 1, $match),
                    SlotSource::loser('winners', $round, $match),
                    1,
                );
            }

            if ($round < $rounds) {
                $minorRound = 2 * $round - 1;
                $minorCount = intdiv($size, 2 ** ($round + 1));

                for ($match = 1; $match <= $minorCount; $match++) {
                    $matchups[] = new MatchupPlan(
                        'losers',
                        $minorRound,
                        $match,
                        SlotSource::winner('losers', $dropRound, $match * 2 - 1),
                        SlotSource::winner('losers', $dropRound, $match * 2),
                        1,
                    );
                }
            }
        }

        $lastLosersRound = 2 * ($rounds - 1);
        $grandFinalBestOf = $bestOfByRound[0] ?? 1;

        $matchups[] = new MatchupPlan(
            'grand_final',
            1,
            1,
            SlotSource::winner('winners', $rounds, 1),
            SlotSource::winner('losers', $lastLosersRound, 1),
            $grandFinalBestOf,
        );

        if ($grandFinalBestOf === 1) {
            $matchups[] = new MatchupPlan(
                'reset',
                1,
                1,
                SlotSource::winner('winners', $rounds, 1),
                SlotSource::winner('losers', $lastLosersRound, 1),
                1,
            );
        }

        return new BracketPlan($matchups);
    }
}
```

- [ ] **Step 3: Run the builder tests**

```powershell
cd src
php artisan test --filter="builds the standard double-elimination shape"
php artisan test --filter="routes winners losers and losers-bracket winners correctly"
php artisan test --filter="uses the configured best-of for winners rounds"
php artisan test --filter="omits the reset when the grand final is a series"
```

- [ ] **Step 4: Commit**

```powershell
git add src/app/Services/Bracket/DoubleEliminationBuilder.php src/tests/Unit/Services/Bracket/DoubleEliminationBuilderTest.php
git commit -m "feat(bracket): add true double-elimination plan builder"
```

## Task 5: Round-robin plan builder

**Files:**
- Create: `src/app/Services/Bracket/RoundRobinBuilder.php`
- Create: `src/tests/Unit/Services/Bracket/RoundRobinBuilderTest.php`

**Interfaces:**
- Produces: `build(array $teamIds, int $legs = 1): BracketPlan`.
- `$teamIds` is the ordered approved team list.
- Matchdays are numbered continuously across legs. Even legs swap home and away.
- Byes are not match rows. `byes` is always 0 at the service layer.

- [ ] **Step 1: Write the failing builder test**

Create `src/tests/Unit/Services/Bracket/RoundRobinBuilderTest.php`:

```php
<?php

use App\Services\Bracket\RoundRobinBuilder;

it('builds a complete single round robin for an even field', function () {
    $plan = (new RoundRobinBuilder)->build([1, 2, 3, 4]);

    $pairs = collect($plan->matchups)
        ->map(fn ($matchup): string => implode('-', [
            $matchup->home->teamId,
            $matchup->away->teamId,
        ]))
        ->sort()
        ->values()
        ->all();

    expect($plan->matchups)->toHaveCount(6)
        ->and(collect($plan->matchups)->pluck('roundNumber')->unique()->values()->all())->toBe([1, 2, 3])
        ->and($pairs)->toBe(['1-2', '1-3', '1-4', '2-3', '2-4', '3-4']);
});

it('creates byes without phantom matches for an odd field', function () {
    $plan = (new RoundRobinBuilder)->build([1, 2, 3, 4, 5]);

    expect($plan->matchups)->toHaveCount(10)
        ->and(collect($plan->matchups)->pluck('roundNumber')->unique()->count())->toBe(5)
        ->and(collect($plan->matchups)->contains(
            fn ($matchup): bool => $matchup->home->teamId === null || $matchup->away->teamId === null,
        ))->toBeFalse();
});

it('supports multiple legs and swaps home and away on even legs', function () {
    $plan = (new RoundRobinBuilder)->build([1, 2, 3, 4], 2);

    expect($plan->matchups)->toHaveCount(12)
        ->and(collect($plan->matchups)->pluck('roundNumber')->unique()->count())->toBe(6);

    $legOne = collect($plan->matchups)->first(
        fn ($matchup): bool => $matchup->roundNumber === 1,
    );
    $legTwo = collect($plan->matchups)->first(
        fn ($matchup): bool => $matchup->roundNumber === 4,
    );

    expect($legOne->home->teamId)->toBe($legTwo->away->teamId)
        ->and($legOne->away->teamId)->toBe($legTwo->home->teamId);
});

it('builds the correct number of games for large odd fields and multiple legs', function () {
    expect((new RoundRobinBuilder)->build(range(1, 15))->matchups)->toHaveCount(105)
        ->and((new RoundRobinBuilder)->build(range(1, 16), 3)->matchups)->toHaveCount(360);
});

it('rejects invalid fields and leg counts', function () {
    expect(fn () => (new RoundRobinBuilder)->build([1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new RoundRobinBuilder)->build(range(1, 17)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new RoundRobinBuilder)->build([1, 2, 3], 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new RoundRobinBuilder)->build([1, 2, 3], 11))->toThrow(InvalidArgumentException::class);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="builds a complete single round robin for an even field"
```

- [ ] **Step 2: Implement the builder**

Create `src/app/Services/Bracket/RoundRobinBuilder.php`:

```php
<?php

namespace App\Services\Bracket;

final class RoundRobinBuilder
{
    /**
     * @param  list<int>  $teamIds
     */
    public function build(array $teamIds, int $legs = 1): BracketPlan
    {
        $teamCount = count($teamIds);

        if ($teamCount < 2 || $teamCount > 16) {
            throw new \InvalidArgumentException('Round robin requires 2 to 16 teams.');
        }

        if ($legs < 1 || $legs > 10) {
            throw new \InvalidArgumentException('Meetings per pair must be between 1 and 10.');
        }

        $rotation = array_values($teamIds);

        if ($teamCount % 2 !== 0) {
            $rotation[] = null;
        }

        $rotationCount = count($rotation);
        $baseRounds = $rotationCount - 1;
        $matchups = [];

        for ($leg = 1; $leg <= $legs; $leg++) {
            $rotation = array_values($teamIds);

            if ($teamCount % 2 !== 0) {
                $rotation[] = null;
            }

            for ($roundIndex = 0; $roundIndex < $baseRounds; $roundIndex++) {
                $matchday = ($leg - 1) * $baseRounds + $roundIndex + 1;
                $matchNumber = 0;

                for ($i = 0; $i < intdiv($rotationCount, 2); $i++) {
                    $home = $rotation[$i];
                    $away = $rotation[$rotationCount - 1 - $i];

                    if ($home === null || $away === null) {
                        continue;
                    }

                    if ($leg % 2 === 0) {
                        [$home, $away] = [$away, $home];
                    }

                    $matchNumber++;
                    $matchups[] = new MatchupPlan(
                        'round_robin',
                        $matchday,
                        $matchNumber,
                        SlotSource::seed($home),
                        SlotSource::seed($away),
                        1,
                    );
                }

                $last = array_pop($rotation);
                array_splice($rotation, 1, 0, [$last]);
            }
        }

        return new BracketPlan($matchups);
    }
}
```

- [ ] **Step 3: Run the builder tests**

```powershell
cd src
php artisan test --filter="builds a complete single round robin for an even field"
php artisan test --filter="creates byes without phantom matches for an odd field"
php artisan test --filter="supports multiple legs and swaps home and away"
php artisan test --filter="builds the correct number of games for large odd fields"
php artisan test --filter="rejects invalid fields and leg counts"
```

- [ ] **Step 4: Commit**

```powershell
git add src/app/Services/Bracket/RoundRobinBuilder.php src/tests/Unit/Services/Bracket/RoundRobinBuilderTest.php
git commit -m "feat(bracket): add accurate round-robin plan builder"
```

---

## Task 6: BracketService generation, plan persistence, and structural byes

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Modify: `src/tests/Unit/Services/BracketServiceTest.php`

**Interfaces:**
- `generate(Tournament $tournament, string $format = 'single-elimination'): array{matches: int, games: int, byes: int}`
- `updateSlots(Bracket $bracket, array $orderedTeamIds): array{matches: int, games: int, byes: int}`
- `resolveStructuralByes(Bracket $bracket): void`
- `propagateMatchResult(MatchModel $match, ?int $winnerId): void`
- The returned `matches` count is anchors (matchups), not series child games. `games` is every row, including conditional child games.

- [ ] **Step 1: Write failing generation tests**

Add to `src/tests/Unit/Services/BracketServiceTest.php`:

```php
it('generates a true 8-team double-elimination bracket', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $result = (new BracketService)->generate($tournament, 'double-elimination');

    $bracket = \App\Models\Bracket::whereIn(
        'division_id',
        $tournament->divisions()->pluck('id'),
    )->where('format', 'double_elim')->firstOrFail();

    $counts = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->selectRaw('stage, count(*) as total')
        ->groupBy('stage')
        ->pluck('total', 'stage');

    expect($result['matches'])->toBe(15)
        ->and($result['byes'])->toBe(0)
        ->and($counts->get('winners'))->toBe(7)
        ->and($counts->get('losers'))->toBe(6)
        ->and($counts->get('grand_final'))->toBe(1)
        ->and($counts->get('reset'))->toBe(1);
});

it('resolves byes and cancellations in a 5-team double-elimination bracket', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $division = $tournament->divisions()->firstOrFail();

    // Keep only five approved teams for this probe.
    $approved = \App\Models\Registration::where('tournament_id', $tournament->id)
        ->where('status', 'approved')
        ->orderBy('seed')
        ->get();

    $approved->slice(5)->each(fn ($registration) => $registration->update(['status' => 'pending']));

    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['slots' => 8]),
    ]);

    $result = (new BracketService)->generate($tournament, 'double-elimination');

    $bracket = \App\Models\Bracket::where('division_id', $division->id)
        ->where('format', 'double_elim')
        ->firstOrFail();

    expect($result['matches'])->toBe(15)
        ->and($result['byes'])->toBeGreaterThanOrEqual(4)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('status', 'cancelled')->count())->toBeGreaterThanOrEqual(1)
        ->and(MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->where('status', 'scheduled')
            ->whereNull('home_team_id')
            ->whereNull('away_team_id')
            ->count())->toBeGreaterThan(0);
});

it('respects configured slots and rejects approved teams above the limit', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['slots' => 4]),
    ]);

    expect(fn () => (new BracketService)->generate($tournament, 'double-elimination'))
        ->toThrow(InvalidArgumentException::class, 'Approved teams exceed the configured slots.');
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="generates a true 8-team double-elimination bracket"
```

- [ ] **Step 2: Replace the generation entry point**

In `BracketService`, add imports:

```php
use App\Models\MatchSlot;
use App\Services\Bracket\BracketPlan;
use App\Services\Bracket\DoubleEliminationBuilder;
use App\Services\Bracket\MatchupPlan;
use App\Services\Bracket\RoundRobinBuilder;
use App\Services\Bracket\SingleEliminationBuilder;
```

Replace `generate` with this shape:

```php
/**
 * @return array{matches: int, games: int, byes: int}
 */
public function generate(Tournament $tournament, string $format = 'single-elimination'): array
{
    if (! in_array($format, ['single-elimination', 'round-robin', 'double-elimination'], true)) {
        throw new \InvalidArgumentException('Invalid bracket format.');
    }

    $registrations = Registration::where('tournament_id', $tournament->id)
        ->where('status', 'approved')
        ->get();

    if ($registrations->count() < 2) {
        throw new \InvalidArgumentException('At least two approved teams are required.');
    }

    $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
    $slots = isset($rules['slots']) && is_numeric($rules['slots']) ? (int) $rules['slots'] : null;

    if ($slots !== null && $registrations->count() > $slots) {
        throw new \InvalidArgumentException('Approved teams exceed the configured slots.');
    }

    $division = $tournament->divisions()->first();

    if (! $division instanceof Division) {
        $division = Division::create([
            'tournament_id' => $tournament->id,
            'name' => 'Default Division',
        ]);
    }

    return DB::transaction(function () use ($tournament, $format, $registrations, $division, $rules, $slots): array {
        $this->deleteBracketData($tournament, $format);

        $ordered = $registrations
            ->sortBy(fn (Registration $registration): int => $registration->seed ?? 999)
            ->values()
            ->pluck('team_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $bracket = Bracket::create([
            'division_id' => $division->id,
            'name' => $tournament->name.' Bracket',
            'format' => match ($format) {
                'single-elimination' => 'single_elim',
                'double-elimination' => 'double_elim',
                default => 'round_robin',
            },
            'phase' => $format === 'round-robin' ? 'group' : 'main',
            'seeding_method' => 'manual',
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        $bestOf = [
            0 => $this->bestOfRule($rules, 'best_of_final'),
            1 => $this->bestOfRule($rules, 'best_of_semifinal'),
            2 => $this->bestOfRule($rules, 'best_of_quarterfinal'),
        ];

        $plan = match ($format) {
            'single-elimination' => (new SingleEliminationBuilder)->build(
                $this->bracketSize($slots, count($ordered), [2, 4, 8, 16, 32]),
                $ordered,
                $bestOf,
            ),
            'double-elimination' => (new DoubleEliminationBuilder)->build(
                $this->bracketSize($slots, count($ordered), [4, 8, 16, 32]),
                $ordered,
                $bestOf,
            ),
            default => (new RoundRobinBuilder)->build(
                $ordered,
                $this->roundRobinLegs($rules),
            ),
        };

        $result = $this->persistPlan($tournament, $bracket, $plan);

        app(AuditLogger::class)->log(
            'bracket.generate',
            'tournament',
            $tournament->id,
            null,
            [
                'teams' => $registrations->count(),
                'byes' => $result['byes'],
                'matches' => $result['matches'],
                'games' => $result['games'],
            ],
            $tournament->organization_id,
        );

        return $result;
    });
}
```

Add these helpers:

```php
protected function nextMatchId(): int
{
    if (DB::getDriverName() === 'pgsql') {
        return (int) DB::selectOne("select nextval('matches_id_seq') as v")->v;
    }

    return (int) (MatchModel::max('id') ?? 0) + 1;
}

/**
 * @param  list<int>  $allowed
 */
protected function bracketSize(?int $slots, int $teamCount, array $allowed): int
{
    if ($slots !== null) {
        if (! in_array($slots, $allowed, true)) {
            throw new \InvalidArgumentException('Bracket size must be '.implode(', ', $allowed).'.');
        }

        return $slots;
    }

    $size = 2;

    while ($size < $teamCount) {
        $size *= 2;
    }

    if (! in_array($size, $allowed, true)) {
        throw new \InvalidArgumentException('Bracket size must be '.implode(', ', $allowed).'.');
    }

    return $size;
}

/**
 * Infer the bracket size from the rows that are already persisted.
 *
 * Single elimination numbers rounds by bracket size (16, 8, 4, 2), while
 * double elimination numbers winners rounds 1..k. Counting the first winners
 * round works for both, and the max round number is the legacy fallback.
 */
protected function existingBracketSize(Bracket $bracket): int
{
    $firstRoundCount = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'winners')
        ->whereNull('series_id')
        ->where('round_number', 1)
        ->count();

    if ($firstRoundCount > 0) {
        return $firstRoundCount * 2;
    }

    return (int) MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'winners')
        ->whereNull('series_id')
        ->max('round_number');
}

/**
 * Keep the existing bracket size when editing slots so a 16-slot bracket does
 * not shrink to 8 slots just because only five teams are approved.
 *
 * Call this before deleting the old matches; it reads the persisted rows.
 *
 * @param  list<int>  $allowed
 */
protected function updateBracketSize(Bracket $bracket, int $teamCount, array $allowed): int
{
    $existing = $this->existingBracketSize($bracket);

    $size = in_array($existing, $allowed, true)
        ? $existing
        : $this->bracketSize(null, $teamCount, $allowed);

    if ($teamCount > $size) {
        throw new \InvalidArgumentException('Approved teams exceed the configured slots.');
    }

    return $size;
}

/**
 * @param  array<string, mixed>  $rules
 */
protected function bestOfRule(array $rules, string $key): int
{
    $value = $rules[$key] ?? 1;

    return is_numeric($value) && in_array((int) $value, [1, 3, 5, 7, 9], true)
        ? (int) $value
        : 1;
}

/**
 * @param  array<string, mixed>  $rules
 */
protected function roundRobinLegs(array $rules): int
{
    $value = $rules['round_robin_legs'] ?? 1;

    return is_numeric($value) && (int) $value >= 1 && (int) $value <= 10
        ? (int) $value
        : 1;
}
```

- [ ] **Step 3: Implement plan persistence and bye resolution**

Add `persistPlan`, `syncSeriesTeams`, `resolveStructuralByes`, `slotState`, `applyBye`, `cancelMatch`, `deleteBracketData`, and replace `propagateMatchResult`.

`persistPlan`:

```php
/**
 * @return array{matches: int, games: int, byes: int}
 */
protected function persistPlan(Tournament $tournament, Bracket $bracket, BracketPlan $plan): array
{
    $anchors = [];
    $games = 0;

    $makeMatch = function (array $attributes): MatchModel {
        $match = new MatchModel($attributes);
        $match->id = $this->nextMatchId();
        $match->save();

        return $match;
    };

    foreach ($plan->matchups as $matchup) {
        $anchor = $makeMatch([
            'bracket_id' => $bracket->id,
            'tournament_id' => $tournament->id,
            'stage' => $matchup->stage,
            'round_number' => $matchup->roundNumber,
            'match_number' => $matchup->matchNumber,
            'series_id' => null,
            'game_number' => 1,
            'best_of' => $matchup->bestOf,
            'series_home_wins' => 0,
            'series_away_wins' => 0,
            'series_winner_team_id' => null,
            'game_winner_team_id' => null,
            'home_team_id' => null,
            'away_team_id' => null,
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
            'status' => $matchup->stage === 'reset' ? 'conditional' : 'scheduled',
            'completed_at' => null,
            'forfeit_reason' => null,
        ]);

        $anchors[$matchup->key()] = $anchor;
        $games++;

        for ($gameNumber = 2; $gameNumber <= $matchup->bestOf; $gameNumber++) {
            $makeMatch([
                'bracket_id' => $bracket->id,
                'tournament_id' => $tournament->id,
                'stage' => $matchup->stage,
                'round_number' => $matchup->roundNumber,
                'match_number' => $matchup->matchNumber,
                'series_id' => $anchor->id,
                'game_number' => $gameNumber,
                'best_of' => $matchup->bestOf,
                'series_home_wins' => 0,
                'series_away_wins' => 0,
                'series_winner_team_id' => null,
                'game_winner_team_id' => null,
                'home_team_id' => null,
                'away_team_id' => null,
                'home_score' => null,
                'away_score' => null,
                'winner_team_id' => null,
                'status' => 'conditional',
                'completed_at' => null,
                'forfeit_reason' => null,
            ]);
            $games++;
        }
    }

    foreach ($plan->matchups as $matchup) {
        $anchor = $anchors[$matchup->key()];

        foreach (['home' => $matchup->home, 'away' => $matchup->away] as $slot => $source) {
            $sourceMatchId = null;
            $outcome = null;

            if ($source->type !== 'seed') {
                $sourceMatchId = $anchors[(string) $source->key()]->id;
                $outcome = $source->type;
            }

            BracketSlot::create([
                'bracket_id' => $bracket->id,
                'stage' => $matchup->stage,
                'round_number' => $matchup->roundNumber,
                'match_number' => $matchup->matchNumber,
                'slot' => $slot,
                'source_type' => $source->type,
                'source_match_id' => $sourceMatchId,
                'source_outcome' => $outcome,
                'team_id' => $source->type === 'seed' ? $source->teamId : null,
            ]);
        }

        if ($matchup->home->type === 'seed' && $matchup->home->teamId !== null) {
            $anchor->home_team_id = $matchup->home->teamId;
        }

        if ($matchup->away->type === 'seed' && $matchup->away->teamId !== null) {
            $anchor->away_team_id = $matchup->away->teamId;
        }

        if ($anchor->isDirty()) {
            $anchor->save();
        }

        $this->syncSeriesTeams($anchor);
    }

    $this->resolveStructuralByes($bracket);

    $anchorCount = MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->count();
    $byeCount = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('status', 'final')
        ->whereNull('home_score')
        ->whereNull('away_score')
        ->count();

    return [
        'matches' => $anchorCount,
        'games' => MatchModel::where('bracket_id', $bracket->id)->count(),
        'byes' => $byeCount,
    ];
}
```

`syncSeriesTeams`:

```php
public function syncSeriesTeams(MatchModel $anchor): void
{
    if ($anchor->best_of <= 1 || $anchor->series_id !== null) {
        return;
    }

    MatchModel::where('series_id', $anchor->id)
        ->whereIn('status', ['conditional', 'scheduled'])
        ->update([
            'home_team_id' => $anchor->home_team_id,
            'away_team_id' => $anchor->away_team_id,
        ]);
}
```

`resolveStructuralByes`:

```php
public function resolveStructuralByes(Bracket $bracket): void
{
    $anchorCount = MatchModel::where('bracket_id', $bracket->id)->whereNull('series_id')->count();
    $limit = max(20, $anchorCount * 3);

    for ($iteration = 0; $iteration < $limit; $iteration++) {
        $changed = false;

        $matches = MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->whereNotIn('status', ['final', 'forfeit', 'in_progress', 'cancelled', 'conditional'])
            ->with('slots.sourceMatch')
            ->get();

        foreach ($matches as $match) {
            $slots = $match->slots->keyBy('slot');
            $home = $slots->get('home');
            $away = $slots->get('away');

            if (! $home instanceof BracketSlot || ! $away instanceof BracketSlot) {
                continue;
            }

            $homeState = $this->slotState($home);
            $awayState = $this->slotState($away);

            if ($homeState === 'impossible' && $awayState === 'impossible') {
                $this->cancelMatch($match);
                $changed = true;
                continue;
            }

            if ($match->home_team_id !== null && $awayState === 'impossible') {
                $this->applyBye($match, (int) $match->home_team_id);
                $changed = true;
                continue;
            }

            if ($match->away_team_id !== null && $homeState === 'impossible') {
                $this->applyBye($match, (int) $match->away_team_id);
                $changed = true;
            }
        }

        if (! $changed) {
            break;
        }
    }
}

protected function slotState(BracketSlot $slot): string
{
    if ($slot->team_id !== null) {
        return 'known';
    }

    if ($slot->source_type === 'seed') {
        return 'impossible';
    }

    $source = $slot->sourceMatch;

    if (! $source instanceof MatchModel || $source->status === 'cancelled') {
        return 'impossible';
    }

    if (! in_array($source->status, ['final', 'forfeit'], true)) {
        return 'pending';
    }

    if ($slot->source_outcome === 'loser') {
        return $source->home_team_id !== null && $source->away_team_id !== null
            ? 'known'
            : 'impossible';
    }

    return $source->winner_team_id !== null ? 'known' : 'pending';
}
```

`applyBye` and `cancelMatch`:

```php
protected function applyBye(MatchModel $match, int $winnerId): void
{
    if ($match->best_of > 1) {
        MatchModel::where('series_id', $match->id)
            ->whereIn('status', ['conditional', 'scheduled'])
            ->update(['status' => 'cancelled']);
        $match->best_of = 1;
    }

    $match->home_score = null;
    $match->away_score = null;
    $match->winner_team_id = $winnerId;
    $match->series_winner_team_id = $winnerId;
    $match->game_winner_team_id = $winnerId;
    $match->series_home_wins = (int) $match->home_team_id === $winnerId ? 1 : 0;
    $match->series_away_wins = (int) $match->away_team_id === $winnerId ? 1 : 0;
    $match->status = 'final';
    $match->completed_at = null;
    $match->save();

    $this->propagateMatchResult($match, $winnerId);
}

protected function cancelMatch(MatchModel $match): void
{
    if ($match->best_of > 1) {
        MatchModel::where('series_id', $match->id)
            ->whereIn('status', ['conditional', 'scheduled'])
            ->update(['status' => 'cancelled']);
        $match->best_of = 1;
    }

    $match->home_team_id = null;
    $match->away_team_id = null;
    $match->winner_team_id = null;
    $match->series_winner_team_id = null;
    $match->game_winner_team_id = null;
    $match->status = 'cancelled';
    $match->save();
}
```

Replace `propagateMatchResult` with the stage-aware version:

```php
protected function propagateMatchResult(MatchModel $match, ?int $winnerId): void
{
    if ($match->series_id !== null) {
        return;
    }

    if ($match->best_of > 1 && $match->status === 'in_progress') {
        return;
    }

    $loserId = null;

    if ($match->home_team_id !== null && $match->away_team_id !== null && $winnerId !== null) {
        $loserId = (int) $match->home_team_id === $winnerId
            ? (int) $match->away_team_id
            : (int) $match->home_team_id;
    }

    $slots = BracketSlot::where('source_match_id', $match->id)->get();

    if ($slots->isEmpty()) {
        $this->propagateLegacySingleElimination($match, $winnerId);

        return;
    }

    foreach ($slots as $slot) {
        $value = $slot->source_outcome === 'loser' ? $loserId : $winnerId;

        $target = MatchModel::where('bracket_id', $slot->bracket_id)
            ->where('stage', $slot->stage)
            ->where('round_number', $slot->round_number)
            ->where('match_number', $slot->match_number)
            ->whereNull('series_id')
            ->first();

        if (! $target instanceof MatchModel) {
            continue;
        }

        if (in_array($target->status, ['final', 'forfeit', 'in_progress'], true)) {
            continue;
        }

        $slot->team_id = $value;
        $slot->save();

        $column = $slot->slot === 'home' ? 'home_team_id' : 'away_team_id';
        $target->{$column} = $value;
        $target->save();

        $this->syncSeriesTeams($target);
    }
}
```

Move the old fallback into `propagateLegacySingleElimination` unchanged.

`deleteBracketData`:

```php
protected function deleteBracketData(Tournament $tournament, string $format): void
{
    $divisionIds = $tournament->divisions()->pluck('id');

    if ($format === 'round-robin') {
        $playoffBrackets = Bracket::whereIn('division_id', $divisionIds)
            ->where('phase', 'playoffs')
            ->get();

        foreach ($playoffBrackets as $playoffBracket) {
            $hasResults = MatchModel::where('bracket_id', $playoffBracket->id)
                ->whereIn('status', ['final', 'forfeit', 'in_progress'])
                ->exists();

            if ($hasResults) {
                throw new \InvalidArgumentException('Playoffs already have results; the group stage cannot be regenerated.');
            }
        }

        $bracketIds = Bracket::whereIn('division_id', $divisionIds)->pluck('id');
        $this->deleteMatchesAndSlots($bracketIds);
        Bracket::whereIn('id', $bracketIds)->delete();

        return;
    }

    $bracketIds = Bracket::whereIn('division_id', $divisionIds)->pluck('id');
    $this->deleteMatchesAndSlots($bracketIds);
    Bracket::whereIn('id', $bracketIds)->delete();
}

/**
 * @param  Collection<int, int>  $bracketIds
 */
protected function deleteMatchesAndSlots(Collection $bracketIds): void
{
    $matchIds = MatchModel::whereIn('bracket_id', $bracketIds)->pluck('id');

    MatchSlot::whereIn('match_id', $matchIds)->delete();
    MatchModel::whereIn('bracket_id', $bracketIds)->delete();
    BracketSlot::whereIn('bracket_id', $bracketIds)->delete();
}
```

- [ ] **Step 4: Implement `updateSlots`**

This rebuilds the bracket from the new ordered team list. It is safer than patching first-round rows and leaving stale downstream byes.

```php
/**
 * @param  list<int|null>  $orderedTeamIds
 * @return array{matches: int, games: int, byes: int}
 */
public function updateSlots(Bracket $bracket, array $orderedTeamIds): array
{
    $tournament = $bracket->division?->tournament;

    if (! $tournament instanceof Tournament) {
        throw new \InvalidArgumentException('Bracket is not attached to a tournament.');
    }

    $hasResults = MatchModel::where('bracket_id', $bracket->id)
        ->whereIn('status', ['final', 'forfeit', 'in_progress'])
        ->exists();

    if ($hasResults) {
        throw new \InvalidArgumentException('Cannot reassign slots after results are recorded.');
    }

    return DB::transaction(function () use ($bracket, $tournament, $orderedTeamIds): array {
        $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
        $bestOf = [
            0 => $this->bestOfRule($rules, 'best_of_final'),
            1 => $this->bestOfRule($rules, 'best_of_semifinal'),
            2 => $this->bestOfRule($rules, 'best_of_quarterfinal'),
        ];
        $teamCount = count(array_filter($orderedTeamIds, fn ($id): bool => $id !== null));

        // Resolve the size before deleting rows: updateBracketSize reads the
        // persisted winners rounds to keep the configured bracket width.
        $size = match ($bracket->format) {
            'single_elim' => $this->updateBracketSize($bracket, $teamCount, [2, 4, 8, 16, 32]),
            'double_elim' => $this->updateBracketSize($bracket, $teamCount, [4, 8, 16, 32]),
            default => null,
        };

        $this->deleteMatchesAndSlots(collect([$bracket->id]));

        $plan = match ($bracket->format) {
            'single_elim' => (new SingleEliminationBuilder)->build(
                (int) $size,
                $orderedTeamIds,
                $bestOf,
            ),
            'double_elim' => (new DoubleEliminationBuilder)->build(
                (int) $size,
                $orderedTeamIds,
                $bestOf,
            ),
            default => (new RoundRobinBuilder)->build(
                array_values(array_filter($orderedTeamIds, fn ($id): bool => $id !== null)),
                $this->roundRobinLegs($rules),
            ),
        };

        return $this->persistPlan($tournament, $bracket, $plan);
    });
}
```

- [ ] **Step 5: Delete the superseded generators**

Remove `generateSingleElimination()`, `generateRoundRobin()`, `generateDoubleElimination()`, and `seedOrder()` from `BracketService`. The plan builders and `persistPlan()` replace them, and leaving them behind creates two conflicting sources of truth (and unused-method warnings in PHPStan).

Keep `propagateLegacySingleElimination()` only because legacy brackets without `BracketSlot` rows still need it.

- [ ] **Step 6: Run generation and existing tests**

```powershell
cd src
php artisan test --filter="generates a true 8-team double-elimination bracket"
php artisan test --filter="resolves byes and cancellations in a 5-team double-elimination bracket"
php artisan test --filter="respects configured slots and rejects approved teams above the limit"
php artisan test --filter="generates a 16-team single-elimination bracket with byes"
php artisan test --filter="generates an 8-team round-robin bracket"
```

The existing 8-team DE test still expects 14. Update it to 15 in this task:

```php
expect($result['matches'])->toBe(15);
```

- [ ] **Step 7: Commit**

```powershell
git add src/app/Services/BracketService.php src/tests/Unit/Services/BracketServiceTest.php
git commit -m "feat(bracket): persist stage-aware plans and resolve structural byes"
```

## Task 7: Single-game recording and stage-aware routing

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Modify: `src/tests/Unit/Services/BracketServiceTest.php`

**Interfaces:**
- `recordScore(MatchModel $match, int $score1, int $score2): void`
- `recordForfeit(MatchModel $match, int $forfeitSide): void`
- `correctScore(MatchModel $match, int $score1, int $score2, string $reason): void`
- `finalizeMatch(MatchModel $match): void`
- For a best-of-1 anchor, these preserve the existing behavior.
- For a series game, they update the individual game and then call `syncSeries()`.

- [ ] **Step 1: Write the failing DE routing test**

Add to `src/tests/Unit/Services/BracketServiceTest.php`:

```php
it('routes double-elimination winners and losers through the correct stages', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = \App\Models\Bracket::whereIn(
        'division_id',
        $tournament->divisions()->pluck('id'),
    )->where('format', 'double_elim')->firstOrFail();

    $wb1m1 = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'winners')->where('round_number', 1)->where('match_number', 1)
        ->firstOrFail();

    $service->recordScore($wb1m1, 80, 70);

    $wb2m1 = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'winners')->where('round_number', 2)->where('match_number', 1)
        ->firstOrFail();

    $lb1m1 = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'losers')->where('round_number', 1)->where('match_number', 1)
        ->firstOrFail();

    expect($wb2m1->home_team_id)->toBe($wb1m1->home_team_id)
        ->and($lb1m1->home_team_id)->toBe($wb1m1->away_team_id);

    $service->recordScore($wb2m1, 75, 70);

    $lb2m1 = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'losers')->where('round_number', 2)->where('match_number', 1)
        ->firstOrFail();

    expect($lb2m1->away_team_id)->toBe($wb2m1->away_team_id);

    $service->recordScore($lb1m1, 90, 85);

    expect($lb2m1->fresh()->home_team_id)->toBe($lb1m1->home_team_id);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="routes double-elimination winners and losers through the correct stages"
```

- [ ] **Step 2: Refactor the shared game-result path**

Add a protected method:

```php
/**
 * @return array{winnerId: int, loserId: int|null}
 */
protected function applyGameResult(MatchModel $game, int $score1, int $score2, string $status, ?string $forfeitReason = null): array
{
    $homeTeamId = $game->home_team_id !== null ? (int) $game->home_team_id : null;
    $awayTeamId = $game->away_team_id !== null ? (int) $game->away_team_id : null;

    if ($homeTeamId === null || $awayTeamId === null) {
        throw new \InvalidArgumentException('Both teams must be known before entering a score.');
    }

    $winnerId = $score1 > $score2 ? $homeTeamId : $awayTeamId;
    $loserId = $winnerId === $homeTeamId ? $awayTeamId : $homeTeamId;

    $game->home_score = $score1;
    $game->away_score = $score2;
    $game->game_winner_team_id = $winnerId;
    $game->winner_team_id = $game->series_id === null && $game->best_of > 1
        ? null
        : $winnerId;
    $game->status = $status;
    $game->forfeit_reason = $forfeitReason;
    $game->completed_at = now();
    $game->save();

    if ($game->series_id === null) {
        if ($game->best_of > 1) {
            $this->syncSeries($game);
        } else {
            $game->series_winner_team_id = $winnerId;
            $game->series_home_wins = $winnerId === $homeTeamId ? 1 : 0;
            $game->series_away_wins = $winnerId === $awayTeamId ? 1 : 0;
            $game->save();
            $this->propagateMatchResult($game, $winnerId);
        }
    } else {
        $anchor = MatchModel::find($game->series_id);

        if ($anchor instanceof MatchModel) {
            $this->syncSeries($anchor);
        }
    }

    return ['winnerId' => $winnerId, 'loserId' => $loserId];
}
```

Rewrite `recordScore` to use it:

```php
public function recordScore(MatchModel $match, int $score1, int $score2): void
{
    if ($score1 < 0 || $score2 < 0) {
        throw new \InvalidArgumentException('Scores must be zero or positive whole numbers.');
    }

    if ($score1 === $score2) {
        throw new \InvalidArgumentException('Elimination games cannot end in a tie.');
    }

    DB::transaction(function () use ($match, $score1, $score2): void {
        $match->refresh();

        if (in_array($match->status, ['final', 'forfeit', 'cancelled', 'conditional'], true)) {
            throw new \InvalidArgumentException('Use correction to change a recorded result.');
        }

        if ($match->series_id === null && $match->best_of > 1 && $match->status === 'in_progress') {
            throw new \InvalidArgumentException('Game 1 is already recorded. Score the active game.');
        }

        $this->applyGameResult($match, $score1, $score2, 'final');

        $this->writeAudit($match, 'score.save', [
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
        ], [
            'home_score' => $score1,
            'away_score' => $score2,
            'winner_team_id' => $match->game_winner_team_id,
        ]);
    });
}
```

Rewrite `recordForfeit` to use `applyGameResult` with `0` and `20`:

```php
public function recordForfeit(MatchModel $match, int $forfeitSide): void
{
    if (! in_array($forfeitSide, [1, 2], true)) {
        throw new \InvalidArgumentException('Choose which team forfeited.');
    }

    DB::transaction(function () use ($match, $forfeitSide): void {
        $match->refresh();

        if (in_array($match->status, ['final', 'forfeit', 'cancelled', 'conditional'], true)) {
            throw new \InvalidArgumentException('Only a scheduled game can be recorded as a forfeit.');
        }

        $score1 = $forfeitSide === 1 ? 0 : 20;
        $score2 = $forfeitSide === 2 ? 0 : 20;

        $this->applyGameResult($match, $score1, $score2, 'forfeit', 'Forfeit recorded');

        $this->writeAudit($match, 'score.forfeit', [
            'home_score' => null,
            'away_score' => null,
            'winner_team_id' => null,
        ], [
            'home_score' => $score1,
            'away_score' => $score2,
            'winner_team_id' => $match->game_winner_team_id,
            'forfeit_side' => $forfeitSide,
        ]);
    });
}
```

Rewrite `finalizeMatch` to delegate:

```php
public function finalizeMatch(MatchModel $match): void
{
    DB::transaction(function () use ($match): void {
        $match->refresh();

        if ($match->home_score === null || $match->away_score === null) {
            return;
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            return;
        }

        if (in_array($match->status, ['final', 'forfeit'], true)) {
            return;
        }

        $this->applyGameResult(
            $match,
            (int) $match->home_score,
            (int) $match->away_score,
            'final',
        );
    });
}
```

- [ ] **Step 3: Run the routing test and the existing score tests**

```powershell
cd src
php artisan test --filter="routes double-elimination winners and losers through the correct stages"
php artisan test --filter="records a score for a scheduled match"
php artisan test --filter="records a forfeit with a 20-0 score"
php artisan test --filter="finalizes a match with scores and propagates winner"
php artisan test --filter="corrects a score and propagates the changed winner"
```

- [ ] **Step 4: Commit**

```powershell
git add src/app/Services/BracketService.php src/tests/Unit/Services/BracketServiceTest.php
git commit -m "feat(bracket): route single-game results by stage"
```

---

## Task 8: Series lifecycle (create all games, activate as needed)

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Create: `src/tests/Unit/Services/Bracket/SeriesLifecycleTest.php`

**Interfaces:**
- `syncSeries(MatchModel $anchor): void`
- `seriesWinsNeeded(int $bestOf): int`
- `cancelRemainingSeriesGames(MatchModel $anchor): void`
- Series game statuses: `conditional`, `scheduled`, `final`, `forfeit`, `cancelled`.
- Anchor statuses: `scheduled`, `in_progress`, `final`, `forfeit`, `cancelled`.

- [ ] **Step 1: Write the failing series tests**

Create `src/tests/Unit/Services/Bracket/SeriesLifecycleTest.php`:

```php
<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function seriesTournament(array $rules = []): Tournament
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], $rules),
    ]);

    return $tournament->fresh();
}

function seriesBracket(Tournament $tournament): Bracket
{
    return Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'single_elim')
        ->firstOrFail();
}

it('creates every potential game up front and activates them as needed', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('stage', 'winners')
        ->where('round_number', 2)
        ->firstOrFail();

    expect($final->best_of)->toBe(3)
        ->and(MatchModel::where('series_id', $final->id)->count())->toBe(2)
        ->and(MatchModel::where('series_id', $final->id)->where('status', 'conditional')->count())->toBe(2);

    // Play the bracket until the final has two known teams.
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 1)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 2)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 3)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 8)->where('match_number', 4)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 4)->where('match_number', 1)->firstOrFail(),
        80,
        70,
    );
    $service->recordScore(
        MatchModel::where('bracket_id', $bracket->id)->where('round_number', 4)->where('match_number', 2)->firstOrFail(),
        80,
        70,
    );

    $final->refresh();
    expect($final->home_team_id)->not->toBeNull()
        ->and($final->away_team_id)->not->toBeNull();

    $service->recordScore($final, 81, 70);

    $final->refresh();
    expect($final->status)->toBe('in_progress')
        ->and($final->series_home_wins)->toBe(1)
        ->and($final->series_away_wins)->toBe(0)
        ->and($final->winner_team_id)->toBeNull();

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    expect($game2->status)->toBe('scheduled')
        ->and($game2->home_team_id)->toBe($final->home_team_id)
        ->and($game2->away_team_id)->toBe($final->away_team_id);

    $service->recordScore($game2, 90, 80);

    $final->refresh();
    expect($final->status)->toBe('final')
        ->and($final->series_home_wins)->toBe(2)
        ->and($final->winner_team_id)->toBe($final->home_team_id)
        ->and($final->series_winner_team_id)->toBe($final->home_team_id)
        ->and(MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail()->status)->toBe('cancelled');
});

it('supports a split series that needs the deciding game', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    // Force the final teams for a focused series test.
    $final->home_team_id = 1;
    $final->away_team_id = 2;
    $final->save();
    $service->syncSeriesTeams($final);

    $service->recordScore($final, 80, 70);
    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $service->recordScore($game2, 60, 70);

    $final->refresh();
    expect($final->status)->toBe('in_progress')
        ->and($final->series_home_wins)->toBe(1)
        ->and($final->series_away_wins)->toBe(1);

    $game3 = MatchModel::where('series_id', $final->id)->where('game_number', 3)->firstOrFail();
    expect($game3->status)->toBe('scheduled');

    $service->recordScore($game3, 99, 90);

    expect($final->fresh()->status)->toBe('final')
        ->and($final->fresh()->series_home_wins)->toBe(2);
});

it('supports best-of-five and best-of-seven series', function () {
    $tournament = seriesTournament(['best_of_final' => 5]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $final->home_team_id = 1;
    $final->away_team_id = 2;
    $final->save();
    $service->syncSeriesTeams($final);

    foreach ([1, 2, 3] as $gameNumber) {
        $game = $gameNumber === 1
            ? $final
            : MatchModel::where('series_id', $final->id)->where('game_number', $gameNumber)->firstOrFail();
        $service->recordScore($game, 80, 70);
    }

    expect($final->fresh()->status)->toBe('final')
        ->and($final->fresh()->series_home_wins)->toBe(3)
        ->and(MatchModel::where('series_id', $final->id)->where('game_number', 4)->firstOrFail()->status)->toBe('cancelled')
        ->and(MatchModel::where('series_id', $final->id)->where('game_number', 5)->firstOrFail()->status)->toBe('cancelled');
});

it('marks the series forfeit when the deciding game is a forfeit', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = seriesBracket($tournament);

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();
    $final->home_team_id = 1;
    $final->away_team_id = 2;
    $final->save();
    $service->syncSeriesTeams($final);

    $service->recordScore($final, 80, 70);
    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $service->recordForfeit($game2, 1);

    expect($final->fresh()->status)->toBe('forfeit')
        ->and($final->fresh()->series_home_wins)->toBe(1)
        ->and($final->fresh()->series_away_wins)->toBe(1)
        ->and($final->fresh()->winner_team_id)->toBe($final->away_team_id);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="creates every potential game up front"
```

- [ ] **Step 2: Implement `syncSeries` and helpers**

Add to `BracketService`:

```php
public function seriesWinsNeeded(int $bestOf): int
{
    return intdiv($bestOf, 2) + 1;
}

public function syncSeries(MatchModel $anchor): void
{
    if ($anchor->series_id !== null) {
        return;
    }

    if ($anchor->best_of <= 1) {
        $anchor->series_winner_team_id = $anchor->winner_team_id;
        $anchor->series_home_wins = $anchor->winner_team_id !== null && (int) $anchor->winner_team_id === (int) $anchor->home_team_id ? 1 : 0;
        $anchor->series_away_wins = $anchor->winner_team_id !== null && (int) $anchor->winner_team_id === (int) $anchor->away_team_id ? 1 : 0;
        $anchor->save();

        return;
    }

    $games = MatchModel::where('series_id', $anchor->id)
        ->orderBy('game_number')
        ->get();

    $allGames = $games->prepend($anchor);
    $homeWins = 0;
    $awayWins = 0;
    $lastDecidingGame = null;
    $needed = $this->seriesWinsNeeded($anchor->best_of);

    foreach ($allGames as $game) {
        if (! in_array($game->status, ['final', 'forfeit'], true) || $game->game_winner_team_id === null) {
            continue;
        }

        if ((int) $game->game_winner_team_id === (int) $anchor->home_team_id) {
            $homeWins++;
            if ($homeWins === $needed) {
                $lastDecidingGame = $game;
            }
        } elseif ((int) $game->game_winner_team_id === (int) $anchor->away_team_id) {
            $awayWins++;
            if ($awayWins === $needed) {
                $lastDecidingGame = $game;
            }
        }
    }

    $anchor->series_home_wins = $homeWins;
    $anchor->series_away_wins = $awayWins;

    if ($homeWins >= $needed || $awayWins >= $needed) {
        $winnerId = $homeWins >= $needed ? $anchor->home_team_id : $anchor->away_team_id;
        $anchor->winner_team_id = $winnerId;
        $anchor->series_winner_team_id = $winnerId;
        $anchor->status = $lastDecidingGame?->status === 'forfeit' ? 'forfeit' : 'final';
        $anchor->completed_at ??= now();
        $anchor->save();

        $this->cancelRemainingSeriesGames($anchor);
        $this->propagateMatchResult($anchor, $winnerId);

        return;
    }

    $hasCompletedGame = $allGames->contains(
        fn (MatchModel $game): bool => in_array($game->status, ['final', 'forfeit'], true),
    );

    $anchor->winner_team_id = null;
    $anchor->series_winner_team_id = null;
    $anchor->status = $hasCompletedGame ? 'in_progress' : 'scheduled';
    $anchor->completed_at = null;
    $anchor->save();

    $this->syncSeriesTeams($anchor);
    $this->activateNextSeriesGame($anchor);
}

public function cancelRemainingSeriesGames(MatchModel $anchor): void
{
    MatchModel::where('series_id', $anchor->id)
        ->where('status', 'conditional')
        ->update(['status' => 'cancelled']);
}

protected function activateNextSeriesGame(MatchModel $anchor): void
{
    $games = MatchModel::where('series_id', $anchor->id)
        ->orderBy('game_number')
        ->get();

    $next = $games->first(
        fn (MatchModel $game): bool => ! in_array($game->status, ['final', 'forfeit'], true),
    );

    if (! $next instanceof MatchModel) {
        return;
    }

    $next->status = 'scheduled';
    $next->home_team_id = $anchor->home_team_id;
    $next->away_team_id = $anchor->away_team_id;
    $next->save();
}
```

`syncSeriesTeams` is already added in Task 6. If a series becomes undecided after a correction, `activateNextSeriesGame` will re-activate the first non-completed child. The `cancelRemainingSeriesGames` only cancels conditional games; if a series becomes undecided, the cancelled child games need to be reactivated. `activateNextSeriesGame` sets the first non-final/cancelled? It filters `! final/forfeit`, which includes cancelled. It sets the first cancelled game to scheduled. But if there are multiple cancelled games, the later ones remain cancelled. When that game is played and the series is still undecided, `activateNextSeriesGame` picks the next non-final (cancelled) game and schedules it. That works. Good.

- [ ] **Step 3: Add the `bestOf` mapping test for DE grand final**

Add to `SeriesLifecycleTest.php`:

```php
it('does not create a reset when the double-elimination grand final is a series', function () {
    $tournament = seriesTournament(['best_of_final' => 3]);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    expect(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'reset')->count())->toBe(0)
        ->and(MatchModel::where('bracket_id', $bracket->id)->where('stage', 'grand_final')->firstOrFail()->best_of)->toBe(3);
});
```

- [ ] **Step 4: Run the series tests**

```powershell
cd src
php artisan test --filter="creates every potential game up front"
php artisan test --filter="supports a split series"
php artisan test --filter="supports best-of-five"
php artisan test --filter="marks the series forfeit"
php artisan test --filter="does not create a reset"
```

- [ ] **Step 5: Commit**

```powershell
git add src/app/Services/BracketService.php src/tests/Unit/Services/Bracket/SeriesLifecycleTest.php
git commit -m "feat(bracket): add best-of series lifecycle"
```

## Task 9: Corrections, grand-final reset, and champion resolution

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Create: `src/tests/Unit/Services/Bracket/GrandFinalResetTest.php`

**Interfaces:**
- `correctScore(...)` must clear downstream auto-resolved matches, reject a change when a downstream matchup has a real result, and re-evaluate the reset.
- `syncGrandFinalReset(MatchModel $grandFinal): void`
- `champion(Tournament $tournament): ?Team`
- `downstreamHasResults(MatchModel $anchor): bool`
- `clearDownstream(MatchModel $source): void`

- [ ] **Step 1: Write the failing reset/champion tests**

Create `src/tests/Unit/Services/Bracket/GrandFinalResetTest.php`:

```php
<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function deTournament(): Tournament
{
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'best_of_final' => 1,
            'best_of_semifinal' => 1,
            'best_of_quarterfinal' => 1,
        ]),
    ]);

    return $tournament->fresh();
}

function deBracket(Tournament $tournament): Bracket
{
    return Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();
}

function forceGrandFinalTeams(Bracket $bracket): array
{
    $grandFinal = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'grand_final')
        ->firstOrFail();
    $reset = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'reset')
        ->firstOrFail();

    $grandFinal->home_team_id = 1;
    $grandFinal->away_team_id = 2;
    $grandFinal->status = 'scheduled';
    $grandFinal->save();

    $reset->home_team_id = 1;
    $reset->away_team_id = 2;
    $reset->status = 'conditional';
    $reset->save();

    return [$grandFinal, $reset];
}

it('crowns the winners-bracket champion when it wins the first grand final', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 80, 70);

    expect($grandFinal->fresh()->status)->toBe('final')
        ->and($reset->fresh()->status)->toBe('cancelled')
        ->and($service->champion($tournament)?->id)->toBe(1);
});

it('requires the reset when the losers-bracket champion wins the first grand final', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 70, 80);

    expect($grandFinal->fresh()->status)->toBe('final')
        ->and($reset->fresh()->status)->toBe('scheduled')
        ->and($reset->fresh()->home_team_id)->toBe(1)
        ->and($reset->fresh()->away_team_id)->toBe(2)
        ->and($service->champion($tournament))->toBeNull();

    $service->recordScore($reset->fresh(), 85, 80);

    expect($reset->fresh()->status)->toBe('final')
        ->and($service->champion($tournament)?->id)->toBe(1);
});

it('re-evaluates the reset when the first grand final is corrected', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 70, 80);
    expect($reset->fresh()->status)->toBe('scheduled');

    $service->correctScore($grandFinal, 80, 70, 'Clock review');

    expect($grandFinal->fresh()->status)->toBe('final')
        ->and($reset->fresh()->status)->toBe('cancelled')
        ->and($service->champion($tournament)?->id)->toBe(1);
});

it('rejects correcting the first grand final after the reset has been played', function () {
    $tournament = deTournament();
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');
    [$grandFinal, $reset] = forceGrandFinalTeams(deBracket($tournament));

    $service->recordScore($grandFinal, 70, 80);
    $service->recordScore($reset->fresh(), 85, 80);

    expect(fn () => $service->correctScore($grandFinal, 80, 70, 'Clock review'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects correcting a series winner when a downstream matchup already has a result', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');
    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'single_elim')
        ->firstOrFail();

    $semi = MatchModel::where('bracket_id', $bracket->id)
        ->where('round_number', 4)
        ->where('match_number', 1)
        ->firstOrFail();
    $semi->home_team_id = 1;
    $semi->away_team_id = 2;
    $semi->save();
    $service->syncSeriesTeams($semi);

    $service->recordScore($semi, 80, 70);
    $service->recordScore($semi, 80, 70); // game 2, series 2-0 decided

    $final = MatchModel::where('bracket_id', $bracket->id)
        ->where('round_number', 2)
        ->firstOrFail();
    $final->home_team_id = 1;
    $final->away_team_id = 3;
    $final->save();

    expect(fn () => $service->correctScore($semi, 70, 80, 'Clock review'))
        ->toThrow(InvalidArgumentException::class);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="crowns the winners-bracket champion"
```

- [ ] **Step 2: Implement `syncGrandFinalReset` and `champion`**

Add to `BracketService`:

```php
public function syncGrandFinalReset(MatchModel $grandFinal): void
{
    if ($grandFinal->stage !== 'grand_final' || $grandFinal->best_of > 1) {
        return;
    }

    $reset = MatchModel::where('bracket_id', $grandFinal->bracket_id)
        ->where('stage', 'reset')
        ->first();

    if (! $reset instanceof MatchModel) {
        return;
    }

    if (! in_array($grandFinal->status, ['final', 'forfeit'], true)) {
        $reset->status = 'conditional';
        $reset->home_team_id = $grandFinal->home_team_id;
        $reset->away_team_id = $grandFinal->away_team_id;
        $reset->save();

        return;
    }

    $winnersFinal = MatchModel::where('bracket_id', $grandFinal->bracket_id)
        ->where('stage', 'winners')
        ->whereNull('series_id')
        ->orderByDesc('round_number')
        ->first();

    $winnersChampionId = $winnersFinal?->winner_team_id;

    if ($winnersChampionId !== null && (int) $grandFinal->winner_team_id === (int) $winnersChampionId) {
        if (! in_array($reset->status, ['final', 'forfeit'], true)) {
            $reset->status = 'cancelled';
            $reset->save();
        }

        return;
    }

    if (in_array($reset->status, ['conditional', 'cancelled'], true)) {
        $reset->status = 'scheduled';
        $reset->home_team_id = $grandFinal->home_team_id;
        $reset->away_team_id = $grandFinal->away_team_id;
        $reset->save();
    }
}

public function champion(Tournament $tournament): ?Team
{
    $format = $tournament->format();
    $divisionIds = $tournament->divisions()->pluck('id');

    if ($format === 'round-robin') {
        $playoff = Bracket::whereIn('division_id', $divisionIds)
            ->where('phase', 'playoffs')
            ->first();

        return $playoff instanceof Bracket
            ? $this->singleEliminationChampion($playoff)
            : null;
    }

    $bracket = Bracket::whereIn('division_id', $divisionIds)
        ->where('phase', 'main')
        ->first();

    if (! $bracket instanceof Bracket) {
        return null;
    }

    return $format === 'double-elimination'
        ? $this->doubleEliminationChampion($bracket)
        : $this->singleEliminationChampion($bracket);
}

protected function singleEliminationChampion(Bracket $bracket): ?Team
{
    $final = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->where('stage', 'winners')
        ->orderByDesc('round_number')
        ->first();

    if (! $final instanceof MatchModel || ! in_array($final->status, ['final', 'forfeit'], true)) {
        return null;
    }

    return $final->winnerTeam instanceof Team ? $final->winnerTeam : null;
}

protected function doubleEliminationChampion(Bracket $bracket): ?Team
{
    $grandFinal = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'grand_final')
        ->first();

    if (! $grandFinal instanceof MatchModel || ! in_array($grandFinal->status, ['final', 'forfeit'], true)) {
        return null;
    }

    if ($grandFinal->best_of > 1) {
        return $grandFinal->winnerTeam instanceof Team ? $grandFinal->winnerTeam : null;
    }

    $reset = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'reset')
        ->first();

    if ($reset instanceof MatchModel && in_array($reset->status, ['final', 'forfeit'], true)) {
        return $reset->winnerTeam instanceof Team ? $reset->winnerTeam : null;
    }

    $winnersFinal = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'winners')
        ->whereNull('series_id')
        ->orderByDesc('round_number')
        ->first();

    if ($winnersFinal instanceof MatchModel
        && $winnersFinal->winner_team_id !== null
        && (int) $grandFinal->winner_team_id === (int) $winnersFinal->winner_team_id) {
        return $grandFinal->winnerTeam instanceof Team ? $grandFinal->winnerTeam : null;
    }

    return null;
}
```

- [ ] **Step 3: Implement downstream checks and clearing**

```php
protected function downstreamHasResults(MatchModel $anchor): bool
{
    $slots = BracketSlot::where('source_match_id', $anchor->id)->get();

    foreach ($slots as $slot) {
        if ($slot->stage === 'reset') {
            continue;
        }

        $target = MatchModel::where('bracket_id', $slot->bracket_id)
            ->where('stage', $slot->stage)
            ->where('round_number', $slot->round_number)
            ->where('match_number', $slot->match_number)
            ->whereNull('series_id')
            ->first();

        if (! $target instanceof MatchModel) {
            continue;
        }

        if ($target->status === 'final' && $target->isByeGame()) {
            continue;
        }

        if (in_array($target->status, ['final', 'forfeit', 'in_progress'], true)) {
            return true;
        }
    }

    if ($anchor->stage === 'grand_final') {
        $reset = MatchModel::where('bracket_id', $anchor->bracket_id)
            ->where('stage', 'reset')
            ->first();

        if ($reset instanceof MatchModel && in_array($reset->status, ['final', 'forfeit', 'in_progress'], true)) {
            return true;
        }
    }

    return false;
}

protected function clearDownstream(MatchModel $source): void
{
    $slots = BracketSlot::where('source_match_id', $source->id)->get();

    foreach ($slots as $slot) {
        if ($slot->stage === 'reset') {
            continue;
        }

        $target = MatchModel::where('bracket_id', $slot->bracket_id)
            ->where('stage', $slot->stage)
            ->where('round_number', $slot->round_number)
            ->where('match_number', $slot->match_number)
            ->whereNull('series_id')
            ->first();

        if (! $target instanceof MatchModel) {
            continue;
        }

        if (in_array($target->status, ['final', 'forfeit', 'in_progress'], true) && ! $target->isByeGame()) {
            throw new \InvalidArgumentException('Cannot change this result because a later matchup already has a result.');
        }

        $slot->team_id = null;
        $slot->save();

        $column = $slot->slot === 'home' ? 'home_team_id' : 'away_team_id';
        $target->{$column} = null;

        if (($target->status === 'final' && $target->isByeGame()) || $target->status === 'cancelled') {
            $target->status = 'scheduled';
            $target->winner_team_id = null;
            $target->series_winner_team_id = null;
            $target->game_winner_team_id = null;
            $target->save();
            $this->clearDownstream($target);
        } else {
            $target->save();
        }

        $this->syncSeriesTeams($target);
    }
}
```

- [ ] **Step 4: Rewrite `correctScore`**

Use the following shape:

```php
public function correctScore(MatchModel $match, int $score1, int $score2, string $reason): void
{
    $reason = trim($reason);

    if ($reason === '') {
        throw new \InvalidArgumentException('Correction reason is required.');
    }

    if ($score1 < 0 || $score2 < 0) {
        throw new \InvalidArgumentException('Scores must be zero or positive whole numbers.');
    }

    if ($score1 === $score2) {
        throw new \InvalidArgumentException('Elimination games cannot end in a tie.');
    }

    DB::transaction(function () use ($match, $score1, $score2, $reason): void {
        $match->refresh();

        if (! in_array($match->status, ['final', 'forfeit'], true)) {
            throw new \InvalidArgumentException('Only a recorded result can be corrected.');
        }

        $anchor = $match->series_id !== null
            ? MatchModel::find($match->series_id)
            : $match;

        if (! $anchor instanceof MatchModel) {
            throw new \InvalidArgumentException('Series anchor not found.');
        }

        $previousHomeScore = $match->home_score;
        $previousAwayScore = $match->away_score;
        $previousWinnerId = $match->winner_team_id;
        $previousGameWinnerId = $match->game_winner_team_id;

        $newGameWinnerId = $score1 > $score2
            ? (int) $match->home_team_id
            : (int) $match->away_team_id;

        $winnerChanged = (int) $previousGameWinnerId !== $newGameWinnerId;

        if ($winnerChanged && $this->downstreamHasResults($anchor)) {
            throw new \InvalidArgumentException('Cannot change this result because a later matchup already has a result.');
        }

        if ($winnerChanged) {
            $this->clearDownstream($anchor);
        }

        $match->home_score = $score1;
        $match->away_score = $score2;
        $match->game_winner_team_id = $newGameWinnerId;
        $match->winner_team_id = $match->series_id === null && $match->best_of > 1
            ? null
            : $newGameWinnerId;
        $match->status = 'final';
        $match->forfeit_reason = null;

        if ($match->completed_at === null) {
            $match->completed_at = now();
        }

        $match->save();

        if ($anchor->id === $match->id && $anchor->best_of <= 1) {
            $anchor->series_winner_team_id = $newGameWinnerId;
            $anchor->series_home_wins = $newGameWinnerId === (int) $anchor->home_team_id ? 1 : 0;
            $anchor->series_away_wins = $newGameWinnerId === (int) $anchor->away_team_id ? 1 : 0;
            $anchor->save();

            if ($winnerChanged) {
                $this->propagateMatchResult($anchor, $newGameWinnerId);
            }
        } else {
            $this->syncSeries($anchor);
        }

        $this->syncGrandFinalReset($anchor);

        $bracket = $anchor->bracket;

        if ($bracket instanceof Bracket) {
            $this->resolveStructuralByes($bracket);
        }

        $this->writeAudit($match, 'score.correct', [
            'home_score' => $previousHomeScore,
            'away_score' => $previousAwayScore,
            'winner_team_id' => $previousWinnerId,
        ], [
            'home_score' => $score1,
            'away_score' => $score2,
            'winner_team_id' => $newGameWinnerId,
            'reason' => $reason,
        ]);
    });
}
```

- [ ] **Step 5: Call `syncGrandFinalReset` after normal GF results**

In `applyGameResult`, after the best-of-1 propagation and after `syncSeries`, add:

```php
if ($game->series_id === null && $game->stage === 'grand_final') {
    $this->syncGrandFinalReset($game);
}
```

For a GF series, `syncSeries` may set the anchor final; `syncGrandFinalReset` returns early because `best_of > 1`.

- [ ] **Step 6: Run the reset tests**

```powershell
cd src
php artisan test --filter="crowns the winners-bracket champion"
php artisan test --filter="requires the reset"
php artisan test --filter="re-evaluates the reset"
php artisan test --filter="rejects correcting the first grand final"
php artisan test --filter="rejects correcting a series winner"
```

- [ ] **Step 7: Commit**

```powershell
git add src/app/Services/BracketService.php src/tests/Unit/Services/Bracket/GrandFinalResetTest.php
git commit -m "feat(bracket): handle grand-final reset and champion resolution"
```

---

## Task 10: Full double-elimination play-through acceptance tests

**Files:**
- Create: `src/tests/Unit/Services/Bracket/DoubleEliminationPlayThroughTest.php`
- Modify: `src/tests/Unit/Services/BracketServiceTest.php` (only if a helper is shared)

**Interfaces:**
- Consumes: `BracketService::generate`, `recordScore`, `champion`.
- Produces: proof that every generated DE bracket can be played to completion.

- [ ] **Step 1: Write the play-through helper and tests**

Create `src/tests/Unit/Services/Bracket/DoubleEliminationPlayThroughTest.php`:

```php
<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

/**
 * Play every playable game, home team wins unless $grandFinalAwayWins is true.
 */
function playOutBracket(BracketService $service, Bracket $bracket, bool $grandFinalAwayWins = false): void
{
    $guard = 0;

    while ($guard++ < 500) {
        $anchor = MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('id')
            ->first();

        if ($anchor instanceof MatchModel) {
            $awayWins = $grandFinalAwayWins && $anchor->stage === 'grand_final';
            $service->recordScore($anchor, $awayWins ? 70 : 80, $awayWins ? 80 : 70);
            continue;
        }

        $game = MatchModel::where('bracket_id', $bracket->id)
            ->whereNotNull('series_id')
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('id')
            ->first();

        if (! $game instanceof MatchModel) {
            break;
        }

        $service->recordScore($game, 80, 70);
    }
}

function makeTournamentWithTeams(int $teamCount, array $rules = []): Tournament
{
    $base = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $tournament = $base->replicate();
    $tournament->name = 'Play-through '.$teamCount.' '.uniqid();
    $tournament->slug = 'play-through-'.$teamCount.'-'.uniqid();
    $tournament->rules_json = array_merge($base->rules_json ?? [], $rules, [
        'slots' => $teamCount,
        'format' => 'double-elimination',
    ]);
    $tournament->save();

    $division = $tournament->divisions()->create([
        'name' => 'Open',
        'max_teams' => $teamCount,
    ]);

    for ($i = 1; $i <= $teamCount; $i++) {
        $team = Team::create([
            'organization_id' => $tournament->organization_id,
            'name' => 'Team '.$i.' '.uniqid(),
            'city' => 'City',
        ]);

        Registration::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed' => $i,
        ]);
    }

    return $tournament->fresh();
}

it('plays a full double-elimination bracket to one champion', function (int $teamCount) {
    $tournament = makeTournamentWithTeams($teamCount);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    playOutBracket($service, $bracket);

    $champion = $service->champion($tournament);
    expect($champion)->not->toBeNull();

    $losses = [];
    $matches = MatchModel::where('bracket_id', $bracket->id)
        ->whereNull('series_id')
        ->whereIn('status', ['final', 'forfeit'])
        ->get();

    foreach ($matches as $match) {
        if ($match->home_team_id === null || $match->away_team_id === null || $match->isByeGame()) {
            continue;
        }

        $loserId = (int) $match->winner_team_id === (int) $match->home_team_id
            ? (int) $match->away_team_id
            : (int) $match->home_team_id;
        $losses[$loserId] = ($losses[$loserId] ?? 0) + 1;
    }

    foreach ($losses as $teamId => $count) {
        if ($teamId === $champion->id) {
            expect($count)->toBeLessThanOrEqual(1);
        } else {
            expect($count)->toBe(2);
        }
    }
})->with([4, 8, 16, 32]);

it('plays through bye-heavy fields without leaving a matchup stuck', function (int $teamCount) {
    $tournament = makeTournamentWithTeams($teamCount, ['slots' => 16]);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    playOutBracket($service, $bracket);

    expect($service->champion($tournament))->not->toBeNull()
        ->and(MatchModel::where('bracket_id', $bracket->id)
            ->whereNull('series_id')
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->count())->toBe(0);
})->with([5, 6, 7, 9, 15]);

it('plays the reset when the losers-bracket champion wins the first grand final', function () {
    $tournament = makeTournamentWithTeams(8);
    $service = new BracketService;
    $service->generate($tournament, 'double-elimination');

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('format', 'double_elim')
        ->firstOrFail();

    playOutBracket($service, $bracket, grandFinalAwayWins: true);

    $reset = MatchModel::where('bracket_id', $bracket->id)
        ->where('stage', 'reset')
        ->firstOrFail();

    expect($reset->status)->toBe('final')
        ->and($service->champion($tournament))->not->toBeNull();
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="plays a full double-elimination bracket to one champion"
```

- [ ] **Step 2: Fix whatever the play-through exposes**

Expected failure modes and fixes:

- A scheduled matchup has two known teams but no result is propagated because `resolveStructuralByes` was not called after a result. Call `resolveStructuralByes($bracket)` at the end of `applyGameResult` and after `syncSeries` decides a series.
- A structural bye with a TBD team is never finalized. The resolver in Task 6 handles it once the source match completes; ensure it runs after every result.
- A losers-bracket round references a round that does not exist for small sizes. The builder tests already cover 4/8/16/32; re-check the drop/minor round formula for size 4:
  - `rounds = 2`; losers R1 count 1; drop r=2 → R2 count 1; no minor; last losers round 2. GF sources WB final (round 2) and LB R2. Correct.
- A double-elimination bracket with 32 teams and full field has 63 anchors; the simulation guard of 500 is enough.

Add the resolver call in `applyGameResult`:

```php
$bracket = $game->bracket;

if ($bracket instanceof Bracket) {
    $this->resolveStructuralByes($bracket);
}
```

For a series, call it after `syncSeries` returns, not inside the loop, to avoid excessive queries. For a best-of-1 anchor, call after propagation.

- [ ] **Step 3: Run all play-through tests**

```powershell
cd src
php artisan test --filter="plays a full double-elimination bracket to one champion"
php artisan test --filter="plays through bye-heavy fields"
php artisan test --filter="plays the reset when the losers-bracket champion"
```

- [ ] **Step 4: Commit**

```powershell
git add src/tests/Unit/Services/Bracket/DoubleEliminationPlayThroughTest.php src/app/Services/BracketService.php
git commit -m "test(bracket): prove double-elimination play-through for all sizes"
```

## Task 11: Standings head-to-head tiebreak and playoff cut

**Files:**
- Modify: `src/app/Services/StandingsService.php`
- Modify: `src/tests/Unit/Services/StandingsServiceTest.php`

**Interfaces:**
- `compute(Tournament|Division $target): Collection` keeps its row shape.
- Ranking order: wins, head-to-head wins among the tied group, point differential, points scored, seed.
- `playoffCut()` returns `rules_json.playoff_teams` when it is a positive allowed value, otherwise the existing top-half behavior.
- Round-robin group standings ignore `phase = playoffs` matches and any non-`round_robin` stage.

- [ ] **Step 1: Write the failing head-to-head test**

Append to `src/tests/Unit/Services/StandingsServiceTest.php`:

```php
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;

function standingsTournament(array $rules = []): array
{
    $tournament = Tournament::factory()->create([
        'rules_json' => array_merge(['format' => 'round-robin', 'slots' => 4], $rules),
    ]);
    $division = $tournament->divisions()->create([
        'name' => 'Open',
        'max_teams' => 4,
    ]);

    $teams = collect(range(1, 4))->map(function (int $seed) use ($tournament, $division): Team {
        $team = Team::factory()->create([
            'organization_id' => $tournament->organization_id,
            'name' => 'Standings Team '.$seed,
        ]);

        Registration::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed' => $seed,
        ]);

        return $team;
    });

    $bracket = $tournament->divisions()->first()->brackets()->create([
        'name' => 'Group',
        'format' => 'round_robin',
        'phase' => 'group',
        'seeding_method' => 'manual',
        'status' => 'generated',
        'generated_at' => now(),
    ]);

    return [$tournament, $division, $teams, $bracket];
}

it('ranks tied teams by head-to-head before point differential', function () {
    [$tournament, , $teams, $bracket] = standingsTournament();

    [$a, $b, $c, $d] = $teams->all();

    $makeMatch = function (Team $home, Team $away, int $homeScore, int $awayScore) use ($tournament, $bracket): void {
        MatchModel::factory()->create([
            'bracket_id' => $bracket->id,
            'tournament_id' => $tournament->id,
            'stage' => 'round_robin',
            'round_number' => 1,
            'match_number' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'winner_team_id' => $homeScore > $awayScore ? $home->id : $away->id,
            'game_winner_team_id' => $homeScore > $awayScore ? $home->id : $away->id,
            'status' => 'final',
        ]);
    };

    // A is 2-0. B and C are both 1-1, but C beat B head-to-head.
    // B has the better point differential (+50 vs -10).
    $makeMatch($a, $b, 60, 50);
    $makeMatch($a, $c, 60, 40);
    $makeMatch($b, $d, 100, 40);
    $makeMatch($c, $b, 60, 50);
    $makeMatch($c, $d, 50, 40);

    $rows = (new StandingsService)->compute($tournament)->keyBy('team_id');

    expect($rows[$a->id]['rank'])->toBe(1)
        ->and($rows[$c->id]['rank'])->toBe(2)
        ->and($rows[$b->id]['rank'])->toBe(3)
        ->and($rows[$d->id]['rank'])->toBe(4);
});

it('uses playoff teams for the qualification cut', function () {
    [$tournament] = standingsTournament(['playoff_teams' => 2]);

    $rows = (new StandingsService)->compute($tournament);

    expect($rows->where('qualified', true))->toHaveCount(2)
        ->and($rows->first()['qualified'])->toBeTrue();
});

it('excludes playoff games from round-robin group standings', function () {
    [$tournament, , $teams, $bracket] = standingsTournament();

    [$a, $b] = $teams->all();

    MatchModel::factory()->create([
        'bracket_id' => $bracket->id,
        'tournament_id' => $tournament->id,
        'stage' => 'round_robin',
        'round_number' => 1,
        'match_number' => 1,
        'home_team_id' => $a->id,
        'away_team_id' => $b->id,
        'home_score' => 60,
        'away_score' => 50,
        'winner_team_id' => $a->id,
        'game_winner_team_id' => $a->id,
        'status' => 'final',
    ]);

    $playoffBracket = $tournament->divisions()->first()->brackets()->create([
        'name' => 'Playoffs',
        'format' => 'single_elim',
        'phase' => 'playoffs',
        'seeding_method' => 'manual',
        'status' => 'generated',
        'generated_at' => now(),
    ]);

    MatchModel::factory()->create([
        'bracket_id' => $playoffBracket->id,
        'tournament_id' => $tournament->id,
        'stage' => 'winners',
        'round_number' => 1,
        'match_number' => 1,
        'home_team_id' => $b->id,
        'away_team_id' => $a->id,
        'home_score' => 80,
        'away_score' => 70,
        'winner_team_id' => $b->id,
        'game_winner_team_id' => $b->id,
        'status' => 'final',
    ]);

    $rows = (new StandingsService)->compute($tournament)->keyBy('team_id');

    expect($rows[$a->id]['wins'])->toBe(1)
        ->and($rows[$b->id]['wins'])->toBe(0)
        ->and($rows[$a->id]['point_diff'])->toBe(10);
});
```

Run and confirm the head-to-head test fails because C is ranked below B:

```powershell
cd src
php artisan test --filter="ranks tied teams by head-to-head"
```

- [ ] **Step 2: Filter the correct match population**

In `compute()`, replace the match query with a stage-aware query:

```php
$format = $this->formatOf($target);

if ($target instanceof Tournament) {
    $tournamentId = $target->id;
    $registrations = Registration::where('tournament_id', $tournamentId)
        ->where('status', 'approved')
        ->get();
    $matches = $this->standingsMatches($tournamentId, $format);
} else {
    $tournamentId = $target->tournament_id;
    $registrations = Registration::where('division_id', $target->id)
        ->where('status', 'approved')
        ->get();
    $divisionTeamIds = $registrations->pluck('team_id')->all();
    $matches = $this->standingsMatches($tournamentId, $format);
    if (! empty($divisionTeamIds)) {
        $matches = $matches->filter(
            fn (MatchModel $match): bool => in_array($match->home_team_id, $divisionTeamIds, true)
                || in_array($match->away_team_id, $divisionTeamIds, true)
        );
    }
}
```

Add helpers:

```php
protected function formatOf(Tournament|Division $target): string
{
    $tournament = $target instanceof Tournament ? $target : $target->tournament;
    $rules = $tournament?->rules_json;
    $format = is_array($rules) && isset($rules['format']) && is_string($rules['format'])
        ? $rules['format']
        : 'single-elimination';

    return in_array($format, ['single-elimination', 'double-elimination', 'round-robin'], true)
        ? $format
        : 'single-elimination';
}

/**
 * @return Collection<int, MatchModel>
 */
protected function standingsMatches(int $tournamentId, string $format): Collection
{
    $query = MatchModel::where('tournament_id', $tournamentId);

    if ($format === 'round-robin') {
        $query->where('stage', 'round_robin');
    }

    return $query->get();
}
```

Also change the winner check inside the row loop to prefer the per-game winner:

```php
$winnerId = $match->game_winner_team_id ?? $match->winner_team_id;

if ((int) $winnerId === (int) $teamId) {
    $wins++;
} else {
    $losses++;
}
```

- [ ] **Step 3: Replace the global sort with a grouped sort**

Replace the `return $rows->sort(...)` block with:

```php
$sorted = $rows
    ->sortByDesc('wins')
    ->values()
    ->groupBy('wins')
    ->flatMap(function (Collection $group) use ($matches): Collection {
        $headToHead = [];

        foreach ($group as $row) {
            $headToHead[$row['team_id']] = 0;
        }

        foreach ($matches as $match) {
            if (! in_array($match->status, ['final', 'forfeit'], true)) {
                continue;
            }

            $homeId = $match->home_team_id !== null ? (int) $match->home_team_id : null;
            $awayId = $match->away_team_id !== null ? (int) $match->away_team_id : null;

            if ($homeId === null || $awayId === null
                || ! array_key_exists($homeId, $headToHead)
                || ! array_key_exists($awayId, $headToHead)) {
                continue;
            }

            $winnerId = (int) ($match->game_winner_team_id ?? $match->winner_team_id);

            if ($winnerId === $homeId) {
                $headToHead[$homeId]++;
            } elseif ($winnerId === $awayId) {
                $headToHead[$awayId]++;
            }
        }

        return $group->sort(function (array $a, array $b) use ($headToHead): int {
            return [
                $headToHead[$b['team_id']],
                $b['point_diff'],
                $b['points_for'],
                $a['seed'] ?? PHP_INT_MAX,
            ] <=> [
                $headToHead[$a['team_id']],
                $a['point_diff'],
                $a['points_for'],
                $b['seed'] ?? PHP_INT_MAX,
            ];
        })->values();
    })
    ->values();
```

Then rank the sorted collection:

```php
return $sorted
    ->values()
    ->map(function (array $row, int $index) use ($cut): array {
        $row['rank'] = $index + 1;
        $row['qualified'] = $index < $cut;

        return $row;
    })
    ->values();
```

- [ ] **Step 4: Use `playoff_teams` in `playoffCut`**

Replace `playoffCut` with:

```php
public function playoffCut(Tournament|Division $target): int
{
    $tournament = $target instanceof Tournament ? $target : $target->tournament;
    $rules = $tournament?->rules_json;
    $playoffTeams = is_array($rules) && isset($rules['playoff_teams']) && is_numeric($rules['playoff_teams'])
        ? (int) $rules['playoff_teams']
        : 0;

    if (in_array($playoffTeams, [2, 4, 8, 16], true)) {
        $approved = $target instanceof Tournament
            ? $target->registrations()->where('status', 'approved')->count()
            : $target->registrations()->where('status', 'approved')->count();

        return min($playoffTeams, (int) $approved);
    }

    if ($target instanceof Tournament) {
        $slots = is_array($rules) ? (int) ($rules['slots'] ?? 0) : 0;

        if ($slots <= 0) {
            $slots = (int) $target->divisions()->max('max_teams');
        }

        if ($slots <= 0) {
            $slots = (int) $target->registrations()->where('status', 'approved')->count();
        }
    } else {
        $slots = (int) ($target->max_teams ?? 0);

        if ($slots <= 0) {
            $slots = (int) $target->registrations()->where('status', 'approved')->count();
        }
    }

    return $slots > 0 ? (int) ceil($slots / 2) : 0;
}
```

- [ ] **Step 5: Run the standings tests**

```powershell
cd src
php artisan test --filter="computes standings for seeded tournament"
php artisan test --filter="computes standings for seeded division"
php artisan test --filter="ranks tied teams by head-to-head"
php artisan test --filter="uses playoff teams for the qualification cut"
php artisan test --filter="excludes playoff games from round-robin group standings"
```

- [ ] **Step 6: Commit**

```powershell
git add src/app/Services/StandingsService.php src/tests/Unit/Services/StandingsServiceTest.php
git commit -m "feat(standings): add head-to-head tiebreak and playoff cut"
```

## Task 12: Stage-aware scheduling

**Files:**
- Modify: `src/app/Services/SchedulingService.php`
- Create: `src/tests/Unit/Services/SchedulingServiceTest.php` (if it does not already exist)
- Modify: `src/tests/Feature/Pages/ScheduleTest.php` (only if an assertion depends on old round ordering)

**Interfaces:**
- `plan()` schedules only `status = scheduled` matches.
- Conditional and cancelled matches are excluded.
- Rounds are grouped by `stage` + `round_number`, ordered winners, interleaved losers, grand final, reset.
- Round-robin matchdays are ordered ascending.

- [ ] **Step 1: Write the failing scheduling test**

Create `src/tests/Unit/Services/SchedulingServiceTest.php`:

```php
<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Tournament;
use App\Services\BracketService;
use App\Services\SchedulingService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('schedules double-elimination rounds in stage order and skips conditional games', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new BracketService)->generate($tournament, 'double-elimination');
    $tournament = $tournament->fresh();

    $plan = (new SchedulingService)->plan($tournament);
    $scheduled = MatchModel::whereIn('id', collect($plan['assignments'])->pluck('matchId'))->get();

    expect($plan['assignments'])->toHaveCount(14)
        ->and($scheduled->where('stage', 'winners'))->toHaveCount(7)
        ->and($scheduled->where('stage', 'losers'))->toHaveCount(6)
        ->and($scheduled->where('stage', 'grand_final'))->toHaveCount(1)
        ->and($scheduled->where('stage', 'reset'))->toHaveCount(0)
        ->and($scheduled->contains(fn (MatchModel $match): bool => $match->status === 'conditional'))->toBeFalse();

    $first = $scheduled->firstWhere('id', $plan['assignments'][0]['matchId']);
    $last = $scheduled->firstWhere('id', $plan['assignments'][count($plan['assignments']) - 1]['matchId']);

    expect($first->stage)->toBe('winners')
        ->and($first->round_number)->toBe(1)
        ->and($last->stage)->toBe('grand_final');
});

it('schedules an activated series game and still skips unplayed conditional games', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    (new BracketService)->generate($tournament, 'single-elimination');
    $tournament = $tournament->fresh();

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    $game2 = MatchModel::where('series_id', $final->id)->where('game_number', 2)->firstOrFail();
    $game2->status = 'scheduled';
    $game2->home_team_id = 1;
    $game2->away_team_id = 2;
    $game2->save();

    $plan = (new SchedulingService)->plan($tournament);
    $ids = collect($plan['assignments'])->pluck('matchId');

    expect($ids)->toContain($game2->id)
        ->and($ids)->not->toContain(MatchModel::where('series_id', $final->id)->where('game_number', 3)->value('id'));
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
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="schedules double-elimination rounds in stage order"
```

- [ ] **Step 2: Filter and group matches**

Replace the opening match query in `plan()`:

```php
$matches = MatchModel::query()
    ->where('tournament_id', $tournament->id)
    ->where('status', 'scheduled')
    ->get()
    ->reject(fn (MatchModel $match): bool => $match->isByeGame())
    ->values();
```

Replace the result-lock query:

```php
$hasResults = MatchModel::query()
    ->where('tournament_id', $tournament->id)
    ->whereIn('status', ['final', 'forfeit', 'in_progress'])
    ->get()
    ->contains(fn (MatchModel $match): bool => ! $match->isByeGame());
```

Replace the `$rounds` block with:

```php
$rounds = $matches
    ->groupBy(fn (MatchModel $match): string => $match->stage.'|'.$match->round_number)
    ->map(function ($group, string $key): array {
        [$stage, $round] = explode('|', $key);

        return [
            'stage' => $stage,
            'round' => (int) $round,
            'sequence' => $this->roundSequence($stage, (int) $round),
            'stage_rank' => $stage === 'winners' ? 0 : 1,
            'matches' => $group->sortBy('match_number')->values(),
        ];
    })
    ->sort(function (array $a, array $b): int {
        return [$a['sequence'], $a['stage_rank'], $a['round']]
            <=> [$b['sequence'], $b['stage_rank'], $b['round']];
    })
    ->values();

$sequences = $rounds->pluck('sequence')->unique()->sort()->values();
```

Replace the per-round loop header and date calculation:

```php
foreach ($rounds as $round) {
    $roundMatches = $round['matches'];
    $date = $baseDate->copy()->addDays((int) $sequences->search($round['sequence']));
    $baseHour = $this->roundStartHour($roundMatches->count());
```

Add the helpers:

```php
protected function roundSequence(string $stage, int $round): int
{
    return match ($stage) {
        'round_robin' => $round,
        'winners' => max(1, 2 * $round - 1),
        'losers' => $round + 1,
        'grand_final' => 10_000,
        'reset' => 10_001,
        default => 10_002 + $round,
    };
}

protected function roundStartHour(int $matchCount): int
{
    return match (true) {
        $matchCount >= 16 => 8,
        $matchCount >= 8 => 9,
        $matchCount >= 4 => 13,
        $matchCount >= 2 => 15,
        default => 16,
    };
}
```

Remove the now-unused `ROUND_START_HOUR` constant and its docblock.

- [ ] **Step 3: Run the scheduling tests**

```powershell
cd src
php artisan test --filter="schedules double-elimination rounds in stage order"
php artisan test --filter="schedules an activated series game"
php artisan test --filter="orders round-robin matchdays ascending"
php artisan test --filter="generates a schedule for the seeded tournament"
```

If `ScheduleTest` asserts a specific old date or round order, update the expectation to the stage-aware order rather than weakening the assertion.

- [ ] **Step 4: Commit**

```powershell
git add src/app/Services/SchedulingService.php src/tests/Unit/Services/SchedulingServiceTest.php src/tests/Feature/Pages/ScheduleTest.php
git commit -m "feat(schedule): order rounds by stage and skip conditional games"
```

## Task 13: Round-robin playoff stage

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Create: `src/tests/Unit/Services/Bracket/RoundRobinPlayoffTest.php`

**Interfaces:**
- `generatePlayoffs(Tournament $tournament): array{matches: int, games: int, byes: int}`
- Rejects when the format is not round robin, `playoff_teams` is not `2, 4, 8, 16`, the group stage is incomplete, or an existing playoff bracket has results.
- Seeds are the top `playoff_teams` rows from `StandingsService::compute()`.
- The playoff bracket is a separate `Bracket` row with `format = single_elim` and `phase = playoffs`.

- [ ] **Step 1: Write the failing playoff tests**

Create `src/tests/Unit/Services/Bracket/RoundRobinPlayoffTest.php`:

```php
<?php

use App\Models\Bracket;
use App\Models\MatchModel;
use App\Models\Registration;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

function playoffTournament(int $playoffTeams = 4): Tournament
{
    $base = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    $tournament = $base->replicate();
    $tournament->name = 'Playoff Probe '.uniqid();
    $tournament->slug = 'playoff-probe-'.uniqid();
    $tournament->rules_json = array_merge($base->rules_json ?? [], [
        'format' => 'round-robin',
        'slots' => 4,
        'playoff_teams' => $playoffTeams,
        'round_robin_legs' => 1,
    ]);
    $tournament->save();

    $division = $tournament->divisions()->create([
        'name' => 'Open',
        'max_teams' => 4,
    ]);

    for ($i = 1; $i <= 4; $i++) {
        $team = Team::create([
            'organization_id' => $tournament->organization_id,
            'name' => 'Playoff Team '.$i.' '.uniqid(),
            'city' => 'City',
        ]);

        Registration::create([
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'status' => 'approved',
            'seed' => $i,
        ]);
    }

    return $tournament->fresh();
}

function playGroupStage(BracketService $service, Tournament $tournament): void
{
    $group = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'group')
        ->firstOrFail();

    $guard = 0;

    while ($guard++ < 50) {
        $match = MatchModel::where('bracket_id', $group->id)
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->first();

        if (! $match instanceof MatchModel) {
            break;
        }

        $service->recordScore($match, 80, 70);
    }
}

it('blocks playoff generation until every group game is complete', function () {
    $tournament = playoffTournament();
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');

    expect(fn () => $service->generatePlayoffs($tournament))
        ->toThrow(InvalidArgumentException::class, 'All group games must be final before generating playoffs.');
});

it('seeds the playoff bracket from the final standings and applies series settings', function () {
    $tournament = playoffTournament();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);

    $result = $service->generatePlayoffs($tournament->fresh());
    $playoff = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'playoffs')
        ->firstOrFail();

    $standings = app(\App\Services\StandingsService::class)->compute($tournament->fresh());
    $firstRound = MatchModel::where('bracket_id', $playoff->id)
        ->whereNull('series_id')
        ->where('round_number', 4)
        ->orderBy('match_number')
        ->get();

    expect($playoff->format)->toBe('single_elim')
        ->and($result['matches'])->toBe(3)
        ->and($firstRound)->toHaveCount(2)
        ->and($firstRound[0]->home_team_id)->toBe($standings[0]['team_id'])
        ->and($firstRound[0]->away_team_id)->toBe($standings[3]['team_id'])
        ->and($firstRound[1]->home_team_id)->toBe($standings[1]['team_id'])
        ->and($firstRound[1]->away_team_id)->toBe($standings[2]['team_id']);

    $final = MatchModel::where('bracket_id', $playoff->id)
        ->whereNull('series_id')
        ->where('round_number', 2)
        ->firstOrFail();

    expect($final->best_of)->toBe(3)
        ->and(MatchModel::where('series_id', $final->id)->count())->toBe(2);
});

it('crowns the playoff final winner as champion', function () {
    $tournament = playoffTournament();
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);
    $service->generatePlayoffs($tournament->fresh());

    $playoff = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'playoffs')
        ->firstOrFail();

    foreach ([4, 2] as $round) {
        $matches = MatchModel::where('bracket_id', $playoff->id)
            ->where('round_number', $round)
            ->whereNull('series_id')
            ->orderBy('match_number')
            ->get();

        foreach ($matches as $match) {
            if ($match->home_team_id !== null && $match->away_team_id !== null) {
                $service->recordScore($match->fresh(), 80, 70);
            }
        }
    }

    expect($service->champion($tournament->fresh()))->not->toBeNull();
});

it('rejects playoff generation when playoffs are disabled', function () {
    $tournament = playoffTournament(0);
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);

    expect(fn () => $service->generatePlayoffs($tournament->fresh()))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects regenerating the group stage after a playoff result exists', function () {
    $tournament = playoffTournament();
    $service = new BracketService;
    $service->generate($tournament, 'round-robin');
    playGroupStage($service, $tournament);
    $service->generatePlayoffs($tournament->fresh());

    $playoff = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'playoffs')
        ->firstOrFail();
    $match = MatchModel::where('bracket_id', $playoff->id)->where('status', 'scheduled')->firstOrFail();
    $service->recordScore($match, 80, 70);

    expect(fn () => $service->generate($tournament->fresh(), 'round-robin'))
        ->toThrow(InvalidArgumentException::class, 'Playoffs already have results');
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="blocks playoff generation until every group game is complete"
```

- [ ] **Step 2: Implement `generatePlayoffs`**

Add to `BracketService`:

```php
/**
 * @return array{matches: int, games: int, byes: int}
 */
public function generatePlayoffs(Tournament $tournament): array
{
    if ($tournament->format() !== 'round-robin') {
        throw new \InvalidArgumentException('Playoffs are only available for round-robin tournaments.');
    }

    $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
    $playoffTeams = isset($rules['playoff_teams']) && is_numeric($rules['playoff_teams'])
        ? (int) $rules['playoff_teams']
        : 0;

    if (! in_array($playoffTeams, [2, 4, 8, 16], true)) {
        throw new \InvalidArgumentException('Choose 2, 4, 8, or 16 playoff teams.');
    }

    $divisionIds = $tournament->divisions()->pluck('id');
    $group = Bracket::whereIn('division_id', $divisionIds)
        ->where('phase', 'group')
        ->first();

    if (! $group instanceof Bracket) {
        throw new \InvalidArgumentException('Generate the round-robin group stage first.');
    }

    $pending = MatchModel::where('bracket_id', $group->id)
        ->whereNotIn('status', ['final', 'forfeit', 'cancelled'])
        ->exists();

    if ($pending) {
        throw new \InvalidArgumentException('All group games must be final before generating playoffs.');
    }

    $standings = app(StandingsService::class)->compute($tournament)->take($playoffTeams)->values();

    if ($standings->count() < $playoffTeams) {
        throw new \InvalidArgumentException('Not enough ranked teams to fill the playoff bracket.');
    }

    $orderedTeamIds = $standings
        ->pluck('team_id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    $division = $tournament->divisions()->first();

    if (! $division instanceof Division) {
        throw new \InvalidArgumentException('The tournament has no division.');
    }

    return DB::transaction(function () use ($tournament, $divisionIds, $division, $orderedTeamIds, $playoffTeams, $rules): array {
        $existing = Bracket::whereIn('division_id', $divisionIds)
            ->where('phase', 'playoffs')
            ->get();

        foreach ($existing as $bracket) {
            $hasResults = MatchModel::where('bracket_id', $bracket->id)
                ->whereIn('status', ['final', 'forfeit', 'in_progress'])
                ->exists();

            if ($hasResults) {
                throw new \InvalidArgumentException('Playoffs already have results; regenerate is not allowed.');
            }

            $this->deleteMatchesAndSlots(collect([$bracket->id]));
            $bracket->delete();
        }

        $bracket = Bracket::create([
            'division_id' => $division->id,
            'name' => $tournament->name.' Playoffs',
            'format' => 'single_elim',
            'phase' => 'playoffs',
            'seeding_method' => 'standings',
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        $bestOf = [
            0 => $this->bestOfRule($rules, 'best_of_final'),
            1 => $this->bestOfRule($rules, 'best_of_semifinal'),
            2 => $this->bestOfRule($rules, 'best_of_quarterfinal'),
        ];

        $plan = (new SingleEliminationBuilder)->build($playoffTeams, $orderedTeamIds, $bestOf);
        $result = $this->persistPlan($tournament, $bracket, $plan);

        app(AuditLogger::class)->log(
            'bracket.playoffs',
            'tournament',
            $tournament->id,
            null,
            [
                'teams' => $playoffTeams,
                'matches' => $result['matches'],
                'games' => $result['games'],
            ],
            $tournament->organization_id,
        );

        return $result;
    });
}
```

The `$hasResults` expression above is intentionally strict: a playoff bracket with any completed or in-progress game is not replaced. If there are no results, it is removed and rebuilt.

- [ ] **Step 3: Run the playoff tests**

```powershell
cd src
php artisan test --filter="blocks playoff generation until every group game is complete"
php artisan test --filter="seeds the playoff bracket from the final standings"
php artisan test --filter="crowns the playoff final winner as champion"
php artisan test --filter="rejects playoff generation when playoffs are disabled"
php artisan test --filter="rejects regenerating the group stage after a playoff result exists"
```

- [ ] **Step 4: Commit**

```powershell
git add src/app/Services/BracketService.php src/tests/Unit/Services/Bracket/RoundRobinPlayoffTest.php
git commit -m "feat(bracket): add round-robin playoff stage"
```

## Task 14: Tournament creation fields for legs, playoffs, and best-of

**Files:**
- Modify: `src/app/Livewire/Organizer/TournamentCreate.php`
- Modify: `src/resources/views/livewire/organizer/tournament-create.blade.php`
- Modify: `src/tests/Feature/Pages/TournamentsTest.php`

**Interfaces:**
- New public properties: `roundRobinLegs`, `playoffTeams`, `bestOfQuarterfinal`, `bestOfSemifinal`, `bestOfFinal`.
- Stored in `rules_json` as `round_robin_legs`, `playoff_teams`, `best_of_quarterfinal`, `best_of_semifinal`, `best_of_final`.
- Defaults: legs 1, playoff teams 0, every best-of 1.

- [ ] **Step 1: Write the failing creation tests**

Append to `src/tests/Feature/Pages/TournamentsTest.php`:

```php
it('stores round-robin legs and playoff team settings', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->set('format', 'round-robin')
        ->set('bracketSlots', 8)
        ->set('roundRobinLegs', 3)
        ->set('playoffTeams', 4)
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $tournament = Tournament::where('name', 'HoopFlow Metro Invitational')->firstOrFail();

    expect($tournament->rules_json['round_robin_legs'])->toBe(3)
        ->and($tournament->rules_json['playoff_teams'])->toBe(4)
        ->and($tournament->rules_json['format'])->toBe('round-robin');
});

it('stores best-of settings for playoff rounds', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->set('bestOfQuarterfinal', 3)
        ->set('bestOfSemifinal', 5)
        ->set('bestOfFinal', 7)
        ->call('nextStep')
        ->call('save')
        ->assertHasNoErrors();

    $tournament = Tournament::where('name', 'HoopFlow Metro Invitational')->firstOrFail();

    expect($tournament->rules_json['best_of_quarterfinal'])->toBe(3)
        ->and($tournament->rules_json['best_of_semifinal'])->toBe(5)
        ->and($tournament->rules_json['best_of_final'])->toBe(7);
});

it('rejects even best-of lengths and out-of-range legs', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(TournamentCreate::class)
        ->call('nextStep')
        ->set('bestOfFinal', 4)
        ->set('roundRobinLegs', 11)
        ->call('nextStep')
        ->assertHasErrors(['bestOfFinal', 'roundRobinLegs']);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="stores round-robin legs and playoff team settings"
```

- [ ] **Step 2: Add properties and validation**

Add to `TournamentCreate`:

```php
public int $roundRobinLegs = 1;

public int $playoffTeams = 0;

public int $bestOfQuarterfinal = 1;

public int $bestOfSemifinal = 1;

public int $bestOfFinal = 1;
```

Add to `formatRules()`:

```php
'roundRobinLegs' => ['required', 'integer', 'min:1', 'max:10'],
'playoffTeams' => ['required', 'integer', 'in:0,2,4,8,16'],
'bestOfQuarterfinal' => ['required', 'integer', 'in:1,3,5,7,9'],
'bestOfSemifinal' => ['required', 'integer', 'in:1,3,5,7,9'],
'bestOfFinal' => ['required', 'integer', 'in:1,3,5,7,9'],
```

Add messages:

```php
'roundRobinLegs.min' => 'Meetings per pair must be between 1 and 10.',
'roundRobinLegs.max' => 'Meetings per pair must be between 1 and 10.',
'playoffTeams.in' => 'Choose 0, 2, 4, 8, or 16 playoff teams.',
'bestOfQuarterfinal.in' => 'Choose a best-of length of 1, 3, 5, 7, or 9.',
'bestOfSemifinal.in' => 'Choose a best-of length of 1, 3, 5, 7, or 9.',
'bestOfFinal.in' => 'Choose a best-of length of 1, 3, 5, 7, or 9.',
```

In `updatedFormat()`, disable playoffs for elimination formats:

```php
if ($value !== 'round-robin') {
    $this->playoffTeams = 0;
}
```

- [ ] **Step 3: Persist the rules**

In the `rules_json` array inside `save()`, add:

```php
'round_robin_legs' => $this->roundRobinLegs,
'playoff_teams' => $this->format === 'round-robin' ? $this->playoffTeams : 0,
'best_of_quarterfinal' => $this->bestOfQuarterfinal,
'best_of_semifinal' => $this->bestOfSemifinal,
'best_of_final' => $this->bestOfFinal,
```

- [ ] **Step 4: Add the form fields**

In `tournament-create.blade.php`, inside the format step after the bracket-slots field, add:

```blade
@if ($format === 'round-robin')
    <div class="hf-field">
        <label class="hf-label" for="createLegs">Meetings per pair</label>
        <input class="hf-input" id="createLegs" type="number" min="1" max="10" wire:model="roundRobinLegs"
               @error('roundRobinLegs') aria-invalid="true" @enderror data-testid="create-round-robin-legs">
        <p class="hf-small hf-muted hf-mb-0">Each pair meets 1–10 times. Even legs swap home and away.</p>
        @error('roundRobinLegs') <p class="hf-field-error">{{ $message }}</p> @enderror
    </div>
    <div class="hf-field">
        <label class="hf-label" for="createPlayoffTeams">Playoff teams</label>
        <select class="hf-select" id="createPlayoffTeams" wire:model="playoffTeams"
                @error('playoffTeams') aria-invalid="true" @enderror data-testid="create-playoff-teams">
            <option value="0">No playoffs</option>
            @foreach ([2, 4, 8, 16] as $playoffOption)
                <option value="{{ $playoffOption }}">Top {{ $playoffOption }}</option>
            @endforeach
        </select>
        @error('playoffTeams') <p class="hf-field-error">{{ $message }}</p> @enderror
    </div>
@endif

<div class="hf-field">
    <label class="hf-label" for="createBestOfQuarterfinal">Quarterfinal series</label>
    <select class="hf-select" id="createBestOfQuarterfinal" wire:model="bestOfQuarterfinal"
            @error('bestOfQuarterfinal') aria-invalid="true" @enderror data-testid="create-best-of-quarterfinal">
        @foreach ([1, 3, 5, 7, 9] as $bestOfOption)
            <option value="{{ $bestOfOption }}">Best of {{ $bestOfOption }}</option>
        @endforeach
    </select>
    @error('bestOfQuarterfinal') <p class="hf-field-error">{{ $message }}</p> @enderror
</div>
<div class="hf-field">
    <label class="hf-label" for="createBestOfSemifinal">Semifinal series</label>
    <select class="hf-select" id="createBestOfSemifinal" wire:model="bestOfSemifinal"
            @error('bestOfSemifinal') aria-invalid="true" @enderror data-testid="create-best-of-semifinal">
        @foreach ([1, 3, 5, 7, 9] as $bestOfOption)
            <option value="{{ $bestOfOption }}">Best of {{ $bestOfOption }}</option>
        @endforeach
    </select>
    @error('bestOfSemifinal') <p class="hf-field-error">{{ $message }}</p> @enderror
</div>
<div class="hf-field">
    <label class="hf-label" for="createBestOfFinal">Final series</label>
    <select class="hf-select" id="createBestOfFinal" wire:model="bestOfFinal"
            @error('bestOfFinal') aria-invalid="true" @enderror data-testid="create-best-of-final">
        @foreach ([1, 3, 5, 7, 9] as $bestOfOption)
            <option value="{{ $bestOfOption }}">Best of {{ $bestOfOption }}</option>
        @endforeach
    </select>
    @error('bestOfFinal') <p class="hf-field-error">{{ $message }}</p> @enderror
</div>
```

- [ ] **Step 5: Run the creation tests**

```powershell
cd src
php artisan test --filter="create wizard starts with prototype defaults"
php artisan test --filter="create wizard publishes a registration-open tournament"
php artisan test --filter="stores round-robin legs and playoff team settings"
php artisan test --filter="stores best-of settings for playoff rounds"
php artisan test --filter="rejects even best-of lengths and out-of-range legs"
```

- [ ] **Step 6: Commit**

```powershell
git add src/app/Livewire/Organizer/TournamentCreate.php src/resources/views/livewire/organizer/tournament-create.blade.php src/tests/Feature/Pages/TournamentsTest.php
git commit -m "feat(tournament): configure round-robin legs, playoffs, and series lengths"
```

## Task 15: Stage-aware labels, bracket sections, series cards, and champion UI

**Files:**
- Modify: `src/app/Models/MatchModel.php`
- Modify: `src/app/Livewire/Organizer/BracketShow.php`
- Modify: `src/app/Livewire/Organizer/TournamentShow.php`
- Modify: `src/resources/views/livewire/organizer/bracket-show.blade.php`
- Modify: `src/resources/views/components/bracket-match.blade.php`
- Modify: `src/tests/Feature/Pages/BracketTest.php`

**Interfaces:**
- `MatchModel::roundLabel()` becomes stage-aware and keeps the existing single-elimination labels.
- `BracketShow::bracket()` returns `sections` instead of a flat `rounds` list.
- `BracketShow` exposes `selectedSeriesGames`, `canGeneratePlayoffs`, `requestGeneratePlayoffs`, and the `playoffs` confirm action.
- `BracketShow::slotAssignments` becomes seed-position based so `updateSlots()` can rebuild the bracket safely.
- `TournamentShow::champion()` delegates to `BracketService::champion()`.

- [ ] **Step 1: Write the failing UI tests**

Append to `src/tests/Feature/Pages/BracketTest.php`:

```php
it('renders winners, losers, and grand final sections for double elimination', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = \App\Models\Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new \App\Services\BracketService)->generate($tournament, 'double-elimination');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertSee('Winners bracket')
        ->assertSee('Losers bracket')
        ->assertSee('Grand final')
        ->assertSee('Winners Quarterfinal')
        ->assertSee('Losers Round 1')
        ->assertDontSeeHtml('data-testid="bracket-champion"');
});

it('shows the best-of length and series score on a playoff card', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = \App\Models\Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);

    (new \App\Services\BracketService)->generate($tournament, 'single-elimination');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertSee('Best of 3')
        ->assertSee('Game 1');
});

it('targets the seed order when editing slots', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = \App\Models\Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    (new \App\Services\BracketService)->generate($tournament, 'double-elimination');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->call('openEditSlots')
        ->assertSet('showEditSlots', true)
        ->assertCount('slotAssignments', 8)
        ->assertSet('slotAssignments.0.seed', 1)
        ->assertSee('Seed 1');
});

it('shows the generate-playoffs action only when the round-robin group is complete', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = \App\Models\Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 4,
        ]),
    ]);

    $service = new \App\Services\BracketService;
    $service->generate($tournament->fresh(), 'round-robin');

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertDontSeeHtml('data-testid="bracket-generate-playoffs"');

    $group = \App\Models\Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->where('phase', 'group')
        ->firstOrFail();

    foreach (\App\Models\MatchModel::where('bracket_id', $group->id)->get() as $match) {
        if ($match->home_team_id !== null && $match->away_team_id !== null) {
            $service->recordScore($match->fresh(), 80, 70);
        }
    }

    Livewire::actingAs($organizer)
        ->test(BracketShow::class, ['id' => $tournament->id])
        ->assertSeeHtml('data-testid="bracket-generate-playoffs"');
});

it('uses the bracket service champion resolution on the tournament page', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $tournament = \App\Models\Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(\App\Livewire\Organizer\TournamentShow::class, ['tournament' => $tournament->id])
        ->assertSet('champion', null);
});
```

Run and confirm failure:

```powershell
cd src
php artisan test --filter="renders winners, losers, and grand final sections"
```

- [ ] **Step 2: Make `roundLabel()` stage-aware**

Replace `MatchModel::roundLabel()`:

```php
public function roundLabel(): string
{
    if ($this->stage === 'round_robin') {
        return 'Matchday '.$this->round_number;
    }

    if ($this->stage === 'grand_final') {
        return 'Grand Final';
    }

    if ($this->stage === 'reset') {
        return 'Grand Final Reset (if needed)';
    }

    $round = (int) $this->round_number;

    if ($this->tournament?->format() === 'double-elimination') {
        $stage = $this->stage === 'losers' ? 'losers' : 'winners';
        $totalRounds = $this->bracket instanceof Bracket
            ? $this->bracket->roundCount($stage)
            : 0;
        $fromEnd = $totalRounds > 0 ? $totalRounds - $round + 1 : 0;

        if ($stage === 'losers') {
            return match ($fromEnd) {
                1 => 'Losers Final',
                2 => 'Losers Semifinal',
                3 => 'Losers Quarterfinal',
                default => 'Losers Round '.$round,
            };
        }

        return match ($fromEnd) {
            1 => 'Winners Final',
            2 => 'Winners Semifinal',
            3 => 'Winners Quarterfinal',
            default => 'Winners Round '.$round,
        };
    }

    // Single elimination numbers rounds by bracket size (2, 4, 8, 16, 32),
    // so log2(round_number) is the distance from the final.
    $fromEnd = $round > 1 ? (int) round(log($round, 2)) : 0;

    return match ($fromEnd) {
        1 => 'Championship Final',
        2 => 'Semifinals',
        3 => 'Quarterfinals',
        4 => 'Round of 16',
        5 => 'Round of 32',
        default => 'Round '.$round,
    };
}
```

The fallback keeps the existing labels when `roundCount()` is zero (for example a legacy row without a loaded bracket).

- [ ] **Step 3: Rebuild `BracketShow::bracket()` into sections**

Replace the `bracket()` computed and its docblock with:

```php
/**
 * @return array{
 *     hasBracket: bool,
 *     sections: array<int, array{key: string, title: string, rounds: array<int, array{round: int, label: string, matches: Collection<int, MatchModel>}>}>,
 *     champion: Team|null
 * }
 */
#[Computed]
public function bracket(): array
{
    if (! $this->tournament instanceof Tournament) {
        return ['hasBracket' => false, 'sections' => [], 'champion' => null];
    }

    $brackets = Bracket::whereIn('division_id', $this->tournament->divisions()->pluck('id'))
        ->orderBy('phase')
        ->get();

    if ($brackets->isEmpty()) {
        return ['hasBracket' => false, 'sections' => [], 'champion' => null];
    }

    $matches = MatchModel::whereIn('bracket_id', $brackets->pluck('id'))
        ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'matchSlots.court', 'bracket'])
        ->whereNull('series_id')
        ->get()
        ->reject(fn (MatchModel $match): bool => $match->status === 'cancelled')
        ->values();

    $sections = [];

    foreach ($brackets as $bracket) {
        $bracketMatches = $matches->where('bracket_id', $bracket->id)->values();

        if ($bracket->format === 'round_robin') {
            $sections[] = $this->section('group', 'Group stage', $bracketMatches, 'round_robin', true);
            continue;
        }

        if ($bracket->phase === 'playoffs') {
            $sections[] = $this->section('playoffs', 'Playoffs', $bracketMatches, 'winners', false);
            continue;
        }

        if ($bracket->format === 'double_elim') {
            $sections[] = $this->section('winners', 'Winners bracket', $bracketMatches, 'winners', true);
            $sections[] = $this->section('losers', 'Losers bracket', $bracketMatches, 'losers', true);
            $sections[] = $this->section('grand_final', 'Grand final', $bracketMatches, 'grand_final', true);
            continue;
        }

        $sections[] = $this->section('main', 'Bracket', $bracketMatches, 'winners', false);
    }

    $sections = array_values(array_filter(
        $sections,
        fn (array $section): bool => $section['rounds'] !== [],
    ));

    return [
        'hasBracket' => true,
        'sections' => $sections,
        'champion' => app(BracketService::class)->champion($this->tournament),
    ];
}

/**
 * @param  Collection<int, MatchModel>  $matches
 * @return array{key: string, title: string, rounds: array<int, array{round: int, label: string, matches: Collection<int, MatchModel>}>}
 */
protected function section(string $key, string $title, Collection $matches, string $stage, bool $ascending): array
{
    if ($stage === 'grand_final') {
        $matches = $matches
            ->whereIn('stage', ['grand_final', 'reset'])
            ->sortBy(fn (MatchModel $match): int => $match->stage === 'reset' ? 1 : 0)
            ->values();

        $rounds = $matches
            ->map(fn (MatchModel $match): array => [
                'round' => (int) $match->round_number,
                'label' => $match->roundLabel(),
                'matches' => new Collection([$match]),
            ])
            ->values()
            ->all();

        return ['key' => $key, 'title' => $title, 'rounds' => $rounds];
    }

    $stageMatches = $matches
        ->where('stage', $stage)
        ->sortBy('round_number')
        ->values();

    $rounds = $stageMatches
        ->groupBy('round_number')
        ->map(function (Collection $group, $round): array {
            $group = $group->sortBy('match_number')->values();

            return [
                'round' => (int) $round,
                'label' => $group->first()?->roundLabel() ?? 'Round '.$round,
                'matches' => $group,
            ];
        })
        ->values();

    if (! $ascending) {
        $rounds = $rounds->reverse()->values();
    }

    return ['key' => $key, 'title' => $title, 'rounds' => $rounds->all()];
}
```

Add `use App\Models\Bracket;` to `BracketShow`.

- [ ] **Step 4: Add series games, playoffs, and seed-order edit slots**

Add computed helpers:

```php
/**
 * @return Collection<int, MatchModel>
 */
#[Computed]
public function selectedSeriesGames(): Collection
{
    $match = $this->selectedMatch;

    if (! $match instanceof MatchModel || $match->best_of <= 1) {
        return new Collection;
    }

    $anchorId = $match->series_id !== null ? (int) $match->series_id : (int) $match->id;

    return MatchModel::query()
        ->where(function ($query) use ($anchorId): void {
            $query->where('id', $anchorId)->orWhere('series_id', $anchorId);
        })
        ->orderBy('game_number')
        ->get();
}

#[Computed]
public function canGeneratePlayoffs(): bool
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament || $this->format !== 'round-robin') {
        return false;
    }

    $rules = is_array($tournament->rules_json) ? $tournament->rules_json : [];
    $playoffTeams = isset($rules['playoff_teams']) && is_numeric($rules['playoff_teams'])
        ? (int) $rules['playoff_teams']
        : 0;

    if (! in_array($playoffTeams, [2, 4, 8, 16], true)) {
        return false;
    }

    $divisionIds = $tournament->divisions()->pluck('id');
    $group = Bracket::whereIn('division_id', $divisionIds)->where('phase', 'group')->first();

    if (! $group instanceof Bracket) {
        return false;
    }

    if (Bracket::whereIn('division_id', $divisionIds)->where('phase', 'playoffs')->exists()) {
        return false;
    }

    return ! MatchModel::where('bracket_id', $group->id)
        ->whereNotIn('status', ['final', 'forfeit', 'cancelled'])
        ->exists();
}

public function requestGeneratePlayoffs(): void
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament) {
        return;
    }

    Gate::authorize('generateBracket', $tournament);

    if (! $this->canGeneratePlayoffs) {
        $this->toast('Finish every group game before generating playoffs.', 'Playoffs unavailable', 'warning');

        return;
    }

    $this->openConfirm(
        'Generate playoffs?',
        'The top ranked teams enter a single-elimination playoff bracket.',
        'Generate playoffs',
        'primary',
        'playoffs',
    );
}
```

In `confirm()`, add:

```php
} elseif ($action === 'playoffs') {
    $this->performGeneratePlayoffs($bracketService);
}
```

Add the performer:

```php
protected function performGeneratePlayoffs(BracketService $bracketService): void
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament) {
        return;
    }

    Gate::authorize('generateBracket', $tournament);

    $result = $bracketService->generatePlayoffs($tournament);

    $this->toast($result['matches'].' playoff matchups created.', 'Playoffs generated');
    $this->selectedMatchId = null;
    $this->showMatchDetail = false;
    $this->showScoreConsole = false;
    $this->showCorrection = false;
}
```

Replace `openEditSlots()` body with seed-position rows:

```php
$this->slotAssignments = $this->approvedTeams
    ->values()
    ->map(fn (Registration $registration, int $index): array => [
        'seed' => $index + 1,
        'team_id' => $registration->team_id !== null ? (int) $registration->team_id : null,
    ])
    ->all();
```

Replace `saveSlots()` with:

```php
public function saveSlots(BracketService $bracketService): void
{
    $tournament = $this->tournament;

    if (! $tournament instanceof Tournament) {
        return;
    }

    Gate::authorize('generateBracket', $tournament);

    if ($this->kpis['matchesPlayed'] > 0) {
        $this->addError('slots', 'Cannot reassign slots after results are recorded.');

        return;
    }

    $approvedIds = $this->approvedTeams
        ->pluck('team_id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    $ordered = [];
    $used = [];

    foreach ($this->slotAssignments as $assignment) {
        $teamId = $this->normalizeSlotTeam($assignment['team_id'] ?? null, $approvedIds);

        if ($teamId !== null) {
            if (in_array($teamId, $used, true)) {
                $this->addError('slots', 'A team cannot be assigned to two slots.');

                return;
            }

            $used[] = $teamId;
        }

        $ordered[] = $teamId;
    }

    $bracket = Bracket::whereIn('division_id', $tournament->divisions()->pluck('id'))
        ->whereIn('phase', ['main', 'group'])
        ->first();

    if (! $bracket instanceof Bracket) {
        $this->addError('slots', 'Generate a bracket before editing slots.');

        return;
    }

    try {
        $bracketService->updateSlots($bracket, $ordered);
    } catch (\InvalidArgumentException $exception) {
        $this->addError('slots', $exception->getMessage());

        return;
    }

    $this->showEditSlots = false;
    $this->slotAssignments = [];
    $this->resetValidation();
    $this->toast('Slots updated.', 'Bracket updated');
}
```

Update `selectedRoundLabel()`:

```php
return $match instanceof MatchModel ? $match->roundLabel() : 'Match';
```

Update the `performFinalize()` toast so a series game does not claim to advance a round:

```php
$message = $match->series_id !== null || $match->best_of > 1
    ? 'Game result recorded. The series updates automatically.'
    : ($winner?->shortLabel() ?? 'The winner').' advances to the next round.';

$this->toast($message, 'Final score recorded');
```

Remove `labelForRound()`, `firstRoundMatches()`, and `firstRoundNumber()`; nothing else should reference them after the blade update.

- [ ] **Step 5: Update the tournament page champion**

In `TournamentShow`, replace the `champion()` body with:

```php
if (! $this->hasBracket || ! $this->tournament instanceof Tournament) {
    return null;
}

return app(BracketService::class)->champion($this->tournament);
```

Add `use App\Services\BracketService;`.

Also update `bracketRounds()` so stage collisions do not undercount:

```php
return $this->tournament?->matches
    ->map(fn (MatchModel $match): string => $match->stage.'|'.$match->round_number)
    ->unique()
    ->count() ?? 0;
```

- [ ] **Step 6: Update the bracket match card**

In `bracket-match.blade.php`, add series metadata after the slot label:

```blade
@if ($match->best_of > 1)
    <div class="hf-bracket-match__series hf-small hf-muted" data-testid="bracket-series-{{ $match->id }}">
        Best of {{ $match->best_of }} · Game {{ $match->series_home_wins + $match->series_away_wins + 1 }}
        <span>{{ $match->series_home_wins }}–{{ $match->series_away_wins }}</span>
    </div>
@endif
```

For a conditional reset, show the card with no score and the “if needed” label already supplied by `roundLabel()`.

- [ ] **Step 7: Render sections and series games in the bracket page**

Replace the flat desktop and mobile round loops with a flattened section list:

```blade
@php
    $sections = $bracket['sections'];
    $mobileRounds = collect($sections)->flatMap(fn ($section) => $section['rounds'])->values();
@endphp

@foreach ($sections as $section)
    <section class="hf-mb-3" data-testid="bracket-section-{{ $section['key'] }}">
        <h2 class="hf-section-title">{{ $section['title'] }}</h2>
        <div class="hf-bracket">
            @foreach ($section['rounds'] as $round)
                <div class="hf-bracket-round">
                    <div class="hf-bracket-round-title">{{ $round['label'] }} <span class="hf-small hf-muted">{{ $round['matches']->count() }}</span></div>
                    @foreach ($round['matches'] as $match)
                        <x-bracket-match :match="$match" :round-label="$round['label']" />
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
@endforeach
```

Use `$mobileRounds` in the mobile pager exactly where the old `$bracket['rounds']` was used. Keep the same `data-testid` values.

Also replace every remaining `count($bracket['rounds'])` reference:

```blade
@php
    $totalRounds = collect($sections)->sum(fn ($section) => count($section['rounds']));
@endphp
```

Use `$totalRounds` in the page description and the “Rounds” KPI, and set `$roundCount = $mobileRounds->count()` before the mobile pager.

Add the playoffs button to the header action row:

```blade
@if ($isOrganizer && $format === 'round-robin' && $this->canGeneratePlayoffs)
    <button type="button" class="hf-btn hf-btn--primary" wire:click="requestGeneratePlayoffs" data-testid="bracket-generate-playoffs">
        Generate playoffs
    </button>
@endif
```

In the match detail dialog, after the score rows, add:

```blade
@if ($this->selectedSeriesGames->isNotEmpty())
    <div class="hf-mt-2" data-testid="bracket-series-games">
        <h3 class="hf-section-title">Series games</h3>
        <div class="hf-table-wrap">
            <table class="hf-table">
                <thead>
                    <tr>
                        <th scope="col">Game</th>
                        <th scope="col">Score</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->selectedSeriesGames as $game)
                        <tr wire:key="series-game-{{ $game->id }}">
                            <td>Game {{ $game->game_number }}</td>
                            <td>{{ $game->home_score ?? '–' }}–{{ $game->away_score ?? '–' }}</td>
                            <td>{{ $game->status }}</td>
                            <td>
                                @if ($game->status === 'scheduled')
                                    <button type="button" class="hf-btn hf-btn--sm" wire:click="openScoreConsole({{ $game->id }})" data-testid="series-score-{{ $game->id }}">Score</button>
                                @elseif (in_array($game->status, ['final', 'forfeit'], true))
                                    <button type="button" class="hf-btn hf-btn--sm" wire:click="openCorrection({{ $game->id }})" data-testid="series-correct-{{ $game->id }}">Correct</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
```

Replace the edit-slots dialog table with seed rows:

```blade
<thead>
    <tr>
        <th scope="col">Seed</th>
        <th scope="col">Team</th>
    </tr>
</thead>
<tbody>
    @foreach ($slotAssignments as $index => $assignment)
        <tr wire:key="bracket-slot-{{ $assignment['seed'] }}">
            <td>Seed {{ $assignment['seed'] }}</td>
            <td>
                <select class="hf-select" wire:model="slotAssignments.{{ $index }}.team_id" aria-label="Team for seed {{ $assignment['seed'] }}">
                    <option value="">— bye —</option>
                    @foreach ($approved as $registration)
                        <option value="{{ $registration->team_id }}">{{ $registration->team?->name ?? 'Team' }}</option>
                    @endforeach
                </select>
            </td>
        </tr>
    @endforeach
</tbody>
```

Remove the `$slotMatches` lookup block and the old `matchId`/`home`/`away` fields.

- [ ] **Step 8: Run the bracket page tests**

```powershell
cd src
php artisan test --filter="renders bracket KPIs"
php artisan test --filter="opens the requested match"
php artisan test --filter="renders winners, losers, and grand final sections"
php artisan test --filter="shows the best-of length"
php artisan test --filter="targets the seed order"
php artisan test --filter="shows the generate-playoffs action"
php artisan test --filter="uses the bracket service champion resolution"
```

- [ ] **Step 9: Commit**

```powershell
git add src/app/Models/MatchModel.php src/app/Livewire/Organizer/BracketShow.php src/app/Livewire/Organizer/TournamentShow.php src/resources/views/livewire/organizer/bracket-show.blade.php src/resources/views/components/bracket-match.blade.php src/tests/Feature/Pages/BracketTest.php
git commit -m "feat(bracket): render stage sections, series cards, and accurate champions"
```

## Task 16: Full verification and cleanup

**Files:**
- Modify: any file needed to fix failures exposed by the full suite.
- Modify: `src/tests/Unit/Services/BracketServiceTest.php` (remove or update obsolete DE count expectations).
- Modify: `src/tests/Feature/Pages/ScheduleTest.php` (only if stage ordering changes an assertion).

**Interfaces:**
- No new interfaces. This task proves the whole feature works together.

- [ ] **Step 1: Search for obsolete identity and label logic**

Run these searches and remove or update every hit:

```powershell
cd src
rg "round_number.*1000|1000.*round_number" app tests
rg "firstRoundNumber|firstRoundMatches" app tests
rg "firstWhere\('round_number', 2\)" app tests
rg "ROUND_START_HOUR" app tests
rg "Championship Final" app resources tests
```

Expected results after the earlier tasks:

- No losers-bracket magic numbers remain.
- No `firstRoundNumber()` or `firstRoundMatches()` remains.
- `TournamentShow` and `BracketShow` do not use `firstWhere('round_number', 2)`.
- `ROUND_START_HOUR` is gone from `SchedulingService`.
- `Championship Final` remains only as the single-elimination label and its test assertions.

- [ ] **Step 2: Run the complete suite**

```powershell
cd src
php artisan test
```

Every test must pass. Pay special attention to:

- `BracketServiceTest` (the old 8-team DE count is now 15).
- `BracketTest` (sections and labels).
- `ScheduleTest` (round ordering).
- `StandingsServiceTest` (head-to-head).
- The new builder, series, reset, playoff, and play-through tests.

- [ ] **Step 3: Run the quality gate**

```powershell
cd src
composer quality
```

`composer quality` must pass. If Pint rewrites formatting, rerun the command and confirm the second run is clean. If PHPStan reports a real type problem, fix the code rather than lowering the level or adding a blanket ignore.

- [ ] **Step 4: Verify the migration on a fresh database**

```powershell
cd src
php artisan migrate:fresh --seed
```

Then load the bracket page for the seeded tournament and confirm the single-elimination bracket still renders. Do not run this against a production database.

- [ ] **Step 5: Verify the manual acceptance scenarios**

Use the seeded tournaments and the test helpers to confirm:

1. A 4-, 8-, 16-, and 32-team DE bracket can be played to one champion.
2. A 5-, 6-, 7-, 9-, and 15-team DE bracket resolves byes without a stuck matchup.
3. A best-of-1 DE grand final plays the reset when the losers champion wins game one.
4. A best-of-3+ DE grand final never creates a reset.
5. A round-robin bracket with an odd field has no fake matches.
6. A round-robin group with `playoff_teams` blocks playoffs until the group is final, then seeds the top teams.
7. The bracket page shows winners, losers, grand final, group, and playoff sections with correct labels and series scores.
8. The tournament page champion matches the bracket page champion.

- [ ] **Step 6: Commit the verification fixes**

```powershell
git add -A
git commit -m "test(bracket): verify double elimination, series, and playoff flows"
```

If there is nothing to commit, skip this step and say so explicitly.

- [ ] **Step 7: Report the actual output**

Report the exact `php artisan test` summary and the exact `composer quality` result. Do not claim success without the real command output. If the shell still fails with “The system cannot find the path specified”, stop and report the blocker instead of guessing.

---

## Plan self-review

- Every design decision in the spec maps to a task: schema (1), pure builders (2–5), persistence and byes (6), single games (7), series (8), reset and champion (9), play-through (10), standings (11), scheduling (12), round-robin playoffs (13), creation UI (14), bracket UI (15), verification (16).
- The anchor-match model is used consistently: only anchors propagate, only anchors appear in the bracket tree, child games are conditional until activated.
- No task adds a foreign key to `series_id` or to the new team ID columns, because `matches` is hash-partitioned.
- The old single-elimination labels and tests are preserved; new labels are stage-aware.
- The plan’s DE totals include the conditional reset, and the reset is omitted when the grand final is a series.
- The round-robin builder never creates a null-side match row.
- The playoff bracket is separate from the group bracket and uses the same series settings.
- Every task ends with tests and a commit, and the final task reports real command output.
