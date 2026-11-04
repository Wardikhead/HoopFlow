# Sub-project 1: Role consolidation and in-game scoring

- **Date:** 2026-11-04
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-10-30-prototype-ui-interactivity-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`). No `src/` changes.

## Problem

The prototype still ships a separate `scorekeeper` role whose only purpose is score entry, and score entry is limited to final scores via the score console. The organizer cannot manage a game's progress quarter by quarter or capture a full player box score in one place. The ten-step demo script (step 8) depends on the scorekeeper role, so removing it requires updating the script, seed data, permissions, and tests together.

## Goals

1. Remove the `scorekeeper` role entirely and merge its capabilities into the organizer (admin).
2. Add an "In-game score & player stats management" surface, reachable from a scheduled/final matchup by the organizer, that captures per-quarter team scores (Q1–Q4) and a full per-player box score, with Save (draft) and Publish result (finalize) actions.
3. Keep the change backward compatible: matches without quarter data still render and score normally; existing tests stay green unless explicitly updated for the role change.

## Non-goals

- No real-time/live game clock, play-by-play, fouls tracking beyond the final box score, or multi-user concurrency.
- No change to the Laravel `src/` application.
- Double elimination, round robin, custom slots, payment destinations, teams-nav filtering, and announcements management are out of scope for this sub-project (Sub-projects 2–4).

## Decisions

### 1. Role consolidation

- **Roles after this change:** `organizer`, `coach`, `fan`. `scorekeeper` is removed from `ROLES`, seed users, the permissions matrix, demo accounts, shell navigation, and the README.
- **Permissions:** `score.save`, `score.correct`, and `stats.view` move into the organizer's permission set. Organizer already held every other permission, so this is additive only.
- **Seed data:** remove the seeded scorekeeper user (if present). No team ownership changes.
- **Demo script step 8** is reworded from "scorekeeper denial" to "non-organizer denial": a coach (or fan) opens a matchup, confirms score entry is unavailable, and is redirected with a toast when attempting a restricted organizer route. The assertion `store.can('score.save')` for a non-organizer is retained (coach/fan → false).
- **Audit:** no new audit action type needed; reuse `score.save` / `score.correct` with the actor being the organizer.

### 2. In-game score & player stats management

- **Trigger:** on the match detail dialog (bracket page), for organizers only, add a button "In-game score & player stats" alongside the existing **Close**, **Game stats**, and **Correct score** actions. The button is hidden for coach/fan.
- **Surface:** a wide dialog (not a new route) with:
  - Quarter tabs: Q1, Q2, Q3, Q4 (OT optional, v1 ships Q1–Q4 only).
  - Per quarter: team-score inputs for both teams.
  - Per player (for each team): full box-score inputs — FG made/att, 3PT made/att, FT made/att, REB, AST, STL, BLK, TO, PF. Points auto-derived from FG/3PT/FT.
  - Running totals: quarter sums auto-populate the final team scores; player stat totals auto-sum into team totals.
  - Buttons: **Save** (draft — persists quarters + stats, match stays `scheduled`, no bracket advance), **Publish result** (finalizes: sets `score1`/`score2`/`winnerId`/`status='final'`, advances the bracket slot, updates standings/records, writes audit, reuses the existing `saveScore` path), **Cancel**.
- **Validation:** per-quarter scores must be non-negative integers; published final must equal the sum of quarters (enforced, with a clear field error). Player stat inputs must be non-negative integers; made ≤ attempted for FG/3PT/FT.
- **Data model change:** extend `match.boxScore` (already `{ team1: [], team2: [] }`) with:
  - `quarters: { team1: [q1,q2,q3,q4], team2: [q1,q2,q3,q4] }`
  - Per-player stats already live in `boxScore.team1/team2` arrays; ensure the full stat line fields are present (some may be 0/undefined today — normalize to 0).
  - Backward compatible: matches without `quarters` render quarters as empty/0 and score normally via the existing console.
- **Permission:** organizer only. The dialog is never opened for non-organizers; the trigger button is not rendered for them.

### 3. Data flow

```
organizer opens matchup → "In-game score & player stats"
  → load existing quarters/stats (or zeros)
  → edit Q1–Q4 + per-player stats
  → Save  → persist boxScore (draft), match stays scheduled, toast "Saved"
  → Publish result → validate → set score1/score2 from quarter sums
                     → reuse saveScore() to finalize + advance bracket + standings + audit
                     → close dialog, re-render
```

### 4. Impact on existing code

| File | Change |
|---|---|
| `store.js` | Add `score.save`/`score.correct`/`stats.view` to organizer; remove scorekeeper role; add `saveGameStats(matchId, payload)` action (draft) and reuse `saveScore` for publish; normalize boxScore shape. |
| `score-dialogs.js` | Add `openInGameManagement(match, { store, ui, components })` (or extend score console with a quarter/stats mode). |
| `pages/bracket.js` | Render the new button for organizers; wire dialog. |
| `pages/game-stats.js` | Render quarters if present. |
| `seed.js` | Remove scorekeeper user; ensure boxScore arrays have full stat fields. |
| `tests/*.mjs` | Update role references; add in-game management tests (quarter summing, validation, publish advances bracket). |
| `demo-script.test.mjs` | Step 8 reworded to non-organizer denial. |
| `README.md` | Remove scorekeeper from demo accounts/permissions. |

### 5. Acceptance criteria

- No `scorekeeper` role anywhere in code, seed, tests, or docs.
- Organizer can open "In-game score & player stats" on a scheduled matchup, edit Q1–Q4 and per-player stats, Save (draft, no finalize) and Publish result (finalizes + advances bracket + updates standings).
- Quarter sums equal final scores; invalid input shows field errors.
- Coach/fan cannot see or open the management surface.
- All existing tests pass (after role updates); new in-game tests pass.
- `node --test` green; static sweeps clean.

## Open questions

- Should OT (overtime) quarters be supported in v1, or strictly Q1–Q4? (Recommend: Q1–Q4 only; OT as a follow-up.)
- Should "Save" (draft) write an audit entry, or only "Publish result"? (Recommend: only publish writes audit, to avoid noise.)

---

Spec written for review. Please confirm or request changes before I start the implementation plan.