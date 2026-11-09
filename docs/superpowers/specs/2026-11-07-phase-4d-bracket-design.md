# Phase 4d — Bracket Design

Date: 2026-11-07
Status: Approved (pending user review of written spec)

## Goal

Port the prototype bracket feature (view, generate, edit slots, score entry, correction) to Livewire.

## Section 1 — Livewire component

`app/Livewire/Organizer/BracketShow.php` full-page component: properties tournamentId, selectedMatch, showEditSlots, showCorrectionDialog. Methods: generateBracket, regenerateBracket, editSlots, saveSlots, openScoreConsole, submitScore, openCorrection, submitCorrection. Uses BracketService. View: `livewire/organizer/bracket-show.blade.php`.

## Section 2 — Routes & tests

Route `/bracket` -> BracketShow (auth). Tests: page loads per role, generate (organizer), non-organizer forbidden. composer quality must pass.

## Constraints

- Livewire for interactivity; no Bootstrap JS.
- composer quality must pass.
- Uses existing BracketService.
