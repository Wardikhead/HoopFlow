# Sub-project 2: Bracket & schedule management + formats

- **Date:** 2026-11-08
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-10-30-prototype-ui-interactivity-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`). Lifts the original "round-robin is v2 non-goal."

## Problem

The prototype only supports single-elimination brackets with fixed slot counts (2/4/8/16/32), and bracket/schedule generation is one-shot with no regeneration or manual adjustment. Organizers cannot re-run, reassign, or manage slots/courts. The create wizard offers no format choice. The user wants full bracket & schedule management plus double elimination, round robin, and custom slot counts.

## Goals

1. **Bracket management:** Regenerate (wipe + rebuild from approved teams), re-bracket (manual slot reassignment via dropdowns), and manual manage mode. All gated behind the existing "no results recorded" lock.
2. **Schedule management:** Regenerate schedule, re-schedule a specific match (court/day/time), and manual manage mode (dropdowns).
3. **Formats:** Single elimination (existing), double elimination (winners + losers + grand final with bracket reset), round robin (full RR, standings decide), and custom slot counts (powers of 2: 2/4/8/16/32).
4. Format is chosen at tournament creation and locked before generation.

## Non-goals

- No drag-and-drop (consistent with original non-goal). Dropdowns only.
- No live game clock, play-by-play, or multi-user concurrency.
- No change to `src/`.
- No new bracket visualizations beyond the existing tree (losers bracket shown as a second tree).

## Decisions

### 1. Data model

Tournament gains:
- `format: 'single-elimination' | 'double-elimination' | 'round-robin'`
- `slots`: powers of 2 for SE/DE (2/4/8/16/32); for RR, `slots` = number of teams (any 2–16).
- `roundRobinRounds`: for RR, number of rounds (default 1 = single RR; could support double RR later).

Match gains (for DE):
- `bracket: 'winners' | 'losers' | 'grand-final'` (which bracket the match belongs to).
- `lossCount` tracking is implicit via bracket membership.

Match gains (for RR):
- `round` = matchday number.
- Standings computed from all RR matches (wins/losses/points).

### 2. Bracket generation

- **SE:** existing algorithm (seedOrder, byes, advancement).
- **DE:** generate winners bracket (SE-style) + losers bracket (receiving losers from each winners round) + grand final. Losers bracket matches receive the loser of the corresponding winners match. Grand final: winners-bracket champion vs losers-bracket champion; if losers champion wins, a bracket-reset match.
- **RR:** generate all pairings (round-robin schedule) across matchdays. No advancement; standings decide.
- **Custom slots:** only powers of 2 for SE/DE; RR allows 2–16 teams.

### 3. Regenerate

- `generateBracket` wipes existing matches for the tournament and rebuilds from approved teams.
- Guarded by `hasResults` (no recorded scores) — disabled otherwise.
- Confirmation dialog before wipe.
- Audit entry: `bracket.regenerate`.

### 4. Manual slot editing ("Edit slots")

- On the bracket page, an organizer-only "Edit slots" toggle reveals dropdowns on each first-round slot (and, for DE, losers-bracket slots) to reassign teams.
- Changes are saved via a new action `setBracketSlots(tournamentId, assignments)` which validates uniqueness and team count.
- Guarded: only when no results recorded.

### 5. Schedule management

- **Regenerate schedule:** `generateSchedule` already exists; add a confirmation and a "regenerate" button (disabled if results recorded or no bracket). Wipes and rebuilds.
- **Re-schedule a match:** a dialog to change court/day/time for a single match, with conflict validation (no double-booking, rest minutes). Action: `rescheduleMatch(matchId, { courtId, scheduledAt })`.
- **Manual manage:** dropdowns on each match card for court and day/time; saved via `rescheduleMatch` per change.

### 6. Tournament creation wizard

- Add a "Format" step with radio buttons: Single elimination (default), Double elimination, Round robin.
- Add a "Slots" input: for SE/DE, select from 2/4/8/16/32; for RR, number of teams (2–16).
- Store `format` and `slots` on the tournament.

### 7. UI changes

- **Tournament show page:** format badge, "Generate bracket" button works for all formats.
- **Bracket page:** shows winners bracket + (for DE) losers bracket tree + grand final; for RR, shows matchday grid + standings link.
- **Schedule page:** unchanged layout; regenerate button added.
- **Matchups page:** unchanged.

### 8. Acceptance criteria

- Organizer can create a tournament with any format and slot count.
- Generate bracket works for SE, DE, RR.
- Regenerate wipes and rebuilds with confirmation.
- Manual slot editing works via dropdowns (gated by no results).
- Schedule regenerate and per-match re-schedule work with conflict validation.
- Manual schedule manage works via dropdowns.
- All existing tests pass; new tests for DE/RR generation and manual editing pass.
- `node --test` green; static sweeps clean.

## Open questions (resolved)

- **DE grand final bracket reset:** yes — standard DE with bracket reset match if losers champion wins. (Resolved.)
- **RR scheduling:** reuse the existing schedule engine (courts/days). (Resolved.)
- **Manual reassignment after partial results:** block entirely when any result exists. (Resolved.)

---

Spec written for review. Confirm or request changes before I write the implementation plan. (Note: this is a large sub-project; I may recommend splitting into 2–3 plans: formats engine, bracket/schedule management UI, create-wizard format step.)