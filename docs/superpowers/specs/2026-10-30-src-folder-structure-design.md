# Src folder structure and prototype consolidation

- **Date:** 2026-10-30
- **Owner:** Jeward T. Mahinay
- **Status:** Approved
- **Related:** [[ADR-0001-stack]] · [[12 Target Architecture]] · [[16 Working Agreement]]

## Problem

The repository contains a working static prototype under `frontend/` but no application. The decided stack
(ADR-0001: Laravel 13, Blade, Livewire 4, Bootstrap 5.3, PostgreSQL 17) has no scaffold, so there is nowhere to
begin the walking skeleton. The prototype also carries defects that would be copied into the Blade views if the
port happened first.

## Decision

1. **The product is `src/`.** An official `laravel/laravel` 13.x scaffold, restyled to Bootstrap, is the
   application root. No hand-authored skeleton.
2. **The prototype is `prototype/`.** Renamed from `frontend/`, kept outside `src/` because it is reference
   material, not shipped code. It stays runnable as a standalone static site.
3. **The dossier stays at `HoopFlow/`.** Only the repo root gains `README.md` and `.editorconfig`.
4. **Domain folders are created empty, with READMEs**, so the intended boundaries survive until the code lands.

## Target tree

```
hoopflow/
├── src/                      Laravel 13 application
│   ├── app/
│   │   ├── Enums/            Role, TournamentStatus, MatchStatus, PaymentStatus
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/         Bracket, Scheduling, Standings, Eligibility
│   │   ├── Livewire/         Organizer, Coach, Public
│   │   └── Http/Controllers/
│   ├── resources/
│   │   ├── css/app.scss      Bootstrap 5.3 + HoopFlow tokens
│   │   ├── js/app.js         Alpine only
│   │   └── views/            layouts, components, pages/{feature}
│   ├── routes/web.php
│   ├── database/{migrations,seeders,factories}
│   ├── tests/{Unit,Feature}
│   └── public/
├── prototype/                static reference build
├── docs/decisions/           ADRs
├── docs/superpowers/specs/   design specs
├── HoopFlow/                 dossier and wiki
└── README.md
```

## Prototype defects fixed before the move

`frontend/` → `prototype/` was a rename, so the fixes below were applied in place and are carried by the move.

| # | Defect | Fix |
|---|---|---|
| 1 | Theme toggle threw `TypeError` on 18 of 22 app pages because only `app.js` defines `toggleTheme` | `app.js` added to every page; `navigation.js` no longer depended on an inline handler and falls back to a local toggle |
| 2 | `window.HF` overwritten by `mock-data.js`, silently dropping `toast` and `toggleTheme` if load order changed | `mock-data.js` now merges with `Object.assign(window.HF \|\| {}, …)` |
| 3 | `app.js` ran before `navigation.js`/`role-switcher.js` rendered their DOM, so the toolbar toggle was never injected and the topbar icon never reflected the saved theme | Enhancement deferred one tick, plus an `hf:nav-rendered` event |
| 4 | `isHome` matched every `index.html`, hiding the mobile back button on teams, schedule, tournaments, announcements, and profile | Restricted to the dashboard route |
| 5 | `standings/table.html` linked to a non-existent `standings/index.html` | Points at `../brackets/view.html` |
| 6 | Logo `onerror` pointed at a file that does not exist | `onerror` removed; `logo.png` is the only asset |
| 7 | Dashboard KPIs and standings preview hardcoded, already contradicting the seed data (2 unpaid vs 3) | Derived from `window.HF.get()` at render time |
| 8 | Nine unreferenced iteration pages (`index_1`, `stats_1`–`stats_5`, `show_1`, `show_2`) | Deleted after consolidating the best variant into the canonical file |
| 9 | No entry point; opening the folder produced a directory listing | `prototype/index.html` with a redirect and fallbacks |

### Variant consolidation

- `games/stats.html` ← `stats_5` (toast instead of `alert`, includes `app.js`), with the role gate restored to
  `scorekeeper,organizer` so the scorekeeper requirement from P0-02 is not lost.
- `tournaments/show.html` ← `show_2` (adds `app.js`; otherwise identical).
- `dashboard/index.html`, `teams/show.html` were already byte-identical to their `_1` variants.

## Application scaffold

| Step | Command / change |
|---|---|
| Skeleton | `composer create-project laravel/laravel src "13.*"` |
| UI components | `composer require livewire/livewire` |
| Testing | Pest with the Laravel plugin |
| Quality | Pint, Larastan level 6 |
| Styling | Remove Tailwind; add `bootstrap` and `sass`; `app.scss` seeded from `prototype/assets/css/hoopflow.css` tokens |
| Client JS | Alpine only — Bootstrap JS is deliberately excluded (ADR-0001) |

## Verification

- `php artisan about` boots inside `src/` (Laravel 13.39.0, PHP 8.5.2).
- `npm run build` produces `public/build` with exit code 0.
- `./vendor/bin/pint --test` passes.
- `./vendor/bin/phpstan analyse` passes at level 6.
- `php artisan test` passes (2 tests).
- `prototype/` opens via `php -S 127.0.0.1:8001 -t prototype`; the theme toggle works on every page and
  survives navigation, verified in a real browser with no console errors.
- Root `README.md` explains which folder is which.

## Environment notes

The local PHP 8.5.2 install had `fileinfo`, `zip`, `pdo_sqlite`, `sqlite3`, `pdo_pgsql`, `pgsql`, `intl`, `gd`,
and `exif` disabled. They were enabled in `C:\php-8.5.2\php.ini` (backup at `php.ini.bak-hoopflow`) because
Composer cannot install Laravel without `fileinfo` and `zip`.

`laravel/pao` was removed from the skeleton's dev dependencies: it conflicts with every Pest 4 release. Pest is
required by the working agreement for the bracket, scheduling, standings, and eligibility services.

## Out of scope

- Porting prototype pages to Blade or Livewire (a later phase; the walking skeleton comes first).
- Database schema and migrations from [[11 Target Data Model]].
- Authentication screens wired to Laravel.
