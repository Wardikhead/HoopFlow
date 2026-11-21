# Bracket Arena Tree Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the HoopFlow bracket into an NBA-style arena board with aligned rounds, connector lines, a gold final, path tracing, correct zoom/fit/jump, and an upgraded mobile round stepper — frontend only.

**Architecture:** Blade renders one dark arena containing a section per bracket, with a tree (rounds + SVG connector layer) for elimination and a matchday grid for group rounds. A single vanilla-JS module measures rendered cards, draws connectors, applies CSS `zoom`, traces paths, and redraws after Livewire morphs. All styling lives in a new `_bracket.scss` partial fed by existing tokens.

**Tech Stack:** Laravel 13 Blade, Livewire 4, Alpine 3, Bootstrap 5.3 SCSS (no Bootstrap JS), vanilla ES modules, Vite.

**Design spec:** `docs/superpowers/specs/2026-11-20-bracket-ui-enhancement-design.md`

## Global Constraints

- Frontend only. Do not modify `src/app/**`, `src/database/**`, `src/routes/**`, migrations, or seeders.
- Do not edit tests. Preserve every existing `data-testid`, `wire:click`, `wire:keydown`, dialog, and test-asserted string.
- The working tree already contains unrelated uncommitted work: `.obsidian/graph.json`, `src/app/Livewire/Organizer/{BracketShow,MatchesIndex,ScheduleShow}.php`, `src/app/Support/RescheduledMatches.php`, `src/resources/views/components/{match-card,status-chip}.blade.php`, `src/resources/views/livewire/organizer/{matches-index,schedule-show}.blade.php`, and the Matches/Schedule tests. Leave all of it untouched.
- `bracket-show.blade.php`, `bracket-match.blade.php`, `_data.scss`, and `BracketTest.php` already contain uncommitted rescheduled-match changes. Preserve them while editing.
- Do not commit while the sibling feature work above is uncommitted. All work stays in the working tree for the user to review and commit.
- No new npm package, font, icon set, or CSS framework.
- No animation loop, sound, or confetti.
- Use existing tokens only: `--hf-rail`, `--hf-rail-ink`, `--hf-rail-muted`, `--hf-primary`, `--hf-primary-on-dark`, `--hf-gold`, `--hf-success`, `--hf-warning`, `--hf-alert`, radii, and motion durations.

## File Map

| File | Responsibility |
|---|---|
| `src/resources/css/hf/_bracket.scss` (create) | Arena, tree, card, connector, final, matchday grid, mobile stepper, responsive, forced-colors, and print-adjacent rules. |
| `src/resources/css/hf/_hoopflow.scss` (modify) | Import `hf/bracket` after `hf/data`. |
| `src/resources/css/hf/_data.scss` (modify) | Delete old bracket blocks now owned by `_bracket.scss`; keep schedule, standings, and `.hf-champion`. |
| `src/resources/css/hf/_print.scss` (modify) | Paper-safe arena and board rules. |
| `src/resources/views/components/bracket-match.blade.php` (modify) | New card rows, schedule meta, pips, featured final, ARIA label. |
| `src/resources/views/livewire/organizer/bracket-show.blade.php` (modify) | Arena, section headers, tree/matchday branches, progress, mobile tablist and swipe. |
| `src/resources/js/ui/bracket.js` (modify) | CSS zoom, fit, jump, connectors, tracing, observers, Livewire redraw, fades. |
| `src/resources/js/ui/page-lifecycle.js` | No change required; `initBracketZoom()` already runs once globally. Verify only. |

---

### Task 1: Bracket stylesheet scaffold and old-style removal

**Files:**
- Create: `src/resources/css/hf/_bracket.scss`
- Modify: `src/resources/css/hf/_hoopflow.scss`
- Modify: `src/resources/css/hf/_data.scss`

**Interfaces:**
- Produces: the class contract used by Task 2–4: `.hf-arena`, `.hf-arena__head`, `.hf-arena__intro`, `.hf-arena__summary`, `.hf-arena__tools`, `.hf-arena__zoom`, `.hf-arena__tool`, `.hf-arena__tool--gold`, `.hf-arena__progress`, `.hf-arena__foot`, `.hf-bracket-section`, `.hf-bracket-section__head`, `.hf-bracket-section__title`, `.hf-bracket-section__meta`, `.hf-bracket-section__note`, `.hf-bracket-viewport`, `.hf-bracket-viewport__fade`, `.hf-bracket`, `.hf-bracket--tree`, `.hf-bracket--matchdays`, `.hf-bracket-tree`, `.hf-bracket-lines`, `.hf-bracket-round`, `.hf-bracket-round-title`, `.hf-bracket-round-title__label`, `.hf-bracket-round-title__meta`, `.hf-bracket-round-title__meter`, `.hf-bracket-round__body`, `.hf-bracket-match`, `.hf-bracket-match__head`, `.hf-bracket-match__slot`, `.hf-bracket-match__trophy`, `.hf-bracket-match__badges`, `.hf-bracket-match__teams`, `.hf-bracket-match__team`, `.hf-bracket-match__name`, `.hf-bracket-match__score`, `.hf-bracket-match__meta`, `.hf-bracket-match__series`, `.hf-bracket-match__pips`, `.hf-bracket-match__pip`, `.hf-matchday-block`, `.hf-matchday-block__head`, `.hf-matchday-block__title`, `.hf-matchday-block__meta`, `.hf-matchday-block__grid`, `.hf-round-stepper`, `.hf-round-pager`, `.hf-round-progress`, `.hf-round-panels`, `.hf-round-panel__head`, `.hf-round-panel__title`, `.hf-bracket-section__title--gold`.

- [ ] **Step 1: Create the partial with the arena shell and variables**

```scss
/* Bracket — Arena Tree */
.hf-arena {
  --hf-arena-line: rgba(243, 245, 249, 0.12);
  --hf-arena-line-strong: rgba(243, 245, 249, 0.22);
  --hf-arena-card-top: rgba(255, 255, 255, 0.055);
  --hf-arena-card-bottom: rgba(255, 255, 255, 0.015);
  --hf-bracket-gap: 40px;
  --hf-bracket-card-w: 236px;
  position: relative;
  overflow: hidden;
  margin-bottom: 18px;
  padding: 20px;
  border: 1px solid var(--hf-arena-line);
  border-radius: var(--hf-radius-xl);
  background:
    radial-gradient(circle at 88% 4%, color-mix(in srgb, var(--hf-primary) 22%, transparent), transparent 34%),
    radial-gradient(circle at 8% 100%, color-mix(in srgb, #1d4f91 18%, transparent), transparent 36%),
    var(--hf-rail);
  color: var(--hf-rail-ink);
  box-shadow: var(--hf-shadow-rest);
}

.hf-arena::before {
  position: absolute;
  inset: 0;
  background:
    repeating-linear-gradient(90deg, transparent 0 79px, rgba(243, 245, 249, 0.035) 79px 80px),
    repeating-linear-gradient(0deg, transparent 0 79px, rgba(243, 245, 249, 0.035) 79px 80px);
  content: '';
  mask-image: radial-gradient(circle at 50% 42%, #000 18%, transparent 78%);
  pointer-events: none;
}

.hf-arena::after {
  position: absolute;
  top: -150px;
  right: -110px;
  width: 380px;
  height: 380px;
  border: 1px solid rgba(224, 161, 0, 0.16);
  border-radius: 50%;
  box-shadow:
    0 0 0 46px rgba(224, 161, 0, 0.04),
    0 0 0 92px rgba(224, 161, 0, 0.025);
  content: '';
  pointer-events: none;
}

.hf-arena > * {
  position: relative;
  z-index: 1;
}
```

- [ ] **Step 2: Add the arena header, progress, tools, sections, and foot**

```scss
.hf-arena__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px 20px;
  margin-bottom: 12px;
}

.hf-arena__intro {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.hf-arena__summary {
  margin: 0;
  color: var(--hf-rail-muted);
  font-size: 12px;
  letter-spacing: 0.02em;
}

.hf-arena__summary strong {
  color: var(--hf-rail-ink);
  font-family: var(--hf-font-display);
  font-size: 17px;
  font-weight: 800;
}

.hf-arena__tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.hf-arena__zoom {
  display: inline-flex;
  gap: 2px;
  padding: 3px;
  border: 1px solid var(--hf-arena-line);
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.18);
}

.hf-arena__tool {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 32px;
  min-height: 30px;
  padding: 0 10px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: transparent;
  color: var(--hf-rail-muted);
  cursor: pointer;
  font-family: var(--hf-font-body);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  transition: color var(--hf-dur-micro) linear, background-color var(--hf-dur-micro) linear, border-color var(--hf-dur-micro) linear;
}

.hf-arena__tool:hover,
.hf-arena__tool:focus-visible {
  background: rgba(243, 245, 249, 0.08);
  color: var(--hf-rail-ink);
}

.hf-arena__tool:focus-visible {
  outline: 2px solid var(--hf-gold);
  outline-offset: 2px;
}

.hf-arena__tool--gold {
  border-color: rgba(224, 161, 0, 0.38);
  color: var(--hf-gold);
}

.hf-arena__tool--gold:hover,
.hf-arena__tool--gold:focus-visible {
  border-color: var(--hf-gold);
  background: rgba(224, 161, 0, 0.12);
  color: #ffe3a3;
}

.hf-arena__progress {
  height: 4px;
  margin-bottom: 20px;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(243, 245, 249, 0.10);
}

.hf-arena__progress span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--hf-primary), var(--hf-gold));
  transition: width var(--hf-dur-expressive) var(--hf-ease-out);
}

.hf-bracket-section + .hf-bracket-section {
  margin-top: 26px;
  padding-top: 22px;
  border-top: 1px solid var(--hf-arena-line);
}

.hf-bracket-section__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 4px 14px;
  margin-bottom: 10px;
}

.hf-bracket-section__title {
  margin: 0;
  color: var(--hf-rail-ink);
  font-family: var(--hf-font-display);
  font-size: 19px;
  font-weight: 800;
  letter-spacing: 0.07em;
  line-height: 1;
  text-transform: uppercase;
}

.hf-bracket-section__title--gold {
  color: var(--hf-gold);
}

.hf-bracket-section__meta {
  color: var(--hf-rail-muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.hf-bracket-section__note {
  margin: 0 0 10px;
  color: var(--hf-rail-muted);
}

.hf-arena__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 16px;
  margin-top: 20px;
  padding-top: 12px;
  border-top: 1px solid var(--hf-arena-line);
  color: var(--hf-rail-muted);
  font-size: 10px;
  letter-spacing: 0.05em;
}

.hf-arena__foot i {
  color: var(--hf-primary);
}
```

- [ ] **Step 3: Add tree, round, and connector styles**

```scss
.hf-bracket-viewport {
  position: relative;
  min-width: 0;
}

.hf-bracket {
  min-width: 0;
}

.hf-bracket--tree {
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 6px;
  scrollbar-color: color-mix(in srgb, var(--hf-rail-ink) 24%, transparent) transparent;
  scrollbar-width: thin;
}

.hf-bracket-viewport__fade {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 6px;
  width: 56px;
  background: linear-gradient(90deg, transparent, var(--hf-rail));
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--hf-dur-standard) linear;
}

.hf-bracket-viewport.is-scrollable .hf-bracket-viewport__fade {
  opacity: 1;
}

.hf-bracket-viewport.is-at-end .hf-bracket-viewport__fade {
  opacity: 0;
}

.hf-bracket-tree {
  position: relative;
  display: flex;
  gap: var(--hf-bracket-gap);
  width: max-content;
  min-width: 100%;
  padding: 18px 14px 14px;
}

.hf-bracket-lines {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 0;
  overflow: visible;
  pointer-events: none;
}

.hf-bracket-lines path {
  fill: none;
  stroke: var(--hf-arena-line-strong);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: stroke var(--hf-dur-micro) linear, opacity var(--hf-dur-micro) linear;
}

.hf-bracket-lines path.is-hot {
  stroke: var(--hf-primary);
  stroke-width: 2;
  filter: drop-shadow(0 0 4px color-mix(in srgb, var(--hf-primary) 55%, transparent));
}

.hf-bracket-tree.is-tracing .hf-bracket-lines path:not(.is-hot) {
  opacity: 0.35;
}

.hf-bracket-round {
  position: relative;
  z-index: 1;
  display: flex;
  flex: 1 0 var(--hf-bracket-card-w);
  flex-direction: column;
  min-width: var(--hf-bracket-card-w);
}

.hf-bracket-round-title {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 2px 8px;
  min-height: 38px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--hf-arena-line);
}

.hf-bracket-round-title__label {
  overflow: hidden;
  color: var(--hf-rail-ink);
  font-family: var(--hf-font-display);
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.07em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.hf-bracket-round-title__meta {
  color: var(--hf-rail-muted);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
}

.hf-bracket-round-title__meter {
  grid-column: 1 / -1;
  height: 2px;
  overflow: hidden;
  border-radius: 2px;
  background: rgba(243, 245, 249, 0.10);
}

.hf-bracket-round-title__meter span {
  display: block;
  height: 100%;
  background: var(--hf-success);
}

.hf-bracket-round__body {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: space-around;
  gap: 12px;
  padding-top: 12px;
}

.hf-bracket-round:only-child .hf-bracket-round__body {
  justify-content: center;
  min-height: 210px;
}
```

- [ ] **Step 4: Add card styles, featured final, and matchday grid**

```scss
.hf-bracket-match {
  position: relative;
  display: grid;
  gap: 6px;
  min-height: 98px;
  padding: 8px 9px 8px 11px;
  border: 1px solid var(--hf-arena-line);
  border-left: 3px solid var(--hf-slate);
  border-radius: 10px;
  background: linear-gradient(180deg, var(--hf-arena-card-top), var(--hf-arena-card-bottom)), var(--hf-rail);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
  color: var(--hf-rail-ink);
  cursor: pointer;
  transition: transform var(--hf-dur-standard) var(--hf-ease-out), border-color var(--hf-dur-standard) linear, box-shadow var(--hf-dur-standard) ease, opacity var(--hf-dur-standard) linear;
}

.hf-bracket-match:hover,
.hf-bracket-match:focus-visible {
  z-index: 2;
  border-color: color-mix(in srgb, var(--hf-primary) 70%, transparent);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--hf-primary) 25%, transparent), 0 16px 34px rgba(0, 0, 0, 0.34);
  transform: translateY(-2px);
}

.hf-bracket-match:focus-visible {
  outline: 2px solid var(--hf-gold);
  outline-offset: 2px;
}

.hf-bracket-match--scheduled { border-left-color: var(--hf-primary); }
.hf-bracket-match--in_progress { border-left-color: var(--hf-warning); background: linear-gradient(180deg, rgba(245, 158, 11, 0.12), transparent), var(--hf-rail); }
.hf-bracket-match--final { border-left-color: var(--hf-success); }
.hf-bracket-match--forfeit { border-left-color: var(--hf-alert); }

.hf-bracket-match.is-featured {
  border-color: rgba(224, 161, 0, 0.48);
  border-left: 4px solid var(--hf-gold);
  background: linear-gradient(180deg, rgba(224, 161, 0, 0.16), rgba(224, 161, 0, 0.03)), var(--hf-rail);
  box-shadow: 0 0 0 1px rgba(224, 161, 0, 0.12), 0 18px 40px rgba(0, 0, 0, 0.32);
}

.hf-bracket-match.is-featured::after {
  position: absolute;
  top: 50%;
  right: 10px;
  color: var(--hf-gold);
  content: '\F5E6';
  font-family: 'bootstrap-icons';
  font-size: 46px;
  line-height: 1;
  opacity: 0.08;
  pointer-events: none;
  transform: translateY(-50%);
}

.hf-bracket-match__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 4px 6px;
}

.hf-bracket-match__slot {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-width: 0;
  overflow: hidden;
  color: var(--hf-rail-muted);
  font-size: 8px;
  font-weight: 800;
  letter-spacing: 0.09em;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.hf-bracket-match__trophy {
  color: var(--hf-gold);
  font-size: 10px;
}

.hf-bracket-match__badges {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  margin-left: auto;
}

.hf-bracket-match__status.hf-chip {
  flex: 0 0 auto;
  padding: 2px 6px;
  font-size: 8px;
  letter-spacing: 0.04em;
}

.hf-bracket-match__teams {
  display: grid;
  gap: 2px;
}

.hf-bracket-match__team {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 7px;
  min-height: 24px;
  color: var(--hf-rail-muted);
}

.hf-bracket-match__team.is-winner { color: var(--hf-rail-ink); }
.hf-bracket-match__team.is-loser { opacity: 0.62; }

.hf-bracket-match__name {
  overflow: hidden;
  font-size: 11px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-bracket-match__team.is-winner .hf-bracket-match__name { font-weight: 800; }

.hf-bracket-match__score {
  min-width: 18px;
  color: var(--hf-rail-muted);
  font-family: var(--hf-font-display);
  font-size: 18px;
  font-weight: 800;
  line-height: 1;
  text-align: right;
}

.hf-bracket-match__team.is-winner .hf-bracket-match__score { color: var(--hf-primary); }
.hf-bracket-match.is-featured .hf-bracket-match__team.is-winner .hf-bracket-match__score { color: var(--hf-gold); }
.hf-bracket-match__score.is-muted { opacity: 0.55; }

.hf-bracket-match__meta {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow: hidden;
  padding-top: 5px;
  border-top: 1px solid var(--hf-arena-line);
  color: var(--hf-rail-muted);
  font-size: 8px;
  letter-spacing: 0.03em;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-bracket-match__meta i { color: color-mix(in srgb, var(--hf-rail-muted) 75%, transparent); }

.hf-bracket-match__series {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 6px;
  color: var(--hf-rail-muted);
  font-size: 8px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.hf-bracket-match__series-label { white-space: nowrap; }

.hf-bracket-match__series-score {
  color: var(--hf-rail-ink);
  font-family: var(--hf-font-display);
  font-size: 11px;
  font-weight: 800;
}

.hf-bracket-match__pips {
  display: inline-flex;
  gap: 2px;
  margin-left: auto;
}

.hf-bracket-match__pip {
  width: 5px;
  height: 5px;
  border: 1px solid color-mix(in srgb, var(--hf-rail-ink) 30%, transparent);
  border-radius: 50%;
}

.hf-bracket-match__pip.is-on { border-color: var(--hf-primary); background: var(--hf-primary); }
.hf-bracket-match.is-featured .hf-bracket-match__pip.is-on { border-color: var(--hf-gold); background: var(--hf-gold); }

.hf-bracket--matchdays { padding: 4px 2px 2px; }

.hf-bracket-matchdays {
  display: grid;
  gap: 18px;
}

.hf-matchday-block__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 4px 12px;
  margin-bottom: 8px;
  padding-bottom: 6px;
  border-bottom: 1px solid var(--hf-arena-line);
}

.hf-matchday-block__title {
  margin: 0;
  color: var(--hf-rail-ink);
  font-family: var(--hf-font-display);
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.hf-matchday-block__meta {
  color: var(--hf-rail-muted);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.hf-matchday-block__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 10px;
}

.hf-matchday-block .hf-bracket-match {
  height: 100%;
  min-height: 0;
}
```

- [ ] **Step 5: Add arena-scoped chips, mobile stepper, responsive, and forced colors**

```scss
.hf-arena .hf-chip {
  border-color: rgba(243, 245, 249, 0.16);
  background: rgba(243, 245, 249, 0.08);
  color: var(--hf-rail-ink);
}

.hf-arena .hf-chip--success { border-color: rgba(52, 211, 153, 0.35); background: rgba(16, 185, 129, 0.12); color: #a7f3d0; }
.hf-arena .hf-chip--warning { border-color: rgba(251, 191, 36, 0.4); background: rgba(245, 158, 11, 0.14); color: #fde68a; }
.hf-arena .hf-chip--danger { border-color: rgba(255, 92, 122, 0.38); background: rgba(239, 68, 68, 0.12); color: #fecdd3; }
.hf-arena .hf-chip--gold { border-color: rgba(224, 161, 0, 0.4); background: rgba(224, 161, 0, 0.12); color: #ffe3a3; }
.hf-arena .hf-chip--slate { color: #cbd5e1; }

.hf-bracket-mobile {
  display: none;
  margin-bottom: 18px;
  padding: 16px;
  border: 1px solid var(--hf-arena-line);
  border-radius: var(--hf-radius-xl);
  background:
    radial-gradient(circle at 90% 0%, color-mix(in srgb, var(--hf-primary) 18%, transparent), transparent 40%),
    var(--hf-rail);
  color: var(--hf-rail-ink);
}

.hf-round-stepper { position: sticky; top: 0; z-index: 3; }

.hf-round-pager {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 8px;
  margin-bottom: 8px;
  scrollbar-width: none;
}

.hf-round-pager::-webkit-scrollbar { display: none; }

.hf-round-pager button {
  flex: 0 0 auto;
  min-height: 38px;
  padding: 0 12px;
  border: 1px solid var(--hf-arena-line);
  border-radius: 999px;
  background: rgba(243, 245, 249, 0.05);
  color: var(--hf-rail-muted);
  cursor: pointer;
  font-family: var(--hf-font-body);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.hf-round-pager button.is-active {
  border-color: var(--hf-primary);
  background: rgba(234, 88, 12, 0.18);
  color: #ffd9c7;
}

.hf-round-pager button:focus-visible {
  outline: 2px solid var(--hf-gold);
  outline-offset: 2px;
}

.hf-round-progress {
  height: 4px;
  margin-bottom: 12px;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(243, 245, 249, 0.12);
}

.hf-round-progress span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--hf-primary), var(--hf-gold));
  transition: width var(--hf-dur-standard) var(--hf-ease-out);
}

.hf-round-panels { touch-action: pan-y; }

.hf-round-panel__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 4px 12px;
  margin-bottom: 8px;
}

.hf-round-panel__title {
  margin: 0;
  color: var(--hf-rail-ink);
  font-family: var(--hf-font-display);
  font-size: 20px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.hf-bracket-mobile .hf-bracket-match {
  min-height: 0;
  margin-bottom: 8px;
  transform: none;
}

.hf-bracket-mobile .hf-bracket-match:hover {
  transform: none;
}

.hf-bracket-mobile .hf-between { color: var(--hf-rail-muted); }

.hf-bracket-mobile .hf-round-pager button,
.hf-bracket-mobile .hf-btn {
  color: var(--hf-rail-ink);
}

.hf-bracket-mobile .hf-btn {
  border-color: var(--hf-arena-line);
  background: rgba(243, 245, 249, 0.06);
}

.hf-bracket-mobile .hf-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.hf-bracket-mobile .hf-small.hf-muted { color: var(--hf-rail-muted); }

@media (min-width: 992px) {
  .hf-bracket-mobile { display: none; }
}

@media (max-width: 991.98px) {
  .hf-bracket-section { display: none; }
  .hf-bracket-mobile { display: block; }
  .hf-arena { padding: 16px; }
  .hf-arena__head { align-items: flex-start; }
  .hf-arena__tools { display: none; }
  .hf-arena__progress { margin-bottom: 14px; }
  .hf-arena__foot { margin-top: 4px; }
}

@media (forced-colors: active) {
  .hf-arena,
  .hf-bracket-mobile,
  .hf-bracket-match {
    border: 1px solid CanvasText;
    background: Canvas;
    color: CanvasText;
  }

  .hf-bracket-match.is-featured { border-width: 3px; }
  .hf-bracket-lines path { stroke: CanvasText; }
  .hf-arena__progress span,
  .hf-round-progress span,
  .hf-bracket-round-title__meter span { background: Highlight; }
}
```

- [ ] **Step 6: Import the partial after data surfaces**

In `src/resources/css/hf/_hoopflow.scss`:

```scss
@use "hf/tokens";
@use "hf/base";
@use "hf/shell";
@use "hf/components";
@use "hf/data";
@use "hf/bracket";
@use "hf/surfaces";
@use "hf/overlays";
@use "hf/motion";
@use "hf/print";
```

- [ ] **Step 7: Remove the superseded rules from `_data.scss`**

Delete only these bracket-owned blocks, preserving surrounding schedule/standings rules and the rescheduled-match work:

- `.hf-bracket` through `.hf-bracket-champion strong` (the whole `/* Bracket */` block).
- `.hf-bracket-toolbar`.
- `[data-bracket-canvas]`.
- `.hf-bracket-round-head`.
- `.hf-bracket-path, .hf-bracket-connector`.

Leave `.hf-champion` and the calendar rules in place. The rescheduled `.hf-bracket-match__head` and badges rules live inside the deleted bracket block; they are re-created in `_bracket.scss` above.

- [ ] **Step 8: Build and verify the stylesheet compiles**

Run: `cd src && npm run build`
Expected: Vite exits 0, no Sass errors, and `public/build` contains the new CSS.

- [ ] **Step 9: Confirm no old bracket selectors remain outside the new partial**

Run: `cd src && rg "hf-bracket-toolbar|hf-bracket-champion|data-bracket-canvas" resources/css`
Expected: no matches in `_data.scss`; only `_bracket.scss` may contain the new contract.

---

### Task 2: Match card component

**Files:**
- Modify: `src/resources/views/components/bracket-match.blade.php`

**Interfaces:**
- Consumes: the card class contract from Task 1.
- Produces: `<x-bracket-match :match :round-label :mobile :rescheduled :featured />` with the same test ids and handlers as today.

- [ ] **Step 1: Replace the component body while keeping the root contract**

```blade
@props(['match', 'roundLabel', 'mobile' => false, 'rescheduled' => false, 'featured' => false])

@php
    $home = $match->homeTeam;
    $away = $match->awayTeam;
    $hasHome = $match->home_team_id !== null;
    $hasAway = $match->away_team_id !== null;
    $isBye = $match->status === 'final'
        && $hasHome !== $hasAway
        && $match->home_score === null
        && $match->away_score === null;
    $winner1 = $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->home_team_id;
    $winner2 = $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $match->away_team_id;
    $decided = in_array($match->status, ['final', 'forfeit'], true);
    $score1 = $isBye ? 'BYE' : ($match->home_score !== null ? (string) $match->home_score : '–');
    $score2 = $match->away_score !== null ? (string) $match->away_score : '–';
    $suffix = $mobile ? '-mobile' : '';
    $slot = $match->matchSlots->first();
    $court = $slot?->court?->name;
    $start = $slot?->start_at;
    $statusLabel = match ($match->status) {
        'final' => 'Final',
        'forfeit' => 'Forfeit',
        'in_progress' => 'In progress',
        default => 'Scheduled',
    };
    $seriesPlayed = (int) $match->series_home_wins + (int) $match->series_away_wins;
    $pipCount = min(9, max(1, (int) $match->best_of));
    $ariaScoreHome = $isBye ? 'bye' : ($match->home_score ?? 'no score');
    $ariaScoreAway = $match->away_score ?? 'no score';
    $ariaLabel = $roundLabel.' slot '.$match->match_number.'. '
        .($home?->name ?? 'TBD').' '.$ariaScoreHome.', '
        .($away?->name ?? 'TBD').' '.$ariaScoreAway.'. '
        .$statusLabel.'. Open match details.';
@endphp

<div
    class="hf-bracket-match hf-bracket-match--{{ $match->status }}{{ $featured ? ' is-featured' : '' }}"
    data-match-id="{{ $match->id }}"
    data-testid="bracket-match-{{ $match->id }}{{ $suffix }}"
    role="button"
    tabindex="0"
    aria-label="{{ $ariaLabel }}"
    wire:key="bracket-match-{{ $match->id }}{{ $suffix }}"
    wire:click="openMatchDetail({{ $match->id }})"
    wire:keydown.enter.prevent="openMatchDetail({{ $match->id }})"
    wire:keydown.space.prevent="openMatchDetail({{ $match->id }})"
>
    <div class="hf-bracket-match__head">
        <span class="hf-bracket-match__slot">
            @if ($featured)
                <i class="bi bi-trophy-fill hf-bracket-match__trophy" aria-hidden="true"></i>
            @endif
            {{ $roundLabel }} · Slot {{ $match->match_number }}
        </span>
        <span class="hf-bracket-match__badges">
            @if ($rescheduled)
                <span class="hf-chip hf-chip--warning hf-bracket-match__status" data-testid="bracket-rescheduled-{{ $match->id }}{{ $suffix }}">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Rescheduled
                </span>
            @endif
            <x-status-chip
                :status="$match->status"
                class="hf-bracket-match__status"
                data-testid="bracket-status-{{ $match->id }}{{ $suffix }}"
            />
        </span>
    </div>

    <div class="hf-bracket-match__teams">
        <div class="hf-bracket-match__team {{ $winner1 ? 'is-winner' : ($decided ? 'is-loser' : '') }}">
            <x-team-shield :team="$home" />
            <span class="hf-bracket-match__name">{{ $home?->name ?? 'TBD' }}</span>
            <span class="hf-bracket-match__score {{ $match->home_score === null && ! $isBye ? 'is-muted' : '' }}">{{ $score1 }}</span>
        </div>
        <div class="hf-bracket-match__team {{ $winner2 ? 'is-winner' : ($decided ? 'is-loser' : '') }}">
            <x-team-shield :team="$away" />
            <span class="hf-bracket-match__name">{{ $away?->name ?? 'TBD' }}</span>
            <span class="hf-bracket-match__score {{ $match->away_score === null ? 'is-muted' : '' }}">{{ $score2 }}</span>
        </div>
    </div>

    <div class="hf-bracket-match__meta">
        @if ($isBye)
            <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
            <span>Bye — advances automatically</span>
        @else
            <i class="bi bi-geo-alt" aria-hidden="true"></i>
            <span>{{ $court ?? 'Court TBD' }}</span>
            <span aria-hidden="true">·</span>
            <i class="bi bi-clock" aria-hidden="true"></i>
            <span>{{ $start?->format('M j · g:i A') ?? 'Time TBD' }}</span>
        @endif
    </div>

    @if ($match->best_of > 1)
        <div class="hf-bracket-match__series" data-testid="bracket-series-{{ $match->id }}{{ $suffix }}">
            <span class="hf-bracket-match__series-label">Best of {{ $match->best_of }} · Game {{ $match->game_number }}</span>
            <span class="hf-bracket-match__series-score">{{ $match->series_home_wins }}–{{ $match->series_away_wins }}</span>
            <span class="hf-bracket-match__pips" aria-hidden="true">
                @for ($pip = 1; $pip <= $pipCount; $pip++)
                    <span class="hf-bracket-match__pip{{ $pip <= $seriesPlayed ? ' is-on' : '' }}"></span>
                @endfor
            </span>
        </div>
    @endif
</div>
```

- [ ] **Step 2: Run the bracket feature tests**

Run: `cd src && php artisan test tests/Feature/Pages/BracketTest.php`
Expected: all existing tests pass; especially rescheduled test ids, status ids, `Best of 3`, and `Game 1`.

- [ ] **Step 3: Check the card in the browser**

Open `http://127.0.0.1:8000/bracket?tournament=16` and confirm: winner scores are orange, losers dim, the status chip and court line fit on one card, and long team names ellipsize.

---

### Task 3: Arena markup, tree/matchday branches, and mobile stepper

**Files:**
- Modify: `src/resources/views/livewire/organizer/bracket-show.blade.php`

**Interfaces:**
- Consumes: `<x-bracket-match>` from Task 2 and classes from Task 1.
- Produces: DOM hooks for Task 4: `[data-bracket-arena]`, `[data-bracket-viewport]`, `[data-bracket-canvas]`, `[data-bracket-tree]`, `[data-bracket-lines]`, `[data-bracket-round]`, `[data-bracket-zoom]`, `[data-bracket-jump]`, `[data-round-index]`.

- [ ] **Step 1: Add the derived presentation values**

At the top `@php` block, after `$totalRounds`:

```php
$totalGames = collect($sections)->sum(
    fn ($section) => collect($section['rounds'])->sum(
        fn ($round) => $round['matches']->count()
    )
);
$playedGames = collect($sections)->sum(
    fn ($section) => collect($section['rounds'])->sum(
        fn ($round) => $round['matches']->filter(
            fn ($match) => in_array($match->status, ['final', 'forfeit'], true)
        )->count()
    )
);
$progressPercent = $totalGames > 0 ? (int) round(($playedGames / $totalGames) * 100) : 0;
$mobileRoundCount = $mobileRounds->count();
$hasTree = collect($sections)->contains(fn ($section) => $section['key'] !== 'group');
```

- [ ] **Step 2: Replace the toolbar and section loop with the arena**

Replace the current `<div class="hf-bracket-toolbar">…</div>` and the `@foreach ($sections …) … @endforeach` block (lines 134–169 in the current file) with:

```blade
<div class="hf-arena" data-bracket-arena>
    <div class="hf-arena__head">
        <div class="hf-arena__intro">
            <span class="hf-eyebrow hf-eyebrow--on-dark">Road to the title</span>
            <p class="hf-arena__summary">
                <strong>{{ $playedGames }}</strong> of {{ $totalGames }} games final
                <span aria-hidden="true">·</span> {{ $approved->count() }} teams
                <span aria-hidden="true">·</span> {{ $totalRounds }} rounds
            </p>
        </div>
        <div class="hf-arena__tools">
            <div class="hf-arena__zoom" role="group" aria-label="Bracket zoom">
                <button type="button" class="hf-arena__tool" data-bracket-zoom="out" aria-label="Zoom out"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                <button type="button" class="hf-arena__tool" data-bracket-zoom="reset">Fit</button>
                <button type="button" class="hf-arena__tool" data-bracket-zoom="in" aria-label="Zoom in"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
            </div>
            @if ($hasTree)
                <button type="button" class="hf-arena__tool hf-arena__tool--gold" data-bracket-jump="final">
                    <i class="bi bi-trophy" aria-hidden="true"></i> Jump to final
                </button>
            @endif
        </div>
    </div>

    <div class="hf-arena__progress" role="progressbar" aria-label="Bracket progress" aria-valuemin="0" aria-valuemax="{{ $totalGames }}" aria-valuenow="{{ $playedGames }}">
        <span style="width: {{ $progressPercent }}%"></span>
    </div>

    @foreach ($sections as $section)
        @php
            $sectionGames = collect($section['rounds'])->sum(fn ($round) => $round['matches']->count());
            $sectionRounds = count($section['rounds']);
            $isGoldSection = $section['key'] === 'grand_final';
        @endphp
        <section class="hf-bracket-section" data-testid="bracket-section-{{ $section['key'] }}">
            <header class="hf-bracket-section__head">
                <h2 class="hf-bracket-section__title{{ $isGoldSection ? ' hf-bracket-section__title--gold' : '' }}">{{ $section['title'] }}</h2>
                <span class="hf-bracket-section__meta">
                    {{ $sectionRounds }} {{ \Illuminate\Support\Str::plural('round', $sectionRounds) }}
                    <span aria-hidden="true">·</span>
                    {{ $sectionGames }} {{ \Illuminate\Support\Str::plural('game', $sectionGames) }}
                </span>
            </header>

            @if ($section['key'] === 'grand_final' && $tournament->isModifiedDoubleElimination())
                <p class="hf-small hf-bracket-section__note" data-testid="bracket-grand-final-note">
                    Best of {{ $tournament->grandFinalBestOf() }} — the series winner takes the title. No reset game is played.
                </p>
            @endif

            <div class="hf-bracket-viewport" data-bracket-viewport>
                <div
                    class="hf-bracket {{ $section['key'] === 'group' ? 'hf-bracket--matchdays' : 'hf-bracket--tree' }}"
                    data-testid="bracket-tree"
                    @if ($section['key'] !== 'group') data-bracket-canvas @endif
                    wire:loading.class="is-updating"
                    wire:loading.attr="aria-busy"
                >
                    <div class="hf-updating-pill" wire:loading wire:target="requestGenerate,requestGeneratePlayoffs,requestRegenerate,saveSlots,addScore,undoScore,requestFinalizeScore,requestCorrection,confirm">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Updating…
                    </div>

                    @if ($section['key'] === 'group')
                        <div class="hf-bracket-matchdays">
                            @foreach ($section['rounds'] as $round)
                                @php
                                    $roundGames = $round['matches']->count();
                                    $roundDone = $round['matches']->filter(fn ($match) => in_array($match->status, ['final', 'forfeit'], true))->count();
                                    $roundTitleId = 'bracket-round-'.$section['key'].'-'.$loop->index;
                                @endphp
                                <section class="hf-matchday-block" aria-labelledby="{{ $roundTitleId }}">
                                    <div class="hf-matchday-block__head">
                                        <h3 class="hf-matchday-block__title" id="{{ $roundTitleId }}">{{ $round['label'] }}</h3>
                                        <span class="hf-matchday-block__meta">{{ $roundGames }} {{ \Illuminate\Support\Str::plural('game', $roundGames) }} · {{ $roundDone }} final</span>
                                    </div>
                                    <div class="hf-matchday-block__grid">
                                        @foreach ($round['matches'] as $match)
                                            <x-bracket-match
                                                :match="$match"
                                                :round-label="$round['label']"
                                                :rescheduled="in_array((int) $match->id, $this->rescheduledMatchIds, true)"
                                            />
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @else
                        <div class="hf-bracket-tree" data-bracket-tree>
                            <svg class="hf-bracket-lines" data-bracket-lines aria-hidden="true" wire:ignore></svg>

                            @foreach ($section['rounds'] as $round)
                                @php
                                    $roundGames = $round['matches']->count();
                                    $roundDone = $round['matches']->filter(fn ($match) => in_array($match->status, ['final', 'forfeit'], true))->count();
                                    $roundPercent = $roundGames > 0 ? (int) round(($roundDone / $roundGames) * 100) : 0;
                                    $isFeaturedRound = $loop->last && in_array($section['key'], ['main', 'playoffs', 'grand_final'], true);
                                @endphp
                                <div class="hf-bracket-round" data-bracket-round="{{ $loop->index }}" data-round-label="{{ $round['label'] }}">
                                    <div class="hf-bracket-round-title">
                                        <span class="hf-bracket-round-title__label">{{ $round['label'] }}</span>
                                        <span class="hf-bracket-round-title__meta">{{ $roundGames }} {{ \Illuminate\Support\Str::plural('game', $roundGames) }}</span>
                                        <span class="hf-bracket-round-title__meter" aria-hidden="true"><span style="width: {{ $roundPercent }}%"></span></span>
                                    </div>
                                    <div class="hf-bracket-round__body">
                                        @foreach ($round['matches'] as $match)
                                            <x-bracket-match
                                                :match="$match"
                                                :round-label="$round['label']"
                                                :featured="$isFeaturedRound"
                                                :rescheduled="in_array((int) $match->id, $this->rescheduledMatchIds, true)"
                                            />
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <span class="hf-bracket-viewport__fade" aria-hidden="true"></span>
            </div>
        </section>
    @endforeach

    <div class="hf-arena__foot">
        <span><i class="bi bi-bezier2" aria-hidden="true"></i> Hover a matchup to trace its road to the title.</span>
        <span>Live results update the board automatically.</span>
    </div>
</div>
```

- [ ] **Step 3: Replace the mobile pager with the ARIA tablist and swipe panel**

Replace the existing `<div class="hf-bracket-mobile" …> … </div>` block (lines 172–205 currently) with:

```blade
<div
    class="hf-bracket-mobile"
    data-testid="bracket-mobile"
    x-data="{
        mobileRound: 0,
        totalRounds: {{ $mobileRoundCount }},
        touchStartX: null,
        touchStartY: null,
        goToRound(index) {
            this.mobileRound = Math.max(0, Math.min(this.totalRounds - 1, index));

            this.$nextTick(() => {
                if (! this.$refs.tabs) return;
                this.$refs.tabs.querySelectorAll('[role=tab]')[this.mobileRound]?.focus();
            });
        },
        onTouchStart(event) {
            const touch = event.changedTouches[0];
            this.touchStartX = touch.clientX;
            this.touchStartY = touch.clientY;
        },
        onTouchEnd(event) {
            if (this.touchStartX === null) return;

            const touch = event.changedTouches[0];
            const dx = touch.clientX - this.touchStartX;
            const dy = touch.clientY - this.touchStartY;
            this.touchStartX = null;
            this.touchStartY = null;

            if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) return;

            this.goToRound(this.mobileRound + (dx < 0 ? 1 : -1));
        }
    }"
    x-cloak
    x-init="mobileRound = Math.min(mobileRound, {{ max(0, $mobileRoundCount - 1) }})"
>
    <div class="hf-round-stepper">
        <div
            class="hf-round-pager"
            role="tablist"
            aria-label="Bracket rounds"
            x-ref="tabs"
            x-on:keydown.arrow-right.prevent="goToRound(mobileRound + 1)"
            x-on:keydown.arrow-left.prevent="goToRound(mobileRound - 1)"
            x-on:keydown.home.prevent="goToRound(0)"
            x-on:keydown.end.prevent="goToRound(totalRounds - 1)"
        >
            @foreach ($mobileRounds as $index => $round)
                <button
                    type="button"
                    role="tab"
                    id="bracket-round-tab-{{ $index }}"
                    aria-controls="bracket-round-panel-{{ $index }}"
                    x-bind:aria-selected="mobileRound === {{ $index }} ? 'true' : 'false'"
                    x-bind:tabindex="mobileRound === {{ $index }} ? 0 : -1"
                    x-bind:class="{ 'is-active': mobileRound === {{ $index }} }"
                    x-on:click="goToRound({{ $index }})"
                    data-testid="bracket-round-tab-{{ $index }}"
                >{{ $round['label'] }}</button>
            @endforeach
        </div>
        <div
            class="hf-round-progress"
            role="progressbar"
            aria-label="Round progress"
            aria-valuemin="1"
            aria-valuemax="{{ $mobileRoundCount }}"
            x-bind:aria-valuenow="mobileRound + 1"
        >
            <span x-bind:style="'width: ' + (((mobileRound + 1) / {{ max(1, $mobileRoundCount) }}) * 100) + '%'"></span>
        </div>
    </div>

    <div class="hf-round-panels" x-on:touchstart.passive="onTouchStart($event)" x-on:touchend.passive="onTouchEnd($event)">
        @foreach ($mobileRounds as $index => $round)
            <div
                id="bracket-round-panel-{{ $index }}"
                role="tabpanel"
                aria-labelledby="bracket-round-tab-{{ $index }}"
                x-show="mobileRound === {{ $index }}"
                x-cloak
            >
                <div class="hf-round-panel__head">
                    <h3 class="hf-round-panel__title">{{ $round['label'] }}</h3>
                    <span class="hf-small hf-muted">{{ $round['matches']->count() }} {{ \Illuminate\Support\Str::plural('game', $round['matches']->count()) }}</span>
                </div>
                @foreach ($round['matches'] as $match)
                    <x-bracket-match
                        :match="$match"
                        :round-label="$round['label']"
                        :mobile="true"
                        :rescheduled="in_array((int) $match->id, $this->rescheduledMatchIds, true)"
                    />
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="hf-between hf-mt-2">
        <button type="button" class="hf-btn hf-btn--sm" x-on:click="goToRound(mobileRound - 1)" x-bind:disabled="mobileRound === 0" data-testid="bracket-prev-round">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
        </button>
        <span class="hf-small hf-muted" x-text="(mobileRound + 1) + ' of {{ $mobileRoundCount }}'"></span>
        <button type="button" class="hf-btn hf-btn--sm" x-on:click="goToRound(mobileRound + 1)" x-bind:disabled="mobileRound === {{ max(0, $mobileRoundCount - 1) }}" data-testid="bracket-next-round">
            Next <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
    </div>
</div>
```

- [ ] **Step 4: Remove the old `$roundCount` variable**

Delete the line `@php $roundCount = $mobileRounds->count(); @endphp`; every use is replaced by `$mobileRoundCount`.

- [ ] **Step 5: Run the bracket tests**

Run: `cd src && php artisan test tests/Feature/Pages/BracketTest.php`
Expected: PASS. `bracket-tree`, `bracket-mobile`, `bracket-round-tab-0`, status/rescheduled ids, `Winners bracket`, `Losers bracket`, `Grand final`, `Winners Quarterfinal`, `Losers Round 1`, `Round of 16`, `Quarterfinals`, and `Championship Final` are all still rendered.

- [ ] **Step 6: Check the page without JavaScript for server-rendered content**

Run: `cd src && php artisan view:clear && curl -s http://127.0.0.1:8000/bracket?tournament=16 | rg "hf-arena|bracket-tree|bracket-round-tab-0"`
Expected: the arena, tree, and tab 0 exist in the HTML.

---

### Task 4: Connector, zoom, fit, jump, and tracing behavior

**Files:**
- Modify: `src/resources/js/ui/bracket.js`

**Interfaces:**
- Consumes: all Task 3 data attributes.
- Produces: `initBracketZoom()` (existing export name, called once from `page-lifecycle.js`).

- [ ] **Step 1: Replace `bracket.js` with the full module**

```js
const MIN_ZOOM = 0.55;
const MAX_ZOOM = 1.5;
const ZOOM_STEP = 0.1;

let scale = 1;
let initialized = false;
let refreshFrame = null;
let autoFitDone = false;
const traceState = new WeakMap();
const boundCanvases = new WeakSet();

const reducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

function canvases() {
    return [...document.querySelectorAll('[data-bracket-canvas]')];
}

function visibleCanvases() {
    return canvases().filter((canvas) => canvas.offsetParent !== null && canvas.clientWidth > 0);
}

function treeOf(canvas) {
    return canvas.querySelector('[data-bracket-tree]');
}

function currentScale(tree) {
    const value = Number.parseFloat(tree.style.zoom);

    return Number.isFinite(value) && value > 0 ? value : 1;
}

function applyScale(nextScale) {
    scale = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, nextScale));

    canvases().forEach((canvas) => {
        const tree = treeOf(canvas);
        if (!tree) return;

        tree.style.zoom = scale === 1 ? '' : String(scale);
    });

    requestAnimationFrame(() => {
        drawAll();
        updateFades();
    });
}

function fitScale() {
    const visible = visibleCanvases();
    if (visible.length === 0) return 1;

    let fit = 1;

    visible.forEach((canvas) => {
        const tree = treeOf(canvas);
        if (!tree) return;

        const activeScale = currentScale(tree);
        const baseWidth = tree.getBoundingClientRect().width / activeScale;
        const available = canvas.clientWidth - 4;

        if (baseWidth > 0 && available > 0) {
            fit = Math.min(fit, available / baseWidth);
        }
    });

    return Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, fit));
}

function drawAll() {
    canvases().forEach(drawCanvas);
}

function localRect(element, tree, treeRect, activeScale) {
    const rect = element.getBoundingClientRect();

    return {
        left: (rect.left - treeRect.left) / activeScale,
        right: (rect.right - treeRect.left) / activeScale,
        top: (rect.top - treeRect.top) / activeScale,
        centerY: (rect.top + rect.height / 2 - treeRect.top) / activeScale,
        width: rect.width / activeScale,
        height: rect.height / activeScale,
    };
}

function drawCanvas(canvas) {
    const tree = treeOf(canvas);
    if (!tree) return;

    const svg = tree.querySelector('[data-bracket-lines]');
    if (!svg) return;

    const activeScale = currentScale(tree);
    const treeRect = tree.getBoundingClientRect();
    const width = treeRect.width / activeScale;
    const height = treeRect.height / activeScale;

    if (width <= 0 || height <= 0) return;

    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('width', String(width));
    svg.setAttribute('height', String(height));
    svg.style.width = `${width}px`;
    svg.style.height = `${height}px`;

    const rounds = [...tree.querySelectorAll('[data-bracket-round]')];
    let markup = '';

    for (let index = 0; index < rounds.length - 1; index += 1) {
        const current = rounds[index];
        const next = rounds[index + 1];
        const currentCards = [...current.querySelectorAll('.hf-bracket-match')];
        const nextCards = [...next.querySelectorAll('.hf-bracket-match')];

        if (currentCards.length === 0 || nextCards.length === 0) continue;

        const currentRect = localRect(current, tree, treeRect, activeScale);
        const nextRect = localRect(next, tree, treeRect, activeScale);
        const startX = currentRect.right;
        const endX = nextRect.left;
        const midX = startX + (endX - startX) / 2;
        const ratio = currentCards.length / nextCards.length;

        nextCards.forEach((nextCard, nextIndex) => {
            const to = nextCard.dataset.matchId;
            const target = localRect(nextCard, tree, treeRect, activeScale);

            if (!to) return;

            const start = currentCards.length >= nextCards.length ? Math.floor(nextIndex * ratio) : 0;
            const end = currentCards.length >= nextCards.length
                ? Math.max(start + 1, Math.floor((nextIndex + 1) * ratio))
                : currentCards.length;

            currentCards.slice(start, end).forEach((currentCard) => {
                const from = currentCard.dataset.matchId;
                if (!from) return;

                const source = localRect(currentCard, tree, treeRect, activeScale);
                const path = `M${startX} ${source.centerY} H${midX} V${target.centerY} H${endX}`;

                markup += `<path class="hf-bracket-path" data-from="${from}" data-to="${to}" d="${path}" />`;
            });
        });
    }

    svg.innerHTML = markup;
    reapplyTrace(canvas);
}

function reapplyTrace(canvas) {
    const traced = traceState.get(canvas);
    if (!traced) return;

    const tree = treeOf(canvas);
    if (!tree || !tree.querySelector(`.hf-bracket-match[data-match-id="${traced}"]`)) {
        traceState.delete(canvas);
        clearTrace(canvas);

        return;
    }

    trace(canvas, tree.querySelector(`.hf-bracket-match[data-match-id="${traced}"]`));
}

function trace(canvas, card) {
    const tree = treeOf(canvas);
    const matchId = card?.dataset.matchId;
    if (!tree || !matchId) return;

    traceState.set(canvas, matchId);

    const graph = new Map();
    tree.querySelectorAll('.hf-bracket-path').forEach((path) => {
        const from = path.dataset.from;
        const to = path.dataset.to;
        if (!from || !to) return;

        if (!graph.has(from)) graph.set(from, new Set());
        if (!graph.has(to)) graph.set(to, new Set());
        graph.get(from).add(to);
        graph.get(to).add(from);
    });

    const pathIds = new Set([matchId]);
    const queue = [matchId];

    while (queue.length > 0) {
        const current = queue.shift();

        graph.get(current)?.forEach((neighbor) => {
            if (pathIds.has(neighbor)) return;

            pathIds.add(neighbor);
            queue.push(neighbor);
        });
    }

    tree.classList.add('is-tracing');
    tree.querySelectorAll('.hf-bracket-match').forEach((element) => {
        element.classList.toggle('is-path', pathIds.has(element.dataset.matchId));
    });
    tree.querySelectorAll('.hf-bracket-path').forEach((element) => {
        element.classList.toggle(
            'is-hot',
            pathIds.has(element.dataset.from) && pathIds.has(element.dataset.to),
        );
    });
}

function clearTrace(canvas) {
    traceState.delete(canvas);

    const tree = treeOf(canvas);
    if (!tree) return;

    tree.classList.remove('is-tracing');
    tree.querySelectorAll('.is-path').forEach((element) => element.classList.remove('is-path'));
    tree.querySelectorAll('.hf-bracket-path.is-hot').forEach((element) => element.classList.remove('is-hot'));
}

function updateFade(canvas) {
    const viewport = canvas.closest('[data-bracket-viewport]');
    if (!viewport) return;

    const scrollable = canvas.scrollWidth > canvas.clientWidth + 2;
    const atEnd = canvas.scrollLeft + canvas.clientWidth >= canvas.scrollWidth - 2;

    viewport.classList.toggle('is-scrollable', scrollable);
    viewport.classList.toggle('is-at-end', scrollable && atEnd);
}

function updateFades() {
    canvases().forEach(updateFade);
}

function bindCanvas(canvas) {
    if (boundCanvases.has(canvas)) return;
    boundCanvases.add(canvas);

    canvas.addEventListener('scroll', () => updateFade(canvas), { passive: true });

    canvas.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'mouse') return;

        const card = event.target.closest?.('.hf-bracket-match');
        if (card && canvas.contains(card)) {
            trace(canvas, card);
        } else {
            clearTrace(canvas);
        }
    });

    canvas.addEventListener('pointerleave', () => clearTrace(canvas));

    canvas.addEventListener('focusin', (event) => {
        const card = event.target.closest?.('.hf-bracket-match');
        if (card && canvas.contains(card)) trace(canvas, card);
    });

    canvas.addEventListener('focusout', (event) => {
        const next = event.relatedTarget;
        if (next instanceof Element && canvas.contains(next)) return;
        clearTrace(canvas);
    });
}

function refresh() {
    canvases().forEach(bindCanvas);
    drawAll();
    updateFades();
}

function scheduleRefresh() {
    if (refreshFrame !== null) return;

    refreshFrame = requestAnimationFrame(() => {
        refreshFrame = null;
        refresh();
    });
}

function autoFit() {
    if (autoFitDone) return;
    autoFitDone = true;

    const overflow = visibleCanvases().some((canvas) => canvas.scrollWidth > canvas.clientWidth + 2);
    if (overflow) applyScale(fitScale());
}

function hookLivewire() {
    const register = () => {
        window.Livewire?.hook?.('morphed', () => scheduleRefresh());
    };

    if (window.Livewire) {
        register();
    } else {
        document.addEventListener('livewire:init', register, { once: true });
    }
}

export function initBracketZoom() {
    if (initialized) return;
    initialized = true;

    document.addEventListener('click', (event) => {
        const zoomButton = event.target.closest('[data-bracket-zoom]');

        if (zoomButton) {
            const action = zoomButton.dataset.bracketZoom;

            if (action === 'in') applyScale(scale + ZOOM_STEP);
            if (action === 'out') applyScale(scale - ZOOM_STEP);
            if (action === 'reset') applyScale(fitScale());

            return;
        }

        const jump = event.target.closest('[data-bracket-jump="final"]');
        if (!jump) return;

        const targets = visibleCanvases();
        const canvas = targets[targets.length - 1];
        if (!canvas) return;

        const rounds = [...canvas.querySelectorAll('[data-bracket-round]')];
        const finalCard = rounds[rounds.length - 1]?.querySelector('.hf-bracket-match');
        if (!finalCard) return;

        finalCard.scrollIntoView({
            behavior: reducedMotion() ? 'auto' : 'smooth',
            block: 'center',
            inline: 'center',
        });
        finalCard.focus({ preventScroll: true });
        trace(canvas, finalCard);
    });

    document.addEventListener('livewire:navigated', () => {
        autoFitDone = false;
        scheduleRefresh();

        window.setTimeout(() => {
            autoFit();
            refresh();
        }, 0);
    });

    const observer = new ResizeObserver(() => scheduleRefresh());

    const observeTrees = () => {
        document.querySelectorAll('[data-bracket-tree]').forEach((tree) => observer.observe(tree));
    };

    const originalRefresh = refresh;
    refresh = () => {
        originalRefresh();
        observeTrees();
    };

    hookLivewire();
    refresh();

    requestAnimationFrame(() => requestAnimationFrame(refresh));

    if (document.fonts?.ready) {
        document.fonts.ready.then(() => {
            refresh();
            autoFit();
        });
    }

    window.addEventListener('resize', scheduleRefresh, { passive: true });
}
```

- [ ] **Step 2: Build and verify there are no syntax errors**

Run: `cd src && npm run build`
Expected: Vite exits 0.

- [ ] **Step 3: Verify connectors and tracing in the browser**

At `http://127.0.0.1:8000/bracket?tournament=16`:

- Four round columns align, and each quarterfinal sits between its two round-of-16 cards.
- Eight connector paths exist from round 1 to round 2 (two per quarterfinal).
- Hovering a round-of-16 card highlights that card, its quarterfinal, its semifinal, the final, and the connecting lines; the rest dims.
- Tab-focusing a card produces the same trace.
- Fit, zoom in/out, and Jump to final all move without clipping.

- [ ] **Step 4: Verify Livewire redraw**

As the organizer, open a scheduled match, enter a score, finalize it, close the dialog, and confirm the connectors redraw and the updated winner advances to the next round without stale lines.

---

### Task 5: Print, polish, and full verification

**Files:**
- Modify: `src/resources/css/hf/_print.scss`
- Verify: `src/resources/css/hf/_bracket.scss`, `src/resources/js/ui/bracket.js`, views

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: a shippable page.

- [ ] **Step 1: Add paper rules**

Inside the existing `@media print` block in `_print.scss`:

```scss
.hf-arena,
.hf-bracket-mobile {
  border-color: #bbb !important;
  background: #fff !important;
  color: #000 !important;
  box-shadow: none !important;
}

.hf-arena::before,
.hf-arena::after,
.hf-arena__tools,
.hf-arena__progress,
.hf-arena__foot,
.hf-bracket-viewport__fade,
.hf-round-stepper,
.hf-round-panels {
  display: none !important;
}

.hf-bracket-section {
  display: block !important;
}

.hf-bracket-section__title,
.hf-bracket-round-title__label,
.hf-bracket-match,
.hf-bracket-match__name,
.hf-bracket-match__score,
.hf-bracket-match__meta {
  color: #000 !important;
}

.hf-bracket-match {
  border-color: #999 !important;
  background: #fff !important;
  box-shadow: none !important;
  break-inside: avoid;
}

.hf-bracket-match.is-featured {
  border: 2px solid #000 !important;
}

.hf-bracket-lines path {
  stroke: #999 !important;
}

.hf-bracket-tree {
  padding: 8px 0 !important;
  width: auto !important;
  zoom: 1 !important;
}

.hf-bracket--tree {
  overflow: visible !important;
}
```

- [ ] **Step 2: Run the full quality gate**

Run: `cd src && composer quality`
Expected: Pint, PHPStan, and the full Pest suite pass with the actual output reported.

- [ ] **Step 3: Build production assets**

Run: `cd src && npm run build`
Expected: Vite exits 0.

- [ ] **Step 4: Browser matrix**

Check each case and record the result:

| Case | URL / setup | Pass condition |
|---|---|---|
| Single elimination | `/bracket?tournament=16` | 4 aligned rounds, connectors, gold final |
| Double elimination | `/bracket?tournament=<demo-double-elimination id>` | 3 boards, grand final gold, no cross-section lines |
| Round robin | `/bracket` with a group-stage tournament | matchday grid, no tree |
| Round robin + playoffs | group section + playoffs section | grid plus tree |
| Desktop wide | 1440×900 | no page horizontal overflow; board scrolls internally |
| Desktop narrow | 1024×768 | auto-fit or horizontal scroll; no clipping |
| Mobile | 390×844 | stepper tabs, swipe, Back/Next, compact cards |
| Light theme | toggle theme | navy arena, readable chips |
| Dark theme | toggle theme | arena blends, text readable |
| Keyboard | Tab to cards, arrows on tabs | focus ring, Enter/Space opens dialog |
| Live update | organizer score flow | connectors redraw, no stale trace |
| Reduced motion | emulation | no smooth scroll or lift animation |
| Print preview | Ctrl+P | black on white, no controls, full board |

- [ ] **Step 5: Confirm preserved contracts**

Run: `cd src && rg "data-testid=\"bracket-(tree|mobile|round-tab|match-|status-|rescheduled-)" resources/views/livewire/organizer/bracket-show.blade.php resources/views/components/bracket-match.blade.php`
Expected: every existing id still appears, including desktop and mobile variants.

- [ ] **Step 6: Report**

Report the actual `composer quality` and `npm run build` output, the browser checks, and any residual issue. Do not claim completion without the command output.

## Self-Review Notes

- Spec coverage: arena, tree alignment, connectors, featured final, tracing, zoom/fit/jump, mobile tabs/swipe, round-robin grid, dark-board tokens, print, accessibility, and Livewire redraw all map to Tasks 1–5.
- No backend or test edits are required.
- Class names and data attributes are consistent across the CSS, Blade, and JS tasks.
- The only intentional deviation from the plan template is the no-commit constraint, required because the working tree contains the user’s uncommitted rescheduled-match feature.
