# 4. Users and Roles

> **Part of:** [[00 HoopFlow]]

## 4.1 Organizer / Tournament Manager

The primary user. Creates tournaments, configures divisions and rules, reviews registrations, verifies eligibility and payment, generates brackets and schedules, publishes announcements, and oversees the event.

**Current document status:** Covered by the Admin use case. The document also calls this role "Admin," "Committee," and "Tournament Manager." Pick one term: **Organizer**.

## 4.2 Coach / Team Head

Registers a team, submits the roster, uploads eligibility documents, pays or records the entry fee, views the schedule and standings, and manages the team profile.

**Current document status:** Covered by the Coach/Team Leader use case.

## 4.3 Scorekeeper / Official

Enters final scores and post-game statistics. Must be assigned to specific matches. Score edits must be audited.

**Current document status:** Listed as a stakeholder, but missing from the use cases, DFD, ERD, and wireframes. This is a **P0 gap**.

## 4.4 Fan / Parent / Guardian

Views public tournament pages: brackets, schedules, results, standings, teams, and rosters. Parents/guardians also consent to a minor's participation and may need to see their child's schedule.

**Current document status:** Covered by the Fan use case. Parents are mentioned in the survey and user stories but are not a distinct stakeholder.

## 4.5 Player

A minor participating in the tournament. Players usually do not need accounts. Their data is managed by the coach and verified by the organizer.

**Current document status:** Players appear in the ERD but have no birthdate or guardian fields. This is a **P0 gap**.

## 4.6 System Administrator

Technical administrator for accounts, backups, and configuration. This role may be the same person as the organizer in a small league, but the permissions should be separate.

**Current document status:** Not modeled.

## Role naming rule

Use these terms consistently in every document, screen, and table:

- **Organizer** (not Admin, Committee, or Tournament Manager)
- **Coach** (not Team Head or Team Leader)
- **Scorekeeper** (not Official, unless the person is also a referee)
- **Fan / Parent** (not Supporter or Audience)
- **Player**
