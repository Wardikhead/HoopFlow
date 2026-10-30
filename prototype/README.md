# HoopFlow — UI Prototype

Static HTML/CSS/JS prototype of the HoopFlow interface. **This is a design reference, not the product.**

The product is the Laravel 13 application in [`../src`](../src). Views here are the source of truth for layout,
spacing, and interaction states; they get rebuilt as Blade components and Livewire components in `src/`.

## Run it

The pages use root-relative script paths (`../assets/js/...`), so they need an HTTP server — do not open the
files with `file://`.

```powershell
# from the repo root
php -S 127.0.0.1:8001 -t prototype
# then open http://127.0.0.1:8001
```

## Structure

```
prototype/
├── index.html          landing/redirect to auth/splash.html
├── auth/               splash · login · register
├── dashboard/          role-aware home (organizer / coach / fan)
├── tournaments/        index · show · create · payments · submissions
├── teams/              index · show · my-team
├── brackets/view.html  bracket + seeding lock
├── schedule/           index · matches
├── standings/table.html
├── games/stats.html    box score + score entry
├── announcements/      index · create
├── profile/index.html
└── assets/
    ├── css/hoopflow.css   design tokens, components, dark theme
    ├── js/mock-data.js    localStorage store (stand-in for the database)
    ├── js/app.js          theme toggle + toast system
    ├── js/navigation.js   topbar + mobile bottom nav
    ├── js/role-switcher.js floating demo role pill
    └── img/logo.png
```

## Demo controls

- **Floating role pill** (bottom-right, every page): switch between Organizer, Coach, and Fan. Persisted in
  `localStorage.hoopflow_role`. Elements carrying `data-role-required="organizer,coach"` show/hide accordingly.
- **Theme toggle** (topbar, and in the floating toolbar): persists in `localStorage.hoopflow_theme`.
- **Mock data** (`hoopflow_db` in localStorage): delete the key to reset to seed data.

## Known prototype-only shortcuts

These are deliberate and must not be carried into `src/`:

- Passwords are not checked; login is a redirect.
- All data is localStorage. No server, no validation, no auth.
- `scorekeeper` appears in `data-role-required` on `games/stats.html` but is not selectable in the demo pill.
- Bootstrap is loaded from a CDN. The Laravel app compiles Bootstrap from npm instead.
