# HoopFlow Landing Page Design

Date: 2026-11-11
Status: Approved (pending user review of written spec)

## Goal

Replace the walking-skeleton home page with a public landing page that sells HoopFlow to barangay
and grassroots youth basketball organizers, coaches, and parents, while letting visitors explore a
real, read-only slice of the product: bracket, schedule, and standings.

The page must feel like a basketball tournament poster, not a generic SaaS template. It should be
warm, local, and confident, with purposeful motion and no fabricated social proof.

## Non-goals

- No public demo routes, controllers, or seeded database records for the demo.
- No authentication changes and no write actions from the landing page.
- No new client-side framework, no Bootstrap JavaScript, no Tailwind.
- No pricing page, testimonials, customer counts, or invented performance claims.
- No generated photography or stock imagery.

## Audience and narrative

Primary audience: barangay and youth league organizers who still run tournaments through
spreadsheets, group chats, and paper consent forms.

Secondary audience: coaches registering teams, and parents/fans who need the bracket and schedule
on a phone.

Narrative arc: the product is already good at the unglamorous local workflow. The landing page
proves it by letting people touch a real interface immediately, then explains the flow and the
roles behind it.

## Page structure

### 1. Landing header

- Sticky, full-width. Starts transparent over the paper background; after scrolling it gains a
  translucent paper blur, a fine bottom border, and a slight shadow.
- Left: brand lockup linking to `route('home')`.
- Center/right anchors: `#features` (Feature lab), `#flow` (Matchday), `#roles`, `#eligibility`.
- Right actions: theme toggle, `Sign in` (ghost), `Create account` (primary).
- Authenticated visitors see `Dashboard` in place of the sign-in/create pair.
- Mobile: menu button toggles a full-width panel with the same anchors and actions. Closes on
  anchor click, Escape, or outside click.
- Skip link to `#main` before the header.

### 2. Hero

Layout: two columns on desktop, stacked on mobile. Left is editorial copy; right is a composed
scoreboard scene over CSS/SVG court artwork.

Copy:

- Eyebrow: `Barangay & youth basketball`
- Headline: `Run the league, not the spreadsheet.`
- Supporting paragraph: `Registration, eligibility, brackets, schedules, and standings — one clean
  flow, built for the covered court.`
- Primary CTA: `Create your account` → `route('register')`
- Secondary CTA: `Explore the demo` → `#features`
- Micro-line: `Built for organizers, coaches, and the parents on the bleachers.`
- Scroll cue: `See it in motion`

Visual scene:

- Large floating live-game card: `San Isidro Cup 2026`, `U16 Boys · Quarterfinal · Court B`,
  `Q4 · 02:14`, `Poblacion Panthers 66`, `Riverside Ravens 50`, with a pulsing `LIVE` chip and
  `Game 4 of 7` footer.
- Smaller overlapping card: `Next up · Semifinal`, `Sat, Nov 21 · 1:00 PM`, `Vipers vs Mavericks`.
- A rotated stamp or ticket reading `MATCHDAY` / `10 teams · 2 courts` (or equivalent bracket note).
- Background: warm paper, oversized halftone basketball, thin court lines, a soft orange glow, and
  a subtle grain overlay. All decorative SVG is `aria-hidden`.

Entrance motion: eyebrow, headline lines, paragraph, CTAs, and scoreboard cards stagger in on load
using CSS. No layout shift after fonts load.

### 3. Feature lab (`#features`)

A dark, scoreboard-style section that is the centerpiece of the page.

Header:

- Eyebrow: `The feature lab · read-only preview`
- Heading: `Poke around. Nothing breaks.`
- Sub: `The real interface, loaded with a sample barangay league. Switch between the bracket,
  schedule, and standings. Editing stays off.`
- Badge: `Preview only · changes disabled`

Desktop layout: sticky left rail with tab list and a short description per tab; right stage holds
the active panel. Mobile: horizontal tab strip above the stage.

Tabs:

1. **Bracket** — `Every path to the final, settled.`
2. **Schedule** — `Courts, rest, and tip-off times in one view.`
3. **Standings** — `The cut line, without the manual math.`

All panels are server-rendered; tab switching only toggles visibility. The tablist uses
`role="tablist"`, arrow-key navigation, `aria-selected`, and `aria-controls`.

#### Bracket panel

- 8-team single-elimination bracket: Quarterfinals (4), Semifinals (2), Final (1).
- Quarterfinal results:
  - Vipers 58–44 Ninos (final)
  - Lions 49–52 Mavericks (final)
  - Saints 61–57 Bolts (final)
  - Panthers vs Ravens — live, 66–50, Q4 02:14
- Semifinals:
  - Vipers vs Mavericks — Sat Nov 21, 1:00 PM, Court A, scheduled
  - Saints vs winner of Panthers/Ravens — Sat Nov 21, 2:30 PM, Court A, TBD
- Final: Sun Nov 22, 4:00 PM, Court A, TBD.
- Winners are visually weighted; losers are muted. Live match gets an orange pulse and `LIVE` chip.
- Connector lines animate in when the panel enters the viewport.
- Hover/focus on a matchup lifts it and dims sibling matchups.
- On mobile, the bracket becomes a horizontal snap-scroll board; each round column keeps its
  heading.

#### Schedule panel

- Day chips: `Sat, Nov 14 · Quarterfinals`, `Sat, Nov 21 · Semifinals`, `Sun, Nov 22 · Final`.
- Court filter: `All courts`, `Court A`, `Court B`.
- Match cards show time, court, round, both teams, status, and score when available.
- Filtering is instant and client-side. Empty state is explicit if a combination has no games
  (e.g. Final + Court B).
- Day 1 games: 8:00 AM QF1 Court A; 9:30 AM QF2 Court A; 11:00 AM QF3 Court B; 1:00 PM QF4 Court B.
- Day 2 games: 1:00 PM SF1 Court A; 2:30 PM SF2 Court A.
- Day 3 game: 4:00 PM Final Court A.

#### Standings panel

- 10 teams, 4 games played each.
- Columns: rank, team, W, L, PF, PA, +/-, seed.
- Top 8 are marked qualified; a gold cut line follows rank 8.
- Caption: `Top 8 advance to the quarterfinals.`
- Rows highlight on hover/focus; qualification rows use a warm gold tint.
- Count-up animation on first reveal; reduced-motion users see final values immediately.
- Desktop: table. Mobile: compact stacked cards, one per team, preserving rank, record, diff, and
  qualified state.
- Internal vertical scroll is allowed if the stage is shorter than the table; the stage must not
  create page-level horizontal overflow.

Panel footer: `This is a read-only sample. Sign in to run your own league.` with links to login and
register.

### 4. Matchday flow (`#flow`)

- Eyebrow: `From sign-up to final buzzer`
- Heading: `One flow, from registration to the final buzzer.`
- Five steps on a dashed court-line timeline:
  1. `Set the stage` — Create the tournament, divisions, venue, courts, and entry fee.
  2. `Register & vet` — Rosters, birthdates, guardian consent, documents, and payment references.
  3. `Seed the bracket` — Approved teams are seeded and published, with byes handled.
  4. `Schedule the floor` — Courts, rest periods, and feeder matches resolve into one schedule.
  5. `Run the night` — Final scores advance winners, update standings, and notify the bleachers.
- Each step has a number, icon, title, short copy, and a small "what you get" tag such as
  `Auto-seeded`, `Eligibility queue`, `Conflict checks`, `Live standings`.
- The dashed line draws on scroll; each step reveals in sequence. On mobile the timeline becomes
  vertical.

### 5. Built for every role (`#roles`)

- Eyebrow: `Built for the whole court`
- Heading: `Everyone sees the game they need.`
- Three cards with mini UI vignettes, not just icons:
  - **Organizer** — `See the whole floor.` Vignette: approval queue rows
    (`Vipers · Roster verified`, `Saints · Consent pending`, `Bolts · Payment received`) and a
    compact `Open review` action. Support copy: eligibility, payments, brackets, schedule, audit.
  - **Coach** — `Know before tip-off.` Vignette: `Roster 12/12`, `Consent 11/12`, progress bar,
    `Next: Sat 1:00 PM vs Vipers`. Support copy: register a team, track requirements, follow
    results.
  - **Fan / Parent** — `Follow from the bleachers.` Vignette: phone-shaped card with live score
    `Q4 · 66–50` and `Semifinal · Sat 1:00 PM`. Support copy: bracket, schedule, standings, and
    announcements on a phone.
- Cards lift slightly on hover; the vignette inside gets a subtle accent glow.

### 6. Eligibility + payments spotlight (`#eligibility`)

Split section on paper background.

Copy:

- Eyebrow: `The unglamorous part, handled`
- Heading: `The paperwork is part of the tournament.`
- Paragraph: `Age and residency checks, guardian consent, and cash or GCash payments are
  first-class steps in HoopFlow. Review them in one queue, with a reason for every decision.`
- Checklist: `Age & residency checks`, `Guardian consent`, `Cash & GCash references`,
  `A reason for every decision`.

Visual composition:

- Eligibility review card titled `Roster check · San Isidro Vipers · U16`:
  - `J. Santos · 15 · Verified` (green)
  - `M. Reyes · 15 · Consent pending` (amber)
  - `A. Cruz · 16 · Document missing` (red)
  - `K. Dela Cruz · 14 · Verified` (green)
  - Footer: `2 of 4 cleared` with a progress bar.
- Payment slip card:
  - `Entry fee · ₱1,500`
  - `GCash · Ref 0928 441 773 · Received`
  - `Cash · ₱500 · Recorded by organizer`
- Cards overlap slightly and tilt on desktop; stack cleanly on mobile.
- Status chips must not rely on color alone: each includes an icon and text.

### 7. Matchday wire

Full-width announcement band between the spotlight and closing CTA.

- Label: `Matchday wire`
- Slow horizontal marquee, duplicated list marked `aria-hidden`, pauses on hover and focus-within.
- Items:
  - `QF4 is live on Court B — Panthers lead Ravens, 66–50 in the fourth.`
  - `Semifinal tip-off moved to 1:00 PM. Arrive 30 minutes early for warm-ups.`
  - `U16 roster lock is Friday, 6:00 PM. No additions after the first semifinal.`
  - `Vipers advance after a 58–44 win over the Ninos.`
  - `Entry-fee payments close before the semifinals.`
  - `Scorekeepers: quarter stats are now recorded per player.`
- Each item is separated by a small basketball/whistle glyph. Reduced motion stops the marquee and
  wraps the items into a readable list.

### 8. Closing CTA + footer

- Dark closing panel with court-line artwork.
- Heading: `Your league deserves a better scoreboard.`
- Sub: `Set up the tournament once. Let matchday flow.`
- Primary: `Create your account` → register.
- Secondary: `Explore the demo` → `#features`.
- Footer:
  - Brand + tagline `Let the games run themselves.`
  - Product column: Features, Bracket, Schedule, Standings. The last three point at the lab and
    activate the matching tab through a `data-demo-target` hook; without JavaScript they still land
    on the lab.
  - Account column: Sign in, Create account.
  - Note: `Built for barangay and grassroots youth basketball.`
  - Copyright: `© 2026 HoopFlow`.
- No fake social links, addresses, or contact details.

## Showcase data

A curated fictional league keeps the page complete on a fresh install.

Tournament: `San Isidro Cup 2026`, division `U16 Boys`, venue `San Isidro Covered Court`,
dates `Nov 14–22, 2026`, format `Single elimination · Top 8 of 10`.

Teams (id, name, short label, color):

| # | Team | Short | Color |
|---|---|---|---|
| 1 | San Isidro Vipers | SIV | `#0f766e` |
| 2 | Poblacion Panthers | POB | `#1d4ed8` |
| 3 | Sta. Cruz Saints | SCS | `#b91c1c` |
| 4 | Lakeside Lions | LAK | `#b45309` |
| 5 | Mabini Mavericks | MAB | `#0e7490` |
| 6 | Bagong Silang Bolts | BSB | `#7c3aed` |
| 7 | Riverside Ravens | RIV | `#4d7c0f` |
| 8 | Northside Ninos | NOR | `#be123c` |
| 9 | Hilltop Hawks | HIL | `#475569` |
| 10 | Bayanihan Ballers | BAY | `#a16207` |

Standings after four games each:

| Rank | Team | W | L | PF | PA | Diff | Seed | Qualified |
|---|---|---|---|---|---|---|---|---|
| 1 | Vipers | 4 | 0 | 302 | 224 | +78 | 1 | yes |
| 2 | Panthers | 3 | 1 | 288 | 236 | +52 | 2 | yes |
| 3 | Saints | 3 | 1 | 274 | 243 | +31 | 3 | yes |
| 4 | Lions | 3 | 1 | 261 | 243 | +18 | 4 | yes |
| 5 | Mavericks | 2 | 2 | 249 | 247 | +2 | 5 | yes |
| 6 | Bolts | 2 | 2 | 238 | 245 | −7 | 6 | yes |
| 7 | Ravens | 1 | 3 | 231 | 253 | −22 | 7 | yes |
| 8 | Ninos | 1 | 3 | 226 | 261 | −35 | 8 | yes |
| 9 | Hawks | 1 | 3 | 219 | 265 | −46 | — | no |
| 10 | Ballers | 0 | 4 | 204 | 275 | −71 | — | no |

Data invariants: 20 total wins, 20 total losses, point differentials sum to zero.

Bracket uses seeds 1–8: 1v8, 4v5, 3v6, 2v7. The live quarterfinal is 2 Panthers vs 7 Ravens.
Semifinal 1 is 1 Vipers vs 5 Mavericks. Semifinal 2 is 3 Saints vs the QF4 winner.

All names, schools, players, references, and amounts are fictional. No real personal data.

## Motion system

- Hero entrance: staggered fade/rise, 60–90 ms apart; total under 900 ms.
- Scroll reveal: `IntersectionObserver` adds `is-in`; CSS handles the transition. Elements reveal
  once and stay visible.
- Bracket: connectors scale/draw when the bracket panel is first visible; live chip pulses.
- Standings: count-up on first reveal, capped at 1.2 s.
- Ticker: continuous CSS marquee, paused on hover/focus.
- Header: state class after 24 px of scroll.
- Lab: pointer-follow radial glow on fine pointers only; no glow on touch.
- Hover: 2–4 px lift, border color shift, and shadow; no layout shift.
- All motion is inside `@media (prefers-reduced-motion: no-preference)` where practical, and the
  global reduced-motion rule is extended so the ticker stops and content wraps.

## Theme

- The page supports the existing light/dark toggle and persists through `localStorage` key
  `hf-theme`.
- Light: warm paper (`--hf-ground`), ink, orange accents, cream cards.
- Dark: deep warm navy/black paper, cream ink, brighter orange; the lab remains dark in both
  themes with a slightly different surface.
- The hero scoreboard and lab are always dark "arena" surfaces for contrast.
- The theme is applied before paint by the existing inline script pattern to avoid a flash.

## Responsive behavior

- `≥ 1200px`: two-column hero; lab rail + stage; bracket fully visible.
- `992–1199px`: hero still two columns but tighter; lab rail narrows.
- `768–991px`: hero stacks; lab tabs move above the stage; role cards become two columns then one.
- `< 768px`: single column; bracket horizontal snap-scroll; schedule stacked; standings cards;
  header collapses to a menu; no horizontal page overflow.
- All tap targets at least 44×44 px.

## Accessibility

- Semantic landmarks (`header`, `nav`, `main`, `section` with accessible names, `footer`).
- One `h1`; heading order is logical.
- Tabs implement the WAI-ARIA tabs pattern, including arrow keys, Home, and End.
- Filter chips expose pressed state; filtered-out cards are removed with `hidden`.
- Visible focus rings from the existing `:focus-visible` token.
- Decorative artwork and duplicate marquee items are `aria-hidden`.
- Color is never the only status signal; every chip has text (and an icon where useful).
- Contrast targets WCAG AA for text and interactive states.
- Progressive enhancement: the server renders all three demo panels visible and all schedule days
  present. JavaScript hides inactive panels and applies filters after init. Without JavaScript every
  panel, match, and row is still readable, and anchors still work.

## Technical architecture

### Routes and controller

- Replace `Route::view('/', 'pages.home')` with `Route::get('/', LandingController::class)->name('home')`.
- `App\Http\Controllers\LandingController` is invokable, injects `LandingShowcase`, and returns
  `view('pages.home', ['showcase' => $showcase])`.
- No middleware change; the page stays public.

### View layer

- `resources/views/layouts/landing.blade.php` — dedicated public layout: head/theme bootstrap, skip
  link, header, main, footer, and Vite entries. No Livewire scripts.
- `resources/views/pages/home.blade.php` — extends the landing layout and composes partials.
- `resources/views/landing/` partials:
  - `hero.blade.php`
  - `lab.blade.php`
  - `bracket-panel.blade.php`
  - `schedule-panel.blade.php`
  - `standings-panel.blade.php`
  - `flow.blade.php`
  - `roles.blade.php`
  - `eligibility.blade.php`
  - `wire.blade.php`
  - `closing.blade.php`
- Each partial receives only the data it needs via the `$showcase` object.

### Data layer

- `App\Support\LandingShowcase` — one cohesive class returning curated arrays for the tournament,
  teams, bracket rounds, schedule days, standings, announcements, flow steps, roles, and
  eligibility/payment previews.
- No Eloquent models, no database queries, no framework coupling beyond being a plain PHP class.
- Shape is documented with PHPDoc array shapes so Larastan can check the Blade data flow.

### Styles

- `resources/css/_bootstrap.scss` — extracts the existing Bootstrap `@use ... with` configuration
  so both entries share it.
- `resources/css/app.scss` — `@use "bootstrap"; @use "hoopflow";`.
- `resources/css/landing.scss` — `@use "bootstrap"; @use "hoopflow"; @use "landing";`.
- `resources/css/_landing.scss` — all landing-specific styles, scoped under `.hf-lp-*` class names.
- Reuse `var(--hf-*)` tokens and Barlow Condensed/Inter; do not duplicate the design system.
- `vite.config.js` input adds `resources/css/landing.scss` and `resources/js/landing.js`.

### Scripts

- `resources/js/landing.js` — vanilla ES module:
  - mobile menu
  - sticky header state
  - tabs (click + keyboard)
  - schedule filters
  - IntersectionObserver reveals + bracket draw trigger
  - count-up standings
  - pointer glow
  - marquee duplication is done in Blade, not JS, to avoid content flash.
- `resources/js/app.js` remains the theme owner; the landing layout loads it before `landing.js`.
- No Bootstrap JS, no Livewire, no Alpine.

## Testing

Feature test (`tests/Feature/Pages/LandingTest.php`):

- `GET /` returns 200 for guests.
- Shows the headline, feature lab badge, sample tournament/team names, and section anchors.
- Contains links to `route('login')` and `route('register')`.
- Shows `Dashboard` instead of auth CTAs when signed in.
- Does not require authentication.
- Renders key `data-testid` hooks for browser checks:
  `landing-hero`, `landing-lab`, `demo-tab-bracket`, `demo-panel-bracket`,
  `demo-tab-schedule`, `demo-panel-schedule`, `demo-tab-standings`,
  `demo-panel-standings`, `landing-wire`, `landing-cta`.

Unit test (`tests/Unit/Support/LandingShowcaseTest.php`):

- 10 teams with unique ids.
- Bracket has 4 quarterfinals, 2 semifinals, and 1 final.
- Every bracket team id exists in the team list; seeds 1–8 are used once.
- Standings have 10 rows; wins total 20 and losses total 20; diff sums to zero; top 8 flagged.
- All completed matches have scores and a winner; live match has no winner yet.
- Schedule days reference known teams and courts.
- Announcements and flow steps are non-empty.

Manual/browser verification:

- `npm run build` succeeds.
- Serve the app and check `/` at desktop, tablet, and mobile widths.
- Toggle light/dark and reload to confirm persistence and no flash.
- Keyboard through the header, tabs, filters, and CTAs.
- Emulate `prefers-reduced-motion` and confirm the ticker is static and count-ups are instant.
- Check the console for errors and confirm no horizontal overflow.

Quality gate before completion:

```powershell
cd src
npm run build
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan test
```

## Constraints

- Laravel 13, PHP 8.4+, Blade, Bootstrap 5.3 from SCSS, Vite.
- No Bootstrap JavaScript, Tailwind, Vue, React, or second client framework.
- No new npm runtime dependencies.
- No `.env` or credentials committed.
- Keep the application shell (`layouts.public`) untouched except for the now-unused home content.
- All content must remain readable without JavaScript. Tabs and filters are enhancements; the
  server-rendered panels and match lists are the source of truth.

## Success criteria

- A first-time visitor understands what HoopFlow does within five seconds.
- A visitor can switch between bracket, schedule, and standings and feel the product without
  signing in.
- The page feels specific to barangay basketball and consistent with the existing app.
- The page is fast, accessible, responsive, and works in both themes.
- All quality gates pass with the actual output reported.
