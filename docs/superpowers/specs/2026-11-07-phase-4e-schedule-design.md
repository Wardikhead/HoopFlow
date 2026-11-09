# Phase 4e — Schedule Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype schedule feature (calendar views + generate schedule) to Livewire.

## Section 1 — Livewire component

`app/Livewire/Organizer/ScheduleShow.php` full-page: properties tournamentId, viewMode, currentMonth, selectedDay. Methods: generateSchedule, navigateMonth, selectDay. Uses SchedulingService. View: `livewire/organizer/schedule-show.blade.php`.

## Section 2 — Routes & tests

Route `/schedule` -> ScheduleShow (auth). Tests: page loads per role, generate (organizer). composer quality must pass.

## Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
- Uses existing SchedulingService.
