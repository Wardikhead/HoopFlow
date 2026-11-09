# Phase 4j — Submissions & Payments Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype submissions (review queue) and payments (verify) features to Livewire.

## Section 1 — Livewire components

- `app/Livewire/Organizer/SubmissionsIndex.php` — filterable registrations by status; approve/reject actions.
- `app/Livewire/Organizer/PaymentsIndex.php` — filterable payments by status; verify/refund actions.

Views in `livewire/organizer/`. #[Layout('layouts.public')].

## Section 2 — Routes & tests

Routes `/submissions`, `/payments` (auth+role:organizer). Tests: pages load for organizer, forbidden for others, guest redirected. composer quality must pass.

## Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
- Uses RegistrationPolicy, PaymentPolicy.
