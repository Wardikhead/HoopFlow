# 11. Target Data Model

> **Part of:** [[00 HoopFlow]]

This is the target v2 model. It is a design direction, not final DDL. The current ERD is in the source document.

## 11.1 Identity and access

| Table | Purpose | Key fields and notes |
|---|---|---|
| `organizations` | Optional tenant/league | id, name, slug, settings, created_at |
| `users` | All accounts | id, email unique, password_hash, first_name, last_name, phone, status, email_verified_at, created_at, updated_at |
| `roles` | Role definitions | id, key (organizer, coach, scorekeeper, admin), name |
| `user_roles` | Multi-role support | user_id, role_id, organization_id nullable, scope, unique(user_id, role_id, scope) |
| `sessions` | Session records if not using an auth provider | id, user_id, token_hash, expires_at, ip, user_agent |
| `password_reset_tokens` | Password reset | id, user_id, token_hash, expires_at, used_at |

## 11.2 Tournament structure

| Table | Purpose | Key fields and notes |
|---|---|---|
| `tournaments` | Tournament header | id, organization_id, name, slug, description, start_date, end_date, status, rules_json, created_by |
| `divisions` | Age/gender divisions | id, tournament_id, name, age_min, age_max, gender, max_teams, entry_fee, currency |
| `venues` | Physical venues | id, name, address, timezone |
| `courts` | Courts inside a venue | id, venue_id, name, notes |
| `court_blackouts` | Unavailable periods | id, court_id, start_at, end_at, reason |
| `game_rules` | Rule configuration | id, tournament_id/division_id, quarter_minutes, overtime_minutes, foul_limit, rest_minutes, max_games_per_day, tiebreakers_json |

## 11.3 Teams and eligibility

| Table | Purpose | Key fields and notes |
|---|---|---|
| `teams` | Team profile | id, name, slug, logo_url, coach_user_id, created_at |
| `team_memberships` | Coaches/managers on a team | id, team_id, user_id, role, status, unique(team_id, user_id) |
| `players` | Player profile | id, team_id, first_name, last_name, birthdate, jersey_number, position, photo_url, guardian_name, guardian_phone, consent_at, consent_version, eligibility_status, verified_by, verified_at |
| `eligibility_documents` | Supporting documents | id, player_id, type, file_url, status, reviewed_by, reviewed_at, notes |
| `registrations` | Team entry into a tournament/division | id, tournament_id, division_id, team_id, status, submitted_at, decided_by, decided_at, rejection_reason, seed, roster_locked_at, unique(tournament_id, team_id) |
| `payments` | Payment ledger | id, registration_id, amount, currency, method, reference_no, receipt_no, status, paid_at, recorded_by, remarks, refunded_amount, refunded_at, created_at |

## 11.4 Brackets and matches

| Table | Purpose | Key fields and notes |
|---|---|---|
| `brackets` | Bracket header | id, division_id, name, format (single_elim, round_robin), seeding_method, status, generated_at |
| `bracket_slots` | Bracket topology | id, bracket_id, round_number, match_number, slot, source_type (seed, match, bye), source_match_id, source_outcome (winner, loser), team_id |
| `matches` | Individual match | id, bracket_id, round_number, match_number, home_team_id, away_team_id, home_score, away_score, winner_team_id, status, completed_at, forfeit_reason, created_at |
| `match_slots` | Court and time assignment | id, match_id unique, court_id, start_at, end_at, status, version, published_at |
| `match_officials` | Referee assignment (later) | id, match_id, referee_id, role |
| `referees` | Referee profile (later) | id, user_id nullable, name, phone, certification, status |

## 11.5 Statistics and standings

| Table | Purpose | Key fields and notes |
|---|---|---|
| `player_stats` | Post-game stats | id, match_id, player_id, team_id, points, assists, rebounds, steals, blocks, fouls, unique(match_id, player_id) |
| `standings` | Computed standings | View or materialized view: division_id, team_id, games_played, wins, losses, points_for, points_against, point_diff, rank, computed_at |
| `game_odds` | Removed | Do not use |

## 11.6 Communication and audit

| Table | Purpose | Key fields and notes |
|---|---|---|
| `announcements` | Broadcast messages | id, tournament_id nullable, title, body, audience, status, published_at, created_by |
| `notifications` | Per-user notifications | id, user_id, type, title, body, data_json, channel, status, sent_at, read_at |
| `audit_logs` | Append-only audit trail | id, actor_user_id, action, entity_type, entity_id, before_json, after_json, ip, user_agent, created_at |
| `fan_follows` | Deferred | Optional; do not build in v1 |

## 11.7 Key constraints

- `users.email` is unique.
- `registrations` is unique on `(tournament_id, team_id)`.
- `player_stats` is unique on `(match_id, player_id)`.
- Scores are non-negative.
- `start_at < end_at` on every slot.
- Prevent overlapping court slots with an application transaction or a PostgreSQL exclusion constraint using `tstzrange`.
- Financial and audit rows use `ON DELETE RESTRICT`; dependent child rows may cascade.
- Every mutable table has `created_at` and `updated_at`.
- `audit_logs` is append-only.
- `winner_team_id` must match the score or be derived.
- Standings are computed, not manually edited.

## 11.8 Migration note

Do not build the final schema all at once. Create migrations in the order of the roadmap, starting with users, tournaments, divisions, teams, players, registrations, brackets, matches, and match slots. Add payments, stats, notifications, and audit when their phases begin.
