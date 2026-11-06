# Per-quarter player stats verification - 2026-11-06

## Environment
- OS: Windows 11 (win32), Node.js v24.15.0.
- Branch: `feat/prototype-ui-interactivity`, HEAD `fe3762c` (Task 3) + Task 4 (no code diff).
- Seed version: `2026-10-30`, schemaVersion 2.

## Automated checks
- `cd prototype; node --test`: 51 tests, 0 failures, 0 cancelled, 0 skipped.
- `node --check` on every file under `prototype/assets/js`: no output (pass).
- Legacy sweep: no matches.
- CDN sweep: no matches.
- Native-dialog sweep: one match — `ui.js:194` custom `ui.confirm` helper definition only.
- HTTPS sweep (excluding vendored): no matches.

## Deliverables
- Data model: each player entry in `match.boxScore.team1/team2` gains `quarters: [...]` (full stat line per quarter). `buildBoxScore` emits `quarters: []`; `saveGameStats` derives totals from quarters when present (with `sumPlayerQuarters` helper); validation covers per-quarter fields.
- In-game page: per-player collapsible "By quarter" panel with Q1-Q4+OT full stat inputs; editing a quarter cell re-derives that quarter's PTS and re-sums player totals + team quarter totals + final score; editing a total clears per-player quarters (manual override); Save/Publish persist quarters.
- Game-stats page: per-player expandable quarter breakdown (points + full stat line per quarter); "—" when no per-quarter data.
- Backward compat: seeded matches (quarters: []) render totals only.

## Deviations and follow-ups
- OT capped at 5 periods (9 quarters total), consistent with the in-game page.
- Lighthouse and full browser rehearsal not run in this environment.