# Phase 1 — Shell & Theme Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype shell (rail, topbar, mobile bottom nav) into the Laravel Blade layout
so every page inherits it, with real session auth (not demo role switcher), theme toggle,
and Bootstrap 5.3 compiled from source.

## Section 1 — Shell layout

`layouts/public.blade.php` becomes the prototype shell: rail (brand + nav groups + footer with
user chip), topbar (page title + context + theme toggle + announcements + user chip), mobile
bottom nav (role tabs), all gated by `@auth`/role. Keep `@vite` + livewire styles. Nav items
hidden by role. Theme toggle persists to localStorage via Alpine; `data-theme` on `<html>`.

## Section 2 — Auth & routes

`AuthController` (login form, attempt, logout) using `Auth::attempt`; redirect intended/dashboard.
Routes: GET/POST /login, POST /logout (named login/logout). Protected pages use `auth` middleware;
role-restricted ones use `role:` middleware. `RoleMiddleware` checks the user has the role via
user_roles/Role. Login view lists the 3 seeded demo accounts with password `password` and a
one-click sign-in form per account.

## Section 3 — Theme & assets

Complete `_hoopflow.scss` port (rail, topbar, bottom nav, auth topbar, cards, badges, buttons)
from prototype CSS; keep existing partial port and extend. `app.js`: theme init from
localStorage before paint (inline script in head to avoid FOUC), toggle handler, no Bootstrap JS.
Copy `prototype/assets/img/logo.png` to `public/img/logo.png`. Remove demo menu markup
(replaced by auth user chip + logout).

## Constraints

- Bootstrap 5.3 compiled from source (already in app.scss); Bootstrap Icons imported in app.js.
- No Bootstrap JS (ADR-0001). No Tailwind, no SPA framework.
- No domain logic in controllers; auth is infra.
- `composer quality` must pass.
