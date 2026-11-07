# Sub-project 4: Announcements management — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add view-single-announcement modal with pin/unpin and delete actions, and extend audience targeting to include `fans` and specific users (`targetUserIds`).

**Architecture:** Extend the announcement data model with `targetUserIds`. Add `deleteAnnouncement` and `pinAnnouncement` store actions. Update feed filtering in `notificationsFor`/selector. Rework `pages/announcements.js` to open a view modal with actions. Rework `pages/announcement-create.js` composer to support new audiences and a user picker.

**Tech Stack:** Static HTML, vanilla ES modules, Node's built-in test runner. No new dependencies.

## Global Constraints

- Work only in `prototype/`, `tools/`, and `docs/superpowers/`. Do not touch `src/`, `docs/wireframes/`, `HoopFlow/`, `logo/`, `video/`, or `_archive/`.
- Runtime must work offline. No `https://` asset references in prototype HTML or CSS.
- No `alert()`, `confirm()`, or `prompt()`. Use `ui.toast()` and `ui.confirm()`.
- No client-side framework, no build step, no runtime npm dependencies.
- All existing prototype routes stay valid. No new routes.
- Node tests: `cd prototype` then `node --test`.
- Never stage `docs/wireframes/`.

---

### Task 1: Extend announcement data model and publishAnnouncement

**Files:**
- Modify: `prototype/assets/js/store.js` (ANNOUNCEMENT_AUDIENCES, publishAnnouncement)
- Modify: `prototype/assets/js/seed.js` (announcement entries)

**Interfaces:**
- Produces: `announcement.audience: 'all'|'coaches'|'organizers'|'fans'|'specific'`, `announcement.targetUserIds: string[]`.

- [ ] **Step 1: Update ANNOUNCEMENT_AUDIENCES** to include `'fans'` and `'specific'`.

- [ ] **Step 2: Update publishAnnouncement** to accept `targetUserIds` array. Validate: if audience === 'specific', require at least one valid user id. Store `targetUserIds` on the announcement (default `[]`).

- [ ] **Step 3: Update seed.js** — ensure seeded announcements have `targetUserIds: []` (add to makeAnnouncement or the announcement objects).

- [ ] **Step 4: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js prototype/assets/js/seed.js
git commit -m "feat(prototype): extend announcement model with fans/specific audiences"
```

---

### Task 2: Add deleteAnnouncement and pinAnnouncement actions

**Files:**
- Modify: `prototype/assets/js/store.js`

**Interfaces:**
- Produces: `store.actions.deleteAnnouncement(id)`, `store.actions.pinAnnouncement(id, pinned)`.

- [ ] **Step 1: Implement deleteAnnouncement** — requires `announcement.publish`. Removes the announcement. Audit entry `announcement.delete`.

- [ ] **Step 2: Implement pinAnnouncement** — requires `announcement.publish`. Sets `pinned`. Audit entry `announcement.pin` or `announcement.unpin`.

- [ ] **Step 3: Export both actions.**

- [ ] **Step 4: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js
git commit -m "feat(prototype): add deleteAnnouncement and pinAnnouncement actions"
```

---

### Task 3: Update feed filtering for new audiences

**Files:**
- Modify: `prototype/assets/js/store.js` (`notificationsFor`, `AUDIENCE_BY_ROLE`)
- Modify: `prototype/assets/js/pages/announcements.js` (visibility filter)

**Interfaces:**
- Consumes: `AUDIENCE_BY_ROLE`, `targetUserIds`.

- [ ] **Step 1: Update AUDIENCE_BY_ROLE** — add 'fans' and 'specific' (specific handled separately). organizer: all audiences; coach: all/coaches/fans/specific-if-targeted; fan: all/fans/specific-if-targeted.

- [ ] **Step 2: Update notificationsFor()** to filter by targetUserIds for 'specific' audience.

- [ ] **Step 3: Update announcements page visibility** — filter announcements by targetUserIds for 'specific' audience.

- [ ] **Step 4: Run tests + commit**

```powershell
cd prototype
node --test
git add prototype/assets/js/store.js prototype/assets/js/pages/announcements.js
git commit -m "feat(prototype): filter announcements by audience and target users"
```

---

### Task 4: View single announcement modal with actions

**Files:**
- Modify: `prototype/assets/js/pages/announcements.js`

**Interfaces:**
- Consumes: `ui.dialog`, `ui.confirm`, `store.actions.pinAnnouncement`, `store.actions.deleteAnnouncement`.

- [ ] **Step 1: Make announcement cards clickable** — add `data-announcement-id` and click handler that opens a modal.

- [ ] **Step 2: Implement view modal** — wide dialog with full title, body, category, audience, author, date, pinned status, tournament name. Footer: Pin/Unpin toggle (organizer only), Delete (organizer only), Close.

- [ ] **Step 3: Wire pin/unpin** — calls `pinAnnouncement(id, !pinned)`, toast, re-render.

- [ ] **Step 4: Wire delete** — confirmation dialog, then `deleteAnnouncement(id)`, toast, re-render.

- [ ] **Step 5: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/announcements.js
node --test
git add prototype/assets/js/pages/announcements.js
git commit -m "feat(prototype): add view/pin/delete modal to announcements feed"
```

---

### Task 5: Composer — new audiences + user picker

**Files:**
- Modify: `prototype/assets/js/pages/announcement-create.js`

**Interfaces:**
- Consumes: `store.select.users` (or a user list), `store.actions.publishAnnouncement`.

- [ ] **Step 1: Add `Fans` and `Specific user` options** to the audience select.

- [ ] **Step 2: Add user picker** — when "Specific user" selected, reveal a multi-select list of users (organizers, coaches, fans) grouped by role. Selected ids stored in `targetUserIds`.

- [ ] **Step 3: Update live preview** to show selected audience.

- [ ] **Step 4: Update publish payload** to include `targetUserIds`.

- [ ] **Step 5: Run tests + commit**

```powershell
cd prototype
node --check assets/js/pages/announcement-create.js
node --test
git add prototype/assets/js/pages/announcement-create.js
git commit -m "feat(prototype): add fans/specific audience targeting to composer"
```

---

### Task 6: Static gates and verification

**Files:**
- Create: `docs/superpowers/verification/2026-11-11-subproject4-announcements-management.md`

- [ ] **Step 1: Run static gates**

```powershell
cd prototype
Get-ChildItem assets/js -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
node --test
```

- [ ] **Step 2: Run sweeps** (legacy, CDN, native dialogs, https, scorekeeper).

- [ ] **Step 3: Write verification record.**

- [ ] **Step 4: Commit**

```powershell
git add docs/superpowers/verification/2026-11-11-subproject4-announcements-management.md
git commit -m "docs: record sub-project 4 verification"
```

---

## Self-Review

1. **Spec coverage:** Data model (Task 1), actions (Task 2), feed filtering (Task 3), view modal (Task 4), composer (Task 5), verification (Task 6). All spec sections covered.
2. **Placeholders:** Tasks 4-5 are large; implementers should model on existing dialog/card patterns.
3. **Type consistency:** `audience` values and `targetUserIds` field consistent across store, seed, announcements page, composer.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-11-subproject4-announcements-management.md`.

Two execution options:
1. Subagent-Driven (recommended)
2. Inline Execution

Which approach? (I recommend Subagent-Driven.)