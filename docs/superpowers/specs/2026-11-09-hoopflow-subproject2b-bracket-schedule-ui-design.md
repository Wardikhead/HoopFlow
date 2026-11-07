# Sub-project 2B: Bracket & schedule management UI

- **Date:** 2026-11-09
- **Owner:** Jeward T. Mahinay
- **Status:** Draft for review
- **Parent:** [[2026-11-08-hoopflow-subproject2-bracket-schedule-formats-design]]
- **Scope:** prototype only (`prototype/`, `docs/superpowers/`).

## Problem

The engine (Sub-project 2A) now supports DE/RR formats and regenerate/reschedule/setBracketSlots actions, but the UI has no regenerate button, no manual slot editing, no re-schedule dialog, no manual manage mode, and no format-aware rendering. The create wizard doesn't let you choose a format. Everything is still SE-only in the UI.

## Goals

1. **Bracket page:** Regenerate button (with confirmation, disabled if results), "Edit slots" mode (dropdowns per first-round slot for manual reassignment), format-aware rendering (DE: winners + losers + grand final trees; RR: matchday grid + standings link).
2. **Schedule page:** Regenerate button, "Re-schedule match" dialog (court/day/time with conflict validation), manual manage mode (dropdowns per match for court and day).
3. **Tournament show page:** Format badge, generate button works for all formats (label adapts).
4. **Create wizard:** Add a "Format" step (SE/DE/RR radio) and a "Slots" step (powers of 2 for SE/DE; 2–16 for RR).

## Non-goals

- No drag-and-drop.
- No new routes (reuse existing pages).
- No change to `src/`.

## Decisions

### 1. Bracket page

- **Regenerate button:** organizer-only, shown when bracket exists and no results. Confirmation dialog. Calls `store.actions.regenerateBracket(tournamentId)`. On success, toast + re-render.
- **Edit slots mode:** organizer-only toggle "Edit slots" that reveals a dropdown per first-round slot (and DE losers round 1) to reassign teams. "Save slots" button calls `setBracketSlots`. "Cancel" reverts. Disabled if results exist.
- **Format-aware rendering:**
  - SE: existing tree (unchanged).
  - DE: two bracket trees — "Winners bracket" and "Losers bracket" — plus a "Grand Final" card. Match cards show which bracket.
  - RR: no tree; show a matchday grid (rounds as columns or rows) with scores, plus a link to standings.
- **Generate button:** label adapts: "Generate bracket" for all formats. Description adapts to format.

### 2. Schedule page

- **Regenerate button:** organizer-only, shown when schedule exists and no results. Confirmation. Calls `generateSchedule(tournamentId)` (existing). Actually, regenerate = wipe + rebuild. The existing `generateSchedule` already wipes and rebuilds. So "Regenerate" just re-invokes it with confirmation. But `generateSchedule` requires a bracket. So regenerate is only available when bracket exists.
- **Re-schedule match dialog:** on each match card, an organizer-only "Reschedule" button opens a dialog with court select + date/time inputs. Calls `rescheduleMatch`. On conflict, shows field error.
- **Manual manage mode:** organizer-only toggle "Manual manage" that reveals court and day dropdowns on each match card. Changes call `rescheduleMatch` per edit (debounced) or on "Save".

### 3. Tournament show page

- **Format badge:** show format name next to the tournament name (e.g., "Double elimination", "Round robin").
- **Generate bracket button:** already works for all formats (calls `generateBracket`). Label adapts to format.

### 4. Create wizard

- **Format step:** radio buttons for Single elimination (default), Double elimination, Round robin. Shown as a step in the wizard.
- **Slots step:** for SE/DE, select from 2/4/8/16/32; for RR, number input (2–16). Adapts based on format selection.

### 5. Acceptance criteria

- Organizer can regenerate bracket/schedule with confirmation.
- Manual slot editing works via dropdowns (gated by no results).
- Re-schedule match dialog works with conflict validation.
- Manual schedule manage works via dropdowns.
- Create wizard supports format + slots selection.
- Bracket page renders DE losers bracket + grand final; RR shows matchday grid.
- Tournament show page shows format badge.
- All existing tests pass; `node --test` green; static sweeps clean.

## Open questions (resolved)

- **Manual manage save strategy:** per-change (immediate rescheduleMatch call). Resolved.
- **RR bracket view:** matchday grid. Resolved.
- **Edit slots UI:** modal with dropdowns. Resolved.

---

Spec written for review. Confirm or request changes before I write the implementation plan. (Note: this is large; I may split into multiple plans: bracket UI, schedule UI, create wizard.)