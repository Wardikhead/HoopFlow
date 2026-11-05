# HoopFlow Data Architecture and Tenancy Strategy

- **Status:** Draft for review
- **Date:** 2026-11-05
- **Owner:** HoopFlow team (Jeward Mahinay)
- **Related:** Approach C — tenant-ready single database

## 1. Context

HoopFlow is a Laravel 13 + PostgreSQL 17 modular monolith (D-009) with a separate worker process (12 Target Architecture). v1 serves a single league/club (D-008), but the product must be able to grow beyond one league later without a data migration rewrite. Non-functional targets (13 NFRs): 1,000 teams, 5,000 players, 200 concurrent users; public read p95 under 500 ms, write p95 under 1 s; 99.5% availability on tournament weekends; daily backups (RPO 24h, RTO 4h). D-008 commits v1 to single-tenant behavior; multi-tenant SaaS is a later decision, not a v1 requirement.

## 2. Decision summary

Approach C is selected: a **tenant-ready single database**.

- One PostgreSQL database serves v1. No database-per-tenant and no separate tenant databases in v1.
- Every domain table carries a nullable `organization_id` foreign key now, even though v1 seeds exactly one default organization. This keeps the schema migration-free when tenancy is activated.
- v1 does **not** implement Row-Level Security (RLS), tenant provisioning, tenant switching, billing, or multi-tenant onboarding. Isolation is enforced at the application layer by always scoping queries to the single default organization.
- Growth is handled by **partitioning** high-volume tables, **materialized/transactional standings**, a **short-TTL public cache**, **PostgreSQL exclusion constraints** plus **optimistic locking**, **queued background jobs**, and **targeted indexes**.
- Later tenancy evolution (in order): app-level scoping -> PostgreSQL RLS -> optional database-per-tenant. Each step is additive and does not require re-shaping v1 tables.

## 3. Baseline schema

Source: `HoopFlow/11 Target Data Model.md` (target v2 model; design direction, not final DDL). `game_odds` is excluded — do not create it.

### 3.1 Identity and access

| Table | Purpose | Key fields |
|---|---|---|
| `organizations` | Optional tenant/league | id, name, slug, settings (jsonb), created_at |
| `users` | All accounts | id, email (unique), password_hash, first_name, last_name, phone, status, email_verified_at, created_at, updated_at |
| `roles` | Role definitions | id, key (organizer, coach, scorekeeper, admin), name |
| `user_roles` | Multi-role support | user_id, role_id, organization_id (nullable), scope, unique(user_id, role_id, scope) |
| `sessions` | Session records if not using an auth provider | id, user_id, token_hash, expires_at, ip, user_agent |
| `password_reset_tokens` | Password reset | id, user_id, token_hash, expires_at, used_at |

### 3.2 Tournament structure

| Table | Purpose | Key fields |
|---|---|---|
| `tournaments` | Tournament header | id, organization_id, name, slug, description, start_date, end_date, status, rules_json, created_by |
| `divisions` | Age/gender divisions | id, tournament_id, name, age_min, age_max, gender, max_teams, entry_fee, currency |
| `venues` | Physical venues | id, name, address, timezone |
| `courts` | Courts inside a venue | id, venue_id, name, notes |
| `court_blackouts` | Unavailable periods | id, court_id, start_at, end_at, reason |
| `game_rules` | Rule configuration | id, tournament_id/division_id, quarter_minutes, overtime_minutes, foul_limit, rest_minutes, max_games_per_day, tiebreakers_json |

### 3.3 Teams and eligibility

| Table | Purpose | Key fields |
|---|---|---|
| `teams` | Team profile | id, name, slug, logo_url, coach_user_id, created_at |
| `team_memberships` | Coaches/managers on a team | id, team_id, user_id, role, status, unique(team_id, user_id) |
| `players` | Player profile | id, team_id, first_name, last_name, birthdate, jersey_number, position, photo_url, guardian_name, guardian_phone, consent_at, consent_version, eligibility_status, verified_by, verified_at |
| `eligibility_documents` | Supporting documents | id, player_id, type, file_url, status, reviewed_by, reviewed_at, notes |
| `registrations` | Team entry into a tournament/division | id, tournament_id, division_id, team_id, status, submitted_at, decided_by, decided_at, rejection_reason, seed, roster_locked_at, unique(tournament_id, team_id) |
| `payments` | Payment ledger | id, registration_id, amount, currency, method, reference_no, receipt_no, status, paid_at, recorded_by, remarks, refunded_amount, refunded_at, created_at |

### 3.4 Brackets and matches

| Table | Purpose | Key fields |
|---|---|---|
| `brackets` | Bracket header | id, division_id, name, format (single_elim, round_robin), seeding_method, status, generated_at |
| `bracket_slots` | Bracket topology | id, bracket_id, round_number, match_number, slot, source_type (seed, match, bye), source_match_id, source_outcome (winner, loser), team_id |
| `matches` | Individual match | id, bracket_id, round_number, match_number, home_team_id, away_team_id, home_score, away_score, winner_team_id, status, completed_at, forfeit_reason, created_at |
| `match_slots` | Court and time assignment | id, match_id (unique), court_id, start_at, end_at, status, version, published_at |
| `match_officials` | Referee assignment (later) | id, match_id, referee_id, role |
| `referees` | Referee profile (later) | id, user_id (nullable), name, phone, certification, status |

### 3.5 Statistics and standings

| Table | Purpose | Key fields |
|---|---|---|
| `player_stats` | Post-game stats | id, match_id, player_id, team_id, points, assists, rebounds, steals, blocks, fouls, unique(match_id, player_id) |
| `standings` | Computed standings | View or materialized view: division_id, team_id, games_played, wins, losses, points_for, points_against, point_diff, rank, computed_at |

### 3.6 Communication and audit

| Table | Purpose | Key fields |
|---|---|---|
| `announcements` | Broadcast messages | id, tournament_id (nullable), title, body, audience, status, published_at, created_by |
| `notifications` | Per-user notifications | id, user_id, type, title, body, data_json, channel, status, sent_at, read_at |
| `audit_logs` | Append-only audit trail | id, actor_user_id, action, entity_type, entity_id, before_json, after_json, ip, user_agent, created_at |
| `fan_follows` | Deferred — do not build in v1 | — |

### 3.7 Baseline constraints (from 11.7)

- `users.email` unique.
- `registrations` unique on `(tournament_id, team_id)`.
- `player_stats` unique on `(match_id, player_id)`.
- Scores non-negative; `start_at < end_at` on every slot.
- Overlapping court slots prevented via `tstzrange` exclusion constraint.
- Financial and audit rows use `ON DELETE RESTRICT`; dependent child rows may cascade.
- Every mutable table has `created_at` and `updated_at`.
- `audit_logs` is append-only.
- `winner_team_id` must match the score or be derived.
- Standings are computed, never manually edited.

## 4. Tenancy model

### 4.1 v1 behavior

- A single default organization is seeded in Phase 1 (id stable, e.g. slug `default`). All v1 data belongs to it.
- `organization_id` is nullable on all domain tables (see 4.3). It is populated on insert from the active organization context (the seeded default in v1) and never null in practice, but nullable keeps the column optional for global/shared rows and avoids breaking seed/import paths.
- No tenant switching, no tenant selection UI, no per-tenant configuration surface beyond the seeded row.
- No tenant provisioning, billing, or onboarding flows.

### 4.2 Future evolution (additive, in order)

1. **App-level scoping.** Laravel global scopes / Eloquent `BelongsToOrganization` trait filter every query by `organization_id` from the authenticated user's organization. Cross-tenant access is impossible at the ORM layer. No schema change.
2. **PostgreSQL RLS.** Enable RLS policies on domain tables keyed on `current_setting('app.organization_id')`, set per transaction by Laravel. Database enforces isolation even if app code forgets a scope. Migration adds policies; no data movement.
3. **Optional database-per-tenant.** Only if a single tenant outgrows the shared DB or requires physical isolation. Move that tenant's rows (partitioned by `organization_id`) to a dedicated database; app resolves the connection per request. v1's nullable `organization_id` makes this a data move, not a schema rewrite.

### 4.3 Tables receiving `organization_id`

`organizations` (self), `tournaments`, `teams`, `venues` (optional — shared venues may stay global; nullable supports both), `user_roles` (already specified), `announcements`, `audit_logs`. Plus any new domain table added later. Tables that are pure children of an organization-scoped parent (divisions, courts, players, matches, etc.) inherit scope through their parent FK and do not need their own column unless direct listing/filtering by organization is required; the default is to add `organization_id` to top-level aggregates (`tournaments`, `teams`) and to cross-cutting tables (`user_roles`, `announcements`, `audit_logs`), and to propagate via FKs elsewhere. Where a child is queried independently and partitioned (e.g. future `matches`), `organization_id` may be denormalized for partition pruning.

## 5. Data isolation and security

- v1 is single-tenant; no cross-tenant queries exist because only one organization exists. The nullable column is forward-compatibility, not active isolation.
- Foreign keys are declared on all relationships. Financial and audit rows use `ON DELETE RESTRICT` (payments, audit_logs, and any ledger/immutable record). Dependent child rows (bracket_slots, match_slots, player_stats, team_memberships, eligibility_documents) may `CASCADE` on parent delete where the parent owns them.
- `audit_logs` is append-only: no UPDATE/DELETE granted to the app role; corrections are new rows.
- Passwords hashed with bcrypt/Argon2; secrets in env; CSRF on state changes; rate-limited auth; least-privilege DB role (12.4).
- Score edits, bracket overrides, schedule changes, and role changes are audited.

## 6. Scalability design

### 6.1 Partitioning

Partition **high-volume, time- or tournament-scoped** tables; leave small/lookup tables unpartitioned.

- Partitioned: `matches`, `match_slots`, `player_stats`, `notifications`, `audit_logs` (and `standings` if materialized as a table).
- Strategy: `matches`, `match_slots`, `player_stats` by **HASH on `tournament_id`** (co-locates a tournament's data, aids pruning on tournament-scoped reads). `notifications` and `audit_logs` by **RANGE on `created_at` monthly**.
- Unpartitioned: `users`, `roles`, `organizations`, `teams`, `players`, `venues`, `courts`, `tournaments`, `divisions`, `registrations`, `payments`, `brackets`, `bracket_slots`, `announcements`, `game_rules`, `court_blackouts` (small).
- Partition maintenance: a scheduled job (Laravel scheduler + worker) creates future partitions N months ahead and drops/archives partitions older than the retention window; partition creation is idempotent.
- Partition count for hash partitions is fixed at creation (e.g. 16) and only grows via table rebuild, which is deferred until needed.

### 6.2 Derived data

- Standings are a **materialized view** (`mv_standings`) refreshed by a queued job after each match completion/score correction, not computed on read and not manually edited. A transactional `standings` table updated in the same transaction as match completion is an acceptable alternative if staleness is unacceptable; v1 uses the materialized view + recompute job for simplicity, with a short refresh.
- `winner_team_id` is derived from scores in the application/DB (check constraint or trigger) and never trusted from input.
- Bracket auto-advance runs in the worker for large divisions; small divisions may run synchronously.

### 6.3 Concurrency

- Court slot conflicts are prevented by a PostgreSQL **exclusion constraint** on `tstzrange(start_at, end_at)` per `court_id` (requires `btree_gist`), not by application transactions alone (addresses P1-01).
- `match_slots.version` supports **optimistic locking**: score/schedule updates compare-and-swap on `version`; stale updates are rejected with a version-mismatch error (also used by the v1.1 live scoreboard).
- Match completion and winner derivation run in a single transaction with the standings refresh job enqueued.

### 6.4 Indexing

- Index all foreign keys and `(organization_id)` where present.
- Date/time columns: `match_slots(start_at, end_at)`, `court_blackouts(start_at, end_at)`, `tournaments(start_date, end_date)`.
- Unique indexes: `users(email)`, `registrations(tournament_id, team_id)`, `player_stats(match_id, player_id)`, `match_slots(match_id)`.
- Partial indexes for hot paths: published tournaments, in-progress matches, unread notifications.
- Covering indexes for public bracket/standings reads to avoid heap fetches.

### 6.5 Read scaling

- Laravel read/write split: `DB::connection` configured with separate `read` (replica) and `write` (primary) hosts via `config/database.php` `read`/`write` arrays.
- Public pages (tournament, bracket, schedule, standings, results) read from the replica with a short staleness tolerance.
- All writes and any read-after-write (organizer preview, score entry confirmation) use the primary.
- Replica lag is acceptable for public reads (seconds); organizer-facing mutations always primary.

### 6.6 Caching

- Driver: database cache initially; Redis when traffic demands (Phase 8/hardening).
- Cached with short TTLs, invalidated on write:
  - Public tournament page: 60s, invalidated on tournament update/publish.
  - Public bracket view: 30s, invalidated on bracket publish/score.
  - Public schedule: 30s, invalidated on schedule publish/change.
  - Public standings: 60s, invalidated on match completion.
  - Team logos/documents: object storage + long TTL (immutable URLs).
- Cache keys include organization + tournament ids for future tenant safety.

### 6.7 Jobs and worker

- Worker process (Laravel queue worker + scheduler) handles: bracket generation, schedule generation, notification dispatch, email, standings refresh, exports, partition maintenance.
- Queue driver: database initially; Redis when throughput requires.
- Session driver: database initially; Redis later.
- Retry with backoff; failed-job logging; idempotent job handlers.

## 7. Migration plan by phase (aligns with 15 Development Roadmap)

- **Phase 1 (walking skeleton):** Create `organizations`, `users`, `roles`, `user_roles`, `sessions`, `password_reset_tokens`. Seed default organization + organizer account. Migrations only; no partitioning/cache/replica.
- **Phase 2 (tournament setup):** `tournaments`, `divisions`, `venues`, `courts`, `court_blackouts`, `game_rules`. Add `organization_id` to `tournaments` (nullable). Public tournament page cache (60s) introduced.
- **Phase 3 (registration/roster):** `teams`, `team_memberships`, `players`, `eligibility_documents`, `registrations`, `payments`. Index FKs. Audit events begin writing `audit_logs` (unpartitioned initially).
- **Phase 4 (brackets):** `brackets`, `bracket_slots`, `matches`. Bracket generation as queued job. Materialized standings view introduced (refresh on completion).
- **Phase 5 (scheduling):** `match_slots`, `match_officials`, `referees`. Add `tstzrange` exclusion constraint on `match_slots`. Add `version` column for optimistic locking. Partition `match_slots` by hash(tournament_id) when volume warrants (deferred to Phase 8 unless needed).
- **Phase 6 (scorekeeping/standings):** `player_stats`. Standings refresh job. Partition `matches` and `player_stats` if load requires.
- **Phase 7 (notifications):** `announcements`, `notifications`. Partition `notifications` by month. Outbox + retry.
- **Phase 8 (hardening):** Enable read replica + Laravel read/write split; migrate cache/queue/session to Redis if metrics justify; partition `audit_logs` by month; add covering/partial indexes; backup/restore test; performance check with seed data (1,000 teams / 5,000 players).

Partitioning, caching TTL tuning, replica, and Redis are **additive** and land in Phase 8 unless an earlier phase hits a measurable bottleneck.

## 8. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Nullable `organization_id` allows accidental global rows | Seed default org; app context always sets it; future app-level scope rejects null in v2. |
| Partitioning added late causes lock-heavy migration | Partition tables before data volume grows (Phase 5/6); test on staging. |
| Replica lag shows stale public data | Short TTL cache + primary for organizer reads; accept seconds of lag on public pages. |
| Standings staleness | Refresh job triggered on match completion; recompute on correction; materialized view `REFRESH CONCURRENTLY`. |
| Court conflicts under concurrency | DB exclusion constraint, not app logic; transactions + optimistic locking. |
| Scope creep into multi-tenancy | D-008 keeps v1 single-tenant; RLS/provisioning explicitly out of scope (section 9). |
| Audit tampering | Append-only table, restricted DB role, hashed/immutable storage. |

## 9. Out of scope for v1

- Multi-tenant provisioning, onboarding, billing, and tenant switching.
- PostgreSQL RLS policies (deferred to tenancy step 2).
- Database-per-tenant (deferred to tenancy step 3).
- `fan_follows` / fan accounts.
- Live play-by-play; full live score entry (v1.1 optional lightweight scoreboard only).
- Referee assignment (deferred unless rubric requires).
- Native mobile apps, microservices, Kubernetes, GraphQL, event sourcing.

## 10. Acceptance criteria / success metrics

- Schema has nullable `organization_id` on all domain tables listed in 4.3; default organization seeded.
- Migrations run in roadmap order (section 7) on a clean database and on staging without manual steps.
- Court slot conflicts are rejected by the database (exclusion constraint test passes).
- Standings update within one job cycle after a match completion; `winner_team_id` is never inconsistent with scores.
- Public pages meet p95 < 500 ms with seed data (1,000 teams / 5,000 players) after caching.
- Read/write split routes organizer writes to primary and public reads to replica.
- Audit log is append-only (no UPDATE/DELETE permitted to app role) and covers score edits, bracket overrides, schedule changes, role changes.
- No `game_odds` table exists.
- All mutable tables have `created_at`/`updated_at`.

## 11. References

- `HoopFlow/00 HoopFlow.md` — dossier index.
- `HoopFlow/08 Decisions Log.md` — D-008 (single league v1), D-009 (stack), D-010 (deployment).
- `HoopFlow/09 Scope.md` — v1 scope and defer list.
- `HoopFlow/11 Target Data Model.md` — baseline schema (sections 11.1–11.8).
- `HoopFlow/12 Target Architecture.md` — modular monolith, security/ops/performance checklists.
- `HoopFlow/13 Non-Functional Requirements.md` — scale and latency targets.
- `HoopFlow/15 Development Roadmap.md` — phases 0–8.
- `docs/decisions/ADR-0001-stack.md` — stack decision.
- `docs/superpowers/specs/` — prior design specs.
