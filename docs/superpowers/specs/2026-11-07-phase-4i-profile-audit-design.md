# Phase 4i — Profile & Audit Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype profile and audit features.

## Section 1 — Views & components

- `pages/profile.blade.php` — user info, role, role links, theme preference.
- `app/Livewire/Organizer/AuditLogIndex.php` + view — filterable audit log (action, entity type, date range), organizer only.
- Routes: `/profile` (auth), `/audit` (auth+role:organizer).

## Constraints

- Livewire for audit filters; no Bootstrap JS.
- composer quality must pass.
- AuditLogPolicy/view-audit gate (organizer only).
