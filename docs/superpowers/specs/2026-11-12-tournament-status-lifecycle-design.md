# Tournament status lifecycle design

- **Date:** 2026-11-12
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Scope:** `src/` tournament status transitions, bracket generation and result hooks, registration review gating, status presentation, audit entries, and tests.

## Problem

`tournaments.status` is written once when the create wizard stores the tournament as `registration-open`. Nothing transitions it afterward:

- No service, observer, command, scheduled job, or UI action changes the status.
- A tournament created through the app stays `registration-open` forever, so coach registration never closes.
- It never becomes `active`, so active-tournament defaults on standings, announcements, and the public header can keep pointing at a seeded demo tournament.
- There is no `completed` state, so finished tournaments look identical to running ones.
- Pending registrations can still be approved after bracket generation, even though the seeds and matchups are already fixed and a late approval cannot enter the bracket.

## Goals

1. Close registration and activate the tournament when the bracket is generated.
2. Complete the tournament when the championship is decided.
3. Complete a round-robin tournament without playoffs when every group game is resolved.
4. Keep status transitions event-driven and auditable; do not add a date-based scheduler.
5. Block late approvals after seeds lock while still allowing organizers to reject pending teams and clear the queue.
6. Show `completed` consistently in status chips, filters, and the tournament hero.
7. Cover the behavior with unit and feature tests.

## Non-goals

- No scheduled job that changes status by calendar date.
- No manual "Close registration", "Activate", "Complete", or "Reopen" button.
- No reopening a completed tournament.
- No new registration status or registration table change.
- No database migration; `tournaments.status` stays a string.
- No change to the public API or the prototype under `prototype/`.
- No change to bracket generation, scoring, scheduling, or standings rules beyond the lifecycle hooks.
- No automatic cleanup of pending registrations at activation. Organizers reject them with a reason.

## Relationship to prior specs

- The bracket engine spec (`2026-11-11-bracket-engine-playoff-series-design.md`) and the bracket series/schedule UX spec (`2026-11-12-bracket-series-schedule-ux-design.md`) already make bracket generation the point where approved teams are seeded and seeds lock. This spec attaches the tournament lifecycle to that event.
- Tournament creation keeps writing `registration-open`; this spec only adds the transitions after creation.
- The prototype UI spec's status chip set is extended with `completed`. Existing `draft`, `registration-open`, `active`, and `upcoming` values keep their current labels.

## Decisions

### 1. State model

`draft` -> `registration-open` -> `active` -> `completed`

`upcoming` remains a legacy pre-activation value. It is accepted as an activation source but is not written by new lifecycle code.

| Event | From | To |
|---|---|---|
| Tournament creation (existing behavior) | `draft` | `registration-open` |
| Bracket generated, including playoff generation | `draft`, `upcoming`, `registration-open` | `active` |
| Champion resolved | any non-`completed` state | `completed` |
| Round-robin, no playoff intent, all group games `final`/`forfeit` | any non-`completed` state | `completed` |

Rules:

- `completed` is terminal. Regenerating a bracket or correcting a result never reopens a tournament.
- Status is not driven by `start_date` or `end_date`.
- Activation closes registration automatically. `TeamRegister` already requires `status === 'registration-open'`, so no new registration state is needed.
- No migration is required. The existing `tournaments_status_idx` index covers status filters.

### 2. Activation

Add `App\Services\TournamentLifecycleService`:

```php
public function activate(Tournament $tournament): bool
```

- Refreshes the tournament before checking its status.
- Transitions `draft`, `upcoming`, or `registration-open` to `active`.
- Returns `false` without writing when the status is already `active` or `completed`.
- Writes one audit entry when the transition happens: action `tournament.activate`, entity `tournament`, before/after status.
- Uses the caller's transaction. If bracket persistence rolls back, the status and audit entry roll back too.

Activation is called inside the existing transactions in:

- `BracketService::generate()` after the bracket plan is persisted and the `bracket.generate` audit entry is written.
- `BracketService::generatePlayoffs()` after the playoff bracket is persisted and audited.

`generatePlayoffs()` is included defensively so a tournament whose group bracket predates this lifecycle still activates when playoffs are generated.

### 3. Completion

```php
public function syncCompletion(Tournament $tournament, ?Team $champion = null): bool
```

- Refreshes the tournament. Returns `false` when the status is already `completed`.
- Completes when `$champion` is non-null.
- Otherwise applies the round-robin fallback only when all of these are true:
  - `format()` is `round-robin`.
  - The `playoff_teams` rule is `0` or absent. A configured playoff field means the tournament is "with playoffs" and must wait for a playoff champion.
  - No bracket with `phase = 'playoffs'` exists for the tournament's divisions.
  - A bracket with `phase = 'group'` exists.
  - At least one group match exists.
  - Every group match status is `final` or `forfeit`.
- A `cancelled` group match blocks completion. The requirement is strictly `final`/`forfeit`; cancellation means the group stage still needs a resolution.
- Writes one audit entry when the transition happens: action `tournament.complete`, entity `tournament`, before/after status.
- Returns `false` when no transition happens.

The champion is resolved by `BracketService::champion()` and passed into the service. The lifecycle service does not inject `BracketService`, so there is no circular dependency.

Completion is monotonic:

- Once `completed`, later corrections keep the status and do not write another audit entry.
- Champion display remains live. If an organizer corrects the final, the bracket shows the corrected champion while the tournament stays completed.

### 4. Hook points

| Location | Timing | Call |
|---|---|---|
| `BracketService::generate()` | Inside the existing transaction, after persistence and audit | `activate($tournament)` |
| `BracketService::generatePlayoffs()` | Inside the existing transaction, after persistence and audit | `activate($tournament)` |
| `BracketService::applyGameResult()` | After series sync, structural byes, and grand-final reset sync settle | `syncCompletion($game->tournament, $this->champion(...))` |
| `BracketService::correctScore()` | Inside the existing transaction, after propagation and audit | `syncCompletion($anchor->tournament, $this->champion(...))` |

`applyGameResult()` is the shared result path for `recordScore()`, `recordForfeit()`, and `finalizeMatch()`, so every score and forfeit is covered. `correctScore()` writes results separately and needs its own hook.

`BracketService` resolves the lifecycle service with `app(TournamentLifecycleService::class)`, matching its existing `app(AuditLogger::class)` usage. This preserves the many tests that instantiate `new BracketService` directly.

All hooks stay inside the existing database transactions. A failed result or bracket write cannot leave a status transition behind.

### 5. Registration review after activation

`Tournament::seedsLocked()` already reports whether any matches exist, which is true immediately after bracket generation.

When `seedsLocked()` is true:

- `SubmissionsIndex::approve()` refuses to open the approval dialog and dispatches a warning toast.
- `SubmissionsIndex::confirmApprove()` and `doApprove()` remain guarded as server-side defense.
- The submissions list hides "Approve" and shows a `Seeds locked` chip for pending teams.
- The review dialog hides "Approve team" and keeps "Reject".
- Rejecting still requires the existing reason and archives the registration.

Coach registration closes through the existing `TeamRegister` status check. No additional guard is added there.

### 6. UI

- `resources/views/components/status-chip.blade.php` adds `completed` with a gold tone, trophy icon, and "Completed" label.
- `resources/views/livewire/organizer/tournaments-index.blade.php` adds a "Completed" filter option.
- `App\Models\Tournament::statusLabel()` returns a human label for `draft`, `registration-open`, `active`, `upcoming`, and `completed`, with a headline fallback for unknown slugs.
- `resources/views/livewire/organizer/tournament-show.blade.php` uses `statusLabel()` in the hero eyebrow instead of the raw status slug.
- The public header and standings already select the first `active` tournament. A completed tournament drops out naturally; no query change is needed.
- No new status action buttons are added to the tournament desk, bracket page, or schedule page.

### 7. Auditing

New action labels in `App\Livewire\Organizer\AuditLogIndex::ACTION_LABELS`:

- `tournament.activate` -> "Tournament activated"
- `tournament.complete` -> "Tournament completed"

The audit page's existing KPIs do not change. Lifecycle entries appear in the activity list and resolve to the tournament entity link.

## Data model

No schema change.

- `tournaments.status` remains a string with the existing default `draft`.
- The create wizard continues to write `registration-open`.
- The lifecycle service writes `active` and `completed`.
- Seeders are unchanged. `BracketDemoSeeder` already sets demo tournaments to `active` before generating brackets, so activation is a no-op there.

## Edge cases

- **Bracket generation fails:** The transaction rolls back both the bracket and the `active` status.
- **Bracket is regenerated while active:** `activate()` is a no-op; no duplicate audit entry.
- **Double-elimination reset is still pending:** `champion()` returns null, so the tournament stays active until the reset is resolved or cancelled by the grand-final outcome.
- **Series grand final:** The anchor match becomes final when the series is clinched; `champion()` and the completion hook then see the winner.
- **Round-robin with playoffs configured:** The organizer sets `playoff_teams` to 2, 4, 8, or 16. Group completion does not complete the tournament; the playoff champion does.
- **Round-robin without playoffs:** `playoff_teams` is 0 and no playoff bracket exists. The last group result completes the tournament.
- **Corrected final after completion:** Status stays `completed`; champion display reflects the correction.
- **Pending registrations at activation:** They remain pending and reviewable for rejection, but can no longer be approved.
- **Only active tournament completes:** Public defaults move to the next active tournament, or fall back according to the existing layout behavior.

## Testing

### Unit: `TournamentLifecycleServiceTest`

- Activates from `draft`, `upcoming`, and `registration-open`.
- Does not change `active` or `completed` and writes no duplicate audit entry.
- Completes when a champion is passed.
- Does not complete an elimination tournament without a champion.
- Completes round-robin when the group bracket exists, playoffs are disabled, and every group match is `final`/`forfeit`.
- Does not complete round-robin when `playoff_teams` is greater than 0.
- Does not complete round-robin when a group match is `scheduled`, `in_progress`, or `cancelled`.
- Does not complete when a playoff bracket exists.
- Writes the expected audit entries.

### Feature: `TournamentLifecycleTest`

- Generating a bracket sets `active`, writes `tournament.activate`, and blocks coach registration.
- A single-elimination final sets `completed` and writes `tournament.complete`.
- A double-elimination grand final sets `completed`.
- A best-of series final sets `completed` when the series is clinched.
- The last round-robin group result sets `completed` when playoffs are disabled.
- A round-robin with playoffs stays active after the group stage and completes after the playoff final.
- Correcting a completed final keeps the status `completed`.
- Submissions: approve is blocked after seeds lock; reject still succeeds.
- Tournament index shows the `completed` filter and chip.
- Tournament hero shows the human-readable status label.

### Regression checks

- Existing bracket, game stats, schedule, standings, audit, and prototype seeder tests continue to pass.
- Tests that rely on active-tournament defaults create their own active tournament and are not affected by another test completing one.

## Risks

- Some existing tests generate brackets on tournaments created with the default `draft` status. They may now see `active`; assertions should be updated only when the old status was an accident, not a requirement.
- The audit log gains two new actions. Existing audit tests query specific actions and should not be affected, but any future exact-count assertion must account for lifecycle entries.
- Completing the only active tournament changes public defaults. This is intended behavior, not a regression.

## Rollout

1. Commit this spec.
2. Write the implementation plan.
3. Implement the lifecycle service and its unit tests.
4. Wire activation into bracket generation.
5. Wire completion into result and correction paths.
6. Add registration review gating and status presentation.
7. Run targeted tests, then `COMPOSER_PROCESS_TIMEOUT=0 composer quality` sequentially: Pint, PHPStan, full suite.
8. Report the actual command output, including the final test count.

## Acceptance criteria

- A tournament created through the wizard starts as `registration-open`.
- Generating its bracket changes it to `active` in the same transaction and writes an audit entry.
- Coach registration is no longer offered or accepted after activation.
- Pending teams can still be rejected but not approved after seeds lock.
- A single-elimination or double-elimination champion marks the tournament `completed` and writes an audit entry.
- A round-robin without playoffs completes when its last group game becomes `final` or `forfeit`.
- A round-robin with playoffs waits for the playoff champion.
- Correcting a result after completion does not reopen the tournament.
- The completed status appears in the status chip, filter, and tournament hero.
- The full quality gate passes.
