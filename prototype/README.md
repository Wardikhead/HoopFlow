# HoopFlow courtside demo

A build-free, click-through prototype of the HoopFlow tournament operations product. This is a demo surface, not the Laravel application in [`../src`](../src).

The prototype runs offline, keeps all state in one browser, and covers the full ten-step organizer, scorekeeper, coach, and fan story.

## Quick start

```powershell
# from the repository root
php -S 127.0.0.1:8001 -t prototype
```

Open `http://127.0.0.1:8001`. The root route redirects to the splash screen.

Do not open the HTML files with `file://`; the ES modules and local assets need an HTTP origin.

## Demo accounts

| Role | Email | Scope |
|---|---|---|
| Organizer | `organizer@hoopflow.ph` | Everything: approvals, payments, tournaments, brackets, schedules, scores, announcements, audit |
| Coach | `coach.marcus@hoopflow.ph` | San Antonio Vipers, roster, eligibility, payment reference |
| Fan | `fan.juan@hoopflow.ph` | Read-only public bracket, schedule, results, standings, announcements |

The login page has quick-fill buttons for all four roles. Passwords are not checked in this prototype.

## Ten-step demo

| # | Action | Expected result |
|---|---|---|
| 1 | Sign in as organizer | Dashboard shows 3 tournaments, 2 pending teams, ₱3,000 uncollected, and 15 approved teams in the active cup |
| 2 | Submissions → approve Bagong Ilog Bears (`team-open-7`) | Pending teams drops to 1; the team becomes approved and an audit entry is written |
| 3 | Payments → verify Maybunga Titans (`team-6`) with GCash | Collected rises from ₱19,500 to ₱21,000; outstanding drops to ₱1,500 |
| 4 | Bracket → open `qf-3` → enter 71–65 for Kapitolyo Knights | Knights advance into semifinal slot 1; the bracket, standings, and audit update together |
| 5 | Schedule → pick a court and day → open a box score | Filters drive the match list; the final opens the official box score |
| 6 | Switch to coach → register for Pasig Youth Hoopfest → Fill demo data → submit | San Antonio Vipers U17 is created as pending with 8 verified players and `GCASH-9090909` |
| 7 | Switch to organizer → approve San Antonio Vipers U17 | The coach's registration status becomes approved; the queue count falls |
| 8 | Switch to scorekeeper → open the bracket → open a score console → visit Submissions | Score entry is available; the restricted route redirects to the dashboard with a toast |
| 9 | Switch to fan → resize to 390×844 | Public dashboard, bracket pager, week strip, standings cards, and announcements; no private payment or eligibility data |
| 10 | Profile → Audit log → Reset demo | New audit entries appear newest first; reset restores 8 seeded entries, 2 pending teams, ₱19,500 collected |

Bracket and schedule generation are demonstrated separately on **CDO Grassroots Classic** (`tournament-3`): generate the 8-team bracket, then generate the schedule. The active cup is never disturbed.

## Roles and permissions

| Role | Allowed | Blocked |
|---|---|---|
| Organizer | All actions | — |
| Coach | `team.register`, `team.edit`, `payment.submit`, `team.view.own` | Score entry, approvals, other teams' private data |
| Fan | `public.view` | Every write action and every private detail |

Restricted routes use `guardRole` and redirect to the dashboard with an explanation. Permissions are enforced in the store, not only by hiding buttons.

## Demo controls

The **Demo** menu in the press-box rail or topbar contains:

- Role switch for organizer, scorekeeper, coach, and fan.
- Light and dark theme.
- Seed version.
- Reset demo.

The profile page repeats the theme and reset controls and links to the audit log for organizers. Reset requires confirmation and restores the exact seeded state.

Theme is stored in `localStorage.hoopflow_theme`. The store is `localStorage.hoopflow_db`.

## Seeded data

Three Philippine grassroots tournaments:

| Tournament | ID | Division | Status | Story |
|---|---|---|---|---|
| HoopFlow Summer Cup | `tournament-1` | U15 | Active | 16 registered, 15 approved, 1 rejected before seeding; 4 courts; one bye, one forfeit, one corrected score; R16 complete, two quarterfinals final, two scheduled |
| Pasig Youth Hoopfest | `tournament-2` | U17 | Registration open | 8 of 16 slots claimed: 6 approved and 2 pending; no bracket; used for live registration and review |
| CDO Grassroots Classic | `tournament-3` | U14 | Draft/upcoming | 8 approved teams, 2 courts, no bracket or schedule; used for live generation |

The coach owns San Antonio Vipers (`team-1`) in the active cup and has not registered for the open cup, so the registration journey can be shown live.

## Routes

| Surface | Route |
|---|---|
| Splash / login / register | `auth/splash.html`, `auth/login.html`, `auth/register.html` |
| Dashboard | `dashboard/index.html` |
| Tournaments | `tournaments/index.html`, `show.html?id=…`, `create.html` |
| Submissions / payments | `tournaments/submissions.html`, `tournaments/payments.html` |
| Teams | `teams/index.html`, `show.html?id=…` |
| Coach registration / My Team | `teams/register.html?tournament=…`, `teams/my-team.html?team=…` |
| Bracket | `brackets/view.html?id=…&match=…` |
| Schedule / matchups | `schedule/index.html?id=…`, `schedule/matches.html?id=…` |
| Standings | `standings/table.html?id=…` |
| Game stats | `games/stats.html?match=…` |
| Announcements | `announcements/index.html`, `announcements/create.html?id=…` |
| Profile / audit | `profile/index.html`, `profile/audit.html` |

Query conventions: `id` scopes a tournament page, `match` opens a game, and `team` / `tournament` scope people flows.

## Architecture

```text
prototype/
  assets/css/hoopflow.css       Design tokens, components, themes, responsive rules
  assets/js/app.js              Boots the store, UI, shell, theme, and page module
  assets/js/seed.js             Deterministic schema-v2 demo data
  assets/js/store.js            Versioned localStorage store, permissions, actions, selectors, audit
  assets/js/ui.js               Formatting, toasts, dialogs, focus, field errors
  assets/js/components.js       Shared cards, KPI tiles, headers, bracket matches, standings
  assets/js/shell.js            Press-box rail, topbar, mobile tabs, demo menu
  assets/js/routes.js           Route map and query builder
  assets/js/guards.js           Role guard with redirect and toast
  assets/js/options.js          Divisions, payment methods, announcement options
  assets/js/stats.js            Box-score row, sort, and total helpers
  assets/js/score-dialogs.js    Shared score console and correction dialog
  assets/js/pages/              One renderer per data-page
  assets/vendor/                Vendored Bootstrap, Bootstrap Icons, and fonts
  tests/                        Node test runner suites
```

Pages never touch `localStorage` directly. They render from store selectors and call named actions. A score change updates the match, the next-round slot, standings, team records, recent results, and audit through one action.

## Tests

```powershell
cd prototype
node --test tests
```

The suite covers the seed fixture, store schema, permissions, every action, bracket propagation, schedule conflicts, scoring, corrections, and the ten-step demo sequence.

## Offline demo

Bootstrap CSS/JS, Bootstrap Icons, and the Barlow Condensed + Inter font files are vendored under `prototype/assets/vendor/`. No page loads a CDN asset.

If the vendor files are missing, rebuild them from the repository root:

```powershell
./tools/vendor-prototype-assets.ps1
```

## Prototype-only shortcuts

These are deliberate and must not be carried into `src/`:

- Passwords are not checked. Quick-fill sets the demo role and redirects.
- All data is browser localStorage. There is no server, database, authentication, file upload, or real payment.
- The profile is read-only; name and organization editing are out of scope.
- The score console records final scores. It does not track a live clock, fouls, timeouts, or play-by-play.
- Single elimination only. The round-robin option is disabled with a v2 note.
- Fan is a read-only public view. Pending and rejected registrations, payments, consent, and documents stay private.
- Tabs do not live-sync. A storage event reloads the page so a second tab cannot silently drift.
- Generated IDs include timestamps. Reset between rehearsal runs.
- Supported demo sizes are 1366×768 and ~390×844.

## Troubleshooting

- **Blank page:** serve over HTTP and check the browser console. ES modules do not load from `file://`.
- **Stale demo state:** Demo → Reset, or remove `localStorage.hoopflow_db`.
- **Wrong role or theme:** reset the store, or clear `hoopflow_theme` and reload.
- **PHP unavailable:** any static server works, for example `python -m http.server 8001 --directory prototype`.
- **Offline check:** load the app once over localhost, switch the network off, and walk the ten steps. There should be no failed requests.

## Design source

- Spec: [`../docs/superpowers/specs/2026-10-30-prototype-ui-interactivity-design.md`](../docs/superpowers/specs/2026-10-30-prototype-ui-interactivity-design.md)
- Plan: [`../docs/superpowers/plans/2026-10-30-prototype-ui-interactivity.md`](../docs/superpowers/plans/2026-10-30-prototype-ui-interactivity.md)