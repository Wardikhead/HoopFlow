# HoopFlow application

Laravel 13 + Livewire 4 + Bootstrap 5.3 + PostgreSQL 17. See the repository [README](../README.md) for the
full setup.

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

## Where things go

| I need to… | Put it in |
|---|---|
| Add a page | `resources/views/pages/{feature}/` + a route in `routes/web.php` |
| Add an interactive panel | `app/Livewire/{Organizer,Coach,Public}/` |
| Add a bracket, schedule, standings, or eligibility rule | `app/Services/{domain}/` |
| Add shared markup (topbar, card, badge) | `resources/views/components/` |
| Add a role, status, or other closed set | `app/Enums/` |
| Add an authorisation rule | `app/Policies/` |
| Add a feature route file | `routes/features/{feature}.php`, loaded from `routes/web.php` |

## Quality gates

```powershell
composer lint      # Pint, check only
composer analyse   # Larastan level 6
composer test      # Pest
composer quality   # all three
```

## Rules that are easy to break

- No Bootstrap JavaScript. Alpine handles behaviour.
- No Tailwind. Bootstrap is compiled from `resources/css/app.scss`.
- No domain logic in controllers or Livewire components.
- Every page must work without an account unless it is explicitly staff-only.
