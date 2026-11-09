# Phase 0 — Complete the Database Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the Laravel data layer in `src/` (model relations/casts, factories, deterministic PrototypeSeeder porting the prototype seed, Pest coverage) so UI phases have real relational data.

**Architecture:** No schema changes. Models gain relations/casts over the existing `public.*` tables. Factories provide test data. `PrototypeSeeder` ports `prototype/assets/js/seed.js` verbatim (3 tournaments, ~32 teams, players, registrations, payments, matches, announcements, audit, 3 demo users) via idempotent `firstOrCreate`/`updateOrCreate`. Tests run on SQLite; a pgsql job covers partitions.

**Tech Stack:** Laravel 13, PHP 8.4, Eloquent, Faker, Pest, PostgreSQL 18 (prod), SQLite (tests).

## Global Constraints

- No Tailwind, no Bootstrap JS, no SPA framework (ADR-0001).
- No domain logic in controllers/Livewire; services only.
- `composer quality` (Pint + Larastan L6 + Pest) must pass before claiming done.
- SQLite acceptable for tests; pgsql for integration/partitions.
- Idempotent seeders (safe to re-run).

## File Structure

- Modify: `src/app/Models/*.php` (relations/casts/fillables) — ~20 files.
- Create: `src/database/factories/{Tournament,Division,Venue,Court,Team,Player,Registration,Payment,Bracket,BracketSlot,Announcement,AuditLog,CourtBlackout,EligibilityDocument,GameRule,MatchSlot}Factory.php`.
- Create: `src/database/seeders/PrototypeSeeder.php`.
- Modify: `src/database/seeders/DatabaseSeeder.php` (env-gated call).
- Create: `src/tests/Feature/Database/PrototypeSeederTest.php`.
- Modify: `src/README.md` (seed docs).

---

### Task 1: Model relationship audit

**Files:** Modify `src/app/Models/{Team,Registration,MatchModel,Player,Payment,Announcement,AuditLog,Bracket,BracketSlot,Tournament,Division,Venue,Court}.php`

**Interfaces:** Produces relations used by seeder/factories/tests: `Team::registrations()`, `Registration::team()/tournament()/division()/payment()/decider()`, `MatchModel::homeTeam()/awayTeam()/winnerTeam()/bracket()/tournament()`, `Player::team()/eligibilityDocuments()`, `Payment::registration()/recordedBy()`, `Announcement::tournament()/createdBy()/organization()`, `AuditLog::organization()/actor()`, `Bracket::division()/slots()/matches()`, `BracketSlot::bracket()/team()/sourceMatch()`.

- [ ] **Step 1: Add relations to Team**

```php
public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
public function coachUser(): BelongsTo { return $this->belongsTo(User::class, 'coach_user_id'); }
public function registrations(): HasMany { return $this->hasMany(Registration::class); }
public function memberships(): HasMany { return $this->hasMany(TeamMembership::class); }
public function players(): HasMany { return $this->hasMany(Player::class); }
public function homeMatches(): HasMany { return $this->hasMany(MatchModel::class, 'home_team_id'); }
public function awayMatches(): HasMany { return $this->hasMany(MatchModel::class, 'away_team_id'); }
```

- [ ] **Step 2: Add relations to Registration**

```php
public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }
public function division(): BelongsTo { return $this->belongsTo(Division::class); }
public function team(): BelongsTo { return $this->belongsTo(Team::class); }
public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
public function payment(): HasOne { return $this->hasOne(Payment::class); }
```

- [ ] **Step 3: Add relations to MatchModel**

```php
public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }
public function bracket(): BelongsTo { return $this->belongsTo(Bracket::class); }
public function homeTeam(): BelongsTo { return $this->belongsTo(Team::class, 'home_team_id'); }
public function awayTeam(): BelongsTo { return $this->belongsTo(Team::class, 'away_team_id'); }
public function winnerTeam(): BelongsTo { return $this->belongsTo(Team::class, 'winner_team_id'); }
public function stats(): HasMany { return $this->hasMany(PlayerStat::class, 'match_id'); }
```

- [ ] **Step 4: Add relations to Player, Payment, Announcement, AuditLog, Bracket, BracketSlot** (analogous, following schema FKs).

- [ ] **Step 5: Add casts/fillables** for json/date/decimal columns (rules_json, tiebreakers_json, before_json, after_json, birthdate, start_date, etc.).

- [ ] **Step 6: Run Pint + tests**

Run: `cd src && composer lint && composer test`
Expected: PASS (no new failures).

- [ ] **Step 7: Commit**

```bash
git add src/app/Models
git commit -m "feat(models): add relations and casts for Phase 0"
```

---

### Task 2: Factories

**Files:** Create `src/database/factories/*Factory.php` (16 files).

**Interfaces:** Each factory defines `definition()` returning valid columns; used by seeder/tests.

- [ ] **Step 1: Create factories** for Tournament, Division, Venue, Court, Team, Player, Registration, Payment, Bracket, BracketSlot, Announcement, AuditLog, CourtBlackout, EligibilityDocument, GameRule, MatchSlot. Follow `UserFactory` style. Use Faker; align enums with DB defaults (`status`, `payment status`, `eligibility_status`).

- [ ] **Step 2: Verify factories instantiate**

Run: `cd src && php artisan tinker --execute="App\Models\Team::factory()->make(); echo 'ok';"`
Expected: no error.

- [ ] **Step 3: Commit**

```bash
git add src/database/factories
git commit -m "feat(factories): add model factories for Phase 0"
```

---

### Task 3: PrototypeSeeder — users & roles

**Files:** Create `src/database/seeders/PrototypeSeeder.php`; Modify `DatabaseSeeder.php`.

- [ ] **Step 1: Scaffold seeder + env gate**

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class PrototypeSeeder extends Seeder {
    public function run(): void {
        $this->call([PrototypeUsersSeeder::class, ...]);
    }
}
```

In `DatabaseSeeder::run()`: if `env('SEED_PROTOTYPE', false)` call `PrototypeSeeder`.

- [ ] **Step 2: Seed org + roles + 3 demo users** with role assignments (organizer/coach/fan), using DefaultOrganizerSeeder pattern. Emails: organizer@hoopflow.ph, coach.marcus@hoopflow.ph, fan.juan@hoopflow.ph. Password `password`.

- [ ] **Step 3: Commit**

---

### Task 4: PrototypeSeeder — tournaments, divisions, venues, courts

- [ ] **Step 1: Seed 3 tournaments** (Summer Cup U15 active, Pasig Youth Hoopfest U17 registration-open, CDO Grassroots Classic U14 draft) with start/end dates, status, fee, description.
- [ ] **Step 2: Seed divisions** (U15 age_max 15, U17 age_max 17, U14 age_max 14), game rules, venues + courts (4/3/2).
- [ ] **Step 3: Commit**

---

### Task 5: PrototypeSeeder — teams & players

- [ ] **Step 1: Seed active teams** (15 + 1 rejected Napico Nuggets) with players (10 each, makePlayers logic: guardianConsent/eligibility_status).
- [ ] **Step 2: Seed open teams** (8, two pending) and CDO teams (8).
- [ ] **Step 3: Seed registrations** linking team↔tournament/division with status + seed + submitted_at; decided_by for approved.
- [ ] **Step 4: Seed payments** per registration (paid/pending, method, ref, amount, recorded_by).
- [ ] **Step 5: Commit**

---

### Task 6: PrototypeSeeder — matches & brackets

- [ ] **Step 1: Create brackets** for active tournament division (single-elim, 16 slots).
- [ ] **Step 2: Seed 15 matches** (R16 8, QF 4, SF 2, Final 1) with scores/status/court/scheduledAt/nextMatchId propagation; QF1 correction + boxScore; QF2 boxScore.
- [ ] **Step 3: Seed bracket_slots** for the 16 seeds (team_id where known, source_type seed).
- [ ] **Step 4: Commit**

---

### Task 7: PrototypeSeeder — announcements & audit

- [ ] **Step 1: Seed 4 announcements** (3 tournaments) with audience/pinned/published_at/created_by.
- [ ] **Step 2: Seed 8 audit entries** with actor/action/entity/changes/before/after/ip.
- [ ] **Step 3: Commit**

---

### Task 8: Seeder tests

**Files:** Create `src/tests/Feature/Database/PrototypeSeederTest.php`.

- [ ] **Step 1: Write failing test**

```php
it('seeds prototype data', function () {
    $this->artisan('migrate:fresh', ['--seed' => true, '--seeder' => 'PrototypeSeeder'])->assertExitCode(0);
    expect(\App\Models\Tournament::count())->toBe(3);
    expect(\App\Models\Team::count())->toBeGreaterThanOrEqual(31);
    expect(\App\Models\Player::count())->toBeGreaterThanOrEqual(200);
    expect(\App\Models\MatchModel::count())->toBe(15);
    expect(\App\Models\Payment::count())->toBeGreaterThanOrEqual(20);
    expect(\App\Models\Announcement::count())->toBe(4);
    expect(\App\Models\AuditLog::count())->toBeGreaterThanOrEqual(8);
});
it('has no orphan registrations', function () {
    expect(\App\Models\Registration::whereDoesntHave('team')->count())->toBe(0);
    expect(\App\Models\Registration::whereDoesntHave('tournament')->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails** (seeder incomplete). Expected: FAIL.

- [ ] **Step 3: Implement seeder until green.**

- [ ] **Step 4: Run test to verify it passes**

Run: `cd src && vendor/bin/pest tests/Feature/Database/PrototypeSeederTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

---

### Task 9: Documentation

- [ ] **Step 1: Update `src/README.md`** — document `SEED_PROTOTYPE=true php artisan db:seed --class=PrototypeSeeder` and default light seed.

- [ ] **Step 2: Run full quality gate**

Run: `cd src && composer quality`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add src/README.md
git commit -m "docs: document Phase 0 seeding"
```

---

## Self-Review Notes

- Spec coverage: all Phase 0 sections (model audit, factories, PrototypeSeeder, tests, acceptance) mapped to Tasks 1–9.
- Placeholders: none; code blocks provided for key edits. Seeder sub-tasks describe concrete data from seed.js.
- Type consistency: relation names consistent across tasks (e.g., `homeTeam`, `awayTeam`, `winnerTeam`).
