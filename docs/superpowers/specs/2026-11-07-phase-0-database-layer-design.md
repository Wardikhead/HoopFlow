# Phase 0 — Complete the Database Layer Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Complete the Laravel data layer in `src/` so UI work (Phase 1+) has real, relational,
seeded data. Schema already exists (migrations ran). This phase fixes model
relations/casts, adds factories, ports the prototype seed into a deterministic
`PrototypeSeeder`, and adds Pest coverage.

## Section 1 — Model relationship audit (`app/Models`)

No schema changes. Add relations matching the verified `public.*` schema:

- `Team`: belongsTo organization, coachUser (users); hasMany registrations, memberships, players, homeMatches/awayMatches.
- `Registration`: belongsTo tournament, division, team, decider (users); hasOne payment; hasMany eligibilityDocuments (via player).
- `MatchModel`: belongsTo tournament, bracket, homeTeam, awayTeam, winnerTeam; (partitioned; id not autoincrement — keep model defaults but document).
- `Player`: belongsTo team, verifiedBy (users); hasMany eligibilityDocuments.
- `Payment`: belongsTo registration, recordedBy (users).
- `Announcement`: belongsTo organization, tournament, createdBy (users).
- `AuditLog`: belongsTo organization, actor (users); (partitioned).
- `Bracket`: belongsTo division; hasMany slots, matches.
- `BracketSlot`: belongsTo bracket, team, sourceMatch.
- Add `BelongsToOrganization` trait where `organization_id` exists; add `$casts` for json/date/decimal columns; complete `#[Fillable]`.

## Section 2 — Factories (`database/factories`)

Add factories for Tournament, Division, Venue, Court, Team, Player, Registration,
Payment, Bracket, BracketSlot, Announcement, AuditLog, CourtBlackout,
EligibilityDocument, GameRule, MatchSlot. Use Faker (PH-flavored where sensible);
align status/payment/eligibility enums with DB defaults.

## Section 3 — PrototypeSeeder (`database/seeders/PrototypeSeeder.php`)

Deterministic port of `prototype/assets/js/seed.js`:

- 1 org (default), roles, 3 users (organizer/coach/fan) with role assignments.
- 3 tournaments (Summer Cup U15 active, Pasig Youth Hoopfest U17 registration, CDO Grassroots Classic U14 draft), divisions, game rules, venues+courts.
- Teams per tournament with players (15/8/8), registrations with statuses, payments (paid/pending), matches for active cup (R16 complete, 2 QF final, 2 scheduled), announcements, audit entries.
- Idempotent via `updateOrCreate`/`firstOrCreate` keyed by slug/natural keys. Matches keyed by bracket slot positions.

Wire into `DatabaseSeeder` behind `SEED_PROTOTYPE=true` env flag so default seed stays light.

## Section 4 — Seeder tests (`tests/Feature/Database`)

- `PrototypeSeederTest`: `migrate:fresh --seed`, assert counts (3 tournaments, ≥31 teams, players, ≥8 matches, payments, announcements, audit≥8) and FK integrity (no orphan registrations/players).
- Run on SQLite in CI; add a pgsql job for partition tables.

## Section 5 — Acceptance

- `php artisan migrate:fresh --seed` succeeds on pgsql.
- `composer test` green.
- Document in `src/README.md`.

## Decisions

- Seed data: full prototype seed (demo story matters).
- Demo users seeded in Phase 0 with roles.
- Tests: SQLite for unit/feature; pgsql for integration/partitions.
- Approach: manual PHP port of seed.js (Approach 1).
