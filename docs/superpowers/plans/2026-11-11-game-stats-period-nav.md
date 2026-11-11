# Game Stats Period Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 2K-style All/Q1–Q4/OT period navigator to `/game-stats` and make its player table show the full stat line already used by `/game-manage`.

**Architecture:** Keep the page as one Livewire component. Add a URL-synced `period` property and period-aware row/total helpers on `GameStatsShow`; render a segmented `<nav>` and a wider table in the existing Blade view; style the navigator with existing SCSS tokens. All filtering, sorting, and totals stay server-side and are covered by Livewire tests.

**Tech Stack:** Laravel 13 · PHP 8.4+ · Blade · Livewire 4 · Alpine 3 · Bootstrap 5.3 SCSS · Pest 4

## Global Constraints

- The product is the Laravel app in `src/`; `prototype/` is reference only and must not change.
- No Bootstrap JavaScript; Alpine owns client behaviour.
- Only `/game-stats` changes. Do not change `/game-manage`, its quarter tabs, or its inputs.
- No new database migration, model, seeder, or route.
- Use existing `hf-*` classes and CSS variables; do not introduce Tailwind or another framework.
- Run all commands from `src/`.
- Before claiming completion, run `composer quality` and report the actual output.

---

### Task 1: Period state and available periods

**Files:**
- Modify: `src/app/Livewire/Organizer/GameStatsShow.php`
- Test: `src/tests/Feature/Pages/GameStatsTest.php`

**Interfaces:**
- Consumes: existing `GameStatsShow::match` computed property and `MatchModel::playerQuarterStats`.
- Produces:
  - `public string $period` (`'all'` or a 0-based quarter index as a string)
  - `periods(): list<array{key: string, label: string, index: int|null}>`
  - `periodLabel(): string`
  - `hasQuarterData(): bool`
  - `setPeriod(string $period): void`

- [ ] **Step 1: Add the test helper and failing period tests**

Add `use App\Models\PlayerStatQuarter;` to the imports at the top of `src/tests/Feature/Pages/GameStatsTest.php`.

Add this helper near the top of the file, after `gsPlayerName()`:

```php
/**
 * @param  array<string, int>  $values
 */
function gsQuarterStat(MatchModel $match, PlayerStat $stat, int $quarter, array $values = []): PlayerStatQuarter
{
    return PlayerStatQuarter::query()->create([
        'match_id' => $match->id,
        'player_id' => $stat->player_id,
        'team_id' => $stat->team_id,
        'quarter_index' => $quarter,
        'field_goals_made' => $values['fgm'] ?? 0,
        'field_goals_attempted' => $values['fga'] ?? 0,
        'three_points_made' => $values['tpm'] ?? 0,
        'three_points_attempted' => $values['tpa'] ?? 0,
        'free_throws_made' => $values['ftm'] ?? 0,
        'free_throws_attempted' => $values['fta'] ?? 0,
        'rebounds' => $values['reb'] ?? 0,
        'assists' => $values['ast'] ?? 0,
        'steals' => $values['stl'] ?? 0,
        'blocks' => $values['blk'] ?? 0,
        'turnovers' => $values['to'] ?? 0,
        'fouls' => $values['pf'] ?? 0,
        'points' => $values['points'] ?? (($values['fgm'] ?? 0) * 2 + ($values['tpm'] ?? 0) * 3 + ($values['ftm'] ?? 0)),
    ]);
}
```

Append these tests at the end of the file:

```php
it('lists only periods that have per-player quarter rows', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    gsQuarterStat($match, $stat, 0);
    gsQuarterStat($match, $stat, 1);

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);

    expect(array_column($component->instance()->periods(), 'key'))->toBe(['all', '0', '1'])
        ->and(array_column($component->instance()->periods(), 'label'))->toBe(['All', 'Q1', 'Q2'])
        ->and($component->instance()->hasQuarterData())->toBeTrue();
});

it('falls back to all for an unknown or unavailable period', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    Livewire::withQueryParams(['period' => '0'])
        ->actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->assertSet('period', 'all');

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);
    $component->call('setPeriod', '99')->assertSet('period', 'all');

    gsQuarterStat($match, $stat, 0);

    Livewire::withQueryParams(['period' => '0'])
        ->actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->assertSet('period', '0');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Feature/Pages/GameStatsTest.php --filter="period"
```

Expected: FAIL with “Call to undefined method ...periods()” or an unknown `period` property.

- [ ] **Step 3: Implement the period state and computed periods**

In `src/app/Livewire/Organizer/GameStatsShow.php`:

Add the import:

```php
use Livewire\Attributes\Url;
```

Add the property next to `side`:

```php
#[Url(as: 'period', history: true, except: 'all')]
public string $period = 'all';
```

Add these computed properties after `quarters()`:

```php
/**
 * @return list<array{key: string, label: string, index: int|null}>
 */
#[Computed]
public function periods(): array
{
    $periods = [['key' => 'all', 'label' => 'All', 'index' => null]];
    $match = $this->match;

    if (! $match instanceof MatchModel) {
        return $periods;
    }

    $indexes = $match->playerQuarterStats
        ->pluck('quarter_index')
        ->filter(fn (mixed $index): bool => is_numeric($index))
        ->map(fn (mixed $index): int => (int) $index)
        ->unique()
        ->filter(fn (int $index): bool => $index >= 0 && $index < GameStatsService::MAX_QUARTERS)
        ->sort()
        ->values();

    foreach ($indexes as $index) {
        $periods[] = [
            'key' => (string) $index,
            'label' => $index < 4 ? 'Q'.($index + 1) : 'OT'.($index - 3),
            'index' => $index,
        ];
    }

    return $periods;
}

#[Computed]
public function periodLabel(): string
{
    foreach ($this->periods as $period) {
        if ($period['key'] === $this->period) {
            return $period['label'];
        }
    }

    return 'All';
}

#[Computed]
public function hasQuarterData(): bool
{
    return count($this->periods) > 1;
}
```

Add these methods near `sortBy()`:

```php
public function setPeriod(string $period): void
{
    $this->period = $this->resolvePeriod($period);
}

protected function resolvePeriod(string $period): string
{
    return in_array($period, array_column($this->periods, 'key'), true) ? $period : 'all';
}
```

At the end of `mount()`, after the `correct` query handling, add:

```php
$this->period = $this->resolvePeriod($this->period);
```

- [ ] **Step 4: Run the tests to verify they pass**

Run:

```powershell
php artisan test tests/Feature/Pages/GameStatsTest.php --filter="period"
```

Expected: PASS for both period tests.

- [ ] **Step 5: Commit**

```powershell
git add src/app/Livewire/Organizer/GameStatsShow.php src/tests/Feature/Pages/GameStatsTest.php
git commit -m "feat(stats): add period state to game stats"
```

---

### Task 2: Period-aware full stat table and navigator

This task is deliberately one unit: changing `rowsFor()` without changing the old per-player accordions would break rendering, so the data and view move together.

**Files:**
- Modify: `src/app/Livewire/Organizer/GameStatsShow.php`
- Modify: `src/resources/views/livewire/organizer/game-stats-show.blade.php`
- Modify: `src/resources/css/_hoopflow.scss`
- Test: `src/tests/Feature/Pages/GameStatsTest.php`

**Interfaces:**
- Consumes: `period`, `periods()`, `periodLabel()`, `hasQuarterData()`, `setPeriod()` from Task 1.
- Produces:
  - `rowsFor(?Team $team): array` with keys `playerId`, `name`, `number`, `position`, `fgm`, `fga`, `tpm`, `tpa`, `ftm`, `fta`, `reb`, `ast`, `stl`, `blk`, `to`, `pf`, `points`.
  - `totalsFor(array $rows): array` with the same numeric stat keys.
  - A rendered `<nav data-testid="stats-period-nav">` and a 12-column player table.

- [ ] **Step 1: Write the failing stat-line and view tests**

Append these tests to `src/tests/Feature/Pages/GameStatsTest.php`:

```php
it('builds the full stat line for the selected period and totals it', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->with('player')
        ->firstOrFail();

    $stat->forceFill([
        'field_goals_made' => 10,
        'field_goals_attempted' => 20,
        'three_points_made' => 2,
        'three_points_attempted' => 6,
        'free_throws_made' => 5,
        'free_throws_attempted' => 6,
        'rebounds' => 9,
        'assists' => 4,
        'steals' => 3,
        'blocks' => 1,
        'turnovers' => 2,
        'fouls' => 3,
        'points' => 29,
    ])->save();

    gsQuarterStat($match, $stat, 0, [
        'fgm' => 3, 'fga' => 7, 'tpm' => 1, 'tpa' => 3, 'ftm' => 2, 'fta' => 2,
        'reb' => 4, 'ast' => 2, 'stl' => 1, 'blk' => 0, 'to' => 1, 'pf' => 2, 'points' => 10,
    ]);
    gsQuarterStat($match, $stat, 1, [
        'fgm' => 2, 'fga' => 5, 'tpm' => 0, 'tpa' => 1, 'ftm' => 1, 'fta' => 2,
        'reb' => 3, 'ast' => 1, 'stl' => 1, 'blk' => 1, 'to' => 0, 'pf' => 1, 'points' => 5,
    ]);

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);
    $allRows = collect($component->instance()->rowsFor($match->homeTeam));
    $allRow = $allRows->firstWhere('playerId', $stat->player_id);

    expect($allRow)->not->toBeNull()
        ->and($allRow['fgm'])->toBe(10)
        ->and($allRow['fga'])->toBe(20)
        ->and($allRow['tpm'])->toBe(2)
        ->and($allRow['tpa'])->toBe(6)
        ->and($allRow['ftm'])->toBe(5)
        ->and($allRow['fta'])->toBe(6)
        ->and($allRow['reb'])->toBe(9)
        ->and($allRow['ast'])->toBe(4)
        ->and($allRow['stl'])->toBe(3)
        ->and($allRow['blk'])->toBe(1)
        ->and($allRow['to'])->toBe(2)
        ->and($allRow['pf'])->toBe(3)
        ->and($allRow['points'])->toBe(29);

    $component->instance()->setPeriod('0');
    $q1Rows = collect($component->instance()->rowsFor($match->homeTeam));
    $q1Row = $q1Rows->firstWhere('playerId', $stat->player_id);

    expect($q1Row)->not->toBeNull()
        ->and($q1Row['fgm'])->toBe(3)
        ->and($q1Row['fga'])->toBe(7)
        ->and($q1Row['points'])->toBe(10);

    $totals = $component->instance()->totalsFor($component->instance()->rowsFor($match->homeTeam));

    expect($totals['fgm'])->toBe(3)
        ->and($totals['fga'])->toBe(7)
        ->and($totals['points'])->toBe(10);
});

it('sorts the table by the selected period stats', function () {
    $match = gsMatch(8, 1);
    $stats = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->with('player')
        ->orderBy('id')
        ->take(2)
        ->get();

    expect($stats)->toHaveCount(2);

    $first = $stats[0];
    $second = $stats[1];

    gsQuarterStat($match, $first, 0, ['fgm' => 1, 'fga' => 1, 'points' => 2]);
    gsQuarterStat($match, $second, 0, ['fgm' => 5, 'fga' => 6, 'points' => 12]);

    $component = Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id]);

    $component->instance()->setPeriod('0');
    $component->instance()->sortKey = 'points';
    $component->instance()->sortDirection = 'desc';

    $rows = $component->instance()->rowsFor($match->homeTeam);

    expect($rows[0]['playerId'])->toBe($second->player_id)
        ->and($rows[1]['playerId'])->toBe($first->player_id);
});

it('renders the period navigator and the full stat-line columns', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    gsQuarterStat($match, $stat, 0, ['fgm' => 3, 'fga' => 7, 'tpm' => 1, 'tpa' => 3, 'ftm' => 2, 'fta' => 2, 'reb' => 4, 'ast' => 2, 'stl' => 1, 'to' => 1, 'pf' => 2, 'points' => 10]);
    gsQuarterStat($match, $stat, 1, ['fgm' => 2, 'fga' => 5, 'tpm' => 0, 'tpa' => 1, 'ftm' => 1, 'fta' => 2, 'reb' => 3, 'ast' => 1, 'blk' => 1, 'pf' => 1, 'points' => 5]);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->assertSeeHtml('data-testid="stats-period-nav"')
        ->assertSeeHtml('data-testid="stats-period-all"')
        ->assertSeeHtml('data-testid="stats-period-0"')
        ->assertSeeHtml('data-testid="stats-period-1"')
        ->assertDontSeeHtml('data-testid="stats-period-2"')
        ->assertSeeHtml('data-testid="stats-period-label"')
        ->assertSee('FG')
        ->assertSee('3PT')
        ->assertSee('FT')
        ->assertSee('STL')
        ->assertSee('BLK')
        ->assertSee('TO')
        ->assertSee('PF')
        ->call('setPeriod', '0')
        ->assertSee('3/7')
        ->assertSee('1/3')
        ->assertSee('2/2');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run:

```powershell
php artisan test tests/Feature/Pages/GameStatsTest.php --filter="full stat line|sorts the table|period navigator"
```

Expected: FAIL because `totalsFor()` does not exist, `rowsFor()` returns only PTS/AST/REB, and the view has no period nav.

- [ ] **Step 3: Replace `rowsFor()` and add the stat-line helpers**

Replace the existing `rowsFor()` method in `src/app/Livewire/Organizer/GameStatsShow.php` with:

```php
/**
 * @return array<int, array{
 *   playerId: int,
 *   name: string,
 *   number: int|string|null,
 *   position: string|null,
 *   fgm: int, fga: int,
 *   tpm: int, tpa: int,
 *   ftm: int, fta: int,
 *   reb: int, ast: int, stl: int, blk: int, to: int, pf: int,
 *   points: int
 * }>
 */
public function rowsFor(?Team $team): array
{
    $match = $this->match;

    if (! $team instanceof Team || ! $match instanceof MatchModel) {
        return [];
    }

    $term = mb_strtolower(trim($this->search));
    $key = in_array($this->sortKey, ['points', 'assists', 'rebounds'], true) ? $this->sortKey : 'points';
    $sortField = match ($key) {
        'assists' => 'ast',
        'rebounds' => 'reb',
        default => 'points',
    };
    $factor = $this->sortDirection === 'asc' ? 1 : -1;
    $quarterIndex = $this->period === 'all' ? null : (int) $this->period;
    $quarterRows = $match->playerQuarterStats->groupBy('player_id');

    return $match->playerStats
        ->where('team_id', $team->id)
        ->map(function (PlayerStat $stat) use ($quarterRows, $quarterIndex): array {
            $player = $stat->player;
            $name = trim(($player->first_name ?? '').' '.($player->last_name ?? ''));
            $line = $quarterIndex === null
                ? $this->statLineFromAggregate($stat)
                : $this->statLineFromQuarter(
                    $quarterRows->get((int) $stat->player_id, collect())
                        ->firstWhere('quarter_index', $quarterIndex)
                );

            return [
                'playerId' => (int) $stat->player_id,
                'name' => $name !== '' ? $name : 'Player',
                'number' => $player->jersey_number,
                'position' => $player->position,
                ...$line,
            ];
        })
        ->filter(fn (array $row): bool => $term === '' || str_contains(mb_strtolower($row['name']), $term))
        ->sort(fn (array $a, array $b): int => (($a[$sortField] <=> $b[$sortField]) * $factor) ?: strcasecmp($a['name'], $b['name']))
        ->values()
        ->all();
}
```

Add these methods after `rowsFor()`:

```php
/**
 * @param  array<int, array<string, mixed>>  $rows
 * @return array{fgm: int, fga: int, tpm: int, tpa: int, ftm: int, fta: int, reb: int, ast: int, stl: int, blk: int, to: int, pf: int, points: int}
 */
public function totalsFor(array $rows): array
{
    $totals = array_fill_keys(
        ['fgm', 'fga', 'tpm', 'tpa', 'ftm', 'fta', 'reb', 'ast', 'stl', 'blk', 'to', 'pf', 'points'],
        0,
    );

    foreach ($rows as $row) {
        foreach (array_keys($totals) as $key) {
            $totals[$key] += (int) ($row[$key] ?? 0);
        }
    }

    return $totals;
}

/**
 * @return array{fgm: int, fga: int, tpm: int, tpa: int, ftm: int, fta: int, reb: int, ast: int, stl: int, blk: int, to: int, pf: int, points: int}
 */
protected function statLineFromAggregate(PlayerStat $stat): array
{
    return [
        'fgm' => (int) ($stat->field_goals_made ?? 0),
        'fga' => (int) ($stat->field_goals_attempted ?? 0),
        'tpm' => (int) ($stat->three_points_made ?? 0),
        'tpa' => (int) ($stat->three_points_attempted ?? 0),
        'ftm' => (int) ($stat->free_throws_made ?? 0),
        'fta' => (int) ($stat->free_throws_attempted ?? 0),
        'reb' => (int) ($stat->rebounds ?? 0),
        'ast' => (int) ($stat->assists ?? 0),
        'stl' => (int) ($stat->steals ?? 0),
        'blk' => (int) ($stat->blocks ?? 0),
        'to' => (int) ($stat->turnovers ?? 0),
        'pf' => (int) ($stat->fouls ?? 0),
        'points' => (int) ($stat->points ?? 0),
    ];
}

/**
 * @return array{fgm: int, fga: int, tpm: int, tpa: int, ftm: int, fta: int, reb: int, ast: int, stl: int, blk: int, to: int, pf: int, points: int}
 */
protected function statLineFromQuarter(?PlayerStatQuarter $row): array
{
    if (! $row instanceof PlayerStatQuarter) {
        return array_fill_keys(
            ['fgm', 'fga', 'tpm', 'tpa', 'ftm', 'fta', 'reb', 'ast', 'stl', 'blk', 'to', 'pf', 'points'],
            0,
        );
    }

    $fgm = (int) ($row->field_goals_made ?? 0);
    $tpm = (int) ($row->three_points_made ?? 0);
    $ftm = (int) ($row->free_throws_made ?? 0);
    $points = (int) ($row->points ?? 0);

    return [
        'fgm' => $fgm,
        'fga' => (int) ($row->field_goals_attempted ?? 0),
        'tpm' => $tpm,
        'tpa' => (int) ($row->three_points_attempted ?? 0),
        'ftm' => $ftm,
        'fta' => (int) ($row->free_throws_attempted ?? 0),
        'reb' => (int) ($row->rebounds ?? 0),
        'ast' => (int) ($row->assists ?? 0),
        'stl' => (int) ($row->steals ?? 0),
        'blk' => (int) ($row->blocks ?? 0),
        'to' => (int) ($row->turnovers ?? 0),
        'pf' => (int) ($row->fouls ?? 0),
        'points' => $points > 0 ? $points : $fgm * 2 + $tpm * 3 + $ftm,
    ];
}
```

- [ ] **Step 4: Add the period navigator and period chip to the Blade view**

In `src/resources/views/livewire/organizer/game-stats-show.blade.php`, replace the player stats card heading block:

```blade
<div>
    <span class="hf-eyebrow">{{ $match->status === 'scheduled' && $hasStats ? 'Draft box score' : 'Official box score' }}</span>
    <h2 class="hf-section-title hf-mb-0">Player stats</h2>
</div>
```

with:

```blade
<div>
    <span class="hf-eyebrow">{{ $match->status === 'scheduled' && $hasStats ? 'Draft box score' : 'Official box score' }}</span>
    <div class="hf-flex hf-items-center hf-gap-2">
        <h2 class="hf-section-title hf-mb-0">Player stats</h2>
        <span class="hf-chip hf-chip--slate" data-testid="stats-period-label">{{ $this->periodLabel }}</span>
    </div>
</div>
```

Immediately after that header `</div>` (before the side-filter `hf-filter-row`), add:

```blade
@if ($home && $away)
    <nav class="hf-period-nav" aria-label="Player stat period" data-testid="stats-period-nav">
        @foreach ($this->periods as $period)
            <button
                type="button"
                class="hf-period-nav__btn {{ $this->period === $period['key'] ? 'is-active' : '' }}"
                wire:click="setPeriod('{{ $period['key'] }}')"
                aria-pressed="{{ $this->period === $period['key'] ? 'true' : 'false' }}"
                @if ($this->period === $period['key']) aria-current="true" @endif
                data-testid="stats-period-{{ $period['key'] }}"
            >{{ $period['label'] }}</button>
        @endforeach
    </nav>

    @if (! $this->hasQuarterData)
        <p class="hf-small hf-muted hf-mb-2" data-testid="stats-period-note">Per-quarter player stats were not recorded for this game.</p>
    @endif
@endif
```

- [ ] **Step 5: Replace the compact table with the full stat-line table**

In the same file, replace the `$totals = [...]` block inside the team loop with:

```blade
$totals = $this->totalsFor($rows);
```

Replace the table from `<table class="hf-table">` through `</table>` (inside the non-empty branch) with:

```blade
<table class="hf-table">
    <thead>
        <tr>
            <th scope="col" class="is-num">#</th>
            <th scope="col">Player</th>
            <th scope="col" class="is-num">FG</th>
            <th scope="col" class="is-num">3PT</th>
            <th scope="col" class="is-num">FT</th>
            <th scope="col" class="is-num">
                <button type="button" class="hf-section-link" wire:click="sortBy('rebounds')" data-testid="stats-sort-{{ $sideKey }}-rebounds">
                    REB
                    @if ($sortKey === 'rebounds')
                        <i class="bi bi-caret-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                    @else
                        <i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>
                    @endif
                </button>
            </th>
            <th scope="col" class="is-num">
                <button type="button" class="hf-section-link" wire:click="sortBy('assists')" data-testid="stats-sort-{{ $sideKey }}-assists">
                    AST
                    @if ($sortKey === 'assists')
                        <i class="bi bi-caret-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                    @else
                        <i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>
                    @endif
                </button>
            </th>
            <th scope="col" class="is-num">STL</th>
            <th scope="col" class="is-num">BLK</th>
            <th scope="col" class="is-num">TO</th>
            <th scope="col" class="is-num">PF</th>
            <th scope="col" class="is-num">
                <button type="button" class="hf-section-link" wire:click="sortBy('points')" data-testid="stats-sort-{{ $sideKey }}-points">
                    PTS
                    @if ($sortKey === 'points')
                        <i class="bi bi-caret-{{ $sortDirection === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
                    @else
                        <i class="bi bi-arrow-down-up hf-muted" aria-hidden="true"></i>
                    @endif
                </button>
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr data-testid="stats-row-{{ $sideKey }}-{{ $row['playerId'] }}">
                <td class="is-num">{{ $row['number'] ?? '–' }}</td>
                <td>
                    <strong>{{ $row['name'] }}</strong>
                    <span class="hf-small hf-muted" style="display: block">{{ $row['position'] ?: 'Player' }}</span>
                </td>
                <td class="is-num">{{ $row['fgm'] }}/{{ $row['fga'] }}</td>
                <td class="is-num">{{ $row['tpm'] }}/{{ $row['tpa'] }}</td>
                <td class="is-num">{{ $row['ftm'] }}/{{ $row['fta'] }}</td>
                <td class="is-num">{{ $row['reb'] }}</td>
                <td class="is-num">{{ $row['ast'] }}</td>
                <td class="is-num">{{ $row['stl'] }}</td>
                <td class="is-num">{{ $row['blk'] }}</td>
                <td class="is-num">{{ $row['to'] }}</td>
                <td class="is-num">{{ $row['pf'] }}</td>
                <td class="is-num">{{ $row['points'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2"><strong>Team totals</strong></td>
            <td class="is-num"><strong>{{ $totals['fgm'] }}/{{ $totals['fga'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['tpm'] }}/{{ $totals['tpa'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['ftm'] }}/{{ $totals['fta'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['reb'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['ast'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['stl'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['blk'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['to'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['pf'] }}</strong></td>
            <td class="is-num"><strong>{{ $totals['points'] }}</strong></td>
        </tr>
    </tfoot>
</table>
```

Delete the entire `@foreach ($rows as $row) ... @endforeach` block that renders `<details data-testid="stats-player-quarters-...">` after the table.

- [ ] **Step 6: Add the period navigator styles**

In `src/resources/css/_hoopflow.scss`, add after `.hf-filter-row`:

```scss
.hf-period-nav {
  display: flex;
  gap: 6px;
  max-width: 100%;
  margin-bottom: 14px;
  padding: 4px;
  overflow-x: auto;
  border: 1px solid var(--hf-border);
  border-radius: var(--hf-pill);
  background: var(--hf-surface-2);
  scrollbar-width: thin;
}

.hf-period-nav__btn {
  flex: 0 0 auto;
  min-height: 36px;
  padding: 0 14px;
  border: 0;
  border-radius: var(--hf-pill);
  background: transparent;
  color: var(--hf-muted);
  cursor: pointer;
  font-family: var(--hf-font-display);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hf-period-nav__btn:hover {
  background: color-mix(in srgb, var(--hf-ink) 6%, transparent);
  color: var(--hf-ink);
}

.hf-period-nav__btn.is-active {
  background: var(--hf-primary);
  box-shadow: 0 2px 0 var(--hf-primary-pressed);
  color: #fff;
}

.hf-period-nav__btn:focus-visible {
  outline: 2px solid var(--hf-primary);
  outline-offset: 2px;
}
```

- [ ] **Step 7: Run the new tests to verify they pass**

Run:

```powershell
php artisan test tests/Feature/Pages/GameStatsTest.php --filter="full stat line|sorts the table|period navigator"
```

Expected: PASS.

- [ ] **Step 8: Commit**

```powershell
git add src/app/Livewire/Organizer/GameStatsShow.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/resources/css/_hoopflow.scss src/tests/Feature/Pages/GameStatsTest.php
git commit -m "feat(stats): render period navigator and full box score"
```

---

### Task 3: Update legacy tests, edge cases, and remove dead code

**Files:**
- Modify: `src/resources/views/livewire/organizer/game-stats-show.blade.php` (only if the old accordion block remains)
- Modify: `src/app/Livewire/Organizer/GameStatsShow.php`
- Test: `src/tests/Feature/Pages/GameStatsTest.php`

**Interfaces:**
- Consumes: completed period nav/table from Task 2.
- Produces: no `stats-player-quarters-*` markup or `shortQuarter()` helper; totals-only games show only the All period and the explanatory note.

- [ ] **Step 1: Run the full page test to see the stale accordion test fail**

Run:

```powershell
php artisan test tests/Feature/Pages/GameStatsTest.php
```

Expected: the old test named `shows per-player quarter details, the draft label, and the in-game link` fails because the accordion markup is gone.

- [ ] **Step 2: Replace the stale test and add the totals-only test**

Replace the old test with:

```php
it('shows the period nav for a draft box score and not the old quarter accordions', function () {
    $match = gsMatch(8, 3);
    $loaded = app(GameStatsService::class)->load($match);
    $playerId = (int) array_key_first($loaded['stats']['team1']);
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fgm'] = 2;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fga'] = 4;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['ftm'] = 1;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fta'] = 2;

    app(GameStatsService::class)->saveDraft($match, $loaded);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSee('Draft box score')
        ->assertSeeHtml('data-testid="stats-in-game"')
        ->assertSeeHtml('data-testid="stats-period-nav"')
        ->assertSeeHtml('data-testid="stats-period-0"')
        ->assertSeeHtml('data-testid="stats-period-3"')
        ->assertDontSeeHtml('data-testid="stats-player-quarters-team1-'.$playerId.'"');
});
```

Append:

```php
it('shows only the all period when player quarter stats were not recorded', function () {
    $match = gsMatch(8, 1);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-period-all"')
        ->assertDontSeeHtml('data-testid="stats-period-0"')
        ->assertSee('Per-quarter player stats were not recorded for this game.');
});
```

- [ ] **Step 3: Remove the unused helper**

In `src/app/Livewire/Organizer/GameStatsShow.php`, delete the now-unused `shortQuarter()` method.

If the old accordion block is still present in `src/resources/views/livewire/organizer/game-stats-show.blade.php`, delete it now.

- [ ] **Step 4: Run the full GameStats test file**

Run:

```powershell
php artisan test tests/Feature/Pages/GameStatsTest.php
```

Expected: PASS for the entire file.

- [ ] **Step 5: Commit**

```powershell
git add src/resources/views/livewire/organizer/game-stats-show.blade.php src/app/Livewire/Organizer/GameStatsShow.php src/tests/Feature/Pages/GameStatsTest.php
git commit -m "refactor(stats): replace quarter accordions with period nav"
```

---

### Task 4: Full quality gate and manual parity check

**Files:**
- Verify only; fix any files flagged by quality tools.

**Interfaces:**
- Consumes: all previous tasks.
- Produces: a passing quality gate and a verified page.

- [ ] **Step 1: Run the full quality gate**

From `src/`:

```powershell
composer quality
```

Expected: Pint, PHPStan, and the full Pest suite all pass. If Pint reports formatting changes, run `composer format`, then re-run `composer quality`.

- [ ] **Step 2: Verify the route remains unchanged**

Run:

```powershell
php artisan route:list --name=gameStats
```

Expected: one `GET|HEAD /game-stats` route named `gameStats`; no new route was added.

- [ ] **Step 3: Manually compare the page with the prototype**

Start the app:

```powershell
npm run dev
php artisan serve
```

Open `/game-stats?match=<a match with stats>` and compare against `prototype/games/stats.html`:

- Header, scoreboard, correction banner, and result controls match the prototype.
- The period bar shows `All` and recorded periods only.
- Switching `Q1 → Q2 → All` updates player rows, PTS, sort order, and team totals.
- Search, side chips, and team select still work while a quarter is selected.
- The page works in light and dark themes and at a narrow viewport.
- The “Enter final score” / “Correct score” dialogs still open and behave as before.

- [ ] **Step 4: Commit any verification fixes**

If the quality gate or manual check required edits:

```powershell
git add src/app/Livewire/Organizer/GameStatsShow.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/resources/css/_hoopflow.scss src/tests/Feature/Pages/GameStatsTest.php
git commit -m "fix(stats): polish period navigation"
```

If no files changed, skip this commit.
