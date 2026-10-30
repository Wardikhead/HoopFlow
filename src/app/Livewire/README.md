# Livewire components

Grouped by the audience that uses them, not by CRUD verb. A component belongs to exactly one group.

```
Livewire/
├── Organizer/   setup, review, and control surfaces
├── Coach/       the coach's own team and registrations
└── Public/      read-only pages that work without an account
```

## Conventions

- Class-based components (`php artisan make:livewire Organizer/CreateTournament`), with views under
  `resources/views/livewire/organizer/`.
- One component per screen or per independently refreshing panel. If a component needs more than roughly
  200 lines, split it.
- Validation belongs in the component; business rules belong in `app/Services`.
- Authorisation is a policy call in `mount()` or the action, never a hidden `if (auth()->user()->role ...)`.
- Use `wire:key` in every loop.
- Do not add Bootstrap JavaScript. Modals and dropdowns are Alpine.
