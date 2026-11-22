# Landing Page Polish — Design Spec

**Date:** 2026-11-22
**Status:** Approved (brainstorming, option A)
**Scope:** Frontend (Blade + SCSS + JS), `LandingShowcase`, landing tests, team photos.

## Goal

Polish the public landing page and make every feature claim match the shipped app.

## Approved changes

### Accuracy

- Feature lab copy: "bracket, calendar, and standings" (not schedule).
- Lab intro: "A working preview" (not "The real interface").
- Footer Product link: Calendar, targeting the calendar tab.
- Add a compact "Also inside" feature index to the lab rail: live scoring, eligibility
  queue, payments, announcements, audit trail.
- Eligibility: replace residency claims with birthdate eligibility and birth-certificate
  review; list GCash, Maya, bank, and cash; say "a written reason on every rejection".
- Flow step 5: final scores post to the matchday wire (not "notify the bleachers").
- Fan role: include live scores.
- Roles header gains a one-line supporting sentence.

### Developers

- Use the supplied photos: Jeward Mahinay (portrait), Rusty Japos (night shot).
- Move the developers out of the closing CTA into their own dark band with a left header
  and two roster-style profile cards.
- Remove the "Profile pictures coming soon" line and the placeholder `#` links.
- Photos live in `src/public/img/team/` with intrinsic dimensions and lazy loading.

### Visual / interaction

- Fix the MATCHDAY stamp being covered and clipped by the hero scoreboard.
- Left-align the lab CTA on mobile.
- Raise the smallest decorative labels; keep section heading scales consistent.
- Add focus-visible states for landing links and buttons.
- Calendar: every day is selectable, empty days show the empty message, and day buttons
  carry accessible labels.
- Respect reduced motion for new hover motion.

## Data

`LandingShowcase::developers()` returns name, role, photo path, initials, accent, and focal
point for each developer. `flow()` and `roles()` copy updates only.

## Constraints

- Reuse `--hf-lp-*` tokens and the existing landing layout.
- No new dependencies, no new interactive panel.
- Keep all existing `data-testid` values used by tests.

## Verification

- `composer quality` and `npm run build`.
- Browser: 1440×900, 819×1000, 390×844; light and dark; tabs, calendar, mobile menu,
  focus states; no horizontal overflow.
