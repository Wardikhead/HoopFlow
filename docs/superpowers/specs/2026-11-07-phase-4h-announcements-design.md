# Phase 4h — Announcements Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype announcements feature (filterable list + create) to Livewire.

## Section 1 — Livewire components

- `app/Livewire/Organizer/AnnouncementsIndex.php` — filterable list (tournament/category/search), role-based audience.
- `app/Livewire/Organizer/AnnouncementCreate.php` — validated form creating Announcement (organizer only).

Views in `livewire/organizer/`. #[Layout('layouts.public')].

## Section 2 — Routes & tests

Routes `/announcements`, `/announcements/create` (role:organizer). Tests: pages load per role, create (organizer). composer quality must pass.

## Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
- AnnouncementPolicy exists.
