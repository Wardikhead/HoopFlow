# HoopFlow

Basketball tournament management for barangay and grassroots youth leagues — registration and eligibility,
bracket generation, scheduling, scorekeeping, standings, and announcements.

Two-person student project. The product is the **Laravel application in [`src/`](src)**.

## Repository layout

| Path | What it is |
|---|---|
| `src/` | **The product.** Laravel 13 · Blade · Livewire 4 · Bootstrap 5.3 · PostgreSQL 17 |
| `prototype/` | Static HTML/CSS/JS UI prototype. Design reference only — never deployed |
| `docs/` | ADRs, specs, design notes |
| `HoopFlow/` | Project dossier and wiki (Obsidian) |
| `tools/` | Repo scripts |
| `logo/`, `video/` | Brand assets and the splash-video renderer |
| `_archive/` | Superseded material |

## Stack

Decided in [`docs/decisions/ADR-0001-stack.md`](docs/decisions/ADR-0001-stack.md):

- PHP 8.4+ (developed against 8.5), Laravel 13
- Blade templates, Livewire 4, Alpine.js 3
- Bootstrap 5.3 compiled from SCSS by Vite — **Bootstrap's JavaScript is not loaded**; Alpine owns behaviour
- PostgreSQL 17 (SQLite is fine locally, but scheduling tests must run on Postgres)
- Pest, Pint, Larastan level 6

## Run the app

```powershell
cd src
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev          # Vite, in one terminal
php artisan serve    # in another → http://127.0.0.1:8000
```

## Run the prototype

```powershell
php -S 127.0.0.1:8001 -t prototype   # → http://127.0.0.1:8001
```

See [`prototype/README.md`](prototype/README.md) for the demo role switcher and mock data.

## Quality gates

Run before every commit that touches `src/`:

```powershell
cd src
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan test
```

## Conventions

- Domain logic (bracket generation, scheduling, standings, eligibility) lives in `src/app/Services/` as plain
  PHP classes with no framework coupling, so it can be unit-tested without HTTP.
- Controllers stay thin; anything stateful and interactive is a Livewire component.
- No Bootstrap JS. No second client-side framework. No SPA.
- Never commit `.env`, credentials, or user data.
