# Tournament Status Lifecycle Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement the approved design in `docs/superpowers/specs/2026-11-12-tournament-status-lifecycle-design.md`: bracket generation activates the tournament and closes registration, a championship result completes it, a round-robin without playoffs completes when its last group game resolves, late approvals are blocked after seeds lock, and `completed` is presented consistently.

**Architecture:** Add a small `TournamentLifecycleService` that owns the two status transitions and their audit entries. Call it from the existing `BracketService` domain events — bracket generation, playoff generation, score/forfeit entry, and score correction — inside the transactions that already exist. Keep Livewire components responsible for authorization, validation display, and rendering. No schema migration and no date-based scheduler.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4, Blade, Bootstrap SCSS, Pest 4, SQLite for tests.

**Spec:** `docs/superpowers/specs/2026-11-12-tournament-status-lifecycle-design.md`

**Conventions:** Work from the repository root. All application commands run in `src`. Follow existing Pest style (`it(...)`, top-level helper functions, `Livewire::actingAs`). Run tests sequentially — never use `--parallel` or compacted test commands. Do not touch `.obsidian/` or `.kilo/`. Commit only the files listed in each task.

---

## Pre-flight

1. Confirm the working tree and note unrelated changes:

```powershell
git status --short
```

Expected: only `.obsidian/graph.json` is modified and `.kilo/` is untracked. Leave both alone.

2. Read the spec and the files this plan changes:

```powershell
Get-Content docs/superpowers/specs/2026-11-12-tournament-status-lifecycle-design.md
Get-Content src/app/Services/BracketService.php
Get-Content src/app/Models/Tournament.php
Get-Content src/app/Livewire/Organizer/SubmissionsIndex.php
```

3. Establish the baseline:

```powershell
cd src
php artisan test
```

Expected: the existing suite passes (baseline was `448 passed`). Record the actual count.

4. Confirm the branch and latest commit:

```powershell
git log -1 --oneline
git branch --show-current
```

Expected: `feat/prototype-ui-interactivity` at or after `3cf9d8c docs: design tournament status lifecycle`.

5. Commit this plan before starting Task 1:

```powershell
git add docs/superpowers/plans/2026-11-12-tournament-status-lifecycle.md
git commit -m "docs: plan tournament status lifecycle"
```

---

## Task 1: Add the lifecycle service with activation and champion completion

**Files:**
- Create: `src/app/Services/TournamentLifecycleService.php`
- Create: `src/tests/Unit/Services/TournamentLifecycleServiceTest.php`

**Why:** Status transitions need one explicit owner. Starting with activation and champion completion keeps the first task small; the round-robin fallback lands in Task 4.

### Step 1: Write the failing unit tests

Create `src/tests/Unit/Services/TournamentLifecycleServiceTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\TournamentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tlsUnitTournament(string $status = 'registration-open', array $rules = []): Tournament
{
    return Tournament::factory()->create([
        'status' => $status,
        'rules_json' => array_merge(['format' => 'round-robin'], $rules),
    ]);
}

it('activates a pre-start tournament', function (string $status) {
    $tournament = tlsUnitTournament($status);

    $changed = app(TournamentLifecycleService::class)->activate($tournament);

    $log = AuditLog::where('action', 'tournament.activate')->firstOrFail();

    expect($changed)->toBeTrue()
        ->and($tournament->fresh()->status)->toBe('active')
        ->and($log->entity_type)->toBe('tournament')
        ->and($log->entity_id)->toBe($tournament->id)
        ->and($log->before_json)->toBe(['status' => $status])
        ->and($log->after_json)->toBe(['status' => 'active']);
})->with(['draft', 'upcoming', 'registration-open']);

it('does not reactivate an active or completed tournament', function (string $status) {
    $tournament = tlsUnitTournament($status);

    $changed = app(TournamentLifecycleService::class)->activate($tournament);

    expect($changed)->toBeFalse()
        ->and($tournament->fresh()->status)->toBe($status)
        ->and(AuditLog::where('action', 'tournament.activate')->count())->toBe(0);
})->with(['active', 'completed']);

it('completes a tournament when a champion is resolved', function () {
    $tournament = tlsUnitTournament('active', ['format' => 'single-elimination']);
    $champion = Team::factory()->create();

    $changed = app(TournamentLifecycleService::class)->syncCompletion($tournament, $champion);

    $log = AuditLog::where('action', 'tournament.complete')->firstOrFail();

    expect($changed)->toBeTrue()
        ->and($tournament->fresh()->status)->toBe('completed')
        ->and($log->before_json)->toBe(['status' => 'active'])
        ->and($log->after_json)->toBe([
            'status' => 'completed',
            'champion_team_id' => $champion->id,
        ]);
});

it('does not complete a tournament without a champion', function () {
    $tournament = tlsUnitTournament('active', ['format' => 'single-elimination']);

    $changed = app(TournamentLifecycleService::class)->syncCompletion($tournament);

    expect($changed)->toBeFalse()
        ->and($tournament->fresh()->status)->toBe('active')
        ->and(AuditLog::where('action', 'tournament.complete')->count())->toBe(0);
});

it('does not duplicate the completion audit entry', function () {
    $tournament = tlsUnitTournament('active', ['format' => 'single-elimination']);
    $champion = Team::factory()->create();
    $service = app(TournamentLifecycleService::class);

    $service->syncCompletion($tournament, $champion);
    $service->syncCompletion($tournament->fresh(), $champion);

    expect(AuditLog::where('action', 'tournament.complete')->count())->toBe(1);
});
```

### Step 2: Run the tests and confirm the failure

```powershell
cd src
php artisan test tests/Unit/Services/TournamentLifecycleServiceTest.php
```

Expected: failure because `App\Services\TournamentLifecycleService` does not exist.

### Step 3: Implement the service

Create `src/app/Services/TournamentLifecycleService.php`:

```php
<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Tournament;

class TournamentLifecycleService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function activate(Tournament $tournament): bool
    {
        $tournament->refresh();

        if (! in_array($tournament->status, ['draft', 'upcoming', 'registration-open'], true)) {
            return false;
        }

        $before = $tournament->status;
        $tournament->update(['status' => 'active']);

        $this->auditLogger->log(
            'tournament.activate',
            'tournament',
            $tournament->id,
            ['status' => $before],
            ['status' => 'active'],
            $tournament->organization_id,
        );

        return true;
    }

    public function syncCompletion(Tournament $tournament, ?Team $champion = null): bool
    {
        $tournament->refresh();

        if ($tournament->status === 'completed') {
            return false;
        }

        if (! $champion instanceof Team) {
            return false;
        }

        return $this->complete($tournament, $champion);
    }

    protected function complete(Tournament $tournament, ?Team $champion): bool
    {
        $before = $tournament->status;
        $tournament->update(['status' => 'completed']);

        $this->auditLogger->log(
            'tournament.complete',
            'tournament',
            $tournament->id,
            ['status' => $before],
            ['status' => 'completed', 'champion_team_id' => $champion?->id],
            $tournament->organization_id,
        );

        return true;
    }
}
```

### Step 4: Run the tests and confirm they pass

```powershell
php artisan test tests/Unit/Services/TournamentLifecycleServiceTest.php
```

Expected: all tests pass.

### Step 5: Commit

```powershell
cd ..
git add src/app/Services/TournamentLifecycleService.php src/tests/Unit/Services/TournamentLifecycleServiceTest.php
git commit -m "feat: add tournament lifecycle service"
```

---

## Task 2: Activate the tournament when a bracket is generated

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Create: `src/tests/Feature/Pages/TournamentLifecycleTest.php`

**Why:** Bracket generation is the moment seeds lock and registration must close. Playoff generation gets the same defensive hook.

### Step 1: Write the failing feature test

Create `src/tests/Feature/Pages/TournamentLifecycleTest.php`:

```php
<?php

use App\Models\AuditLog;
use App\Models\Tournament;
use App\Models\User;
use App\Services\BracketService;
use Database\Seeders\PrototypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PrototypeSeeder::class);
});

it('activates the tournament when a bracket is generated and closes coach registration', function () {
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    $coach = User::where('email', 'coach.marcus@hoopflow.ph')->firstOrFail();

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="show-register"');

    (new BracketService)->generate($tournament, 'single-elimination');

    expect($tournament->fresh()->status)->toBe('active')
        ->and(AuditLog::where('action', 'tournament.activate')->count())->toBe(1);

    $this->actingAs($coach)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertDontSeeHtml('data-testid="show-register"');
});
```

### Step 2: Run the test and confirm the failure

```powershell
cd src
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php
```

Expected: failure because the tournament stays `registration-open` after generation.

### Step 3: Add the activation hooks

In `src/app/Services/BracketService.php`, inside `generate()`'s existing transaction, immediately after the `bracket.generate` audit call and before `return $result;`, add:

```php
            app(TournamentLifecycleService::class)->activate($tournament);
```

The surrounding block must read:

```php
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
                    'shuffled' => $shuffle,
                    'seedingMethod' => $shuffle ? 'random' : 'manual',
                ],
                $tournament->organization_id,
            );

            app(TournamentLifecycleService::class)->activate($tournament);

            return $result;
```

In `generatePlayoffs()`, inside its existing transaction, immediately after the `bracket.playoffs` audit call and before `return $result;`, add the same call:

```php
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

            app(TournamentLifecycleService::class)->activate($tournament);

            return $result;
```

`BracketService` and `TournamentLifecycleService` are in the same namespace, so no import is required. Resolve the lifecycle service with `app(...)` to preserve existing tests that instantiate `new BracketService`.

### Step 4: Run the test and confirm it passes

```powershell
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php
```

Expected: the activation test passes.

### Step 5: Run the bracket regression tests

```powershell
php artisan test tests/Unit/Services/BracketServiceTest.php
```

Expected: all bracket service tests still pass.

### Step 6: Commit

```powershell
cd ..
git add src/app/Services/BracketService.php src/tests/Feature/Pages/TournamentLifecycleTest.php
git commit -m "feat: activate tournaments when brackets are generated"
```

---

## Task 3: Complete the tournament when a result decides the champion

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Modify: `src/tests/Feature/Pages/TournamentLifecycleTest.php`

**Why:** `applyGameResult()` is the shared result path for `recordScore()`, `recordForfeit()`, and `finalizeMatch()`. One hook there covers score entry, forfeits, series clinches, and double-elimination grand finals.

### Step 1: Add the failing feature tests

In `src/tests/Feature/Pages/TournamentLifecycleTest.php`, add these imports:

```php
use App\Models\MatchModel;
```

Add these helpers after the `beforeEach` block:

```php
function tlsPlayableMatch(Tournament $tournament): ?MatchModel
{
    return MatchModel::query()
        ->where('tournament_id', $tournament->id)
        ->where('status', 'scheduled')
        ->whereNotNull('home_team_id')
        ->whereNotNull('away_team_id')
        ->orderBy('id')
        ->first();
}

function tlsPlayOut(BracketService $service, Tournament $tournament): void
{
    $guard = 0;

    while ($guard++ < 500) {
        $match = tlsPlayableMatch($tournament);

        if (! $match instanceof MatchModel) {
            return;
        }

        $service->recordScore($match, 80, 70);
    }

    throw new RuntimeException('The bracket did not finish within 500 results.');
}
```

Append these tests:

```php
it('completes a single-elimination tournament when the final is decided', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');

    tlsPlayOut($service, $tournament);

    expect($tournament->fresh()->status)->toBe('completed')
        ->and(AuditLog::where('action', 'tournament.complete')->count())->toBe(1);
});

it('does not complete a single-elimination tournament before the final', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');

    $first = tlsPlayableMatch($tournament);

    expect($first)->toBeInstanceOf(MatchModel::class);

    $service->recordScore($first, 80, 70);

    expect($tournament->fresh()->status)->toBe('active');

    tlsPlayOut($service, $tournament);

    expect($tournament->fresh()->status)->toBe('completed');
});

it('completes a double-elimination tournament when the grand final is decided', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['format' => 'double-elimination']),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'double-elimination');

    tlsPlayOut($service, $tournament->fresh());

    expect($tournament->fresh()->status)->toBe('completed');
});

it('completes a best-of series final when the series is clinched', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], ['best_of_final' => 3]),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'single-elimination');

    tlsPlayOut($service, $tournament->fresh());

    expect($tournament->fresh()->status)->toBe('completed');
});
```

### Step 2: Run the tests and confirm the failures

```powershell
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php
```

Expected: the completion tests fail because the tournament stays `active`, and the premature-completion regression test fails once the champion query is corrected.

### Step 3: Call completion from the shared result path

In `src/app/Services/BracketService.php`, at the end of `applyGameResult()`, add the completion call after the grand-final reset block:

```php
        if ($game->series_id === null && $game->stage === 'grand_final') {
            $this->syncGrandFinalReset($game);
        }

        $this->syncTournamentCompletion($game);

        return ['winnerId' => $winnerId, 'loserId' => $loserId];
    }
```

Add the helper immediately after `applyGameResult()`:

```php
    protected function syncTournamentCompletion(MatchModel $match): void
    {
        $tournament = $match->tournament;

        if (! $tournament instanceof Tournament) {
            return;
        }

        app(TournamentLifecycleService::class)->syncCompletion(
            $tournament,
            $this->champion($tournament),
        );
    }
```

The champion is resolved by `BracketService::champion()` and passed into the lifecycle service. The lifecycle service never injects `BracketService`, so there is no circular dependency.

**Required correction:** `singleEliminationChampion()` currently selects the final with `orderByDesc('round_number')`. Single elimination numbers rounds by bracket size (`16, 8, 4, 2`), so the final is the **lowest** round number. Change that query to `orderBy('round_number')` before wiring completion; otherwise the first-round winner is crowned and the tournament completes too early. The regression test above covers this.

### Step 4: Run the tests and confirm they pass

```powershell
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php
```

Expected: activation, single-elimination, double-elimination, and series tests pass.

### Step 5: Run the score/series regressions

```powershell
php artisan test tests/Unit/Services/Bracket/SeriesLifecycleTest.php
php artisan test tests/Unit/Services/Bracket/DoubleEliminationPlayThroughTest.php
php artisan test tests/Feature/Pages/GameStatsTest.php
```

Expected: all pass.

### Step 6: Commit

```powershell
cd ..
git add src/app/Services/BracketService.php src/tests/Feature/Pages/TournamentLifecycleTest.php
git commit -m "feat: complete tournaments when champions are decided"
```

---

## Task 4: Complete a round-robin without playoffs when the group stage ends

**Files:**
- Modify: `src/app/Services/TournamentLifecycleService.php`
- Modify: `src/tests/Unit/Services/TournamentLifecycleServiceTest.php`
- Modify: `src/tests/Feature/Pages/TournamentLifecycleTest.php`

**Why:** A round-robin tournament with no playoff stage has no championship match. Its last group result is the completion event, but only when `playoff_teams` is `0`/absent and no playoff bracket exists.

### Step 1: Add the failing unit tests

In `src/tests/Unit/Services/TournamentLifecycleServiceTest.php`, add these imports:

```php
use App\Models\Bracket;
use App\Models\Division;
use App\Models\MatchModel;
```

Add these helpers after `tlsUnitTournament()`:

```php
function tlsUnitGroupBracket(Tournament $tournament, string $phase = 'group'): Bracket
{
    $division = Division::factory()->create(['tournament_id' => $tournament->id]);

    return Bracket::factory()->create([
        'division_id' => $division->id,
        'format' => 'round_robin',
        'phase' => $phase,
    ]);
}

function tlsUnitMatch(Tournament $tournament, Bracket $bracket, string $status): MatchModel
{
    return MatchModel::factory()->create([
        'bracket_id' => $bracket->id,
        'tournament_id' => $tournament->id,
        'stage' => 'round_robin',
        'status' => $status,
    ]);
}
```

Append these tests:

```php
it('completes a round-robin tournament when every group game is resolved', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');
    tlsUnitMatch($tournament, $group, 'forfeit');

    $changed = app(TournamentLifecycleService::class)->syncCompletion($tournament);

    expect($changed)->toBeTrue()
        ->and($tournament->fresh()->status)->toBe('completed');
});

it('waits for playoffs when playoff teams are configured', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 4]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse()
        ->and($tournament->fresh()->status)->toBe('active');
});

it('does not complete a round-robin while a group game is unresolved', function (string $status) {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');
    tlsUnitMatch($tournament, $group, $status);

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
})->with(['scheduled', 'in_progress', 'cancelled']);

it('waits when a playoff bracket already exists', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    $group = tlsUnitGroupBracket($tournament);
    tlsUnitMatch($tournament, $group, 'final');
    tlsUnitGroupBracket($tournament, 'playoffs');

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
});

it('does not complete a round-robin without a group bracket', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
});

it('does not complete a round-robin with an empty group bracket', function () {
    $tournament = tlsUnitTournament('active', ['playoff_teams' => 0]);
    tlsUnitGroupBracket($tournament);

    expect(app(TournamentLifecycleService::class)->syncCompletion($tournament))->toBeFalse();
});
```

### Step 2: Run the unit tests and confirm the failures

```powershell
cd src
php artisan test tests/Unit/Services/TournamentLifecycleServiceTest.php
```

Expected: the round-robin completion test fails; the guard tests already pass because completion always returns `false` without a champion.

### Step 3: Add the round-robin fallback

In `src/app/Services/TournamentLifecycleService.php`, add these imports:

```php
use App\Models\Bracket;
use App\Models\MatchModel;
```

Replace the body of `syncCompletion()` with:

```php
    public function syncCompletion(Tournament $tournament, ?Team $champion = null): bool
    {
        $tournament->refresh();

        if ($tournament->status === 'completed') {
            return false;
        }

        if (! $champion instanceof Team && ! $this->roundRobinGroupStageFinished($tournament)) {
            return false;
        }

        return $this->complete($tournament, $champion);
    }
```

Add this method before `complete()`:

```php
    protected function roundRobinGroupStageFinished(Tournament $tournament): bool
    {
        if ($tournament->format() !== 'round-robin') {
            return false;
        }

        $playoffTeams = $tournament->ruleValue('playoff_teams', 0);

        if (is_numeric($playoffTeams) && (int) $playoffTeams > 0) {
            return false;
        }

        $divisionIds = $tournament->divisions()->pluck('id');

        if ($divisionIds->isEmpty()) {
            return false;
        }

        if (Bracket::whereIn('division_id', $divisionIds)->where('phase', 'playoffs')->exists()) {
            return false;
        }

        $group = Bracket::whereIn('division_id', $divisionIds)->where('phase', 'group')->first();

        if (! $group instanceof Bracket) {
            return false;
        }

        $matches = MatchModel::where('bracket_id', $group->id)->get();

        return $matches->isNotEmpty()
            && $matches->every(fn (MatchModel $match): bool => in_array($match->status, ['final', 'forfeit'], true));
    }
```

The fallback is strict: `cancelled` and `conditional` matches block completion, because the requirement is every group game `final` or `forfeit`.

### Step 4: Run the unit tests and confirm they pass

```powershell
php artisan test tests/Unit/Services/TournamentLifecycleServiceTest.php
```

Expected: all unit tests pass.

### Step 5: Add the failing feature tests

In `src/tests/Feature/Pages/TournamentLifecycleTest.php`, append:

```php
it('completes a round-robin tournament without playoffs after the last group game', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 0,
        ]),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'round-robin');

    $matches = MatchModel::where('tournament_id', $tournament->id)->orderBy('id')->get();
    $last = $matches->pop();

    foreach ($matches as $match) {
        $service->recordScore($match->fresh(), 80, 70);
    }

    expect($tournament->fresh()->status)->toBe('active');

    $service->recordScore($last->fresh(), 80, 70);

    expect($tournament->fresh()->status)->toBe('completed');
});

it('waits for the playoff final in a round-robin tournament with playoffs', function () {
    $tournament = Tournament::where('slug', 'cdo-grassroots-classic')->firstOrFail();
    $tournament->update([
        'rules_json' => array_merge($tournament->rules_json ?? [], [
            'format' => 'round-robin',
            'playoff_teams' => 4,
        ]),
    ]);

    $service = new BracketService;
    $service->generate($tournament->fresh(), 'round-robin');

    tlsPlayOut($service, $tournament);

    expect($tournament->fresh()->status)->toBe('active');

    $service->generatePlayoffs($tournament->fresh());

    tlsPlayOut($service, $tournament);

    expect($tournament->fresh()->status)->toBe('completed');
});
```

### Step 6: Run the feature tests

```powershell
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php
```

Expected: both round-robin tests pass.

### Step 7: Commit

```powershell
cd ..
git add src/app/Services/TournamentLifecycleService.php src/tests/Unit/Services/TournamentLifecycleServiceTest.php src/tests/Feature/Pages/TournamentLifecycleTest.php
git commit -m "feat: complete round-robin tournaments without playoffs"
```

---

## Task 5: Re-evaluate completion when a result is corrected

**Files:**
- Modify: `src/app/Services/BracketService.php`
- Modify: `src/tests/Feature/Pages/TournamentLifecycleTest.php`

**Why:** `correctScore()` writes results outside `applyGameResult()`. A correction must not be able to leave a decided tournament missing its completion state, and it must never reopen a completed tournament.

### Step 1: Add the failing feature tests

In `src/tests/Feature/Pages/TournamentLifecycleTest.php`, add this helper after `tlsPlayOut()`:

```php
function tlsPlayUntilFinal(BracketService $service, Tournament $tournament, MatchModel $final): void
{
    $guard = 0;

    while ($guard++ < 500) {
        $match = MatchModel::query()
            ->where('tournament_id', $tournament->id)
            ->where('status', 'scheduled')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->where('id', '!=', $final->id)
            ->orderBy('id')
            ->first();

        if (! $match instanceof MatchModel) {
            return;
        }

        $service->recordScore($match, 80, 70);
    }

    throw new RuntimeException('The bracket did not reach the final within 500 results.');
}
```

Append these tests:

```php
it('completes a legacy tournament when its recorded final is corrected', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('stage', 'winners')
        ->orderBy('round_number')
        ->firstOrFail();

    tlsPlayUntilFinal($service, $tournament, $final);

    $final->refresh();

    expect($final->home_team_id)->not->toBeNull()
        ->and($final->away_team_id)->not->toBeNull();

    $final->update([
        'status' => 'final',
        'home_score' => 80,
        'away_score' => 70,
        'winner_team_id' => $final->home_team_id,
        'game_winner_team_id' => $final->home_team_id,
        'completed_at' => now(),
    ]);

    expect($tournament->fresh()->status)->toBe('active');

    $service->correctScore($final->fresh(), 70, 80, 'Corrected final.');

    expect($tournament->fresh()->status)->toBe('completed')
        ->and(AuditLog::where('action', 'tournament.complete')->count())->toBe(1);
});

it('keeps a completed tournament completed when the final is corrected', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $service = new BracketService;
    $service->generate($tournament, 'single-elimination');

    tlsPlayOut($service, $tournament);

    $final = MatchModel::where('tournament_id', $tournament->id)
        ->whereNull('series_id')
        ->where('stage', 'winners')
        ->orderBy('round_number')
        ->firstOrFail();

    $service->correctScore(
        $final,
        (int) $final->away_score,
        (int) $final->home_score,
        'Corrected final.',
    );

    expect($tournament->fresh()->status)->toBe('completed')
        ->and(AuditLog::where('action', 'tournament.complete')->count())->toBe(1);
});
```

### Step 2: Run the tests and confirm the expected failure

```powershell
cd src
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="corrected"
```

Expected: `completes a legacy tournament when its recorded final is corrected` fails because the tournament stays `active`; `keeps a completed tournament completed when the final is corrected` passes because nothing reopens a completed tournament.

### Step 3: Add the correction hook

In `src/app/Services/BracketService.php`, inside `correctScore()`'s existing transaction, immediately after `$this->writeAudit($match, 'score.correct', ...)` and before the closing `});`, add:

```php
            $this->syncTournamentCompletion($anchor);
```

The end of the transaction must read:

```php
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

            $this->syncTournamentCompletion($anchor);
        });
    }
```

`syncTournamentCompletion()` refreshes the tournament and exits immediately when it is already `completed`, so a correction cannot reopen it and cannot write a duplicate `tournament.complete` entry.

### Step 4: Run the correction tests and regressions

```powershell
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="corrected"
php artisan test tests/Feature/Pages/GameStatsTest.php
```

Expected: both correction tests pass and the game-stats suite still passes.

### Step 5: Commit

```powershell
cd ..
git add src/app/Services/BracketService.php src/tests/Feature/Pages/TournamentLifecycleTest.php
git commit -m "feat: sync tournament completion after score corrections"
```

---

## Task 6: Block late approvals after seeds lock while keeping rejection available

**Files:**
- Modify: `src/app/Livewire/Organizer/SubmissionsIndex.php`
- Modify: `src/resources/views/livewire/organizer/submissions-index.blade.php`
- Modify: `src/tests/Feature/Pages/TournamentLifecycleTest.php`

**Why:** Once the bracket exists, an approved team cannot enter the seeded field. Approvals must close, but organizers still need to clear the pending queue with a reasoned rejection.

### Step 1: Add the failing feature test

In `src/tests/Feature/Pages/TournamentLifecycleTest.php`, add these imports:

```php
use App\Livewire\Organizer\SubmissionsIndex;
use App\Models\Registration;
use Livewire\Livewire;
```

Append:

```php
it('blocks approvals after seeds lock but still allows rejection', function () {
    $tournament = Tournament::where('slug', 'pasig-youth-hoopfest')->firstOrFail();
    (new BracketService)->generate($tournament, 'single-elimination');

    $registration = Registration::where('tournament_id', $tournament->id)
        ->where('status', 'submitted')
        ->firstOrFail();

    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('approve', $registration->id)
        ->assertSet('approvingId', null)
        ->assertDispatched('hf-toast');

    expect($registration->fresh()->status)->toBe('submitted');

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->set('approvingId', $registration->id)
        ->set('approveAnyway', true)
        ->call('confirmApprove')
        ->assertHasErrors(['approveAnyway']);

    expect($registration->fresh()->status)->toBe('submitted');

    $this->actingAs($organizer)
        ->get('/submissions')
        ->assertOk()
        ->assertSeeHtml('data-testid="submission-seeds-locked-'.$registration->id.'"')
        ->assertDontSeeHtml('data-testid="submission-approve-'.$registration->id.'"')
        ->assertSeeHtml('data-testid="submission-reject-'.$registration->id.'"');

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('openReview', $registration->id)
        ->assertDontSeeHtml('data-testid="submission-detail-approve"')
        ->assertSeeHtml('data-testid="submission-detail-reject"');

    Livewire::actingAs($organizer)
        ->test(SubmissionsIndex::class)
        ->call('reject', $registration->id)
        ->set('rejectReason', 'Seeds are locked for this tournament.')
        ->call('confirmReject');

    expect($registration->fresh()->status)->toBe('rejected')
        ->and($registration->fresh()->rejection_reason)->toBe('Seeds are locked for this tournament.');
});
```

### Step 2: Run the test and confirm the failure

```powershell
cd src
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="blocks approvals after seeds lock"
```

Expected: failure because the approval still opens and succeeds.

### Step 3: Add the server-side guard

In `src/app/Livewire/Organizer/SubmissionsIndex.php`, add this guard at the top of `approve()`:

```php
    public function approve(int $registrationId): void
    {
        if ($this->seedsLockedForRegistration($registrationId)) {
            $this->dispatch(
                'hf-toast',
                title: 'Seeds are locked',
                message: 'Approvals close when the bracket is generated. Reject the team with a reason instead.',
                tone: 'warning',
            );

            return;
        }

        $this->approvingId = $registrationId;
        $this->approveAnyway = false;
        $this->reviewingId = null;
        $this->rejectingId = null;
        $this->resetValidation();
    }
```

Add this guard to `confirmApprove()` immediately after the `$this->approvingId === null` check:

```php
        if ($this->seedsLockedForRegistration($this->approvingId)) {
            $this->addError('approveAnyway', 'Seeds are locked. Reject the team with a reason instead.');

            return;
        }
```

Add this guard at the top of `doApprove()`:

```php
        if ($this->seedsLockedForRegistration($registrationId)) {
            return;
        }
```

Add the helper after `findRegistration()`:

```php
    protected function seedsLockedForRegistration(int $registrationId): bool
    {
        $registration = Registration::query()->with('tournament')->find($registrationId);

        return $registration?->tournament?->seedsLocked() ?? false;
    }
```

### Step 4: Update the submissions list row

In `src/resources/views/livewire/organizer/submissions-index.blade.php`, replace the pending-actions block (currently the `@if ($status === 'submitted')` block containing Approve and Reject) with:

```blade
                            @if ($status === 'submitted')
                                @if ($tournament?->seedsLocked())
                                    <span class="hf-chip hf-chip--gold" data-testid="submission-seeds-locked-{{ $registration->id }}"><i class="bi bi-lock-fill" aria-hidden="true"></i>Seeds locked</span>
                                @else
                                    <button type="button" class="hf-btn hf-btn--sm hf-btn--primary" wire:click="approve({{ $registration->id }})" data-testid="submission-approve-{{ $registration->id }}">Approve</button>
                                @endif
                                <button type="button" class="hf-btn hf-btn--sm hf-btn--danger" wire:click="reject({{ $registration->id }})" data-testid="submission-reject-{{ $registration->id }}">Reject</button>
                            @endif
```

### Step 5: Update the review dialog footer

In the same Blade file, replace the review dialog footer block with:

```blade
                <div class="hf-dialog__foot">
                    <button type="button" class="hf-btn" wire:click="closeReview()" data-testid="submission-detail-close">Close</button>
                    @if ($review->status === 'submitted')
                        <button type="button" class="hf-btn hf-btn--danger" wire:click="reject({{ $review->id }})" data-testid="submission-detail-reject">Reject</button>
                        @if ($review->tournament?->seedsLocked())
                            <span class="hf-chip hf-chip--gold" data-testid="submission-detail-seeds-locked"><i class="bi bi-lock-fill" aria-hidden="true"></i>Seeds locked</span>
                        @else
                            <button type="button" class="hf-btn hf-btn--primary" wire:click="approve({{ $review->id }})" data-testid="submission-detail-approve">Approve team</button>
                        @endif
                    @endif
                </div>
```

The approval confirmation dialog stays as-is. It is unreachable through the UI after seeds lock, and `confirmApprove()` remains guarded for direct calls.

### Step 6: Run the test and the submissions regressions

```powershell
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="blocks approvals after seeds lock"
php artisan test tests/Feature/Pages/SubmissionsPaymentsTest.php
```

Expected: both pass. The existing approval and rejection tests still pass because their tournaments have no generated bracket.

### Step 7: Commit

```powershell
cd ..
git add src/app/Livewire/Organizer/SubmissionsIndex.php src/resources/views/livewire/organizer/submissions-index.blade.php src/tests/Feature/Pages/TournamentLifecycleTest.php
git commit -m "feat: block tournament approvals after seeds lock"
```

---

## Task 7: Present the completed status and label the lifecycle audit entries

**Files:**
- Modify: `src/app/Models/Tournament.php`
- Modify: `src/resources/views/components/status-chip.blade.php`
- Modify: `src/resources/views/livewire/organizer/tournaments-index.blade.php`
- Modify: `src/resources/views/livewire/organizer/tournament-show.blade.php`
- Modify: `src/app/Livewire/Organizer/AuditLogIndex.php`
- Modify: `src/tests/Feature/Pages/TournamentLifecycleTest.php`

**Why:** `completed` must read as a real lifecycle state everywhere an organizer looks, and the new audit entries need human labels.

### Step 1: Add the failing feature tests

In `src/tests/Feature/Pages/TournamentLifecycleTest.php`, add this import:

```php
use App\Livewire\Organizer\AuditLogIndex;
```

Append:

```php
it('shows the completed status in the index filter, chip, and hero', function () {
    $tournament = Tournament::where('slug', 'hoopflow-summer-cup')->firstOrFail();
    $tournament->update(['status' => 'completed']);

    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();

    $this->actingAs($organizer)
        ->get('/tournaments')
        ->assertOk()
        ->assertSeeHtml('value="completed"')
        ->assertSeeHtml('<i class="bi bi-trophy-fill" aria-hidden="true"></i>Completed');

    $this->actingAs($organizer)
        ->get('/tournaments/'.$tournament->id)
        ->assertOk()
        ->assertSee('Completed');
});

it('labels tournament lifecycle audit actions', function () {
    expect(AuditLogIndex::ACTION_LABELS['tournament.activate'])->toBe('Tournament activated')
        ->and(AuditLogIndex::ACTION_LABELS['tournament.complete'])->toBe('Tournament completed');
});

it('labels tournament statuses for display', function () {
    $tournament = Tournament::factory()->create(['status' => 'registration-open']);

    expect($tournament->statusLabel())->toBe('Registration open');

    $tournament->update(['status' => 'legacy-status']);

    expect($tournament->fresh()->statusLabel())->toBe('Legacy Status');
});
```

### Step 2: Run the tests and confirm the failures

```powershell
cd src
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="completed status"
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="lifecycle audit actions"
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="labels tournament statuses"
```

Expected: the chip/filter test fails because `completed` has no dedicated chip or filter option; the label tests fail because the actions and labels are missing.

### Step 3: Add the model label

In `src/app/Models/Tournament.php`, add this method after `formatLabel()`:

```php
    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'registration-open' => 'Registration open',
            'active' => 'Active',
            'upcoming' => 'Upcoming',
            'completed' => 'Completed',
            default => ucwords(str_replace('-', ' ', (string) $this->status)),
        };
    }
```

### Step 4: Add the completed chip

In `src/resources/views/components/status-chip.blade.php`, add this case after the `active` case:

```blade
        'completed' => ['tone' => 'gold', 'label' => 'Completed', 'icon' => 'trophy-fill'],
```

The `hf-chip--gold` tone already exists in `resources/css/_hoopflow.scss`; no SCSS change is needed.

### Step 5: Add the completed filter

In `src/resources/views/livewire/organizer/tournaments-index.blade.php`, add this option after the `active` option:

```blade
                <option value="completed">Completed</option>
```

### Step 6: Use the human label in the tournament hero

In `src/resources/views/livewire/organizer/tournament-show.blade.php`, change the hero eyebrow from:

```blade
            <span class="hf-eyebrow" style="color: var(--hf-gold)">{{ $division?->name ?? 'Division' }} · {{ $tournament->status }}</span>
```

to:

```blade
            <span class="hf-eyebrow" style="color: var(--hf-gold)">{{ $division?->name ?? 'Division' }} · {{ $tournament->statusLabel() }}</span>
```

### Step 7: Add the audit action labels

In `src/app/Livewire/Organizer/AuditLogIndex.php`, add these entries to `ACTION_LABELS` immediately after `'tournament.create'`:

```php
        'tournament.activate' => 'Tournament activated',
        'tournament.complete' => 'Tournament completed',
```

### Step 8: Run the tests and the presentation regressions

```powershell
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="completed status"
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="lifecycle audit actions"
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php --filter="labels tournament statuses"
php artisan test tests/Feature/Pages/TournamentsTest.php
php artisan test tests/Feature/Pages/AuditTrailTest.php
```

Expected: all pass.

### Step 9: Commit

```powershell
cd ..
git add src/app/Models/Tournament.php src/resources/views/components/status-chip.blade.php src/resources/views/livewire/organizer/tournaments-index.blade.php src/resources/views/livewire/organizer/tournament-show.blade.php src/app/Livewire/Organizer/AuditLogIndex.php src/tests/Feature/Pages/TournamentLifecycleTest.php
git commit -m "feat: present completed tournament status"
```

---

## Task 8: Run the full quality gate

**Files:**
- No intended source changes.

**Why:** The project rule is evidence before completion. Run the full gate and report the actual output, including the final test count.

### Step 1: Run the new tests sequentially

```powershell
cd src
php artisan test tests/Unit/Services/TournamentLifecycleServiceTest.php
php artisan test tests/Feature/Pages/TournamentLifecycleTest.php
```

Expected: all lifecycle tests pass.

### Step 2: Run the directly affected regressions sequentially

```powershell
php artisan test tests/Unit/Services/BracketServiceTest.php
php artisan test tests/Unit/Services/Bracket/SeriesLifecycleTest.php
php artisan test tests/Unit/Services/Bracket/DoubleEliminationPlayThroughTest.php
php artisan test tests/Feature/Pages/SubmissionsPaymentsTest.php
php artisan test tests/Feature/Pages/TournamentsTest.php
php artisan test tests/Feature/Pages/AuditTrailTest.php
```

Expected: all pass.

### Step 3: Run the full quality gate

```powershell
$env:COMPOSER_PROCESS_TIMEOUT = 0
composer quality
```

Expected:

- `pint --test` passes.
- `phpstan analyse --memory-limit=512M` prints `[OK] No errors`.
- `php artisan test` passes with a count greater than the `448 passed` baseline.

If Pint reports style violations, run `composer format`, then rerun `composer quality` and commit the formatting changes:

```powershell
cd ..
git add src
git commit -m "style: format tournament lifecycle changes"
```

### Step 4: Report the evidence

Include the actual command output in the completion message:

- Pint result
- PHPStan result
- Final test count and assertions
- Any files changed by formatting

### Step 5: Confirm the final tree

```powershell
git status --short
git log --oneline -8
```

Expected: only `.obsidian/graph.json` and `.kilo/` remain unrelated. The lifecycle commits are on `feat/prototype-ui-interactivity`.

---

## Acceptance checklist

- [ ] Creating a tournament still writes `registration-open`.
- [ ] Generating a bracket writes `active`, audits `tournament.activate`, and closes coach registration.
- [ ] Single-elimination, double-elimination, and series finals write `completed` and audit `tournament.complete`.
- [ ] A round-robin with `playoff_teams = 0` and no playoff bracket completes after the last group game is `final`/`forfeit`.
- [ ] A round-robin with playoffs stays `active` after the group stage and completes after the playoff final.
- [ ] A cancelled or unresolved group game blocks round-robin completion.
- [ ] Correcting a result after completion keeps the tournament `completed` with one completion audit entry.
- [ ] Pending registrations can be rejected but not approved after seeds lock.
- [ ] The completed chip, index filter, and tournament hero label render correctly.
- [ ] `composer quality` passes with `COMPOSER_PROCESS_TIMEOUT=0`.
