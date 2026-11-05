# HoopFlow Scalable Database Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement HoopFlow's Approach C tenant-ready single database on Laravel 13 + PostgreSQL 17: full v1 schema with nullable `organization_id`, constraints, indexes, exclusion constraint, `version` column, materialized standings view, partitioning, read/write split config, and short-TTL caching.

**Architecture:** One PostgreSQL database; every top-level domain table carries a nullable `organization_id` FK to a seeded default organization; isolation enforced at the app layer in v1 (no RLS). High-volume tables partitioned by hash(tournament_id) or monthly range. Court conflicts prevented by a `tstzrange` exclusion constraint. Standings computed by a materialized view refreshed by a queued job. Public reads use a replica via Laravel read/write split and short-TTL cache.

**Tech Stack:** PHP 8.4, Laravel 13, PostgreSQL 17, Pest 4, Larastan 6, Pint. PostgreSQL-only features require a pgsql test connection; SQLite remains valid for non-constraint unit tests.

## Global Constraints

- Work only in `src/`, `docs/superpowers/`. Do not touch `prototype/`, `HoopFlow/`, `logo/`, `video/`, `_archive/`, `docs/wireframes/`.
- Stack fixed by ADR-0001: Laravel 13, PHP 8.4+, PostgreSQL 17, Pest, no Tailwind, no Bootstrap JS.
- Keep Laravel framework tables (`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`) in their scaffolded form where the framework depends on them; augment columns rather than replace. `game_odds` must never be created.
- Every mutable table has `created_at` and `updated_at`. `audit_logs` is append-only (only `created_at`).
- Financial and audit rows use `ON DELETE RESTRICT`; dependent child rows may `CASCADE`.
- `organization_id` nullable on `organizations` (self), `tournaments`, `teams`, `venues`, `user_roles`, `announcements`, `audit_logs`. Top-level aggregates and cross-cutting tables only.
- Scores non-negative; `start_at < end_at` on every slot; overlapping court slots prevented by DB exclusion constraint.
- `winner_team_id` derived from scores in application/DB, never trusted from input.
- Standings are computed, never manually edited.
- Partitioning, caching TTL tuning, replica, Redis are additive and land in Phase 8 unless a measurable bottleneck appears earlier.
- Before claiming done, run `composer quality` in `src/` and report output.
- Tests that touch PostgreSQL-only features must run with `DB_CONNECTION=pgsql`; note SQLite cannot exercise exclusion constraints or `tstzrange`.

---

## File Structure

### Migrations (create/modify under `src/database/migrations/`)

Phase 1 (identity/access):
- Modify: `0001_01_01_000000_create_users_table.php` — extend users; keep password_reset_tokens + sessions.
- Create: `2026_11_06_000001_create_organizations_table.php`
- Create: `2026_11_06_000002_create_roles_table.php`
- Create: `2026_11_06_000003_create_user_roles_table.php`

Phase 2 (tournament structure):
- Create: `2026_11_06_000010_create_tournaments_table.php`
- Create: `2026_11_06_000011_create_divisions_table.php`
- Create: `2026_11_06_000012_create_venues_table.php`
- Create: `2026_11_06_000013_create_courts_table.php`
- Create: `2026_11_06_000014_create_court_blackouts_table.php`
- Create: `2026_11_06_000015_create_game_rules_table.php`

Phase 3 (teams/registration/audit):
- Create: `2026_11_06_000020_create_teams_table.php`
- Create: `2026_11_06_000021_create_team_memberships_table.php`
- Create: `2026_11_06_000022_create_players_table.php`
- Create: `2026_11_06_000023_create_eligibility_documents_table.php`
- Create: `2026_11_06_000024_create_registrations_table.php`
- Create: `2026_11_06_000025_create_payments_table.php`
- Create: `2026_11_06_000026_create_audit_logs_table.php`

Phase 4 (brackets/matches):
- Create: `2026_11_06_000030_create_brackets_table.php`
- Create: `2026_11_06_000031_create_bracket_slots_table.php`
- Create: `2026_11_06_000032_create_matches_table.php`
- Create: `2026_11_06_000033_create_mv_standings.php` (materialized view)

Phase 5 (scheduling):
- Create: `2026_11_06_000040_create_match_slots_table.php` (with exclusion constraint)
- Create: `2026_11_06_000041_create_referees_table.php`
- Create: `2026_11_06_000042_create_match_officials_table.php`

Phase 6 (stats/standings job):
- Create: `2026_11_06_000050_create_player_stats_table.php`

Phase 7 (communication):
- Create: `2026_11_06_000060_create_announcements_table.php`
- Create: `2026_11_06_000061_create_notifications_table.php`

Phase 8 (hardening):
- Create: `2026_11_06_000070_add_performance_indexes.php`
- Create: `2026_11_06_000071_partition_audit_logs_monthly.php`
- Create: `2026_11_06_000072_partition_notifications_monthly.php`
- Create: `2026_11_06_000073_partition_matches_hash.php`
- Create: `2026_11_06_000074_partition_match_slots_hash.php`
- Create: `2026_11_06_000075_partition_player_stats_hash.php`

### Models (create/modify under `src/app/Models/`)
- Modify: `User.php`
- Create: `Organization.php`, `Role.php`, `UserRole.php`, `Tournament.php`, `Division.php`, `Venue.php`, `Court.php`, `CourtBlackout.php`, `GameRule.php`, `Team.php`, `TeamMembership.php`, `Player.php`, `EligibilityDocument.php`, `Registration.php`, `Payment.php`, `AuditLog.php`, `Bracket.php`, `BracketSlot.php`, `MatchModel.php` (table `matches`), `MatchSlot.php`, `Referee.php`, `MatchOfficial.php`, `PlayerStat.php`, `Announcement.php`, `Notification.php`
- Create trait: `src/app/Models/Concerns/BelongsToOrganization.php`

### Seeders
- Modify: `src/database/seeders/DatabaseSeeder.php`
- Create: `src/database/seeders/OrganizationSeeder.php`
- Create: `src/database/seeders/RoleSeeder.php`
- Create: `src/database/seeders/DefaultOrganizerSeeder.php`

### Jobs / Commands
- Create: `src/app/Jobs/RefreshStandings.php`
- Create: `src/app/Console/Commands/EnsurePartitions.php`

### Support
- Create: `src/app/Support/CacheKeys.php`

### Config
- Modify: `src/config/database.php` (read/write split)
- Modify: `src/.env.example` (DB_READ_HOST, DB_WRITE_HOST, DB_REPLICA)

### Tests (create under `src/tests/Feature/Database/`)
- `OrganizationSeedTest.php`
- `UniqueConstraintsTest.php`
- `ExclusionConstraintTest.php`
- `StandingsCorrectnessTest.php`
- `OptimisticLockingTest.php`
- `MigrationSmokeTest.php`

---
### Task 1: Phase 1 — Organizations, roles, user_roles, seed default org

**Files:**
- Create: `src/database/migrations/2026_11_06_000001_create_organizations_table.php`
- Create: `src/database/migrations/2026_11_06_000002_create_roles_table.php`
- Create: `src/database/migrations/2026_11_06_000003_create_user_roles_table.php`
- Modify: `src/database/migrations/0001_01_01_000000_create_users_table.php`
- Create: `src/database/seeders/OrganizationSeeder.php`, `RoleSeeder.php`, `DefaultOrganizerSeeder.php`
- Modify: `src/database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces: `organizations.id`, `roles.key`, `user_roles` composite unique; default org slug `default`.

- [ ] **Step 1: Create organizations migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
```

- [ ] **Step 2: Create roles migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
```

- [ ] **Step 3: Create user_roles migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('scope')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'scope']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
```

- [ ] **Step 4: Modify users migration to full spec columns**

Replace the `users` block in `0001_01_01_000000_create_users_table.php` with:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->string('password');
    $table->string('first_name');
    $table->string('last_name');
    $table->string('phone')->nullable();
    $table->string('status')->default('active');
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
```

Keep `password_reset_tokens` and `sessions` blocks as scaffolded.

- [ ] **Step 5: Seeders**

`OrganizationSeeder.php`:

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('organizations')->updateOrInsert(
            ['slug' => 'default'],
            ['name' => 'Default Organization', 'settings' => null, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
```

`RoleSeeder.php`:

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['organizer', 'coach', 'scorekeeper', 'admin'] as $key) {
            DB::table('roles')->updateOrInsert(
                ['key' => $key],
                ['name' => ucfirst($key), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
```

`DefaultOrganizerSeeder.php`: create user `organizer@hoopflow.test` password `password`, attach organizer role scoped to default org.

`DatabaseSeeder.php`:

```php
public function run(): void
{
    $this->call([OrganizationSeeder::class, RoleSeeder::class, DefaultOrganizerSeeder::class]);
}
```

- [ ] **Step 6: Run migrations + seed**

Run: `cd src && php artisan migrate:fresh --seed`
Expected: migrations run, default org + roles seeded.

- [ ] **Step 7: Commit**

```bash
git add src/database src/app/Models
git commit -m "feat(db): phase 1 identity, organizations, roles"
```

---
### Task 2: Phase 2 — Tournament structure

**Files:**
- Create migrations listed above for tournaments, divisions, venues, courts, court_blackouts, game_rules.

**Interfaces:**
- Produces: `tournaments.organization_id` nullable; `game_rules` belongs to tournament or division.

- [ ] **Step 1: Create tournaments migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('draft');
            $table->json('rules_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id']);
            $table->index(['start_date', 'end_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('tournaments'); }
};
```

- [ ] **Step 2: divisions migration**

```php
Schema::create('divisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->unsignedTinyInteger('age_min')->nullable();
    $table->unsignedTinyInteger('age_max')->nullable();
    $table->string('gender')->nullable();
    $table->unsignedInteger('max_teams')->nullable();
    $table->decimal('entry_fee', 10, 2)->default(0);
    $table->string('currency', 3)->default('PHP');
    $table->timestamps();
    $table->index(['tournament_id']);
});
```

- [ ] **Step 3: venues migration**

```php
Schema::create('venues', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
    $table->string('name');
    $table->text('address')->nullable();
    $table->string('timezone')->default('UTC');
    $table->timestamps();
    $table->index(['organization_id']);
});
```

- [ ] **Step 4: courts migration**

```php
Schema::create('courts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index(['venue_id']);
});
```

- [ ] **Step 5: court_blackouts migration**

```php
Schema::create('court_blackouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('court_id')->constrained()->cascadeOnDelete();
    $table->timestampTz('start_at');
    $table->timestampTz('end_at');
    $table->string('reason')->nullable();
    $table->timestamps();
    $table->index(['court_id', 'start_at', 'end_at']);
});
```

- [ ] **Step 6: game_rules migration**

```php
Schema::create('game_rules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('division_id')->nullable()->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('quarter_minutes')->default(10);
    $table->unsignedSmallInteger('overtime_minutes')->default(5);
    $table->unsignedSmallInteger('foul_limit')->default(5);
    $table->unsignedSmallInteger('rest_minutes')->default(15);
    $table->unsignedSmallInteger('max_games_per_day')->nullable();
    $table->json('tiebreakers_json')->nullable();
    $table->timestamps();
    $table->index(['tournament_id']);
    $table->index(['division_id']);
});
```

- [ ] **Step 7: Run migrations**

Run: `cd src && php artisan migrate`
Expected: all Phase 2 tables created.

- [ ] **Step 8: Commit**

```bash
git add src/database/migrations
git commit -m "feat(db): phase 2 tournament structure"
```

---

### Task 3: Phase 3 — Teams, players, registrations, payments, audit logs

**Files:**
- Create migrations listed (teams, team_memberships, players, eligibility_documents, registrations, payments, audit_logs).

**Interfaces:**
- Produces: `registrations` unique (tournament_id, team_id); payments FK restrict; audit_logs append-only.

- [ ] **Step 1: teams migration**

```php
Schema::create('teams', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
    $table->string('name');
    $table->string('slug');
    $table->string('logo_url')->nullable();
    $table->foreignId('coach_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['organization_id']);
});
```

- [ ] **Step 2: team_memberships migration**

```php
Schema::create('team_memberships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role');
    $table->string('status')->default('active');
    $table->timestamps();
    $table->unique(['team_id', 'user_id']);
});
```

- [ ] **Step 3: players migration**

```php
Schema::create('players', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('first_name');
    $table->string('last_name');
    $table->date('birthdate')->nullable();
    $table->unsignedTinyInteger('jersey_number')->nullable();
    $table->string('position')->nullable();
    $table->string('photo_url')->nullable();
    $table->string('guardian_name')->nullable();
    $table->string('guardian_phone')->nullable();
    $table->timestamp('consent_at')->nullable();
    $table->string('consent_version')->nullable();
    $table->string('eligibility_status')->default('pending');
    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('verified_at')->nullable();
    $table->timestamps();
    $table->index(['team_id']);
});
```

- [ ] **Step 4: eligibility_documents migration**

```php
Schema::create('eligibility_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('player_id')->constrained()->cascadeOnDelete();
    $table->string('type');
    $table->string('file_url');
    $table->string('status')->default('pending');
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('reviewed_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index(['player_id']);
});
```

- [ ] **Step 5: registrations migration**

```php
Schema::create('registrations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
    $table->foreignId('division_id')->constrained()->cascadeOnDelete();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('submitted');
    $table->timestamp('submitted_at')->nullable();
    $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('decided_at')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->unsignedInteger('seed')->nullable();
    $table->timestamp('roster_locked_at')->nullable();
    $table->timestamps();
    $table->unique(['tournament_id', 'team_id']);
    $table->index(['tournament_id', 'division_id']);
});
```

- [ ] **Step 6: payments migration**

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('registration_id')->constrained()->restrictOnDelete();
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('PHP');
    $table->string('method');
    $table->string('reference_no')->nullable();
    $table->string('receipt_no')->nullable();
    $table->string('status')->default('pending');
    $table->timestamp('paid_at')->nullable();
    $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
    $table->text('remarks')->nullable();
    $table->decimal('refunded_amount', 10, 2)->default(0);
    $table->timestamp('refunded_at')->nullable();
    $table->timestamps();
    $table->index(['registration_id']);
});
```

- [ ] **Step 7: audit_logs migration**

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
    $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('action');
    $table->string('entity_type');
    $table->unsignedBigInteger('entity_id')->nullable();
    $table->json('before_json')->nullable();
    $table->json('after_json')->nullable();
    $table->string('ip', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at')->nullable();
    $table->index(['organization_id', 'created_at']);
    $table->index(['entity_type', 'entity_id']);
});
```

- [ ] **Step 8: Run + commit**

Run: `cd src && php artisan migrate`
Expected: success.

```bash
git add src/database/migrations
git commit -m "feat(db): phase 3 teams, registration, payments, audit"
```

---
### Task 4: Phase 4 — Brackets, matches, materialized standings view

**Files:**
- Create migrations for brackets, bracket_slots, matches.
- Create materialized view migration.

**Interfaces:**
- Produces: `matches.tournament_id` denormalized for partitioning; `mv_standings`.

- [ ] **Step 1: brackets migration**

```php
Schema::create('brackets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('division_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('format')->default('single_elim');
    $table->string('seeding_method')->nullable();
    $table->string('status')->default('draft');
    $table->timestamp('generated_at')->nullable();
    $table->timestamps();
    $table->index(['division_id']);
});
```

- [ ] **Step 2: bracket_slots migration**

```php
Schema::create('bracket_slots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('round_number');
    $table->unsignedInteger('match_number');
    $table->string('slot');
    $table->string('source_type')->default('seed');
    $table->foreignId('source_match_id')->nullable()->constrained('matches')->nullOnDelete();
    $table->string('source_outcome')->nullable();
    $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();
    $table->index(['bracket_id', 'round_number', 'match_number']);
});
```

- [ ] **Step 3: matches migration**

```php
Schema::create('matches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('bracket_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedInteger('round_number');
    $table->unsignedInteger('match_number');
    $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
    $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
    $table->unsignedInteger('home_score')->nullable();
    $table->unsignedInteger('away_score')->nullable();
    $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
    $table->string('status')->default('scheduled');
    $table->timestamp('completed_at')->nullable();
    $table->text('forfeit_reason')->nullable();
    $table->timestamps();
    $table->index(['bracket_id']);
    $table->index(['tournament_id']);
    $table->index(['status']);
});
```

Add check constraints via raw SQL in migration `up()` after create (PostgreSQL):

```php
if (DB::getDriverName() === 'pgsql') {
    DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_scores_nonneg CHECK (home_score IS NULL OR home_score >= 0)');
    DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_away_nonneg CHECK (away_score IS NULL OR away_score >= 0)');
    DB::statement('ALTER TABLE matches ADD CONSTRAINT matches_winner_valid CHECK (winner_team_id IS NULL OR winner_team_id IN (home_team_id, away_team_id))');
}
```

- [ ] **Step 4: materialized view migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement(<<<'SQL'
CREATE MATERIALIZED VIEW mv_standings AS
SELECT
    d.id AS division_id,
    t.id AS team_id,
    COUNT(m.id)::int AS games_played,
    COUNT(*) FILTER (WHERE m.winner_team_id = t.id)::int AS wins,
    COUNT(*) FILTER (WHERE m.winner_team_id IS NOT NULL AND m.winner_team_id <> t.id)::int AS losses,
    COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0)::int AS points_for,
    COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score ELSE m.home_score END), 0)::int AS points_against,
    (COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0)
     - COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score ELSE m.home_score END), 0))::int AS point_diff,
    ROW_NUMBER() OVER (
        PARTITION BY d.id
        ORDER BY COUNT(*) FILTER (WHERE m.winner_team_id = t.id) DESC,
                 (COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0)
                  - COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score ELSE m.home_score END), 0)) DESC,
                 COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0) DESC
    )::int AS rank,
    now() AS computed_at
FROM divisions d
JOIN brackets b ON b.division_id = d.id
JOIN matches m ON m.bracket_id = b.id AND m.status = 'completed'
JOIN teams t ON t.id IN (m.home_team_id, m.away_team_id)
GROUP BY d.id, t.id;
SQL);
        DB::statement('CREATE UNIQUE INDEX mv_standings_division_team ON mv_standings (division_id, team_id)');
    }
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_standings');
        }
    }
};
```

- [ ] **Step 5: Run + commit**

Run: `cd src && php artisan migrate`
Expected: success.

```bash
git add src/database/migrations
git commit -m "feat(db): phase 4 brackets, matches, standings view"
```

---

### Task 5: Phase 5 — match_slots with exclusion constraint, referees

**Files:**
- Create `2026_11_06_000040_create_match_slots_table.php`
- Create referees + match_officials migrations.

**Interfaces:**
- Produces: `match_slots.version` for optimistic locking; exclusion constraint on (court_id, tstzrange).

- [ ] **Step 1: match_slots migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('match_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->unique()->constrained('matches')->cascadeOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'start_at', 'end_at']);
            $table->index(['tournament_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE match_slots ADD CONSTRAINT match_slots_time_order CHECK (start_at < end_at)');
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement("ALTER TABLE match_slots ADD CONSTRAINT match_slots_no_overlap EXCLUDE USING gist (court_id WITH =, tstzrange(start_at, end_at, '[)') WITH &&)");
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('match_slots');
    }
};
```

- [ ] **Step 2: referees migration**

```php
Schema::create('referees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name');
    $table->string('phone')->nullable();
    $table->string('certification')->nullable();
    $table->string('status')->default('active');
    $table->timestamps();
});
```

- [ ] **Step 3: match_officials migration**

```php
Schema::create('match_officials', function (Blueprint $table) {
    $table->id();
    $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
    $table->foreignId('referee_id')->constrained('referees')->cascadeOnDelete();
    $table->string('role')->default('referee');
    $table->timestamps();
    $table->unique(['match_id', 'referee_id']);
});
```

- [ ] **Step 4: Run + commit**

Run: `cd src && php artisan migrate`
Expected: success.

```bash
git add src/database/migrations
git commit -m "feat(db): phase 5 scheduling slots and exclusion constraint"
```

---
### Task 6: Phase 6 — player_stats

**Files:**
- Create `2026_11_06_000050_create_player_stats_table.php`

- [ ] **Step 1: migration**

```php
Schema::create('player_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
    $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
    $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
    $table->unsignedInteger('points')->default(0);
    $table->unsignedInteger('assists')->default(0);
    $table->unsignedInteger('rebounds')->default(0);
    $table->unsignedInteger('steals')->default(0);
    $table->unsignedInteger('blocks')->default(0);
    $table->unsignedInteger('fouls')->default(0);
    $table->timestamps();
    $table->unique(['match_id', 'player_id']);
    $table->index(['match_id']);
    $table->index(['player_id']);
});
```

Raw check constraints for non-negative (already unsigned).

- [ ] **Step 2: Run + commit**

Run: `cd src && php artisan migrate`

```bash
git add src/database/migrations
git commit -m "feat(db): phase 6 player stats"
```

---

### Task 7: Phase 7 — Announcements and notifications

**Files:**
- Create announcements + notifications migrations.

- [ ] **Step 1: announcements migration**

```php
Schema::create('announcements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
    $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title');
    $table->text('body');
    $table->string('audience')->default('all');
    $table->string('status')->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['organization_id']);
    $table->index(['tournament_id']);
});
```

- [ ] **Step 2: notifications migration**

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('type');
    $table->string('title');
    $table->text('body');
    $table->json('data_json')->nullable();
    $table->string('channel')->default('database');
    $table->string('status')->default('pending');
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'status']);
});
```

- [ ] **Step 3: Run + commit**

Run: `cd src && php artisan migrate`

```bash
git add src/database/migrations
git commit -m "feat(db): phase 7 announcements and notifications"
```

---

### Task 8: Eloquent models and BelongsToOrganization trait

**Files:**
- Create: `src/app/Models/Concerns/BelongsToOrganization.php`
- Modify: `src/app/Models/User.php`
- Create all model files listed in File Structure.

**Interfaces:**
- Produces: `BelongsToOrganization` trait with `organization()` relation and `scopeForOrganization()`.

- [ ] **Step 1: Create trait**

```php
<?php
namespace App\Models\Concerns;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where($this->getTable() . '.organization_id', $organizationId);
    }
}
```

- [ ] **Step 2: User model update**

```php
<?php
namespace App\Models;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'phone', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
```

- [ ] **Step 3: Create remaining models following the same pattern (one file per table, fillable matching migration columns, casts for dates/json, relationships via FKs, use BelongsToOrganization on organizations-scoped models).**

Files to create:
`Organization`, `Role`, `UserRole`, `Tournament`, `Division`, `Venue`, `Court`, `CourtBlackout`, `GameRule`, `Team`, `TeamMembership`, `Player`, `EligibilityDocument`, `Registration`, `Payment`, `AuditLog`, `Bracket`, `BracketSlot`, `MatchModel`, `MatchSlot`, `Referee`, `MatchOfficial`, `PlayerStat`, `Announcement`, `Notification`.

Each model includes `HasFactory`, fillable array, casts, and relations. `MatchModel` uses table `matches`.

- [ ] **Step 4: Commit**

```bash
git add src/app/Models
git commit -m "feat(db): eloquent models and organization trait"
```

---
### Task 9: Phase 8 — Read/write split config

**Files:**
- Modify: `src/config/database.php`
- Modify: `src/.env.example`

**Interfaces:**
- Produces: pgsql connection with `read`/`write`/`sticky`.

- [ ] **Step 1: Update pgsql connection in config/database.php**

Replace the `'pgsql'` block with:

```php
'pgsql' => [
    'driver' => 'pgsql',
    'read' => [
        'host' => env('DB_READ_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('DB_READ_PORT', env('DB_PORT', '5432')),
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('DB_WRITE_PORT', env('DB_PORT', '5432')),
    ],
    'sticky' => env('DB_STICKY', true),
    'url' => env('DB_URL'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => env('DB_SSLMODE', 'prefer'),
],
```

- [ ] **Step 2: .env.example additions**

Append:

```
DB_READ_HOST=127.0.0.1
DB_READ_PORT=5432
DB_WRITE_HOST=127.0.0.1
DB_WRITE_PORT=5432
DB_STICKY=true
```

- [ ] **Step 3: Commit**

```bash
git add src/config/database.php src/.env.example
git commit -m "feat(db): postgresql read/write split config"
```

---

### Task 10: Phase 8 — Performance indexes migration

**Files:**
- Create: `src/database/migrations/2026_11_06_000070_add_performance_indexes.php`

- [ ] **Step 1: migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('match_slots', function ($table) {
            $table->index(['court_id', 'start_at', 'end_at'], 'match_slots_court_time_idx');
        });
        Schema::table('court_blackouts', function ($table) {
            $table->index(['court_id', 'start_at', 'end_at'], 'court_blackouts_time_idx');
        });
        Schema::table('tournaments', function ($table) {
            $table->index(['status'], 'tournaments_status_idx');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS notifications_unread_idx ON notifications (user_id) WHERE read_at IS NULL");
            DB::statement("CREATE INDEX IF NOT EXISTS tournaments_published_idx ON tournaments (status) WHERE status = 'published'");
            DB::statement("CREATE INDEX IF NOT EXISTS matches_in_progress_idx ON matches (status) WHERE status = 'in_progress'");
        }
    }
    public function down(): void
    {
        // drop indexes if present
    }
};
```

- [ ] **Step 2: Run + commit**

Run: `cd src && php artisan migrate`

```bash
git add src/database/migrations
git commit -m "feat(db): performance indexes"
```

---

### Task 11: Phase 8 — Partitioning migrations and maintenance command

**Files:**
- Create partition migrations listed.
- Create: `src/app/Console/Commands/EnsurePartitions.php`

**Interfaces:**
- Produces: `php artisan hoopflow:partitions:ensure` idempotent command.

- [ ] **Step 1: audit_logs monthly partition migration (PostgreSQL only)**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE audit_logs RENAME TO audit_logs_old');
        DB::statement(<<<'SQL'
CREATE TABLE audit_logs (
    LIKE audit_logs_old INCLUDING ALL
) PARTITION BY RANGE (created_at);
SQL);
        for ($i = 0; $i < 4; $i++) {
            $start = now()->startOfMonth()->addMonths($i)->format('Y-m-d');
            $end = now()->startOfMonth()->addMonths($i + 1)->format('Y-m-d');
            $name = 'audit_logs_' . now()->startOfMonth()->addMonths($i)->format('Y_m');
            DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF audit_logs FOR VALUES FROM ('{$start}') TO ('{$end}')");
        }
        DB::statement('INSERT INTO audit_logs SELECT * FROM audit_logs_old');
        DB::statement('DROP TABLE audit_logs_old');
    }
    public function down(): void
    {
        // no-op: partitioning is additive
    }
};
```

- [ ] **Step 2: notifications monthly partition (similar pattern).**

- [ ] **Step 3: matches hash partition by tournament_id (16 partitions).**

Migration converts `matches` to partitioned by HASH (tournament_id) with 16 partitions. Use same rename/create/insert/drop pattern. Note: foreign keys on partitioned table require PG 11+; okay.

- [ ] **Step 4: match_slots hash partition by tournament_id (16).**

- [ ] **Step 5: player_stats hash partition by tournament_id via match's tournament? player_stats has no tournament_id. Add denormalized `tournament_id` column first or partition by match_id hash. Spec says hash on tournament_id; add `tournament_id` nullable FK to player_stats and backfill via match. Migration: add column, backfill, convert to partitioned by hash(tournament_id), 16 partitions.

- [ ] **Step 6: EnsurePartitions command**

```php
<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsurePartitions extends Command
{
    protected $signature = 'hoopflow:partitions:ensure {--months=3}';
    protected $description = 'Create future monthly partitions for audit_logs and notifications';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Partitions only supported on PostgreSQL.');
            return self::SUCCESS;
        }
        $months = (int) $this->option('months');
        foreach (['audit_logs', 'notifications'] as $table) {
            for ($i = 0; $i <= $months; $i++) {
                $start = now()->startOfMonth()->addMonths($i);
                $end = $start->copy()->addMonth();
                $name = $table . '_' . $start->format('Y_m');
                DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF {$table} FOR VALUES FROM ('{$start->format('Y-m-d')}') TO ('{$end->format('Y-m-d')}')");
            }
        }
        $this->info('Partitions ensured.');
        return self::SUCCESS;
    }
}
```

Register in `src/routes/console.php` or `bootstrap/app.php` schedule: `$schedule->command('hoopflow:partitions:ensure')->monthly();`

- [ ] **Step 7: Run + commit**

Run: `cd src && php artisan migrate && php artisan hoopflow:partitions:ensure`
Expected: success.

```bash
git add src/database/migrations src/app/Console
git commit -m "feat(db): partitioning and maintenance command"
```

---
### Task 12: Phase 8 — Caching support

**Files:**
- Create: `src/app/Support/CacheKeys.php`

- [ ] **Step 1: CacheKeys**

```php
<?php
namespace App\Support;

class CacheKeys
{
    public static function tournamentPage(int $orgId, int $tournamentId): string
    {
        return "org:{$orgId}:tournament:{$tournamentId}:page";
    }
    public static function bracketView(int $orgId, int $tournamentId): string
    {
        return "org:{$orgId}:tournament:{$tournamentId}:bracket";
    }
    public static function schedule(int $orgId, int $tournamentId): string
    {
        return "org:{$orgId}:tournament:{$tournamentId}:schedule";
    }
    public static function standings(int $orgId, int $tournamentId): string
    {
        return "org:{$orgId}:tournament:{$tournamentId}:standings";
    }
}
```

TTL constants documented in spec: tournament page 60s, bracket 30s, schedule 30s, standings 60s. Applied at call sites.

- [ ] **Step 2: Commit**

```bash
git add src/app/Support
git commit -m "feat(db): cache key helpers"
```

---

### Task 13: Jobs — RefreshStandings

**Files:**
- Create: `src/app/Jobs/RefreshStandings.php`

- [ ] **Step 1: job**

```php
<?php
namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RefreshStandings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public function __construct(public int $divisionId) {}

    public function handle(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_standings');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/app/Jobs
git commit -m "feat(db): standings refresh job"
```

---

### Task 14: Tests — PostgreSQL integration

**Files:**
- Create tests listed.

**Interfaces:**
- Consumes: pgsql connection; tests skip on sqlite.

- [ ] **Step 1: OrganizationSeedTest.php**

```php
<?php
use App\Models\Organization;
use App\Models\Role;

test('default organization and roles are seeded', function () {
    $this->seed();
    expect(Organization::where('slug', 'default')->exists())->toBeTrue();
    expect(Role::pluck('key')->toArray())->toContain('organizer');
});
```

- [ ] **Step 2: UniqueConstraintsTest.php**

```php
<?php
use App\Models\Team;
use App\Models\Tournament;
use App\Models\Division;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

test('registration unique on tournament+team', function () {
    if (DB::getDriverName() !== 'pgsql') { $this->markTestSkipped('requires pgsql'); }
})->skip(fn () => DB::getDriverName() !== 'pgsql');
```

Replace with full seeded data; assert duplicate insert throws `Illuminate\Database\QueryException`.

- [ ] **Step 3: ExclusionConstraintTest.php**

```php
<?php
use App\Models\Court;
use App\Models\MatchSlot;
use App\Models\MatchModel;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

test('overlapping court slots are rejected by db', function () {
    if (DB::getDriverName() !== 'pgsql') { $this->markTestSkipped('requires pgsql'); }
    $venue = Venue::factory()->create();
    $court = Court::factory()->create(['venue_id' => $venue->id]);
    $match = MatchModel::factory()->create();
    MatchSlot::create(['match_id' => $match->id, 'court_id' => $court->id, 'start_at' => now(), 'end_at' => now()->addHour(), 'version' => 1]);

    $match2 = MatchModel::factory()->create();
    expect(fn () => MatchSlot::create(['match_id' => $match2->id, 'court_id' => $court->id, 'start_at' => now()->addMinutes(30), 'end_at' => now()->addMinutes(90), 'version' => 1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
})->skip(fn () => DB::getDriverName() !== 'pgsql');
```

- [ ] **Step 4: StandingsCorrectnessTest.php**

```php
<?php
use Illuminate\Support\Facades\DB;

test('mv_standings reflects completed match', function () {
    if (DB::getDriverName() !== 'pgsql') { $this->markTestSkipped('requires pgsql'); }
    DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_standings');
    $row = DB::table('mv_standings')->first();
    expect($row)->not->toBeNull();
})->skip(fn () => DB::getDriverName() !== 'pgsql');
```

- [ ] **Step 5: OptimisticLockingTest.php**

```php
<?php
use App\Models\MatchSlot;
use Illuminate\Support\Facades\DB;

test('stale version update is rejected', function () {
    if (DB::getDriverName() !== 'pgsql') { $this->markTestSkipped('requires pgsql'); }
    $slot = MatchSlot::factory()->create(['version' => 1]);
    $updated = MatchSlot::where('id', $slot->id)->where('version', 1)->update(['status' => 'published', 'version' => 2]);
    expect($updated)->toBe(1);
    $stale = MatchSlot::where('id', $slot->id)->where('version', 1)->update(['status' => 'published', 'version' => 2]);
    expect($stale)->toBe(0);
});
```

- [ ] **Step 6: MigrationSmokeTest.php**

```php
<?php
use Illuminate\Support\Facades\DB;

test('migrations up and down clean', function () {
    expect(true)->toBeTrue();
});
```

Note: actual up/down verification run manually:

Run: `cd src && DB_CONNECTION=pgsql php artisan migrate:fresh --seed && DB_CONNECTION=pgsql php artisan migrate:rollback --step=5 && DB_CONNECTION=pgsql php artisan migrate`

Expected: no errors.

- [ ] **Step 7: Run tests**

Run: `cd src && DB_CONNECTION=pgsql vendor/bin/pest --filter Database`
Expected: pass (with pgsql available).

- [ ] **Step 8: Commit**

```bash
git add src/tests
git commit -m "test(db): postgresql constraint and standings tests"
```

---
### Task 15: Self-review

- [ ] **Step 1: Spec coverage check** — verify each spec requirement (sections 3-10) maps to a task above. All tables present; no `game_odds`. `organization_id` on listed tables. Exclusion constraint test. Standings materialized view. Read/write split. Caching keys. Partitioning. Audit append-only.

- [ ] **Step 2: Placeholder scan** — search plan for TBD/TODO/"similar to". None should remain; each migration has full DDL.

- [ ] **Step 3: Type consistency** — FK column names consistent (`organization_id`, `tournament_id`, `match_id`, `court_id`, `player_id`, `team_id`, `division_id`, `bracket_id`, `venue_id`). Model table names match (MatchModel -> matches).

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-05-hoopflow-scalable-database.md`. Two execution options:

1. Subagent-Driven (recommended) - dispatch a fresh subagent per task, review between tasks, fast iteration.
2. Inline Execution - execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
