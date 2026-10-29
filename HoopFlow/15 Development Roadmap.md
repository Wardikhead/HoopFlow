# 15. Development Roadmap

> **Part of:** [[00 HoopFlow]]

The plan is deliberately slow and steady. One phase at a time. Do not start the next phase until the current phase's exit criteria are met.

## Phase 0 — Documentation repair and decisions (1-2 weeks)

**Goal:** Make the academic document and the product plan consistent before writing code.

Tasks:

- [ ] [[07.2 P0 Issues#P0-01 — Survey data does not reconcile|Reconcile the survey data]] or label it as a pilot.
- [ ] Add the raw-data appendix.
- [ ] Approve the [[20 Glossary|glossary]] and apply it everywhere.
- [ ] Approve the [[10 Target Product Design#10.3 Role-permission matrix|RBAC matrix]].
- [ ] Approve the [[11 Target Data Model|v2 data model]].
- [ ] Approve the [[09 Scope#9.1 In scope for v1|v1 scope]] and [[09 Scope#9.2 Later|defer list]].
- [ ] Choose the [[12 Target Architecture#12.2 Stack options|stack]] ([[08 Decisions Log|D-009]]).
- [ ] Choose the [[12 Target Architecture#12.3 Environments|deployment target]] ([[08 Decisions Log|D-010]]).
- [ ] Create the repository, README, issue tracker, and CI skeleton.
- [ ] Add [[13 Non-Functional Requirements|NFRs]], acceptance criteria, assumptions, risks, and a traceability matrix.
- [ ] [[07.4 P2 Issues#^p2-05|Compress the document]] to under 10 MB.
- [ ] Fix the cover year, TOC letters, figure numbers, and typos.

**Exit criteria:** The document is internally consistent; the decisions log has no blocking "Pending" items; the repository exists.

## Phase 1 — Walking skeleton (2 weeks)

**Goal:** A deployed app with authentication and one protected page.

Tasks:

- [ ] Create the app shell and design tokens.
- [ ] Set up PostgreSQL and migrations.
- [ ] Implement [[11 Target Data Model#11.1 Identity and access|User, Role, and UserRole]].
- [ ] Implement sign-up (Coach only) and login.
- [ ] Seed an Organizer account.
- [ ] Add [[10 Target Product Design#10.3 Role-permission matrix|role guards]] and a 403 page.
- [ ] Add health check, logging, and error tracking.
- [ ] Add CI: lint, typecheck, test, build.
- [ ] Deploy to staging.
- [ ] Write the first end-to-end test: login and access a protected page.

**Exit criteria:** A coach can sign up, log in, and see a protected dashboard; an organizer account is seeded; staging is live.

## Phase 2 — Tournament setup and public shell (2 weeks)

**Goal:** An organizer can create a tournament and the public can view it.

Tasks:

- [ ] [[11 Target Data Model#11.2 Tournament structure|Tournament, Division, Venue, and Court models]].
- [ ] Organizer create/edit tournament.
- [ ] Configure division age range, format, entry fee, and rules.
- [ ] Public tournament page.
- [ ] Publish/unpublish behavior.
- [ ] [[14 Testing Strategy#14.1 Unit tests|RBAC tests]].

**Exit criteria:** Organizer creates a U15 tournament with 4 courts; the public page shows the correct details; a coach cannot edit it.

## Phase 3 — Registration, roster, and eligibility (2-3 weeks)

**Goal:** A coach can register a team, and an organizer can approve it.

Tasks:

- [ ] [[11 Target Data Model#11.3 Teams and eligibility|Team and TeamMembership models]].
- [ ] [[11 Target Data Model#11.3 Teams and eligibility|Player model]] with birthdate, guardian, [[10 Target Product Design#10.8 Privacy and consent|consent]], eligibility.
- [ ] Registration model and status workflow.
- [ ] Coach registration form and roster editor.
- [ ] Eligibility document upload.
- [ ] Organizer review screen.
- [ ] [[10 Target Product Design#10.6 Payments|Manual payment recording]].
- [ ] [[10 Target Product Design#10.7 Notifications|Notifications]] for submitted/approved/rejected.
- [ ] Audit events.
- [ ] [[14 Testing Strategy#14.1 Unit tests|Tests]] for age validation and duplicate registration.

**Exit criteria:** End-to-end registration works; an ineligible player is rejected; a duplicate team registration is blocked; the organizer can approve and record a payment.

## Phase 4 — Bracket generation (2 weeks)

**Goal:** Generate and publish a correct [[10 Target Product Design#10.5 Bracket rules|single-elimination bracket]].

Tasks:

- [ ] [[11 Target Data Model#11.4 Brackets and matches|Bracket and BracketSlot models]].
- [ ] Seeding and bye logic.
- [ ] Bracket generation service and unit tests.
- [ ] Organizer preview and publish.
- [ ] Manual override with audit.
- [ ] Public bracket view with zoom/scroll.
- [ ] [[10 Target Product Design#10.5 Bracket rules|Auto-advance]] on score.

**Exit criteria:** A 16-team bracket generates correctly; byes advance; the public view matches the organizer view.

## Phase 5 — Scheduling (2-3 weeks)

**Goal:** Generate a [[10 Target Product Design#10.4 Scheduling rules|conflict-free schedule]] for courts and teams.

Tasks:

- [ ] [[11 Target Data Model#11.4 Brackets and matches|MatchSlot]] model and [[11 Target Data Model#11.2 Tournament structure|court availability]].
- [ ] Scheduling constraints and service.
- [ ] Schedule generation with deterministic output.
- [ ] Conflict and rest validation.
- [ ] Organizer preview, manual adjustment, and publish.
- [ ] Public schedule view with date/team filters.
- [ ] Schedule-change notifications.
- [ ] Tests for the [[14 Testing Strategy#14.4 Test data|16-team/4-court scenario]].

**Exit criteria:** The acceptance criteria in [[07.3 P1 Issues#P1-01 — Scheduling schema cannot enforce court conflicts or rest rules|P1-01]] pass; a manual change creates a new version and notifies affected teams.

## Phase 6 — Scorekeeping and standings (2-3 weeks)

**Goal:** A scorekeeper can enter a result and the bracket and [[11 Target Data Model#11.5 Statistics and standings|standings]] update.

Tasks:

- [ ] Scorekeeper assignment.
- [ ] Score entry form with validation.
- [ ] Match completion and winner derivation.
- [ ] Bracket auto-advance.
- [ ] [[11 Target Data Model#11.5 Statistics and standings|Standings view]] or materialized view.
- [ ] Score correction with organizer approval and [[11 Target Data Model#11.6 Communication and audit|audit]].
- [ ] Public results and standings.
- [ ] Tests for corrections and tiebreakers.

**Exit criteria:** A score updates the bracket and standings; a coach cannot enter a score; a correction is audited.

## Phase 7 — Notifications and announcements (1-2 weeks)

**Goal:** Users are informed without manual messaging.

Tasks:

- [ ] [[11 Target Data Model#11.6 Communication and audit|Notification model]] and outbox.
- [ ] In-app notification center.
- [ ] Email provider for transactional messages.
- [ ] Announcement publisher with audience targeting.
- [ ] Notification preferences.
- [ ] Delivery status and retry.
- [ ] Tests for event-to-notification mapping.

**Exit criteria:** Registration, schedule change, and score events create notifications; delivery is logged.

## Phase 8 — Hardening and demo (2 weeks)

**Goal:** A reliable demo and a submission-ready package.

Tasks:

- [ ] [[14 Testing Strategy#14.3 End-to-end tests|End-to-end test suite]].
- [ ] Security review against the [[12 Target Architecture#12.4 Security checklist|checklist]].
- [ ] [[12 Target Architecture#12.5 Operations checklist|Backup]] and restore test.
- [ ] [[12 Target Architecture#12.6 Performance checklist|Performance check]] with [[14 Testing Strategy#14.4 Test data|seed data]].
- [ ] Accessibility and responsive check.
- [ ] [[10 Target Product Design#10.8 Privacy and consent|Privacy policy]] and consent screens.
- [ ] Documentation update.
- [ ] Demo seed script and rehearsal.
- [ ] Known-issues list.

**Exit criteria:** The demo runs clean from start to finish; the checklist is complete; the document and dossier are current.

## Optional extension after Phase 8 — Lightweight live scoreboard (v1.1)

**Goal:** A [[10 Target Product Design#10.9 Live score view vs live score entry|public score view]] that looks live without the cost of full live score entry.

**Precondition:** [[#Phase 6 — Scorekeeping and standings (2-3 weeks)|Phases 1-6]] are stable. Do not start this before scorekeeping and standings work.

Tasks:

- [ ] Add `matches.score_updated_at` and `matches.version`.
- [ ] Add a scorekeeper-only score update endpoint with an assignment check and audit.
- [ ] Add a public live-score endpoint that returns only in-progress matches.
- [ ] Add a public UI that polls every 20-30 seconds and pauses when the tab is hidden.
- [ ] Add optimistic concurrency: reject stale updates with a version mismatch.
- [ ] Test a score update, a stale update, and an unauthorized update.
- [ ] Add the live view to the demo only if it is stable.

**Exit criteria:** A score update appears on the public page within 30 seconds; there is no game clock or play-by-play; every update is audited.

## Timeline estimate

Part-time, two students: about **14-18 weeks**. The estimate assumes 8-12 hours per person per week and no major scope changes. School requirements take priority; adjust the timeline rather than skipping quality gates.
