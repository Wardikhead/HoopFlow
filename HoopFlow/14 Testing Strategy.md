# 14. Testing Strategy

> **Part of:** [[00 HoopFlow]]

## 14.1 Unit tests

- Bracket generation for 2, 3, 5, 8, 16, and 17 teams.
- Bye placement and auto-advance.
- Seeding order.
- Round-robin pairings.
- Standings and tiebreakers.
- Scheduling constraints: court overlap, team overlap, rest, dependencies.
- RBAC: each role can and cannot perform the expected actions.
- Payment balance calculations.
- Eligibility age calculation at the tournament date.

## 14.2 Integration tests

- Registration → approval → bracket → schedule → score → standings.
- Payment recorded → registration balance updated.
- Schedule change → notification created.
- Score correction → bracket and standings recomputed.

## 14.3 End-to-end tests

- Organizer creates a tournament and publishes it.
- Coach registers a team and submits a roster.
- Organizer approves the team and generates the bracket.
- Organizer generates and publishes the schedule.
- Scorekeeper enters a result.
- Public page shows the updated bracket and standings.
- A coach cannot enter a score.

## 14.4 Test data

- 16 teams.
- 4 courts.
- 30-minute slots.
- 15-minute minimum rest.
- One U12 division and one U15 division.
- One team with a bye.
- One forfeit.
- One score correction.

## 14.5 Definition of done

A feature is done when:

- Acceptance criteria are met.
- Unit and integration tests pass.
- Lint, typecheck, and build pass.
- The happy path and at least one error path are manually verified.
- Responsive behavior is checked on a phone-sized viewport.
- Audit events are verified.
- The dossier and issue register are updated.
