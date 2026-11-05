# Sub-project 1 verification - 2026-11-04

## Environment
- OS: Windows 11 (win32), Node.js v24.15.0.
- Branch: `feat/prototype-ui-interactivity`, HEAD `b411d57` (Task 6) + Task 7/8 (no code diff).
- Seed version: `2026-10-30`, schemaVersion 2.

## Automated checks
- `cd prototype; node --test`: 51 tests, 0 failures, 0 cancelled, 0 skipped.
- `node --check` on every file under `prototype/assets/js` (store, ui, components, shell, routes, guards, options, stats, score-dialogs, all page modules, app): no output (pass).
- Legacy sweep (`mock-data|navigation.js|role-switcher|data-role-required|hoopflow_role`): no matches.
- CDN sweep (`cdn.|jsdelivr|fonts.googleapis.com|unpkg`): no matches.
- Native-dialog sweep (`(?<!\.)\b(alert|confirm|prompt)\(`): one match — `ui.js:194` custom `ui.confirm` helper definition only.
- Scorekeeper sweep: no matches anywhere in `prototype/` (role fully removed).
- HTTPS sweep: matches only inside vendored `bootstrap-icons.min.css` license header (pre-existing, third-party).

## Deliverables
- Role consolidation: `scorekeeper` removed; organizer now holds `score.save`, `score.correct`, `stats.view`; seed, guards, shell, README, tests updated.
- boxScore normalized: quarters + full stat line (fgm/fga/tpm/tpa/ftm/fta/reb/ast/stl/blk/to/pf/points); `saveScore`/`correctScore` construct new shape; seed qf-1 (78-70) quarters sum correctly.
- `saveGameStats(matchId, payload)` draft action added and exported; validates quarters/stats; persists without finalizing.
- In-game management dialog (`openInGameManagement`) added to `score-dialogs.js`: Q1-Q4 per team, per-player full box score, Save (draft) + Publish result (finalize + advance), validation + field errors.
- `store.saveScore` honors `options.boxScore` so published results retain entered stats/quarters.
- Bracket page: organizer-only "In-game score & player stats" button wired to the dialog.
- Game-stats page: quarter breakdown renders above box score when quarters exist.

## Deviations and follow-ups
- OT (overtime) quarters not supported in v1 (Q1-Q4 only).
- Draft saves do not write audit entries (only publish does).
- Lighthouse and full browser rehearsal not run in this environment.