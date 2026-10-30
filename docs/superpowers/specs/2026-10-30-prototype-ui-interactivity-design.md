# Prototype UI and interactivity enhancement

- **Date:** 2026-10-30
- **Owner:** Jeward T. Mahinay
- **Status:** Approved for planning
- **Related:** [[ADR-0001-stack]] · [[2026-10-30-src-folder-structure-design]] · [[18 Demo Script]] · [[A Review Scorecard]]

## Problem

The static prototype in `prototype/` looks plausible but is not demo-safe:

- Many controls are decorative. Schedule arrows, court filters, standings filters, and some tournament actions are unwired; several organizer actions still call `alert()`.
- State is not normalized. Teams, players, and matches are not scoped to tournaments, and each page mutates a private copy of `hoopflow_db`. Cross-page actions drift or contradict each other.
- Score entry reloads the page, does not advance the bracket, and does not update standings.
- The floating role pill overlaps content and the mobile bottom navigation.
- Mobile layouts break down: the month calendar occupies a full screen, standings columns clip, and the bracket is a 680px horizontal canvas.
- The visual language is generic Bootstrap with an orange accent. It does not express the courtside basketball identity, and status colors have no consistent meaning.
- The written demo script expects a scorekeeper role and a 16-team dataset that the prototype does not provide.

The prototype is the source of truth for layout, spacing, and interaction states that will be rebuilt in `src/` as Blade and Livewire components. Every shortcut kept here must be deliberate and documented.

## Goals

1. Make the 10-step demo script work end-to-end against persisted mock data, in any reasonable order, across refresh.
2. Give the prototype a distinctive **courtside broadcast** identity that still reads as a trustworthy organizer tool.
3. Make every visible control do something real or be removed.
4. Reach parity between a 1366×768 laptop demo and a ~390px phone check.
5. Leave a component and state reference that is straightforward to port to Laravel, Blade, and Livewire.

## Non-goals

- Real authentication, server persistence, file upload, email/SMS, payment gateway integration, or a live game clock.
- Replacing the Laravel application. `src/` remains the product.
- A second client-side framework or a single-page application.
- Drag-and-drop bracket seeding. Seeding is shuffle/regenerate plus lock.
- Round-robin bracket generation. The create wizard shows the option as disabled with a “v2” note; v1 is single elimination only.
- Export, print layouts, or offline PWA installation.

## Decisions

### 1. Shared demo engine

Introduce one normalized, versioned store and a small action layer. Pages render from selectors; they never write to `localStorage` directly.

New modules:

| Module | Responsibility |
|---|---|
| `assets/js/store.js` | Schema version, seed selection, get/save, named actions, selectors, reset, `hf:store-changed` event, storage-error recovery. |
| `assets/js/seed.js` | The demo dataset for the three seeded tournaments, users, teams, players, matches, announcements, and audit seed. |
| `assets/js/ui.js` | Toasts, dialogs, formatters, status chips, empty states, validation helpers, focus management, reduced-motion guard. |
| `assets/js/shell.js` | Desktop rail, topbar, mobile bottom nav, demo menu, route metadata, active states. Replaces `navigation.js` and `role-switcher.js`. |
| `assets/js/pages/*.js` | One renderer per page module, registered by the page’s `data-page` attribute. |
| `assets/vendor/` | Vendored Bootstrap 5.3 CSS/JS, Bootstrap Icons, and self-hosted Barlow Condensed + Inter WOFF2 files. |

Mock data is no longer read from `mock-data.js`; that file is removed after migration. Existing routes and URLs stay valid.

### 2. Data model

```js
{
  schemaVersion: 2,
  seedVersion: '2026-10-30',
  currentUserId: 'user-organizer',
  role: 'organizer',
  users: [{
    id, name, email, role, organization, teamId
  }],
  tournaments: [{
    id, name, division,
    format: 'single-elimination',
    slots, status, startDate, endDate, venue,
    courts: [{ id, name }],
    slotMinutes, restMinutes, fee, description,
    registrationOpen, seedLocked
  }],
  teams: [{
    id, tournamentId, name, shortName, coach, city, avatarColor,
    status: 'pending' | 'approved' | 'rejected',
    rejectionReason, submittedAt,
    payment: { status, method, ref, amount, verifiedAt },
    eligibility: { documentsComplete, consentComplete, verifiedAt },
    players: [{
      id, name, number, position, age, guardianConsent, documentStatus
    }]
  }],
  matches: [{
    id, tournamentId, round, roundLabel, bracketSlot,
    team1Id, team2Id, score1, score2, winnerId,
    status: 'scheduled' | 'final' | 'forfeit',
    courtId, scheduledAt, nextMatchId, nextSlot,
    boxScore: { team1: [], team2: [] },
    correction: { reason, previousScore1, previousScore2, correctedAt, correctedBy }
  }],
  announcements: [{
    id, tournamentId, title, body, category, audience,
    pinned, author, createdAt
  }],
  audit: [{
    id, at, actorId, actorRole, action,
    entityType, entityId, summary, changes
  }]
}
```

Rules:

- Teams belong to exactly one tournament. Players are nested in their team.
- Standings, records, KPIs, bracket progress, and payment totals are **computed** from matches and teams. They are never stored twice.
- IDs are generated from a stable prefix plus a monotonic counter, not array length.
- Actions append audit entries with actor, entity, summary, and old/new values where relevant.
- A schema-version mismatch or a corrupt store reseeds automatically and shows a warning toast.
- Multi-tab changes are detected through the `storage` event and re-rendered.

### 3. Actions

| Action | Behaviour |
|---|---|
| `createTournament(input)` | Validates name, dates, slots, courts, fee, and format. Adds the tournament, writes audit, returns the new ID. |
| `registerTeam(tournamentId, input)` | Validates registration openness, duplicate team name, roster size, jersey uniqueness, age division, consent, documents, and payment reference. Creates a pending team. |
| `reviewTeam(teamId, decision, reason)` | Approves or rejects. Rejection requires a reason. Updates the coach’s status and the organizer queue. |
| `verifyPayment(teamId, payment)` | Validates method, reference, and amount. Updates totals and audit. |
| `generateBracket(tournamentId)` | Requires enough approved teams. Seeds teams, inserts byes, creates matches and `nextMatchId` links, and locks seeds. |
| `generateSchedule(tournamentId, options)` | Assigns courts and time slots, rejects court or team double-booking and rest violations, and creates scheduled matches. |
| `saveScore(matchId, score1, score2, options)` | Rejects ties in elimination matches, accepts a forfeit, sets the winner, advances the winner into `nextMatchId`, updates box-score availability, and writes audit. |
| `correctScore(matchId, score1, score2, reason)` | Requires a reason, records previous values, re-propagates the winner and downstream bracket state. |
| `publishAnnouncement(input)` | Validates title, body, category, and audience. Adds the item, updates the notification dot, and writes audit. |
| `resetDemo()` | Confirms, reseeds the store, and returns to the dashboard. |

A score change must update, in one action: the match card, the next-round slot, championship state, standings, team records, recent results, and the audit trail.

### 4. Roles and permissions

Four selectable roles:

| Role | Can |
|---|---|
| **Organizer** | Everything: tournaments, approvals, payments, bracket, schedule, scores, announcements, audit. |
| **Scorekeeper** | Enter final scores, correct scores with a reason, view game stats. No approvals, payments, tournament setup, or roster edits. |
| **Coach** | Register a team, manage own roster and eligibility, submit a payment reference, view own status and schedule. No score entry or organizer actions. |
| **Fan** | Read-only public bracket, schedule, results, standings, and announcements. No payment or eligibility details. |

Role restrictions are enforced in renderers and actions, not only by hiding elements with CSS. A restricted route redirects to the dashboard with an explanatory toast.

### 5. Visual system

**Concept:** the courtside scorebook meets a broadcast control room. Grassroots basketball energy, organized like an operations desk.

**Type**

- Barlow Condensed for display, section labels, and numerals; uppercase, tight tracking, tabular scores.
- Inter for body, forms, and tables.
- Self-hosted WOFF2 with system fallbacks.

**Palette**

| Token | Light | Dark |
|---|---|---|
| Ground | `#f2f0ea` | `#080d17` |
| Surface | `#fffdf8` | `#101827` |
| Elevated surface | `#ffffff` | `#162033` |
| Ink | `#0b1220` | `#f3f5f9` |
| Muted ink | `#5b6472` | `#9aa6b8` |
| Primary | `#ea580c` | `#fb7a1e` |
| Primary pressed | `#c2410c` | `#ea580c` |
| Gold | `#e0a100` | `#f5b301` |
| Alert / forfeit | `#d61f3f` | `#ff5c7a` |
| Final / success | `#0e7a5f` | `#34d399` |
| Pending / warning | `#b45309` | `#fbbf24` |
| Upcoming / slate | `#475569` | `#94a3b8` |

Status colors are semantic, always paired with a label or icon, and never used decoratively. Orange is reserved for primary actions, active navigation, and the current selection. Gold is reserved for trophies, champions, and the qualification line. Alert red marks forfeits, corrections, and destructive controls; the prototype does not track live game clocks, so it does not claim a “live” state. Links use ink with an orange underline on hover instead of orange text everywhere.

**Surfaces and shape**

- 14px cards, 10px controls, 999px only for chips and pills.
- 1px ink-tinted borders; strong 2px borders on scoreboard modules.
- No gradient-everywhere treatment. Primary buttons are flat orange with a 2px darker bottom edge; hover lifts 1px.
- Subtle court-line and halftone motifs may appear in page headers, never behind dense text.

**Shell**

- Desktop (≥992px): dark **press-box rail** with brand, primary navigation, organizer tools, Demo menu, and user. Public/auth pages use a slim topbar instead.
- Mobile: five bottom tabs; the fifth is role-aware (Profile for organizer, scorekeeper, and fan; My Team for coach). Active state uses a scoreboard indicator.
- Page headers become a **matchday strip** with tournament context, date, status, and the primary action.
- The floating role pill is removed. Demo controls live in the Demo menu.

**Components**

- Scoreboard KPI tiles with large condensed numerals and one-line labels.
- Match cards with a 4px status bar, seed number, monogram shield, condensed score, and final/forfeit/upcoming chip.
- Bracket: desktop tree with ink connectors and an orange active path; mobile round pager (R16 → QF → SF → Final) with a progress strip.
- Schedule: month grid on desktop, horizontal week strip on mobile; both drive the match list. Day cells show event counts, not just dots.
- Standings: sticky team column, qualification line after the playoff cut, mobile card list.
- Score console: final-score inputs, +1/+2/+3, undo, tie validation, forfeit toggle, correction reason, and a finalize confirmation.
- Empty states, inline validation, toasts, and dialogs share one language.

**Motion**

- 160–240ms ease-out. Score changes pop, bracket paths draw on advance, toasts slide, content staggers 40ms.
- No confetti. The champion card gets one restrained gold glow.
- `prefers-reduced-motion: reduce` disables all of it.

### 6. Screens

Existing routes stay valid. Two routes are added: `teams/register.html` and `profile/audit.html`.

| Surface | Required interactions |
|---|---|
| **Auth** | Splash → login/register. Quick-fill accounts for organizer, scorekeeper, coach, fan. Login sets the role and lands on the correct dashboard. Field validation and password visibility toggle. |
| **Dashboard** | Role-aware matchday strip, computed KPIs, next-action queue, active tournament progress, recent results, standings snapshot, announcements. Every tile links to its filtered destination. |
| **Tournaments index** | Search, status and division filters, empty state, organizer create CTA, coach register CTA on open tournaments. |
| **Tournament show** | Hero, dates, venue, courts, fee, teams, bracket/schedule shortcuts, announcements, organizer controls. Coach can register. |
| **Tournament create** | Three-step wizard: Basics → Format & Courts → Review. Repeatable courts, slot length, rest, fee, description. Publish redirects to the new show page. |
| **Submissions** | Queue counts, search, status filter. Detail drawer with roster, eligibility checklist, payment reference. Approve or reject with reason. Coach status updates. |
| **Payments** | Computed collected/uncollected totals, filters, and row detail. Verify modal with method, reference, amount, and status. Audit entry on save. |
| **Teams index** | Search and filter by division/status. Cards show record, coach, city, and eligibility. |
| **Team show** | Hero, roster, results, eligibility, payment status, bracket position. Public view hides private details. |
| **Coach registration** | `teams/register.html?tournament=…`: team info → roster → eligibility/payment → review. Submits as pending. |
| **My Team** | Editable roster, add/edit player, consent and document status, payment reference, registration status timeline, next game. Roster locks after verification. |
| **Bracket** | Empty state with seed preview and generate action; seeded tree; byes; winner slots; desktop hover path; mobile round pager; match dialog; correct-score flow; champion state. |
| **Schedule** | Month navigation, day selection, wired court filters, match list, generate-schedule preview with court/rest conflict checks, box-score links. Generation is disabled with an explanation when the tournament has no bracket. |
| **Matches** | Filterable matchup list grouped by day (date, court, status). |
| **Standings** | Computed table or mobile cards, search, filters, qualification line, tiebreaker note, team links. |
| **Game stats** | Official scoreboard, team filter, player search, sortable box score, score correction for organizer/scorekeeper, empty state before a final. |
| **Announcements** | Category-filtered feed, pinned item, audience labels, create form with publish + pin. |
| **Profile** | User, role, organization/team, theme preference, demo reset, link to audit. |
| **Audit** | Reverse-chronological timeline with actor, role, action, entity, summary, and old → new values for corrections. Filters by entity type and actor. |
| **Public/fan** | Read-only bracket, schedule, results, standings, and announcements. No payment or eligibility data. Phone layout is a first-class target. |

### 7. Seed data

Three tournaments, all in Philippine grassroots basketball:

1. **HoopFlow Summer Cup** — U15, Active, 16 registered teams. One team withdrew before seeding and is archived as rejected; 15 eligible teams produce one bye in a 16-slot bracket. 4 courts, 30-minute slots, 15-minute rest, one forfeit recorded 20–0 per FIBA rules, one corrected score. Bracket is partially complete: Round of 16 finished, two quarterfinals final, two quarterfinals scheduled.
2. **Pasig Youth Hoopfest** — U17, Registration Open, 8 of 16 slots claimed (6 approved, 2 pending), 3 courts. Used to demonstrate coach registration and organizer review without disturbing the active bracket.
3. **CDO Grassroots Classic** — U14, Draft/Upcoming, 8 approved teams, 2 courts, no bracket or schedule. Used to demonstrate live bracket and schedule generation.

Seed users:

- Organizer: `organizer@hoopflow.ph`
- Scorekeeper: `scorekeeper@hoopflow.ph`
- Coach: `coach.marcus@hoopflow.ph`, attached to San Antonio Vipers
- Fan: `fan.juan@hoopflow.ph`

The coach’s team is approved and paid in the active cup and has not yet registered for the open cup, so the registration journey can be shown live. The open cup review queue already contains two pending teams from other clubs, so the organizer queue is never empty.

### 8. Demo path

The recommended 10-step run:

1. Organizer login → dashboard KPIs.
2. Approve a pending team → KPI decreases.
3. Verify a GCash payment → collected total rises.
4. Open the bracket → enter a quarterfinal score → winner advances → standings update.
5. Show schedule filters → select a court/day → open a box score.
6. Switch to coach → register for the open tournament → add players → submit.
7. Switch to organizer → approve that registration.
8. Switch to scorekeeper → score entry allowed, approvals denied.
9. Switch to fan → phone-sized public view.
10. Show audit log and reset demo.

Generation is demonstrated separately on CDO Grassroots Classic (generate bracket, then schedule) so the active bracket is never disturbed.

### 9. Error, empty, and loading states

- **Empty:** no tournaments, no teams, no matches, no announcements, no pending submissions, no audit entries, no box score yet.
- **Validation:** required fields, date order, slot count, duplicate team name, roster limits, duplicate jersey number, age-division mismatch, missing consent, missing document, malformed payment reference, tie score, missing correction reason.
- **Action failures:** insufficient approved teams, registration closed, court double-booking, rest violation, restricted role, stale schema, corrupt store.
- **Loading:** bracket and schedule generation show a short progress checklist while the real computation runs. No fake network delay elsewhere.

All errors are inline where a field is at fault and in a toast where an action fails. `alert()` is removed from the prototype.

### 10. Accessibility

- 44px minimum touch targets and 8px minimum spacing between adjacent targets.
- Visible `:focus-visible` rings in both themes.
- Semantic headings, landmarks, table scopes, and `aria-label` on icon-only controls.
- Dialogs trap focus, close on Escape, and return focus to the trigger.
- Status is never conveyed by color alone.
- AA contrast for text and controls in both themes.
- `prefers-reduced-motion` removes all non-essential animation.

### 11. Reliability and offline demo

- Framework-free, build-free static pages served by `php -S`.
- Bootstrap CSS/JS, Bootstrap Icons, and fonts vendored under `prototype/assets/vendor/`. No network dependency during the demo.
- Demo menu exposes role, theme, seed version, and reset.
- Reset confirms, reseeds, and returns to the dashboard.
- Every page tolerates an empty or partially completed tournament.
- No page assumes a particular seed order or array index.

### 12. Verification

- `data-testid` on every demo-critical control.
- Automated browser run of the 10-step path at 1366×768 and ~390×844, starting from a fresh and a mutated store.
- Assertions: KPI values, approval status, payment totals, bracket advancement, standings order, registration status, role denial, audit entries, reset state.
- No console errors on any route.
- Lighthouse accessibility ≥ 95 on dashboard, bracket, schedule, standings, and login.
- Manual rehearsal with the browser at demo resolution, including a mid-demo refresh.

### 13. Delivery phases

Each phase ends with a walkable demo.

1. **Foundation:** store, seed, shell, tokens, auth, dashboard.
2. **Organizer desk:** tournaments index/show/create, submissions, payments, teams.
3. **Competition:** bracket, score console, schedule, standings, game stats, scorekeeper.
4. **People and public:** coach registration, My Team, announcements, profile/audit, fan mobile pass.
5. **Polish and rehearsal:** responsive fixes, motion, accessibility, full verification, README update.

### 14. Files

Create:

- `prototype/assets/js/store.js`
- `prototype/assets/js/seed.js`
- `prototype/assets/js/ui.js`
- `prototype/assets/js/shell.js`
- `prototype/assets/js/pages/*.js`
- `prototype/teams/register.html`
- `prototype/profile/audit.html`
- `prototype/assets/vendor/*`
- `prototype/assets/fonts/*`

Rewrite or update:

- `prototype/assets/css/hoopflow.css`
- All existing HTML pages: shared shell, vendored assets, `data-page`, `data-testid`, role gates.
- `prototype/README.md`

Remove after migration:

- `prototype/assets/js/mock-data.js`
- `prototype/assets/js/navigation.js`
- `prototype/assets/js/role-switcher.js`

### 15. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Scope is large across 20+ pages | Shared engine and components first; phases keep every checkpoint demoable. |
| Seed data contradicts the written demo script | Seed is aligned to the script and documented, including the 16-registered → 15-eligible bye explanation. |
| Offline venue | Vendor all assets; verify with network disabled. |
| State drift during a live demo | Single action layer, computed selectors, audit, reset. |
| Overly decorative “broadcast” styling harms dense tables | Display type limited to headings and numerals; body and tables stay Inter; density checked at 1366 and 390. |
| Prototype shortcuts leak into `src/` | `prototype/README.md` keeps the shortcut list current; the spec’s non-goals are explicit. |

## Acceptance

The enhancement is done when:

- All 10 demo steps pass in the browser on a fresh store and after a refresh.
- Every visible control has a real outcome or is removed.
- No `alert()` remains.
- The four roles see the correct actions, and restricted routes deny access.
- A score change advances the bracket and updates standings without a reload.
- Reset restores the exact seeded demo state.
- No console errors, and the accessibility check passes on the five named screens.
- The prototype runs offline from `php -S`.
