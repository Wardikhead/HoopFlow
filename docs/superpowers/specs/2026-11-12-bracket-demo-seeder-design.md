# Bracket demo seeder design

- **Date:** 2026-11-12
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/database/seeders/BracketDemoSeeder.php`, one feature test, no production code changes.

## Problem

The prototype seeder demonstrates only one real bracket: a hand-built 16-team single-elimination Summer Cup. The Pasig and CDO tournaments have teams but no brackets. There is no seeded data that lets an organizer open the bracket page and inspect the new double-elimination or round-robin engines, their series games, or the round-robin playoff gate.

## Goals

1. Provide a one-command, destructive demo seed with exactly three tournaments: single elimination, double elimination, and round robin.
2. Generate every bracket through `BracketService` and every schedule through `SchedulingService`, so the seeded data is produced by the real engines.
3. Make the demo visually complete: 16 teams per elimination tournament, 8 teams for round robin, rosters, venues, courts, and published schedules.
4. Keep `PrototypeSeeder` unchanged as the stable fixture for the existing test suite.

## Non-goals

- No changes to `PrototypeSeeder` or `DatabaseSeeder`.
- No changes to bracket, scheduling, standings, or UI production code.
- No pre-played results or crowned champions.
- No new competition formats.
- No production-safe data migration; the demo seeder is explicitly destructive.

## Decisions

### 1. Seeder and usage

Add `src/database/seeders/BracketDemoSeeder.php`.

Run it manually:

```powershell
php artisan db:seed --class=BracketDemoSeeder
```

The seeder is not registered in `DatabaseSeeder`. It ensures the default organization, roles, and organizer account exist, then clears demo data and rebuilds it. It prefers `organizer@hoopflow.ph` / `password` so the familiar prototype login works, creating that organizer if missing.

### 2. Destructive reset

The seeder deletes rows in dependency order before rebuilding:

1. `player_stat_quarters`, `player_stats`, `match_officials`
2. `match_slots`, `bracket_slots`, `matches`, `brackets`
3. `payments`, `registrations`, `announcements`, `audit_logs`
4. `game_rules`, `divisions`
5. `court_blackouts`, `courts`, `venues`, `tournaments`
6. `team_memberships`, `eligibility_documents`, `players`, `teams`

Users, roles, the organization, and unrelated application tables are kept. Running the seeder repeatedly produces the same three tournaments; it is idempotent by clearing first.

### 3. Tournament data

| Tournament | Slug | Format | Teams | Rules |
|---|---|---|---|---|
| HoopFlow Single Elimination Showcase | `demo-single-elimination` | single elimination | 16 | Best-of-1 throughout |
| HoopFlow Double Elimination Showcase | `demo-double-elimination` | double elimination | 16 | QF, SF, F, and grand final best-of-3 |
| HoopFlow Round Robin Showcase | `demo-round-robin` | round robin | 8 | 1 leg; top-4 playoffs; playoff SF and final best-of-3 |

All three tournaments are `active`. Each has one division, one venue with four courts, and a `GameRule` row.

- Single elimination: U17, 16 slots, Dec 1–4, 2026.
- Double elimination: U17, 16 slots, Dec 8–16, 2026.
- Round robin: U15, 8 slots, Dec 15–21, 2026.

`rules_json` carries `slots`, `fee`, `format`, `venue_id`, `round_robin_legs`, `playoff_teams`, `best_of_quarterfinal`, `best_of_semifinal`, and `best_of_final`.

### 4. Teams and rosters

Create 16 shared demo teams with unique names, short names, colors, coaches, and cities. Each team gets 10 players with verified consent and approved birth certificates.

- Single elimination and double elimination register all 16 teams with seeds 1–16.
- Round robin registers the first 8 teams with seeds 1–8.
- Every registration is `approved` and paid.

Reusing the same teams across the two elimination tournaments keeps the seed small and is valid because teams may enter multiple tournaments.

### 5. Generation and scheduling

For each tournament:

1. Call `app(BracketService::class)->generate($tournament, $format)`.
2. Call `app(SchedulingService::class)->generate($tournament)`.

Expected shapes:

- Single elimination: 15 matches across 4 rounds.
- Double elimination: 30 anchor matchups (15 winners, 14 losers, 1 grand final). With best-of-3 QF/SF/F/GF, child games are created upfront. No reset match is created because the grand final is a series. Conditional games stay `conditional` and unscheduled.
- Round robin: 7 rounds and 28 games, with no byes and no phantom matches. The top-4 playoff bracket is not generated; the organizer can generate it from the UI once group play is complete.

No scores, winners, forfeits, or completed timestamps are seeded. All matches remain `scheduled` or `conditional`.

### 6. Verification

Add `src/tests/Feature/Database/BracketDemoSeederTest.php` covering:

- Exactly three demo tournaments with the expected slugs and formats.
- Approved team counts of 16, 16, and 8.
- Single-elimination bracket has 15 matches.
- Double-elimination bracket has 30 anchor matchups, best-of-3 on QF/SF/F/GF, series child games, and no reset.
- Round robin has 7 rounds, 28 games, and no phantom matches.
- Schedules exist, and no conditional match has a match slot.
- No match has a score, winner, or final status.

Existing tests stay on `PrototypeSeeder` and must remain green. Run `composer quality` after implementation.

## Acceptance criteria

1. `php artisan db:seed --class=BracketDemoSeeder` completes on a fresh database.
2. The tournament list shows exactly the three demo tournaments.
3. Each bracket page renders the correct format, stage sections, and series metadata.
4. The double-elimination page shows winners, losers, and grand-final sections; only playable winners-round-one games have court slots.
5. The round-robin page shows 7 rounds and 28 scheduled group games, with the Generate playoffs action gated until the group is complete.
6. `composer quality` passes.
