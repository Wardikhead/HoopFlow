# ADR-0001 — Technology stack

- **Status:** Accepted
- **Date:** 2026-10-27
- **Decision owner:** Jeward T. Mahinay
- **Supersedes:** the open `D-009` entry in [[08 Decisions Log]]
- **Related:** [[12 Target Architecture]] · [[11 Target Data Model]] · [[09 Scope]] · [[13 Non-Functional Requirements]]

## Context

HoopFlow is a basketball tournament management system built by two students, part-time, over an estimated 14-18 weeks. The dossier is explicit about the shape of the product: tournament setup, registration and eligibility, bracket generation, scheduling, scorekeeping, standings, and notifications. Almost all of it is forms, tables, and server-rendered pages over a relational schema.

Three constraints from the dossier drive this decision more than preference does:

1. **Scheduling integrity must be enforced by the database.** [[11 Target Data Model]] §11.7 requires a PostgreSQL exclusion constraint on `tstzrange` to prevent overlapping court slots. On a database without that capability, the headline feature degrades into application-level locking that must be written and tested by hand.
2. **The public pages must work on low-end Android phones.** [[13 Non-Functional Requirements]] sets WCAG 2.1 AA basics, 44 px tap targets, and p95 under 500 ms for public reads. Shipping less JavaScript is the cheapest way to hit that.
3. **Bracket, scheduling, standings, and RBAC logic must be unit-tested.** [[16 Working Agreement]] §3 makes automated tests mandatory for exactly the parts that are algorithmically interesting.

## Decision

Use one server-rendered monolith:

| Layer                     | Choice                                                                 | Version                                                   |
| ------------------------- | ---------------------------------------------------------------------- | --------------------------------------------------------- |
| Language                  | PHP                                                                    | 8.4                                                       |
| Framework                 | Laravel                                                                | 13.x (supports PHP 8.3-8.5; security fixes to March 2028) |
| HTML                      | Blade templates                                                        | as shipped                                                |
| CSS                       | Bootstrap, SCSS source compiled by Vite                                | 5.3.x                                                     |
| Client JavaScript         | Alpine.js (bundled with Livewire) plus plain ES2022                    | 3.x                                                       |
| Server-side UI components | Livewire                                                               | 4.x                                                       |
| Database                  | PostgreSQL                                                             | 17                                                        |
| Asset build               | Vite + `laravel-vite-plugin`                                           | as scaffolded                                             |
| Queue and cache           | `database` driver                                                      | -                                                         |
| Sessions                  | `database` driver                                                      | -                                                         |
| File storage              | local disk in development, S3-compatible (Cloudflare R2) in production | -                                                         |
| Authentication            | Laravel session auth, authorisation via Gates and Policies             | -                                                         |
| Testing                   | Pest, Livewire component tests, Playwright for end-to-end              | -                                                         |
| Quality gates             | Pint, Larastan level 6, GitHub Actions                                 | -                                                         |
| Hosting                   | Railway, with managed PostgreSQL; target recorded separately as D-010  | -                                                         |

**JavaScript scope is deliberately bounded.** There is no SPA and no client-side framework. Alpine handles small interactions: modals, toggles, and the optional v1.1 polling scoreboard. Anything that needs shared state goes through Livewire, on the server. If a single screen ever genuinely requires components, mount Vue as an island inside one Blade element rather than introducing a second application architecture.

**Bootstrap is used for CSS only.** Its JavaScript plugins are not loaded, because they maintain DOM state that Livewire re-renders, which produces stuck modals, orphaned tooltips, and dropdowns that will not close. Behaviour is Alpine's job; styling is Bootstrap's.

## Consequences

**Positive**

- One language for the team to master, one deployable unit, one database, one build. Matches [[12 Target Architecture]] §12.1.
- The scheduling constraint in §11.7 is enforced by PostgreSQL, not by application code.
- Public pages ship roughly 15 KB of JavaScript, which serves the mobile-first and low-end Android requirements.
- Livewire covers the roster editor, registration review, score entry, and schedule builder without custom JavaScript.
- Every algorithm (bracket, schedule, standings, eligibility) can live in a plain PHP service class with no framework coupling, which is what makes the mandatory unit tests cheap to write.

**Negative**

- No React or Vue experience for the team's portfolio. This is a real cost and was accepted knowingly.
- Laravel's official starter kits ship Tailwind and Flux UI, so adopting Bootstrap means swapping the styling layer once at Phase 1.
- Livewire has its own learning curve: component hydration, `wire:key` in loops, and `wire:ignore` around third-party DOM.
- PHP hosting is slightly less turnkey than Vercel for a JavaScript stack; Railway covers it.

**Neutral**

- MySQL would have worked for everything except the exclusion constraint. That single requirement decides the database.
- Redis is unnecessary at this scale; the database queue is adequate until proven otherwise.

## Alternatives rejected

| Alternative | Why not |
|---|---|
| Next.js + React + Prisma (Option A) | Best if the team wanted TypeScript, but moves the court-conflict guarantee from PostgreSQL into hand-written application code and adds a second runtime to operate. |
| Laravel + Inertia + Vue (Option B variant) | Strong option, but adds a component framework for screens that are mostly forms and tables. Kept as the escape hatch if a bracket UI demands it. |
| Laravel API + React SPA (Option C variant) | CORS, token auth, two deploys, two test suites. No demo value for the added cost. |
| Django + HTMX | Equally viable, but no member of the team is stronger in Python than PHP. |

## Notes for implementers

- Scaffold with the Blade stack and restyle to Bootstrap; do not adopt the Tailwind starter kit.
- Keep bracket, schedule, standings, and eligibility logic out of controllers and Livewire components. They belong in `app/Services` so they can be tested without HTTP.
- Local development can use SQLite for speed, but tests that touch scheduling constraints must run against PostgreSQL, or the exclusion constraint is never exercised.
