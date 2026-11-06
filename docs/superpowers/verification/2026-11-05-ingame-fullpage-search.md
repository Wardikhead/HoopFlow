# In-game full page + OT + search/filter verification - 2026-11-05

## Environment
- OS: Windows 11 (win32), Node.js v24.15.0.
- Branch: `feat/prototype-ui-interactivity`, HEAD `cbf28e6` (Task 6) + Task 7 (no code diff).
- Seed version: `2026-10-30`, schemaVersion 2.

## Automated checks
- `cd prototype; node --test`: 51 tests, 0 failures, 0 cancelled, 0 skipped.
- `node --check` on every file under `prototype/assets/js`: no output (pass).
- Legacy sweep: no matches.
- CDN sweep: no matches.
- Native-dialog sweep: one match — `ui.js:194` custom `ui.confirm` helper definition only.
- HTTPS sweep (excluding vendored): no matches.
- Scorekeeper sweep: no matches.
- `openInGameManagement` sweep: no references (dialog fully removed).

## Deliverables
- Route `gameManage: 'games/manage.html'` added; `prototype/games/manage.html` created with app-shell skeleton.
- `prototype/assets/js/pages/game-manage.js`: organizer-only full page with match picker (tournament/status/team filters), management form (Q1-Q4 + up to 5 OT periods with add/remove), per-player full box score, Save (draft) + Publish result (finalize), player search (debounced) + team filter.
- `prototype/assets/js/pages/game-stats.js`: player search + team filter added, sortable headers preserved.
- `prototype/assets/js/pages/bracket.js`: "In-game score & player stats" is now a link to the full page; dialog removed from `score-dialogs.js`.

## Deviations and follow-ups
- OT is capped at 5 periods (9 quarters total).
- Editable in-game stats tables are not sortable (sorting editable inputs would scramble input-to-player mapping); sorting remains on the read-only game-stats page.
- Lighthouse and full browser rehearsal not run in this environment.