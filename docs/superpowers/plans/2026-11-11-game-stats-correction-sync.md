# Game Stats Correction & Box Score Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make final-score finalization and correction update the score and box score together for games that have an editable box score, while keeping the score-only flows for games without per-quarter detail.

**Architecture:** `GameStatsService::correct()` writes quarters, player totals, and per-quarter rows, then calls `BracketService::correctScore()` inside one transaction and records `stats.correct`. `GameStatsShow` routes eligible games to `/game-manage?correct=1`, and `GameManage` gains a correction mode with a required reason, a non-blocking mismatch line, and transactional save/cancel.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Pest, PHP 8.4.

## Global Constraints

- Do not change the prototype, migrations, models, seeders, or routes.
- Only organizers can enter correction mode or save a correction.
- A correction reason is required and must be 5–500 characters after trimming.
- The final score must equal the sum of the quarters; elimination games cannot end in a tie.
- Player points that differ from the final score warn but never block a save.
- Aggregate-only games (for example match 9 / qf-1) keep the score-only dialog so their totals are never zeroed.
- Final games stay locked outside correction mode.
- Everything runs from `src/`; finish with `composer quality` and report the actual output.
- Do not commit `.obsidian/graph.json` or `.kilo/`.

---

### Task 1: Transactional `GameStatsService::correct()` and OT-aware `load()`

**Files:**
- Modify: `src/app/Services/GameStatsService.php`
- Test: `src/tests/Feature/Services/GameStatsServiceTest.php`

**Interfaces:**
- Consumes: existing `normalize()`, `write()`, `writeAudit()`, `intValue()`, and `BracketService::correctScore()`.
- Produces: `public function correct(MatchModel $match, array $payload, string $reason): MatchModel`.
- Produces: `load()` now sizes quarters from `player_stat_quarters.quarter_index` as well as `quarters_json`.

- [ ] **Step 1: Add the failing service tests**

Append to `src/tests/Feature/Services/GameStatsServiceTest.php`:

```php
function gssEditableFinal(): MatchModel
{
    $match = gssMatch(8, 3);
    app(GameStatsService::class)->publish($match, gssPayload($match));

    return $match->refresh();
}

it('corrects a final result and box score together', function () {
    $match = gssEditableFinal();
    $service = app(GameStatsService::class);
    $next = MatchModel::where('round_number', 4)->where('match_number', 2)->firstOrFail();

    $service->correct($match, gssPayload($match, [
        'quarters' => ['team1' => [7, 8, 8, 7], 'team2' => [9, 9, 9, 8]],
        'finals' => ['team1' => 30, 'team2' => 35],
    ]), 'Official review changed the winner.');

    $match->refresh();
    $next->refresh();

    expect($match->status)->toBe('final')
        ->and((int) $match->home_score)->toBe(30)
        ->and((int) $match->away_score)->toBe(35)
        ->and((int) $match->winner_team_id)->toBe((int) $match->away_team_id)
        ->and($match->quarters_json)->toBe(['team1' => [7, 8, 8, 7], 'team2' => [9, 9, 9, 8]])
        ->and($next->home_team_id)->toBe((int) $match->away_team_id);

    expect(AuditLog::where('action', 'score.correct')->where('entity_id', $match->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'stats.correct')->where('entity_id', $match->id)->exists())->toBeTrue();

    $log = AuditLog::where('action', 'stats.correct')
        ->where('entity_id', $match->id)
        ->latest('id')
        ->firstOrFail();

    expect($log->after_json['reason'] ?? null)->toBe('Official review changed the winner.')
        ->and($log->after_json['finals'] ?? null)->toBe(['team1' => 30, 'team2' => 35]);
});

it('loads overtime rows that are missing from the quarters json', function () {
    $match = gssEditableFinal();
    $playerId = gssPlayerId($match, 'team1');

    PlayerStatQuarter::query()->create([
        'match_id' => $match->id,
        'player_id' => $playerId,
        'team_id' => $match->home_team_id,
        'quarter_index' => 4,
        'field_goals_made' => 1,
        'field_goals_attempted' => 1,
        'free_throws_made' => 0,
        'points' => 2,
    ]);

    $service = app(GameStatsService::class);
    $loaded = $service->load($match->refresh());

    expect($loaded['quarters']['team1'])->toHaveCount(5)
        ->and($loaded['stats']['team1'][$playerId]['quarters'])->toHaveCount(5)
        ->and($loaded['stats']['team1'][$playerId]['quarters'][4]['fgm'])->toBe(1);

    $service->correct($match, $loaded, 'Overtime row retained.');

    expect(PlayerStatQuarter::where('match_id', $match->id)
        ->where('player_id', $playerId)
        ->where('quarter_index', 4)
        ->exists())->toBeTrue();
});

it('rejects a correction when the match is not final', function () {
    $match = gssMatch(8, 3);

    app(GameStatsService::class)->correct($match, gssPayload($match), 'A valid correction reason.');
})->throws(InvalidArgumentException::class, 'Only a final result can be corrected.');

it('rejects a correction with a short reason', function () {
    $match = gssEditableFinal();

    app(GameStatsService::class)->correct($match, gssPayload($match), 'oops');
})->throws(InvalidArgumentException::class, 'Enter at least 5 characters for the correction reason.');

it('rejects a correction when finals do not equal the quarter sums', function () {
    $match = gssEditableFinal();

    app(GameStatsService::class)->correct($match, gssPayload($match, [
        'finals' => ['team1' => 41, 'team2' => 32],
    ]), 'A valid correction reason.');
})->throws(InvalidArgumentException::class, 'Final score must equal the sum of the quarters.');

it('rejects a tied correction', function () {
    $match = gssEditableFinal();

    app(GameStatsService::class)->correct($match, gssPayload($match, [
        'quarters' => ['team1' => [10, 10, 10, 10], 'team2' => [10, 10, 10, 10]],
        'finals' => ['team1' => 40, 'team2' => 40],
    ]), 'A valid correction reason.');
})->throws(InvalidArgumentException::class, 'Elimination games cannot end in a tie.');

it('does not write a failed correction', function () {
    $match = gssEditableFinal();
    $beforeScore = (int) $match->home_score;
    $beforeQuarters = $match->quarters_json;

    try {
        app(GameStatsService::class)->correct($match, gssPayload($match, [
            'finals' => ['team1' => 41, 'team2' => 32],
        ]), 'A valid correction reason.');
    } catch (InvalidArgumentException) {
        // Expected.
    }

    $match->refresh();

    expect((int) $match->home_score)->toBe($beforeScore)
        ->and($match->quarters_json)->toBe($beforeQuarters)
        ->and(AuditLog::where('action', 'stats.correct')->where('entity_id', $match->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the service test file to verify it fails**

Run from `src/`:

```powershell
php artisan test --filter=GameStatsServiceTest
```

Expected: FAIL with `Call to undefined method App\Services\GameStatsService::correct()` on the correction tests, and the OT test fails because `load()` returns four quarters.

- [ ] **Step 3: Add `correct()` to `GameStatsService`**

Insert this method after `publish()` in `src/app/Services/GameStatsService.php`:

```php
    /**
     * @param  array{quarters?: array{team1?: array<int|string, mixed>, team2?: array<int|string, mixed>}, finals?: array{team1?: mixed, team2?: mixed}, stats?: array<string, mixed>}  $payload
     */
    public function correct(MatchModel $match, array $payload, string $reason): MatchModel
    {
        if ($match->status !== 'final') {
            throw new \InvalidArgumentException('Only a final result can be corrected.');
        }

        if ($match->home_team_id === null || $match->away_team_id === null) {
            throw new \InvalidArgumentException('Both teams must be known before correcting stats.');
        }

        $reason = trim($reason);
        $length = mb_strlen($reason);

        if ($length < 5) {
            throw new \InvalidArgumentException('Enter at least 5 characters for the correction reason.');
        }

        if ($length > 500) {
            throw new \InvalidArgumentException('The correction reason cannot exceed 500 characters.');
        }

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

        DB::transaction(function () use ($match, $normalized, $final1, $final2, $previous, $reason): void {
            $this->write($match, $normalized, false);
            $this->bracketService->correctScore($match->refresh(), $final1, $final2, $reason);
            $this->writeAudit($match, 'stats.correct', $previous, [
                'quarters' => $normalized['quarters'],
                'finals' => ['team1' => $final1, 'team2' => $final2],
                'players' => count($normalized['stats']['team1']) + count($normalized['stats']['team2']),
                'reason' => $reason,
            ]);
        });

        return $match->refresh();
    }
```

- [ ] **Step 4: Make `load()` honor recorded overtime rows**

Replace this line in `src/app/Services/GameStatsService.php`:

```php
        $count = min(self::MAX_QUARTERS, max(self::MIN_QUARTERS, count($team1), count($team2)));
```

with:

```php
        $maxQuarterIndex = (int) $match->playerQuarterStats->max('quarter_index');
        $count = min(
            self::MAX_QUARTERS,
            max(self::MIN_QUARTERS, count($team1), count($team2), $maxQuarterIndex + 1),
        );
```

- [ ] **Step 5: Run the service tests and the existing stats page tests**

Run from `src/`:

```powershell
php artisan test --filter=GameStatsServiceTest
php artisan test --filter=GameStatsTest
```

Expected: PASS for both files.

- [ ] **Step 6: Commit**

```powershell
git add src/app/Services/GameStatsService.php src/tests/Feature/Services/GameStatsServiceTest.php
git commit -m "feat(stats): add transactional score and box score correction"
```

---

### Task 2: Route editable box scores through the combined editor

**Files:**
- Modify: `src/app/Livewire/Organizer/GameStatsShow.php`
- Test: `src/tests/Feature/Pages/GameStatsTest.php`

**Interfaces:**
- Consumes: `GameStatsService::correct()` from Task 1.
- Produces: `#[Computed] public function hasEditableBoxScore(): bool`.
- Produces: redirects from `openScoreConsole()`, `requestFinalizeScore()`, `performFinalize()`, `openCorrection()`, `requestCorrection()`, and `performCorrection()`.

- [ ] **Step 1: Add the failing routing tests**

Append to `src/tests/Feature/Pages/GameStatsTest.php`:

```php
function gsMakeEditableFinal(): MatchModel
{
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    gsQuarterStat($match, $stat, 0, ['fgm' => 2, 'fga' => 4, 'points' => 4]);

    return $match;
}

function gsDraftEditable(MatchModel $match): void
{
    $loaded = app(GameStatsService::class)->load($match);
    $playerId = (int) array_key_first($loaded['stats']['team1']);
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fgm'] = 2;
    $loaded['stats']['team1'][$playerId]['quarters'][0]['fga'] = 4;

    app(GameStatsService::class)->saveDraft($match, $loaded);
}

it('routes a final editable box score correction to the combined editor', function () {
    $match = gsMakeEditableFinal();

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openCorrection')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));
});

it('redirects a final editable correct deep link to the combined editor', function () {
    $match = gsMakeEditableFinal();

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id.'&correct=1')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));
});

it('keeps the score-only correction dialog for an aggregate-only final', function () {
    $match = gsMatch(8, 1);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openCorrection')
        ->assertNoRedirect()
        ->assertSet('showCorrection', true)
        ->assertSeeHtml('data-testid="stats-correction-dialog"');
});

it('routes final score entry to the editor when draft quarter stats exist', function () {
    $match = gsMatch(8, 3);
    gsDraftEditable($match);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->call('openScoreConsole')
        ->assertRedirect(route('gameManage', ['match' => $match->id]));
});

it('blocks a score-only finalize when draft quarter stats exist', function () {
    $match = gsMatch(8, 3);
    gsDraftEditable($match);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('score1', 10)
        ->set('score2', 8)
        ->call('requestFinalizeScore')
        ->assertRedirect(route('gameManage', ['match' => $match->id]));

    expect($match->refresh()->status)->toBe('scheduled')
        ->and($match->home_score)->toBeNull();
});

it('blocks a direct score-only finalize confirmation when draft quarter stats exist', function () {
    $match = gsMatch(8, 3);
    gsDraftEditable($match);

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('confirmAction', 'finalize')
        ->set('score1', 10)
        ->set('score2', 8)
        ->call('confirm')
        ->assertRedirect(route('gameManage', ['match' => $match->id]));

    expect($match->refresh()->status)->toBe('scheduled');
});

it('blocks a score-only correction when quarter stats exist', function () {
    $match = gsMakeEditableFinal();

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('score1', 80)
        ->set('score2', 70)
        ->set('reason', 'Referee review changed the score.')
        ->call('requestCorrection')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));

    expect((int) $match->refresh()->home_score)->toBe(78);
});

it('blocks a direct score-only correction confirmation when quarter stats exist', function () {
    $match = gsMakeEditableFinal();

    Livewire::actingAs(gsUser('organizer@hoopflow.ph'))
        ->test(GameStatsShow::class, ['match' => $match->id])
        ->set('confirmAction', 'correction')
        ->set('score1', 80)
        ->set('score2', 70)
        ->set('reason', 'Referee review changed the score.')
        ->call('confirm')
        ->assertRedirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));

    expect((int) $match->refresh()->home_score)->toBe(78);
});
```

- [ ] **Step 2: Run the game stats tests to verify they fail**

Run from `src/`:

```powershell
php artisan test --filter=GameStatsTest
```

Expected: FAIL because `hasEditableBoxScore` does not exist and the action methods still open the dialogs.

- [ ] **Step 3: Add the editable detection computed property**

Add these entries to the class docblock above `class GameStatsShow`:

```php
 * @property-read bool $hasEditableBoxScore
```

Insert after `hasStats()` in `src/app/Livewire/Organizer/GameStatsShow.php`:

```php
    #[Computed]
    public function hasEditableBoxScore(): bool
    {
        $match = $this->match;

        return $match instanceof MatchModel
            && $match->playerStats->isNotEmpty()
            && $match->playerQuarterStats->isNotEmpty();
    }
```

- [ ] **Step 4: Guard the score console and finalize path**

In `openScoreConsole()`, insert this block after the both-teams check and before `$this->score1 = 0;`:

```php
        if ($this->hasEditableBoxScore) {
            $this->redirect(route('gameManage', ['match' => $match->id]));

            return;
        }
```

In `requestFinalizeScore()`, insert this block after the forfeit branch and before `$this->validate(...)`:

```php
        if ($this->hasEditableBoxScore) {
            $this->redirect(route('gameManage', ['match' => $match->id]));

            return;
        }
```

In `performFinalize()`, insert this block after `Gate::authorize('correctScore', $match);` and before `$score1 = ...`:

```php
        if ($match->status === 'scheduled' && $this->hasEditableBoxScore) {
            $this->showScoreConsole = false;
            $this->scoreHistory = [];
            $this->redirect(route('gameManage', ['match' => $match->id]));

            return;
        }
```

- [ ] **Step 5: Guard the correction dialog and correction path**

In `openCorrection()`, insert this block after the final/forfeit status check and before `$this->score1 = ...`:

```php
        if ($match->status === 'final' && $this->hasEditableBoxScore) {
            $this->redirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));

            return;
        }
```

In `requestCorrection()`, insert the same block after the final/forfeit status check and before `$this->validate(...)`:

```php
        if ($match->status === 'final' && $this->hasEditableBoxScore) {
            $this->redirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));

            return;
        }
```

In `performCorrection()`, insert this block after `Gate::authorize('correctScore', $match);` and before `$bracketService->correctScore(...)`:

```php
        if ($match->status === 'final' && $this->hasEditableBoxScore) {
            $this->showCorrection = false;
            $this->redirect(route('gameManage', ['match' => $match->id, 'correct' => 1]));

            return;
        }
```

- [ ] **Step 6: Run the game stats and game manage tests**

Run from `src/`:

```powershell
php artisan test --filter=GameStatsTest
php artisan test --filter=GameManageTest
```

Expected: PASS. The existing aggregate-only correction and score console tests must stay green.

- [ ] **Step 7: Commit**

```powershell
git add src/app/Livewire/Organizer/GameStatsShow.php src/tests/Feature/Pages/GameStatsTest.php
git commit -m "feat(stats): route editable corrections through in-game editor"
```

---

### Task 3: Non-blocking score mismatch warning on `/game-stats`

**Files:**
- Modify: `src/app/Livewire/Organizer/GameStatsShow.php`
- Modify: `src/resources/views/livewire/organizer/game-stats-show.blade.php`
- Test: `src/tests/Feature/Pages/GameStatsTest.php`

**Interfaces:**
- Consumes: `hasEditableBoxScore` from Task 2.
- Produces: `#[Computed] public function scoreMismatch(): array` returning `list<array{side: string, team: string, points: int, score: int, editable: bool}>`.

- [ ] **Step 1: Add the failing warning tests**

Append to `src/tests/Feature/Pages/GameStatsTest.php`:

```php
it('warns when player points do not match an aggregate-only final score', function () {
    $match = gsMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();
    $stat->forceFill(['points' => (int) $stat->points - 2])->save();

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-score-mismatch"')
        ->assertSee('Box score doesn’t match the final score')
        ->assertSee('vs final 78')
        ->assertSee('Per-quarter stats were not recorded, so only the score can be corrected.');
});

it('does not warn when player points match the final score', function () {
    $match = gsMatch(8, 1);

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertDontSeeHtml('data-testid="stats-score-mismatch"');
});

it('does not add the aggregate-only note when quarter rows exist', function () {
    $match = gsMakeEditableFinal();
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();
    $stat->forceFill(['points' => (int) $stat->points - 2])->save();

    $this->actingAs(gsUser('organizer@hoopflow.ph'))
        ->get('/game-stats?match='.$match->id)
        ->assertOk()
        ->assertSeeHtml('data-testid="stats-score-mismatch"')
        ->assertDontSee('Per-quarter stats were not recorded, so only the score can be corrected.');
});
```

- [ ] **Step 2: Run the game stats tests to verify they fail**

Run from `src/`:

```powershell
php artisan test --filter=GameStatsTest
```

Expected: FAIL because `stats-score-mismatch` is not rendered.

- [ ] **Step 3: Add the computed mismatch data**

Add this entry to the class docblock above `class GameStatsShow`:

```php
 * @property-read list<array{side: string, team: string, points: int, score: int, editable: bool}> $scoreMismatch
```

Insert after `hasEditableBoxScore()`:

```php
    /**
     * @return list<array{side: string, team: string, points: int, score: int, editable: bool}>
     */
    #[Computed]
    public function scoreMismatch(): array
    {
        $match = $this->match;

        if (! $match instanceof MatchModel
            || ! in_array($match->status, ['final', 'forfeit'], true)
            || $match->home_score === null
            || $match->away_score === null
            || $match->playerStats->isEmpty()) {
            return [];
        }

        $editable = $this->hasEditableBoxScore;
        $mismatches = [];

        /** @var list<array{side: string, team: Team|null, teamId: int|null, score: int}> $teams */
        $teams = [
            [
                'side' => 'team1',
                'team' => $match->homeTeam,
                'teamId' => $match->home_team_id !== null ? (int) $match->home_team_id : null,
                'score' => (int) $match->home_score,
            ],
            [
                'side' => 'team2',
                'team' => $match->awayTeam,
                'teamId' => $match->away_team_id !== null ? (int) $match->away_team_id : null,
                'score' => (int) $match->away_score,
            ],
        ];

        foreach ($teams as $entry) {
            if ($entry['teamId'] === null) {
                continue;
            }

            $points = (int) $match->playerStats
                ->where('team_id', $entry['teamId'])
                ->sum('points');

            if ($points === $entry['score']) {
                continue;
            }

            $mismatches[] = [
                'side' => $entry['side'],
                'team' => $entry['team'] instanceof Team ? $entry['team']->name : 'Team',
                'points' => $points,
                'score' => $entry['score'],
                'editable' => $editable,
            ];
        }

        return $mismatches;
    }
```

- [ ] **Step 4: Render the warning**

Insert this block in `src/resources/views/livewire/organizer/game-stats-show.blade.php` after the closing `</section>` of the scoreboard and before the `@if ($correction)` banner:

```blade
        @if ($this->scoreMismatch !== [])
            <div class="hf-card hf-card--flat hf-mb-3" data-testid="stats-score-mismatch">
                <div class="hf-between hf-wrap">
                    <span class="hf-chip hf-chip--gold"><i class="bi bi-exclamation-triangle"></i> Box score mismatch</span>
                </div>
                @foreach ($this->scoreMismatch as $mismatch)
                    <p class="hf-small hf-mt-1 hf-mb-0">
                        Box score doesn’t match the final score: <strong>{{ $mismatch['team'] }}</strong> player points {{ $mismatch['points'] }} vs final {{ $mismatch['score'] }}.
                        @if (! $mismatch['editable'])
                            Per-quarter stats were not recorded, so only the score can be corrected.
                        @endif
                    </p>
                @endforeach
            </div>
        @endif
```

- [ ] **Step 5: Run the game stats tests**

Run from `src/`:

```powershell
php artisan test --filter=GameStatsTest
```

Expected: PASS, including the existing correction-banner and period-navigation tests.

- [ ] **Step 6: Commit**

```powershell
git add src/app/Livewire/Organizer/GameStatsShow.php src/resources/views/livewire/organizer/game-stats-show.blade.php src/tests/Feature/Pages/GameStatsTest.php
git commit -m "feat(stats): warn when box score points differ from the score"
```

---

### Task 4: Correction mode in `/game-manage`

**Files:**
- Modify: `src/app/Livewire/Organizer/GameManage.php`
- Modify: `src/resources/views/livewire/organizer/game-manage.blade.php`
- Test: `src/tests/Feature/Pages/GameManageTest.php`

**Interfaces:**
- Consumes: `GameStatsService::correct()` from Task 1.
- Produces: `public bool $correctionMode`, `public string $correctionReason`.
- Produces: `#[Computed] public function hasEditableBoxScore(): bool`.
- Produces: `public function requestSaveCorrection(): void`, `public function confirmSaveCorrection(): void`, `public function cancelCorrection(): void`, `public function statPointsForSide(string $side): int`.
- Consumes from Task 2: `/game-stats` redirects to `/game-manage?correct=1`.

- [ ] **Step 1: Add the failing correction-mode tests**

Append to `src/tests/Feature/Pages/GameManageTest.php`:

```php
function gmFinalWithQuarters(): MatchModel
{
    $match = gmMatch(8, 1);
    $stat = PlayerStat::where('match_id', $match->id)
        ->where('team_id', $match->home_team_id)
        ->firstOrFail();

    PlayerStatQuarter::query()->create([
        'match_id' => $match->id,
        'player_id' => $stat->player_id,
        'team_id' => $stat->team_id,
        'quarter_index' => 0,
        'field_goals_made' => 2,
        'field_goals_attempted' => 4,
        'three_points_made' => 1,
        'three_points_attempted' => 2,
        'free_throws_made' => 1,
        'free_throws_attempted' => 2,
        'points' => 6,
    ]);

    return $match;
}

it('opens correction mode for a final game with an editable box score', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('correctionMode', true)
        ->assertSeeHtml('data-testid="ingame-correction-banner"')
        ->assertSeeHtml('data-testid="ingame-correction-reason"')
        ->assertSeeHtml('data-testid="ingame-save-correction"')
        ->assertSeeHtml('data-testid="ingame-cancel-correction"')
        ->assertDontSeeHtml('data-testid="ingame-save"')
        ->assertDontSeeHtml('data-testid="ingame-publish"')
        ->assertDontSee('Locked result');
});

it('keeps a final game locked without the correction flag', function () {
    $match = gmFinalWithQuarters();

    Livewire::actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('correctionMode', false)
        ->assertSee('Locked result')
        ->assertDontSeeHtml('data-testid="ingame-correction-banner"')
        ->assertSeeHtml('data-testid="ingame-correct"');
});

it('links a locked editable game to correction mode', function () {
    $match = gmFinalWithQuarters();

    $this->actingAs(gmUser('organizer@hoopflow.ph'))
        ->get('/game-manage?match='.$match->id)
        ->assertOk()
        ->assertSee(route('gameManage', ['match' => $match->id, 'correct' => 1]));
});

it('redirects correction mode to the score-only dialog when quarter rows are missing', function () {
    $match = gmMatch(8, 1);

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertRedirect(route('gameStats', ['match' => $match->id, 'correct' => 1]));
});

it('ignores the correction flag for a scheduled game', function () {
    $match = gmMatch(8, 3);

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSet('correctionMode', false)
        ->assertSeeHtml('data-testid="ingame-save"')
        ->assertDontSeeHtml('data-testid="ingame-correction-banner"');
});

it('forbids correction mode to non-organizers', function () {
    $match = gmFinalWithQuarters();

    $this->actingAs(gmUser('coach.marcus@hoopflow.ph'))
        ->get('/game-manage?match='.$match->id.'&correct=1')
        ->assertForbidden();
});

it('shows a mismatch warning in correction mode without blocking the save', function () {
    $match = gmFinalWithQuarters();

    $component = Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->assertSeeHtml('data-testid="ingame-mismatch-team1"')
        ->assertSee('Player points 6 · Final 78 · 72 unassigned');

    $component
        ->set('quarters.team1', [20, 20, 20, 20])
        ->set('quarters.team2', [18, 18, 17, 17])
        ->set('finals.team1', 80)
        ->set('finals.team2', 70)
        ->set('correctionReason', 'Referee review changed the home total.')
        ->call('requestSaveCorrection')
        ->assertSet('showConfirm', true)
        ->assertSet('confirmAction', 'confirmSaveCorrection')
        ->call('confirmSaveCorrection')
        ->assertRedirect(route('gameStats', ['match' => $match->id]));

    $match->refresh();

    expect((int) $match->home_score)->toBe(80)
        ->and($match->quarters_json['team1'])->toBe([20, 20, 20, 20])
        ->and(AuditLog::where('action', 'score.correct')->where('entity_id', $match->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'stats.correct')->where('entity_id', $match->id)->exists())->toBeTrue();
});

it('requires a correction reason', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('correctionReason', 'oops')
        ->call('requestSaveCorrection')
        ->assertHasErrors('correctionReason')
        ->assertSet('showConfirm', false);
});

it('rejects a correction when the final does not equal the quarters', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('correctionReason', 'A valid correction reason.')
        ->set('quarters.team1', [20, 20, 20, 20])
        ->set('finals.team1', 79)
        ->call('requestSaveCorrection')
        ->assertSet('showConfirm', false)
        ->assertSet('errorMessage', 'Final score must equal the sum of the quarters.');
});

it('cancels a correction without writing', function () {
    $match = gmFinalWithQuarters();
    $before = (int) $match->home_score;

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->set('quarters.team1', [20, 20, 20, 20])
        ->call('cancelCorrection')
        ->assertRedirect(route('gameStats', ['match' => $match->id]));

    expect((int) $match->refresh()->home_score)->toBe($before)
        ->and(AuditLog::where('action', 'stats.correct')->where('entity_id', $match->id)->exists())->toBeFalse();
});

it('ignores draft and publish actions in correction mode', function () {
    $match = gmFinalWithQuarters();

    Livewire::withQueryParams(['correct' => 1])
        ->actingAs(gmUser('organizer@hoopflow.ph'))
        ->test(GameManage::class, ['match' => $match->id])
        ->call('saveDraft')
        ->call('requestPublish')
        ->assertSet('showConfirm', false);

    expect(AuditLog::where('action', 'stats.save')->where('entity_id', $match->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run the game manage tests to verify they fail**

Run from `src/`:

```powershell
php artisan test --filter=GameManageTest
```

Expected: FAIL because `correctionMode`, the correction UI, and the save/cancel methods do not exist.

- [ ] **Step 3: Add correction state and helpers to `GameManage`**

Add these entries to the class docblock:

```php
 * @property-read bool $hasEditableBoxScore
```

Add these public properties after `public string $errorMessage = '';`:

```php
    public bool $correctionMode = false;

    public string $correctionReason = '';
```

Add this computed property after `isLocked()`:

```php
    #[Computed]
    public function hasEditableBoxScore(): bool
    {
        $match = $this->match;

        return $match instanceof MatchModel
            && $match->playerStats->isNotEmpty()
            && $match->playerQuarterStats->isNotEmpty();
    }
```

Change `isLocked()` to:

```php
    #[Computed]
    public function isLocked(): bool
    {
        return ! $this->correctionMode
            && in_array($this->match?->status, ['final', 'forfeit'], true);
    }
```

- [ ] **Step 4: Enter correction mode from the query flag**

Replace the end of `mount()`:

```php
        $this->selectMatch($found);
    }
```

with:

```php
        $this->selectMatch($found);

        if (request()->boolean('correct')) {
            $this->enterCorrectionMode();
        }
    }
```

Add this protected method before `selectMatch()`:

```php
    protected function enterCorrectionMode(): void
    {
        $match = $this->match;

        if (! $match instanceof MatchModel || $match->status !== 'final') {
            return;
        }

        Gate::authorize('correctScore', $match);

        if (! $this->hasEditableBoxScore) {
            $this->redirect(route('gameStats', ['match' => $match->id, 'correct' => 1]));

            return;
        }

        $this->correctionMode = true;
        $this->correctionReason = '';
        $this->errorMessage = '';
    }
```

- [ ] **Step 5: Make `editableMatch()` correction-aware**

Replace `editableMatch()` with:

```php
    protected function editableMatch(): ?MatchModel
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return null;
        }

        if ($this->correctionMode) {
            if ($match->status !== 'final') {
                $this->fail('Only a final result can be corrected.');

                return null;
            }

            Gate::authorize('correctScore', $match);
            Gate::authorize('enterStats', $match);

            if ($match->home_team_id === null || $match->away_team_id === null) {
                $this->fail('Both teams must be known before entering stats.');

                return null;
            }

            return $match;
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
```

Add these guards at the top of `saveDraft()` and `requestPublish()`:

```php
        if ($this->correctionMode) {
            return;
        }
```

- [ ] **Step 6: Add the correction save and cancel actions**

Insert these methods after `confirmPublish()`:

```php
    public function requestSaveCorrection(): void
    {
        if (! $this->correctionMode) {
            return;
        }

        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        $this->validate([
            'correctionReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'correctionReason.required' => 'Enter at least 5 characters for the correction reason.',
            'correctionReason.min' => 'Enter at least 5 characters for the correction reason.',
        ]);

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
            'Save correction?',
            'This updates the score and box score, may rewrite the downstream bracket if the winner changes, and is written to the audit trail.',
            'Save correction',
            'danger',
            'confirmSaveCorrection',
        );
    }

    public function confirmSaveCorrection(): void
    {
        $this->showConfirm = false;
        $match = $this->editableMatch();

        if ($match === null) {
            return;
        }

        try {
            app(GameStatsService::class)->correct($match, $this->payload(), $this->correctionReason);
        } catch (\InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());

            return;
        }

        $this->redirect(route('gameStats', ['match' => $match->id]));
    }

    public function cancelCorrection(): void
    {
        $match = $this->match;

        if (! $match instanceof MatchModel) {
            return;
        }

        $this->redirect(route('gameStats', ['match' => $match->id]));
    }
```

Insert this method after `statPoints()`:

```php
    public function statPointsForSide(string $side): int
    {
        $entries = match ($side) {
            'team1' => $this->stats['team1'],
            'team2' => $this->stats['team2'],
            default => [],
        };

        $service = app(GameStatsService::class);
        $total = 0;

        foreach ($entries as $entry) {
            foreach ($entry['quarters'] as $quarter) {
                $total += $service->pointsFor($quarter);
            }
        }

        return $total;
    }
```

- [ ] **Step 7: Add the correction UI**

Insert this banner in `src/resources/views/livewire/organizer/game-manage.blade.php` after the closing `</header>` and before `@if (! $teamsReady)`:

```blade
        @if ($correctionMode)
            <div class="hf-card hf-card--flat hf-mb-3" data-testid="ingame-correction-banner">
                <div class="hf-between hf-wrap">
                    <span class="hf-chip hf-chip--gold"><i class="bi bi-arrow-repeat"></i> Correction mode</span>
                </div>
                <p class="hf-small hf-mt-1 hf-mb-0">You are editing a recorded result. A reason is required, and changes are written to the audit trail.</p>
            </div>
        @endif
```

Replace the locked-state **Correct score** link with:

```blade
                    @if ($this->hasEditableBoxScore)
                        <a class="hf-btn hf-btn--danger" href="{{ route('gameManage', ['match' => $match->id, 'correct' => 1]) }}" data-testid="ingame-correct">Correct score</a>
                    @else
                        <a class="hf-btn hf-btn--danger" href="{{ route('gameStats', ['match' => $match->id, 'correct' => 1]) }}" data-testid="ingame-correct">Correct score</a>
                    @endif
```

Insert the mismatch line inside the team panel, after the final-score input:

```blade
                        @if ($correctionMode)
                            @php
                                $playerPoints = $this->statPointsForSide($panel['side']);
                                $finalPoints = (int) $this->finals[$panel['side']];
                            @endphp
                            <p class="hf-small hf-mt-1 hf-mb-0" data-testid="ingame-mismatch-{{ $panel['side'] }}">
                                @if ($playerPoints === $finalPoints)
                                    Player points match the final score
                                @elseif ($playerPoints < $finalPoints)
                                    Player points {{ $playerPoints }} · Final {{ $finalPoints }} · {{ $finalPoints - $playerPoints }} unassigned
                                @else
                                    Player points {{ $playerPoints }} · Final {{ $finalPoints }} · {{ $playerPoints - $finalPoints }} above the final score
                                @endif
                            </p>
                        @endif
```

Replace the action row inside `data-testid="ingame-actions"` with:

```blade
                @if ($correctionMode)
                    <div class="hf-field hf-mb-2">
                        <label class="hf-label" for="ingameCorrectionReason">Correction reason</label>
                        <textarea class="hf-textarea" id="ingameCorrectionReason" rows="3" placeholder="Why is this result being corrected?" wire:model.blur="correctionReason" data-testid="ingame-correction-reason"></textarea>
                        @error('correctionReason')
                            <p class="hf-field-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                <div class="hf-btn-row">
                    @if ($correctionMode)
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="requestSaveCorrection" wire:loading.attr="disabled" data-testid="ingame-save-correction"><i class="bi bi-check2-circle"></i> Save correction</button>
                        <button type="button" class="hf-btn" wire:click="cancelCorrection" data-testid="ingame-cancel-correction">Cancel correction</button>
                    @else
                        <button type="button" class="hf-btn" wire:click="saveDraft" wire:loading.attr="disabled" data-testid="ingame-save"><i class="bi bi-save"></i> Save</button>
                        <button type="button" class="hf-btn hf-btn--primary" wire:click="requestPublish" wire:loading.attr="disabled" data-testid="ingame-publish"><i class="bi bi-check2-circle"></i> Publish result</button>
                        <a class="hf-btn" href="{{ route('bracket', ['id' => $match->tournament_id]) }}" data-testid="ingame-cancel">Cancel</a>
                    @endif
                </div>
```

- [ ] **Step 8: Run the game manage tests**

Run from `src/`:

```powershell
php artisan test --filter=GameManageTest
```

Expected: PASS for all correction-mode tests and the existing normal editor, publish, lock, and overtime tests.

- [ ] **Step 9: Commit**

```powershell
git add src/app/Livewire/Organizer/GameManage.php src/resources/views/livewire/organizer/game-manage.blade.php src/tests/Feature/Pages/GameManageTest.php
git commit -m "feat(ingame): add correction mode for final box scores"
```

---

### Task 5: Audit log label and summary for `stats.correct`

**Files:**
- Modify: `src/app/Livewire/Organizer/AuditLogIndex.php`
- Test: `src/tests/Feature/Pages/ProfileAuditTest.php`

**Interfaces:**
- Consumes: the `stats.correct` audit entries written by `GameStatsService::correct()`.
- Produces: `ACTION_LABELS['stats.correct'] = 'Box score corrected'` and a `summary()` arm.

- [ ] **Step 1: Add the failing audit test**

Add these imports to `src/tests/Feature/Pages/ProfileAuditTest.php`:

```php
use App\Models\AuditLog;
use App\Models\MatchModel;
```

Append:

```php
it('labels box score corrections in the audit timeline', function () {
    $organizer = User::where('email', 'organizer@hoopflow.ph')->firstOrFail();
    $match = MatchModel::where('status', 'final')->firstOrFail();

    AuditLog::create([
        'id' => (int) (AuditLog::max('id') ?? 0) + 1,
        'organization_id' => $match->tournament?->organization_id,
        'actor_user_id' => $organizer->id,
        'action' => 'stats.correct',
        'entity_type' => 'match',
        'entity_id' => $match->id,
        'before_json' => ['quarters' => ['team1' => [20, 20, 19, 19], 'team2' => [18, 18, 17, 17]]],
        'after_json' => [
            'quarters' => ['team1' => [20, 20, 20, 20], 'team2' => [18, 18, 17, 17]],
            'finals' => ['team1' => 80, 'team2' => 70],
            'players' => 20,
            'reason' => 'Referee review changed the home total.',
        ],
        'ip' => null,
        'user_agent' => null,
        'created_at' => now(),
    ]);

    $this->actingAs($organizer)
        ->get('/audit')
        ->assertOk()
        ->assertSee('Box score corrected')
        ->assertSee('Corrected the')
        ->assertSee('box score');
});
```

- [ ] **Step 2: Run the audit test to verify it fails**

Run from `src/`:

```powershell
php artisan test --filter=ProfileAuditTest
```

Expected: FAIL because the action label falls back to `Stats Correct`.

- [ ] **Step 3: Add the label and summary**

Add this line to `ACTION_LABELS` in `src/app/Livewire/Organizer/AuditLogIndex.php` after `'score.forfeit'`:

```php
        'stats.correct' => 'Box score corrected',
```

Add this arm to `summary()` after the `'score.forfeit'` arm:

```php
            'stats.correct' => 'Corrected the '.$entity.' box score',
```

- [ ] **Step 4: Run the audit and service tests**

Run from `src/`:

```powershell
php artisan test --filter=ProfileAuditTest
php artisan test --filter=GameStatsServiceTest
```

Expected: PASS for both files.

- [ ] **Step 5: Commit**

```powershell
git add src/app/Livewire/Organizer/AuditLogIndex.php src/tests/Feature/Pages/ProfileAuditTest.php
git commit -m "feat(audit): label box score corrections"
```

---

### Task 6: Full quality gate and manual verification

**Files:**
- No source changes expected.

- [ ] **Step 1: Run the full quality gate**

Run from `src/`:

```powershell
composer quality
```

Expected: Pint passes, PHPStan passes, and the full Pest suite passes. Record the actual pass count, assertion count, and duration.

- [ ] **Step 2: Verify the critical flows manually**

Start the app if it is not already running, then check:

1. Final game with an editable box score (`qf-1` after a quarter row exists): **Correct score** on `/game-stats` opens `/game-manage?correct=1`; the banner, reason field, mismatch line, and Save correction / Cancel correction buttons render; saving redirects back to `/game-stats` and the score, box score, quarter breakdown, correction banner, and audit log all agree.
2. Aggregate-only final (`qf-1` without quarter rows): **Correct score** still opens the `/game-stats` dialog and its totals are unchanged after closing it.
3. Scheduled game with draft quarter stats: **Enter final score** opens `/game-manage`; saving publishes score and stats together.
4. Scheduled game without stats: **Enter final score** still opens the score console and records a score-only result.
5. Coach and fan views show no correction controls and can still read the mismatch warning.
6. Light and dark themes plus a narrow viewport render the correction banner, reason field, and mismatch lines without overflow.

- [ ] **Step 3: Confirm the tree is clean except for unrelated files**

Run from the repository root:

```powershell
git status --short
```

Expected: no modified source files. `.obsidian/graph.json` and `.kilo/` may still appear and must not be committed.

---

## Self-Review

**Spec coverage**

- Routing table: Task 2 covers final/editable → combined editor, aggregate-only → dialog, scheduled/editable → editor, and the direct action guards.
- Correction UI: Task 4 covers banner, reason field, save/cancel buttons, mismatch line, confirm dialog, and redirect.
- Atomic save: Task 1 covers `write()`, `correctScore()`, `stats.correct`, validation, and transaction.
- OT preservation: Task 1 covers both `load()` and `correct()`.
- Mismatch warning: Task 3 covers aggregate-only copy, editable copy, visible/hidden states.
- Audit: Task 1 writes `stats.correct`; Task 5 labels it.
- Preserved flows: existing tests for aggregate-only correction, no-stat console, forfeits, period navigation, sorting, filtering, and roles remain in place.
- Quality gate: Task 6 runs `composer quality`.

**Placeholder scan**

No TBD, TODO, “implement later”, or “similar to Task N” references. Every code step contains the actual code.

**Type consistency**

- `GameStatsService::correct(MatchModel $match, array $payload, string $reason): MatchModel` is used identically in Tasks 1, 4, and 5.
- `GameStatsShow::hasEditableBoxScore` and `GameManage::hasEditableBoxScore` are both `bool` computed properties backed by `playerStats` and `playerQuarterStats`.
- `scoreMismatch` returns the same shape consumed by the Blade warning.
- `confirmAction` is `confirmSaveCorrection`, matching the public method called by the shared confirm dialog.
