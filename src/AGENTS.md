<laravel-boost-guidelines>
# HoopFlow application conventions

This is the Laravel application for HoopFlow, a youth basketball tournament manager. Read
`README.md` in this directory and `../README.md` at the repository root before making changes.

Stack is fixed by ADR-0001 (`../docs/decisions/ADR-0001-stack.md`):

- Laravel 13, PHP 8.4+, Blade, Livewire 4, Alpine 3
- Bootstrap 5.3 compiled from SCSS by Vite — **no Tailwind, no Bootstrap JavaScript**
- PostgreSQL 17 in production; SQLite is acceptable for local and unit tests

Structure:

- `app/Services/{Bracket,Scheduling,Standings,Eligibility}` — plain PHP domain logic, unit tested
- `app/Livewire/{Organizer,Coach,Public}` — class components, views in `resources/views/livewire`
- `resources/views/{layouts,components,pages}` — Blade shell, shared partials, full pages
- `routes/web.php` — table of contents; feature routes load from `routes/features/*.php`

Before claiming work is done, run `composer quality` and report the actual output.

Do not install starter kits, Tailwind, Vue, React, or Bootstrap's JS bundle. Do not commit `.env` or
credentials.
</laravel-boost-guidelines>
