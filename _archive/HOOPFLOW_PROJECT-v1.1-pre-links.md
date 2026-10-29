# HoopFlow — Project Dossier & Development Roadmap

> **Basketball Tournament Management System**
> **Course:** CS Elective 1 — System Fundamentals
> **Team:** Rusty Japos, Jeward T. Mahinay
> **Source document:** `C:\Users\JewardMahinay\Downloads\Basketball_Tournament_System all for one.docx`
> **Figma:** https://www.figma.com/design/QDqY8HSXs7jCnqHbEbvDmi/HoopFlow?node-id=0-1&t=uzoC1HzX3UeMEv76-1
> **Dossier version:** 1.1
> **Last updated:** 2026-10-26
> **Status:** Documentation review complete; no application code yet.

> **For future sessions and AI agents:** Read this file before doing any HoopFlow work. It is the single source of truth for the project context, scope, decisions, known issues, and roadmap. Do not silently expand scope. If a decision is marked **Pending**, ask Jeward before implementing it. If an issue is marked **Open**, it has not been fixed in the source document or code yet.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Project Identity](#2-project-identity)
3. [Problem and Vision](#3-problem-and-vision)
4. [Users and Roles](#4-users-and-roles)
5. [Current Deliverables](#5-current-deliverables)
6. [Review Summary](#6-review-summary)
7. [Issue Register](#7-issue-register)
8. [Decisions Log](#8-decisions-log)
9. [Scope](#9-scope)
10. [Target Product Design](#10-target-product-design)
11. [Target Data Model](#11-target-data-model)
12. [Target Architecture](#12-target-architecture)
13. [Non-Functional Requirements](#13-non-functional-requirements)
14. [Testing Strategy](#14-testing-strategy)
15. [Development Roadmap](#15-development-roadmap)
16. [Working Agreement](#16-working-agreement)
17. [Immediate Next Actions](#17-immediate-next-actions)
18. [Demo Script](#18-demo-script)
19. [Open Questions](#19-open-questions)
20. [Glossary](#20-glossary)
21. [Source Inventory](#21-source-inventory)
22. [Change Log](#22-change-log)

---

## 1. Executive Summary

HoopFlow is a tournament management system for grassroots youth basketball. It is intended to replace the spreadsheets, group chats, paper brackets, and manual score updates that organizers currently use.

The product promise is a single lifecycle:

**Register → Auto-bracket → Auto-schedule → Play → Enter results → Auto-advance → Notify → Track history**

The academic document is strong on ambition and artifact coverage. It contains requirements, personas, user stories, use cases, wireframes, flowcharts, DFDs, an ERD, and a system architecture diagram. For a two-person fundamentals project, that is a lot of work.

The document is **not yet submission-tight**. The three most serious problems are:

1. **The survey data does not reconcile.** The claimed sample size of 50 does not match percentages that imply samples of 7, 8, or 16.
2. **The role and permission model is unsafe.** The sign-up screen exposes a role field, and the scorekeeper role is missing from the use cases and data model.
3. **Eligibility is not modeled.** There is an age limit on tournaments, but no birthdate, guardian consent, or verification status on players.

If the team builds the product, the biggest architectural gap is scheduling. The document promises conflict-free scheduling, but the current schema stores only a free-text court name and has no venues, courts, time slots, or rest constraints. The headline feature cannot be enforced from the current data model.

The recommended path is to repair the documentation first, then build a small modular monolith in vertical slices, one phase at a time.

---

## 2. Project Identity

| Field                | Value                                                                                         |
| -------------------- | --------------------------------------------------------------------------------------------- |
| Product name         | **HoopFlow**                                                                                  |
| Product type         | Basketball tournament management system                                                       |
| Target market        | Grassroots and amateur youth basketball tournaments                                           |
| Primary users        | Tournament organizers, coaches/team heads, scorekeepers, fans/parents                         |
| Course               | CS Elective 1 — System Fundamentals                                                           |
| Team members         | Rusty Japos, Jeward T. Mahinay                                                                |
| Semester on cover    | First Semester, 2025–202 (truncated in source; confirm the correct year)                      |
| Source document      | `Basketball_Tournament_System all for one.docx`                                               |
| Source size          | 24.7 MB, about 2,255 words, 62 embedded images                                                |
| Source last modified | 2026-10-06                                                                                    |
| Figma link           | https://www.figma.com/design/QDqY8HSXs7jCnqHbEbvDmi/HoopFlow?node-id=0-1&t=uzoC1HzX3UeMEv76-1 |
| Repository           | Not created yet                                                                               |
| Current phase        | Phase 0 — documentation repair and decisions                                                  |

### Product positioning

HoopFlow should not try to beat Challonge, LeagueApps, TeamSnap, or SportsEngine on generic bracket features. Those products already exist. The realistic wedge is the unglamorous local workflow:

- Cash and e-wallet payments.
- Age and residency eligibility checks.
- Roster verification and guardian consent.
- A schedule that respects court availability and rest between games.
- A public page that parents can actually use on a phone.

That is where grassroots organizers still use spreadsheets.

---

## 3. Problem and Vision

### Problem

Running a youth basketball tournament involves many manual and scattered tasks. Organizers commonly use spreadsheets, group texts, and paper brackets. The same problems recur:

- **Registration is error-prone.** Rosters, player eligibility, and payments are hard to track. Incomplete and duplicate sign-ups are common.
- **Brackets are manual and slow.** Organizers physically build brackets and update them after every game.
- **Scheduling is fragile.** Double-booked gyms, insufficient rest, and delays cascade through the day.
- **Rules are inconsistent.** Different leagues and venues do not standardize game rules.
- **Score reporting is manual.** Standings are updated by hand, and game statistics are often not recorded.
- **History is lost.** Player and team data are not preserved beyond the current tournament.

### Solution

A configurable tournament management system that automates the full lifecycle:

1. Teams register online.
2. The system generates a bracket.
3. The system generates a schedule that respects courts and rest.
4. Games are played.
5. A scorekeeper enters final results.
6. Winners advance automatically.
7. Teams and fans are notified.
8. Results and history are stored.

### Product principles

1. **Post-game entry before live tracking.** Entering final scores after the game is much simpler than live play-by-play. This is the right v1 choice.
2. **One source of truth.** Registration, schedule, results, and standings should not live in separate spreadsheets.
3. **Fairness is visible.** Publish seeding and tiebreaker rules. Keep an audit log. Do not rely on hidden manual decisions.
4. **Mobile-first for fans and coaches.** Most parents and coaches will use a phone at the venue.
5. **Simple before clever.** A modular monolith and a relational database are enough. No microservices, no Kubernetes, no native apps for v1.
6. **Privacy by default.** Youth players are minors. Collect only what is needed and record consent.

---

## 4. Users and Roles

### 4.1 Organizer / Tournament Manager

The primary user. Creates tournaments, configures divisions and rules, reviews registrations, verifies eligibility and payment, generates brackets and schedules, publishes announcements, and oversees the event.

**Current document status:** Covered by the Admin use case. The document also calls this role "Admin," "Committee," and "Tournament Manager." Pick one term: **Organizer**.

### 4.2 Coach / Team Head

Registers a team, submits the roster, uploads eligibility documents, pays or records the entry fee, views the schedule and standings, and manages the team profile.

**Current document status:** Covered by the Coach/Team Leader use case.

### 4.3 Scorekeeper / Official

Enters final scores and post-game statistics. Must be assigned to specific matches. Score edits must be audited.

**Current document status:** Listed as a stakeholder, but missing from the use cases, DFD, ERD, and wireframes. This is a **P0 gap**.

### 4.4 Fan / Parent / Guardian

Views public tournament pages: brackets, schedules, results, standings, teams, and rosters. Parents/guardians also consent to a minor's participation and may need to see their child's schedule.

**Current document status:** Covered by the Fan use case. Parents are mentioned in the survey and user stories but are not a distinct stakeholder.

### 4.5 Player

A minor participating in the tournament. Players usually do not need accounts. Their data is managed by the coach and verified by the organizer.

**Current document status:** Players appear in the ERD but have no birthdate or guardian fields. This is a **P0 gap**.

### 4.6 System Administrator

Technical administrator for accounts, backups, and configuration. This role may be the same person as the organizer in a small league, but the permissions should be separate.

**Current document status:** Not modeled.

### Role naming rule

Use these terms consistently in every document, screen, and table:

- **Organizer** (not Admin, Committee, or Tournament Manager)
- **Coach** (not Team Head or Team Leader)
- **Scorekeeper** (not Official, unless the person is also a referee)
- **Fan / Parent** (not Supporter or Audience)
- **Player**

---

## 5. Current Deliverables

The source document contains four deliverables.

### Deliverable 1 — Requirements Gathering

Contents:

- Problem statement.
- Solution and lifecycle.
- System objectives and goals.
- Stakeholder list and roles.
- Survey result (n = 50 claimed).
- Proof and snippets (survey tables and screenshots).
- User stories for organizer, coach, scorekeeper, and fan/parent.
- Use case diagrams for Admin, Coach/Team Leader, and Fans.
- Feasibility report (technical, operational, economic).

**Status:** Complete in coverage, incomplete in rigor. Needs the survey fix, NFRs, acceptance criteria, scope boundaries, risks, and a traceability matrix.

### Deliverable 2 — Wireframes, Flowcharts, and DFDs

Contents:

- Mobile wireframes: splash, login, admin, coach, fan.
- Flowcharts for Admin, Coach/Team Leader, and Fans.
- Context flow diagram.
- Data Flow Diagram Level 1.

**Status:** Good coverage. Needs readable layouts, system boundaries, consistent numbering, error/rejection paths, and a scorekeeper flow.

### Deliverable 3 — ERD

Contents:

- Entities: `users`, `teams`, `players`, `player_stats`, `tournaments`, `tournament_registrations`, `payments`, `brackets`, `matches`, `schedules`, `standings`, `announcements`, `fan_follows`, `game_odds`.
- Relationships described in prose.

**Status:** Good first pass. Missing venues/courts, divisions, referees, guardians, eligibility documents, notifications, and audit logs. Several derived fields are stored as source data.

### Deliverable 4 — System Architecture Diagram

Contents:

- Cross-platform mobile and desktop front ends.
- API layer.
- Web server and "HoopFlow Logic."
- File system for team logos.
- Relational database.

**Status:** Too thin to build from. Needs authentication/authorization, background jobs, object storage, backups, observability, and a deployment target.

---

## 6. Review Summary

### What is genuinely good

- The problem statement is specific and credible.
- The lifecycle one-liner gives the system a clear spine.
- Choosing post-game data entry over live tracking is mature scope control.
- The artifact set is complete for a fundamentals course.
- The ERD uses associative tables correctly for many-to-many relationships.
- Passwords are stored as hashes, not plaintext.
- The team clearly understands the domain and the organizer's pain.

### Verdict by area

| Area | Verdict |
|---|---|
| Problem and vision | Strong |
| Requirements | Good stories, but no NFRs, acceptance criteria, or traceability |
| Survey and feasibility | Weakest part; numbers do not reconcile; no cost, risk, or schedule analysis |
| UML and DFD | Right artifacts; craft and consistency issues |
| ERD | Good bones; missing eligibility, venues, notifications, audit |
| Architecture | Too thin for implementation; no auth, jobs, storage, or operations |
| Wireframes | Good coverage; missing states and a desktop layout |
| Writing and formatting | Needs a cleanup pass; typos and numbering issues |

### Submission risk

If a strict panel reviews the document as-is, the survey math is the most likely first challenge. The second is the missing scorekeeper. The third is the gap between the "conflict-free scheduling" promise and the schema.

---

## 7. Issue Register

**Important:** All issues below are **Open**. They were raised in review. This dossier records them; the source document and code have not been changed yet. When an issue is fixed, update its status to **Done** and add an entry to the [Change Log](#22-change-log).

### Severity definitions

| Severity | Meaning | When to fix |
|---|---|---|
| **P0** | Blocks submission credibility, or creates a security/ethical risk | Before the next submission or demo |
| **P1** | Blocks a credible build or a core feature | Before implementing that feature |
| **P2** | Quality, clarity, or polish | Before final submission or release |

### 7.1 Summary table

| ID | Severity | Area | Issue | Status |
|---|---|---|---|---|
| P0-01 | P0 | Evidence | Survey numbers do not reconcile with n = 50 | Open |
| P0-02 | P0 | Security / roles | Role model allows privilege escalation; scorekeeper is missing | Open |
| P0-03 | P0 | Eligibility / privacy | Age eligibility and guardian consent are not modeled | Open |
| P1-01 | P1 | Scheduling | Schema cannot enforce court conflicts or rest rules | Open |
| P1-02 | P1 | Brackets | Auto-advance has no bracket topology; "any format" is overpromised | Open |
| P1-03 | P1 | Data integrity | Standings, winner, and odds are derived but stored as source data | Open |
| P1-04 | P1 | Ethics / scope | "Game odds" is betting-adjacent in a youth product | Open |
| P1-05 | P1 | Notifications | "Notify" is promised but only announcements exist | Open |
| P1-06 | P1 | Traceability | Referee assignment and game rules are promised but not designed | Open |
| P1-07 | P1 | Payments | Payment ledger is incomplete for cash reconciliation | Open |
| P1-08 | P1 | Architecture | No auth, jobs, object storage, backups, or observability | Open |
| P1-09 | P1 | Privacy | No consent, retention, or public-display policy for minors | Open |
| P1-10 | P1 | Scope | Contradictions: post-game vs real-time, any format, standard rules, rigging detection | Open |
| P2-01 | P2 | Requirements | No NFRs, acceptance criteria, assumptions, risks, or traceability matrix | Open |
| P2-02 | P2 | UML / DFD | Missing system boundary, unlabeled include arrows, unbalanced DFD, huge flowcharts | Open |
| P2-03 | P2 | UX | Wireframes lack loading, empty, error, offline, and validation states | Open |
| P2-04 | P2 | Writing | Terminology drift and multiple typos | Open |
| P2-05 | P2 | Submission | 24.7 MB file with 16k-pixel diagrams | Open |
| P2-06 | P2 | Feasibility | No cost numbers, alternatives, risks, or schedule analysis | Open |
| P2-07 | P2 | Testing | No test plan or quality gate | Open |
| P2-08 | P2 | Formatting | Broken TOC letters, duplicate figure numbers, incomplete cover year | Open |
| P2-09 | P2 | Consistency | Architecture claims desktop, but wireframes are mobile-only | Open |

### 7.2 P0 issues

#### P0-01 — Survey data does not reconcile

**Area:** Requirements and evidence
**Severity:** P0
**Status:** Open

**Evidence:**

- The document claims **n = 50** respondents.
- The role table has 25%, 25%, 6.25%, 6.25%, 12.5%, and 25%. That sums to 100% only at **n = 16**. At n = 50, 25% is 12.5 people and 6.25% is 3.125 people, which is impossible.
- The Yes/No table includes 42.9% / 57.1% (implies n = 7), 62.5% / 37.5% (implies n = 8), and 87.5% / 12.5% (implies n = 8).
- The Likert table includes 14.3% / 28.6% / 57.1% (implies n = 7) and 42.9% (implies n = 7).
- Q2 says 100% agree that a digital system would reduce manual workload, while the Likert table says 14.3% **strongly disagree** with the same proposition.
- Q7's total is printed as **1000%**.
- "14..3%" and "42.3%" are typos.

**Impact:** The entire requirements justification rests on this survey. A panel will challenge the numbers before discussing the product. The inconsistency also makes it impossible to know which findings are real.

**Fix:**

1. Export the raw Google Forms response sheet.
2. Recompute every percentage from the raw data.
3. Report the denominator (`n`) for each question.
4. Do not mix pilot data with final data.
5. Remove or reconcile the contradictory Likert item.
6. Avoid leading "Will X improve...?" questions, or add a neutral option.
7. Add a short "Limitations" paragraph: sample composition, self-selection, and small sample size.
8. Add an appendix with the raw CSV, the form link, and the date collected.
9. If the data is illustrative, label it clearly as a **pilot/pretest**, not as n = 50.

**Done when:**

- Every percentage has a valid denominator.
- Every table sums to 100%.
- No two questions contradict each other.
- The raw data appendix exists.
- The document states the sample composition and limitations.

---

#### P0-02 — Role model allows privilege escalation; scorekeeper is missing

**Area:** Security, roles, fairness
**Severity:** P0
**Status:** Open

**Evidence:**

- The sign-up wireframe exposes a **Role** field. If Organizer or Admin is selectable, anyone can self-promote.
- The Admin use case includes "Manage in-game stats" → "Add team score and player stats" → "Update team score and player stats." The organizer is therefore entering scores for their own tournament.
- Scorekeepers/Officials are listed as stakeholders but do not appear in the use cases, DFD, ERD, or wireframes.
- The ERD models `users.role` as a single text field. A coach who is also a parent cannot hold both roles.
- There is no audit trail for score edits or role changes.

**Impact:**

- Privilege escalation: a user could register as an organizer.
- No separation of duties: the organizer controls registration, brackets, and scores.
- Unfair results and disputes.
- No way to investigate "who changed this score?"

**Fix:**

1. Approve the RBAC matrix in [Section 10.3](#103-role-permission-matrix).
2. Roles: **Organizer**, **Coach**, **Scorekeeper**, **System Admin**. Fans use public pages without an account.
3. Allow only Coach sign-up. Organizer and Scorekeeper accounts are provisioned by an admin or by invitation.
4. Enforce authorization on the server for every endpoint, not just in the UI.
5. Assign scorekeepers to specific matches.
6. Log every score edit, bracket override, schedule change, and role change in `audit_logs`.
7. Consider a two-step correction flow: scorekeeper submits, organizer approves a correction after the match is locked.

**Done when:**

- Sign-up cannot create an Organizer or Scorekeeper.
- Every protected endpoint returns 403 for the wrong role.
- A scorekeeper can enter a score only for assigned matches.
- Every score edit has an audit record.
- Role changes are audited.

---

#### P0-03 — Age eligibility and guardian consent are not modeled

**Area:** Eligibility, privacy, youth compliance
**Severity:** P0
**Status:** Open

**Evidence:**

- `tournaments` has an `age_limit`.
- `players` has only `player_id`, `team_id`, `first_name`, `last_name`, `jersey_number`, and `position`.
- There is no birthdate, guardian name, guardian contact, consent record, eligibility status, or supporting document.
- There is no division or age-group table. One bracket per tournament cannot represent U12 and U15 divisions.

**Impact:**

- The system cannot verify whether a player is eligible for an age group.
- A youth tournament cannot legally or ethically operate without guardian consent for minors' data.
- Public names, photos, and stats create privacy risk.
- Organizers will fall back to paper verification, which defeats the purpose.

**Fix:**

1. Add `players.birthdate`, `guardian_name`, `guardian_phone`, `consent_at`, `consent_version`, `eligibility_status`, `verified_by`, and `verified_at`.
2. Add `eligibility_documents` for birth certificates, school IDs, or residency proof.
3. Add `divisions` with `age_min`, `age_max`, and `gender` (if needed).
4. Add a roster-lock date after which coaches cannot edit the roster.
5. Add organizer verification screens and an audit trail.
6. Define a public-display policy: full name, first name + last initial, or jersey number only, depending on consent.
7. If the project is deployed in the Philippines, align with RA 10173 (Data Privacy Act of 2012).

**Done when:**

- The system rejects a player outside the division's age range.
- Guardian consent is recorded with a timestamp and version.
- The organizer can mark a player eligible or rejected with a reason.
- Public display follows the consent policy.

### 7.3 P1 issues

#### P1-01 — Scheduling schema cannot enforce court conflicts or rest rules

**Area:** Scheduling, data model
**Severity:** P1
**Status:** Open

**Evidence:**

- `schedules` stores `court_name` as free text.
- There are no `venues`, `courts`, `slots`, or `match_slots` tables.
- There are no constraints for minimum rest, maximum games per day, or court availability.
- `schedules` has both `tournament_id` and `match_id`; `tournament_id` is derivable through the match and can drift.
- The relationship from `matches` to `schedules` suggests multiple schedules per match, but there is no version or `is_current` flag.

**Impact:** The headline promise, "eliminate scheduling conflicts," cannot be enforced. Double bookings and unfair rest will still happen.

**Fix:**

1. Add `venues` (name, address, timezone).
2. Add `courts` (venue_id, name, availability window).
3. Add `match_slots` (match_id unique, court_id, start_at, end_at, status, version).
4. Add `court_blackouts` for unavailable periods.
5. Add tournament rules: `rest_minutes`, `max_games_per_day`, `slot_minutes`, `buffer_minutes`.
6. Add a scheduling service with hard constraints:
   - No court overlap.
   - No team in two matches at once.
   - Minimum rest between a team's matches.
   - A match cannot start before its feeder matches finish.
   - Only available courts and venue hours.
7. Use a deterministic algorithm so tests are repeatable. For 16 teams and 4 courts, a greedy scheduler plus local repair is enough.
8. Publish the schedule with a version; every manual change creates a new version and an audit entry.

**Acceptance criteria:**

> Given 16 teams, 4 courts, 30-minute slots, and 15 minutes of minimum rest, when the organizer generates the schedule, then no court has overlapping matches, no team has overlapping matches, and no team plays back-to-back without the minimum rest.

**Done when:** The scenario above passes automated tests, and a manual reschedule is audited.

---

#### P1-02 — Bracket auto-advance has no topology; "any format" is overpromised

**Area:** Brackets, data model
**Severity:** P1
**Status:** Open

**Evidence:**

- `matches` has `bracket_id`, `round_number`, `team1_id`, `team2_id`, scores, `winner_team_id`, and `status`.
- There is no `next_match_id`, `next_slot`, `loser_next_match_id`, or bracket-slot graph.
- `brackets` has one `format` field. The document claims "support any tournament format."
- The flowchart shows a double-elimination decision, but the schema cannot represent a losers' bracket.

**Impact:** Auto-advance will be manual. Double elimination and round robin cannot be generated correctly. "Any format" is an impossible promise for v1.

**Fix:**

1. Narrow v1 to **single elimination** and **round robin / pool play**.
2. Add `bracket_slots` (bracket_id, round_number, match_number, slot, source_type, source_match_id, source_outcome, team_id).
3. Alternatively, add `next_match_id`, `next_slot`, and `loser_next_match_id` to `matches`.
4. Add seeding and bye rules:
   - Bracket size = next power of two.
   - Top seeds receive byes.
   - A bye auto-advances the team.
5. Add manual override with an audit record.
6. Add double elimination later, behind a separate design.

**Done when:** A 16-team single-elimination bracket generates correctly with byes and auto-advance, and the winner of each match populates the correct next slot.

---

#### P1-03 — Derived data will drift

**Area:** Data integrity
**Severity:** P1
**Status:** Open

**Evidence:**

- `standings` stores `team_name`, `games_played`, `wins`, `losses`, `total_points_scored`, `total_points_conceded`, and `point_differential`.
- `matches.winner_team_id` is stored.
- `game_odds` stores computed win percentages.
- `standings.team_name` duplicates `teams.name`.

**Impact:** A score correction can leave standings and winners stale. Two sources of truth create disputes.

**Fix:**

1. Compute standings from matches as a database view or materialized view.
2. If materialized, add `computed_at` and a recompute job triggered by score changes.
3. Derive the winner from the scores, or validate `winner_team_id` with a constraint/trigger.
4. Remove `standings.team_name`; join to `teams`.
5. Define tiebreakers explicitly: head-to-head, point differential, points scored, then coin toss.
6. Add unique constraints where needed: one standings row per division/team, one stat row per match/player.

**Done when:** Changing a final score updates the bracket and standings in the same transaction or a verified job, and no stale row remains.

---

#### P1-04 — "Game odds" is betting-adjacent in a youth product

**Area:** Ethics, scope
**Severity:** P1
**Status:** Open

**Evidence:**

- `game_odds` table in the ERD.
- "View Odds" in the Coach and Fan use cases.
- "Generate Game Odds" and "Game Odds" data flows in the DFD.
- No requirement, user story, algorithm, or disclaimer explains it.

**Impact:** Betting-adjacent features in a youth tournament are an ethical and reputational risk. They also add scope with no validated user need.

**Fix:** Remove `game_odds` and every "odds" reference. If the team wants engagement, use "team comparison" or "win probability" with an explicit no-betting policy. The safer choice is to remove it entirely.

**Done when:** No odds references remain in the document, schema, UI, or API.

---

#### P1-05 — "Notify" is promised but only announcements exist

**Area:** Notifications
**Severity:** P1
**Status:** Open

**Evidence:**

- The lifecycle includes "Notify."
- User stories mention notifications.
- The ERD has `announcements` but no `notifications` or outbox table.
- The DFD has "Manage Notification," but it appears to mean announcements.

**Impact:** Teams and fans will not be informed of registration decisions, schedule changes, or results unless someone manually tells them.

**Fix:**

1. Add `notifications` (user_id, type, title, body, data_json, channel, status, sent_at, read_at).
2. Use an outbox pattern: create the notification in the same transaction as the event, then a worker sends it.
3. Start with in-app + email for transactional events.
4. Events: registration submitted, approved, rejected; payment recorded; bracket published; schedule published or changed; score finalized; announcement published.
5. Add notification preferences, with transactional messages always on.

**Done when:** Each event creates a notification row, delivery status is visible, and a schedule change notifies affected coaches.

---

#### P1-06 — Referee assignment and game rules are promised but not designed

**Area:** Traceability
**Severity:** P1
**Status:** Open

**Evidence:**

- The objectives mention "fairness in ... referee assignment."
- There is no referee actor, entity, use case, or screen.
- The problem mentions standard rules, but the only rule configuration is `elimination_rule`.
- The user story mentions "detect possible bracket rigging" and "alert unusual loss tendencies."

**Impact:** Goals without implementation are traceability gaps. "Rigging detection" is also ethically risky and technically vague, especially in youth sports.

**Fix:**

1. Either add `referees`, `match_officials`, availability, and assignment screens, or remove the referee goal.
2. Either add game rules (quarters, clock, fouls, overtime, tiebreakers) or remove the "standard rules" claim.
3. Replace "rigging detection" with an audit log and published seeding/tiebreaker rules. Do not accuse minors of throwing games.

**Done when:** Every goal maps to a feature, story, entity, and screen, or is removed from the document.

---

#### P1-07 — Payment ledger is incomplete for cash reconciliation

**Area:** Payments
**Severity:** P1
**Status:** Open

**Evidence:**

- `payments` has `payment_id`, `registration_id`, `amount`, `status`, and `paid_at`.
- There is no method, reference number, receipt number, currency, recorded_by, refund, or remarks.
- There is no coach "pay/submit receipt" use case.
- The problem statement mentions cash being paid in.

**Impact:** Cash and e-wallet reconciliation is the actual pain point. A single status field cannot answer "who paid, how, when, and who recorded it?"

**Fix:**

1. Add `method` (cash, GCash, Maya, bank transfer), `reference_no`, `receipt_no`, `currency`, `recorded_by`, `remarks`, `refunded_amount`, and `refunded_at`.
2. Allow multiple payments per registration for installments.
3. Never delete a payment row. Refunds are separate entries.
4. Add an organizer payment screen and a coach receipt upload.
5. Do not store card data. If a gateway is added later, use hosted checkout and store only the provider reference.

**Done when:** An organizer can record a cash payment and a GCash reference, the registration balance is correct, and the audit log shows who recorded it.

---

#### P1-08 — Architecture lacks auth, jobs, storage, backups, and observability

**Area:** Architecture and operations
**Severity:** P1
**Status:** Open

**Evidence:**

- The architecture diagram shows front end → API → web server → file system + database.
- There is no auth/authorization layer.
- There is no background worker for bracket generation, scheduling, or notifications.
- Team logos are stored on a local file system.
- There are no backups, logs, metrics, error tracking, or environments.
- "Web Server" and "HoopFlow Logic" are drawn separately, and the Request arrow bypasses the API box.
- The text claims mobile and desktop front ends, but the wireframes are mobile-only.

**Impact:** The system will be insecure, fragile, and hard to operate. Local files disappear on most PaaS redeploys. There is no recovery plan.

**Fix:**

1. Use a modular monolith: web app + API + worker + PostgreSQL + object storage.
2. Add authentication and RBAC.
3. Add a `jobs` table and a worker for bracket generation, schedule generation, notifications, and exports.
4. Use S3-compatible object storage for logos and documents.
5. Add daily backups and test a restore.
6. Add structured logs, error tracking, a health check, and an uptime monitor.
7. Add local, staging, and production environments.
8. Use a responsive web app/PWA. Do not build native desktop for v1.
9. State the monolith and single-region trade-offs explicitly.

**Done when:** The architecture diagram v2 is approved, staging is deployed, and a backup restore has been tested.

---

#### P1-09 — No privacy or consent program for minors

**Area:** Privacy and compliance
**Severity:** P1
**Status:** Open

**Evidence:**

- Players are minors.
- The document stores names, team logos, rosters, stats, and payments.
- There is no consent, retention, deletion, or public-display policy.
- The survey includes parents/guardians but the system does not model them.

**Impact:** Legal and ethical exposure. Parents may object to public names or photos. Data can be retained indefinitely by accident.

**Fix:**

1. Create a data map: what is collected, why, where it is stored, who can see it, and how long it is kept.
2. Record guardian consent for minors.
3. Minimize data: do not collect birth certificates unless required for eligibility.
4. Define public-display rules for names and photos.
5. Define retention and deletion after the season.
6. Encrypt data in transit and at rest.
7. Add a privacy policy and terms page.
8. If deployed in the Philippines, align with RA 10173.

**Done when:** A privacy policy exists, consent is recorded, and a deletion request can be fulfilled.

---

#### P1-10 — Scope contradictions

**Area:** Scope
**Severity:** P1
**Status:** Open

**Evidence:**

- Objective: "favor post-game data entry over real-time tracking."
- DFD description: "real time tournament match results ... continuously broadcast to Fans."
- DFD Level 1: "Manage In-game stats."
- Flowchart: online/offline in-game stats.
- Objective: "support any tournament format."
- Problem statement: "it's tough to standardize different leagues."
- User story: "detect possible bracket rigging."

**Impact:** The reader cannot tell what is in scope. The build team will chase features that contradict the chosen architecture.

**Fix:**

1. Write a one-page scope statement with **In v1**, **Later**, and **Out**.
2. Choose post-game entry for v1.
3. Choose single elimination + round robin for v1.
4. Remove "any format," "standard rules," "real-time," and "rigging detection" unless they have a concrete design.
5. Make every artifact use the same scope language.

**Done when:** The scope statement is approved and every deliverable matches it.

### 7.4 P2 issues

- **P2-01 Requirements rigor.** Add NFRs, acceptance criteria, assumptions, constraints, out-of-scope, risks, and a traceability matrix: goal → story → use case → DFD process → entity → screen. The matrix will expose the missing scorekeeper, notifications, referee, and eligibility pieces.
- **P2-02 UML/DFD craft.** Add a system boundary to the use case diagrams. Label `«include»` and `«extend»` arrows or remove use-case-to-use-case arrows. Number every DFD process and data store. Balance the context diagram and Level 1. Split the admin flowchart into four subflows on landscape pages. Add rejection, payment pending, postponed, and forfeit paths.
- **P2-03 Wireframe states.** Show loading, empty, error, offline, validation, and confirmation states. Fix the sign-up role field. Add "Add Player" with jersey, position, age, and eligibility badge. Add bracket zoom/scroll, date/venue filters, and a desktop layout if desktop is claimed. Check contrast and 44px tap targets.
- **P2-04 Terminology and typos.** Fix "youdouble," "paper bracket," "Don't have and account?", "Announcment," "Toogle," "PLayer," "Tournament Tournament," "1000%," "14..3%," "42.3%," "Commy," "Manage Manage," and "Payed." Apply the glossary in [Section 20](#20-glossary).
- **P2-05 File size.** The document is 24.7 MB. The three large diagrams are 6-7 MB at 16k pixels. Resize to about 2,000 px wide and export a PDF under 10 MB.
- **P2-06 Feasibility depth.** Add development hours (for example, 2 people × 15 weeks × 10 hours = 300 hours), hosting/domain/SMS costs, break-even, build-vs-buy, a risk register, and a milestone schedule. The current feasibility report has no numbers.
- **P2-07 Testing.** Add a test plan: unit tests for brackets, scheduling, standings, and RBAC; integration tests for the lifecycle; end-to-end tests for the four roles; a manual demo checklist.
- **P2-08 Formatting.** The cover says `2025-202`; finish the year. The TOC jumps from F to J. There are two "Figure 2.3" labels. The ERD is "Figure 4.3" inside Deliverable 3. Use one numbering scheme.
- **P2-09 Desktop consistency.** Either add desktop wireframes or change the architecture claim to "responsive web/PWA."

---

## 8. Decisions Log

| ID | Decision | Status | Notes |
|---|---|---|---|
| D-001 | Product name is **HoopFlow** | Accepted | Use consistently |
| D-002 | Target grassroots youth basketball tournaments | Accepted | Not professional leagues |
| D-003 | Post-game score entry for v1 | Recommended | The document contradicts itself; confirm. Live view is optional in v1.1. |
| D-004 | v1 formats: single elimination + round robin/pool play | Recommended | Add double elimination later |
| D-005 | Fans use public pages without accounts | Recommended | Reduces scope and privacy risk |
| D-006 | Remove game odds | Recommended | Betting-adjacent in youth sports |
| D-007 | Payments are manual record-only in v1 | Recommended | Add a gateway later |
| D-008 | One deployment serving one league/club in v1 | Recommended | Multi-tenant SaaS later; confirm |
| D-009 | Tech stack | Pending | Needs Jeward's skills and preference |
| D-010 | Deployment target | Pending | Decide after stack |
| D-011 | Offline score entry | Deferred | Online-only v1; paper backup |
| D-012 | Referee assignment | Deferred | Unless required by the rubric |
| D-013 | Dynamic rescheduling | Deferred | Manual reschedule with audit in v1 |
| D-014 | Native desktop app | Deferred | Responsive web/PWA covers desktop |
| D-015 | Fan follows/accounts | Deferred | Public pages first |
| D-016 | Live play-by-play | Deferred | Post-game entry only |
| D-017 | Live score view | Deferred to v1.1 | Optional lightweight polling scoreboard; no game clock or play-by-play. Full live entry is not in v1. |

---

## 9. Scope

### 9.1 In scope for v1

- Authentication and RBAC: Organizer, Coach, Scorekeeper; public fan pages.
- Tournament setup: name, dates, division/age group, venue, courts, format.
- Team registration and roster submission.
- Player birthdate, guardian consent, eligibility status, and documents.
- Organizer approval/rejection of registrations.
- Manual payment recording with method, reference, receipt, and audit.
- Single-elimination bracket generation with seeding and byes.
- Round-robin/pool-play standings (if included in the first release).
- Court and time-slot scheduling with conflict and rest checks.
- Scorekeeper final-score entry with audit.
- Automatic winner advancement and standings updates.
- Public tournament page: bracket, schedule, results, standings, teams, rosters.
- Announcements and basic in-app/email notifications.
- Audit log for score edits, bracket overrides, schedule changes, and role changes.
- Admin: user management, backups, health check.

### 9.2 Later

- Double elimination.
- Payment gateway (GCash/Maya/Stripe).
- Referee assignment and availability.
- Dynamic rescheduling.
- Email/SMS/push preferences.
- Fan accounts and follows.
- Advanced statistics (minutes, FG%, 3P%, FT%, turnovers, plus/minus).
- CSV/PDF exports.
- Multi-venue and multi-league support.
- Offline score entry with sync.
- Lightweight live score view (polling, quarter-break updates, no game clock).
- Native mobile app.
- Photo galleries and social sharing.

### 9.3 Out of scope

- Betting or odds.
- Live streaming or video.
- Full live score entry with a game clock, play-by-play, or shot tracking.
- Rigging detection or "unusual loss" alerts.
- Full accounting, payroll, or refunds beyond simple payment records.
- Public chat or social network.
- Multi-language UI.
- Kubernetes or microservices.

### 9.4 Scope guardrail

Any new idea goes into a **Later** list first. It does not enter the current phase unless the current phase is complete and the decision is recorded in the Decisions Log.

---

## 10. Target Product Design

### 10.1 Core lifecycle

1. **Organizer creates a tournament.** Name, dates, division, venue, courts, format, rules, entry fee.
2. **Coach registers a team.** Team profile, roster, player birthdates, guardian consent, documents, payment reference.
3. **Organizer reviews.** Eligibility, roster, payment. Approve or reject with a reason.
4. **Organizer generates the bracket.** Seeded or random, byes, published.
5. **Organizer generates the schedule.** Courts, slots, rest, dependencies, published.
6. **Scorekeeper enters the final score.** The system validates, advances the winner, and updates standings.
7. **Public pages update.** Bracket, schedule, results, standings, rosters.
8. **Notifications go out.** Registration status, schedule changes, results, announcements.
9. **Organizer closes the tournament.** Export results, archive data per retention policy.

### 10.2 Core workflows

#### Workflow A — Registration

1. Coach signs up and creates a team.
2. Coach selects an open tournament and division.
3. Coach adds players: name, birthdate, jersey number, position, guardian contact.
4. Guardian consent is recorded.
5. Coach uploads eligibility documents (optional).
6. Coach submits the registration.
7. Organizer reviews eligibility and payment.
8. Organizer approves or rejects with a reason.
9. Coach receives a notification.
10. Approved teams appear in the bracket pool.

#### Workflow B — Bracket generation

1. Organizer selects the division and the approved teams.
2. Organizer chooses seeded or random.
3. System builds a bracket of the next power of two.
4. Top seeds receive byes.
5. Organizer previews and publishes.
6. Manual changes are allowed before publishing and are audited.

#### Workflow C — Scheduling

1. Organizer sets venue hours, courts, slot length, buffer, and minimum rest.
2. System assigns every match to a court and slot.
3. System checks hard constraints.
4. Organizer previews, adjusts manually if needed, and publishes.
5. A schedule change creates a new version and notifies affected teams.

#### Workflow D — Score entry

1. Scorekeeper opens an assigned match.
2. Scorekeeper enters the final score and optional stats.
3. System validates the score and marks the match complete.
4. Winner advances to the next bracket slot.
5. Standings recompute.
6. A correction after lock requires an organizer and is audited.

### 10.3 Role-permission matrix

Legend: **Yes** = allowed; **No** = denied; **Own** = only their own team/registration; **Assigned** = only assigned matches.

| Action | Public/Fan | Coach | Scorekeeper | Organizer |
|---|---|---|---|---|
| View public tournament pages | Yes | Yes | Yes | Yes |
| Create/edit tournament | No | No | No | Yes |
| Configure divisions, venues, courts, rules | No | No | No | Yes |
| Register a team | No | Yes | No | No |
| Edit own team profile | No | Own | No | Yes |
| Add/edit roster before lock | No | Own | No | Yes |
| Verify eligibility | No | No | No | Yes |
| Record payment | No | No | No | Yes |
| Approve/reject registration | No | No | No | Yes |
| Generate/edit bracket | No | No | No | Yes |
| Generate/edit schedule | No | No | No | Yes |
| Enter final score | No | No | Assigned | Yes, with audit |
| Edit a locked score | No | No | No | Yes, with audit |
| View audit log | No | No | No | Yes |
| Manage users and roles | No | No | No | System Admin |
| Publish announcement | No | No | No | Yes |
| View own payment status | No | Own | No | Yes |
| View all payments | No | No | No | Yes |

### 10.4 Scheduling rules

Hard constraints:

1. A match occupies exactly one court and one time slot.
2. No court has overlapping matches.
3. No team has overlapping matches.
4. A team gets at least the configured rest between matches.
5. A team does not exceed the configured maximum games per day.
6. A match cannot start before its feeder matches finish.
7. Matches only use available courts and venue hours.
8. A team cannot play in two divisions at the same time.

Soft constraints (nice to have):

- Keep a team's games on the same court when possible.
- Avoid late-night slots for younger divisions.
- Balance the number of games per court.
- Leave a buffer for overtime.

Algorithm approach:

- Build the dependency graph from the bracket.
- Sort matches by round and dependency depth.
- Assign the earliest feasible slot on an available court.
- Use backtracking or local repair for conflicts.
- Use a deterministic seed so tests reproduce the same schedule.

### 10.5 Bracket rules

Single elimination:

- Bracket size = next power of two greater than or equal to the number of teams.
- Seeds: 1 vs N, 2 vs N-1, and so on.
- Byes go to the top seeds.
- A bye auto-advances the team.
- The winner populates `next_match_id` and `next_slot`.
- Third-place match is optional.

Round robin:

- Use the circle method.
- Each team plays every other team in the pool.
- Standings use wins, head-to-head, point differential, points scored, then coin toss.
- If teams are split into pools, the top teams advance to a playoff bracket.

Double elimination (later):

- Winners' bracket, losers' bracket, and grand final.
- Grand final reset if the losers' bracket team wins the first final.
- Requires a separate bracket topology design.

### 10.6 Payments

v1 is manual recording, not a payment gateway.

Fields:

- `registration_id`
- `amount`
- `currency` (default PHP if applicable)
- `method` (cash, GCash, Maya, bank transfer)
- `reference_no`
- `receipt_no`
- `status` (pending, partial, paid, refunded, waived)
- `paid_at`
- `recorded_by`
- `remarks`
- `created_at`

Rules:

- Multiple payments per registration are allowed.
- Never delete a payment. Refunds are separate negative entries.
- Do not store card numbers.
- If a gateway is added later, store only the provider reference and use hosted checkout.
- Show the balance to the coach and the organizer.

### 10.7 Notifications

Channels for v1:

- In-app notification center.
- Email for transactional events.

Events:

- Registration submitted.
- Registration approved or rejected.
- Payment recorded.
- Bracket published.
- Schedule published or changed.
- Score finalized.
- Announcement published.

Rules:

- Transactional notifications cannot be opted out of.
- Marketing notifications are opt-in.
- Every notification has a delivery status.
- A schedule change notifies only affected teams.

### 10.8 Privacy and consent

- Collect the minimum data needed for eligibility and operations.
- Record guardian consent for every minor.
- Record the consent version and timestamp.
- Allow a guardian to request access, correction, or deletion.
- Define a retention period after the season.
- Publish a privacy policy and terms page.
- Restrict public display of names and photos based on consent.
- Encrypt data in transit and at rest.
- Limit admin access and audit every sensitive action.
- If deployed in the Philippines, align with RA 10173 (Data Privacy Act of 2012).

### 10.9 Live score view vs live score entry

These are different features, and they have very different costs:

| Feature | What it means | Complexity | v1 decision |
|---|---|---|---|
| **Live score view** | A public page shows the current score and refreshes automatically | Low | Not in v1; optional v1.1 |
| **Live score entry** | A scorekeeper updates the score during play, possibly with quarter and clock controls | Medium | Not in v1 |
| **Live play-by-play** | Every event, game clock, fouls, timeouts, shot charts | High | Out of scope |

**Recommendation:** keep post-game final-score entry for v1. If we want a "live" moment for the demo, build the lightweight live score view after Phase 6:

- The scorekeeper updates the score at quarter breaks or timeouts.
- The public page polls every 20-30 seconds.
- No game clock, no play-by-play, no WebSocket.
- One assigned scorekeeper per match.
- Every update is audited.
- Use `matches.home_score`, `matches.away_score`, `matches.status`, and add `matches.score_updated_at` plus `matches.version` for optimistic concurrency.
- Endpoint: `GET /api/public/tournaments/:slug/live` for the public view.
- Endpoint: `PATCH /api/matches/:id/score` for the assigned scorekeeper.
- Pause polling when the browser tab is hidden.

**Why full live score entry is hard:**

- The game clock must stay in sync with the venue and the server.
- Rapid writes create race conditions and correction conflicts.
- Undo and correction flows are much harder during play.
- Venue connectivity is often poor, which pushes toward offline sync.
- The UI needs score, fouls, timeouts, possession, and period states.
- Testing time-sensitive behavior is slow and flaky.
- Disputes require a stronger audit trail.

**Rule:** do not build live score until Phases 1-6 are stable. It is a demo enhancement, not a core feature. A read-only scoreboard that refreshes every 30 seconds is days of work; full live entry is weeks.

---

## 11. Target Data Model

This is the target v2 model. It is a design direction, not final DDL. The current ERD is in the source document.

### 11.1 Identity and access

| Table | Purpose | Key fields and notes |
|---|---|---|
| `organizations` | Optional tenant/league | id, name, slug, settings, created_at |
| `users` | All accounts | id, email unique, password_hash, first_name, last_name, phone, status, email_verified_at, created_at, updated_at |
| `roles` | Role definitions | id, key (organizer, coach, scorekeeper, admin), name |
| `user_roles` | Multi-role support | user_id, role_id, organization_id nullable, scope, unique(user_id, role_id, scope) |
| `sessions` | Session records if not using an auth provider | id, user_id, token_hash, expires_at, ip, user_agent |
| `password_reset_tokens` | Password reset | id, user_id, token_hash, expires_at, used_at |

### 11.2 Tournament structure

| Table | Purpose | Key fields and notes |
|---|---|---|
| `tournaments` | Tournament header | id, organization_id, name, slug, description, start_date, end_date, status, rules_json, created_by |
| `divisions` | Age/gender divisions | id, tournament_id, name, age_min, age_max, gender, max_teams, entry_fee, currency |
| `venues` | Physical venues | id, name, address, timezone |
| `courts` | Courts inside a venue | id, venue_id, name, notes |
| `court_blackouts` | Unavailable periods | id, court_id, start_at, end_at, reason |
| `game_rules` | Rule configuration | id, tournament_id/division_id, quarter_minutes, overtime_minutes, foul_limit, rest_minutes, max_games_per_day, tiebreakers_json |

### 11.3 Teams and eligibility

| Table | Purpose | Key fields and notes |
|---|---|---|
| `teams` | Team profile | id, name, slug, logo_url, coach_user_id, created_at |
| `team_memberships` | Coaches/managers on a team | id, team_id, user_id, role, status, unique(team_id, user_id) |
| `players` | Player profile | id, team_id, first_name, last_name, birthdate, jersey_number, position, photo_url, guardian_name, guardian_phone, consent_at, consent_version, eligibility_status, verified_by, verified_at |
| `eligibility_documents` | Supporting documents | id, player_id, type, file_url, status, reviewed_by, reviewed_at, notes |
| `registrations` | Team entry into a tournament/division | id, tournament_id, division_id, team_id, status, submitted_at, decided_by, decided_at, rejection_reason, seed, roster_locked_at, unique(tournament_id, team_id) |
| `payments` | Payment ledger | id, registration_id, amount, currency, method, reference_no, receipt_no, status, paid_at, recorded_by, remarks, refunded_amount, refunded_at, created_at |

### 11.4 Brackets and matches

| Table | Purpose | Key fields and notes |
|---|---|---|
| `brackets` | Bracket header | id, division_id, name, format (single_elim, round_robin), seeding_method, status, generated_at |
| `bracket_slots` | Bracket topology | id, bracket_id, round_number, match_number, slot, source_type (seed, match, bye), source_match_id, source_outcome (winner, loser), team_id |
| `matches` | Individual match | id, bracket_id, round_number, match_number, home_team_id, away_team_id, home_score, away_score, winner_team_id, status, completed_at, forfeit_reason, created_at |
| `match_slots` | Court and time assignment | id, match_id unique, court_id, start_at, end_at, status, version, published_at |
| `match_officials` | Referee assignment (later) | id, match_id, referee_id, role |
| `referees` | Referee profile (later) | id, user_id nullable, name, phone, certification, status |

### 11.5 Statistics and standings

| Table | Purpose | Key fields and notes |
|---|---|---|
| `player_stats` | Post-game stats | id, match_id, player_id, team_id, points, assists, rebounds, steals, blocks, fouls, unique(match_id, player_id) |
| `standings` | Computed standings | View or materialized view: division_id, team_id, games_played, wins, losses, points_for, points_against, point_diff, rank, computed_at |
| `game_odds` | Removed | Do not use |

### 11.6 Communication and audit

| Table | Purpose | Key fields and notes |
|---|---|---|
| `announcements` | Broadcast messages | id, tournament_id nullable, title, body, audience, status, published_at, created_by |
| `notifications` | Per-user notifications | id, user_id, type, title, body, data_json, channel, status, sent_at, read_at |
| `audit_logs` | Append-only audit trail | id, actor_user_id, action, entity_type, entity_id, before_json, after_json, ip, user_agent, created_at |
| `fan_follows` | Deferred | Optional; do not build in v1 |

### 11.7 Key constraints

- `users.email` is unique.
- `registrations` is unique on `(tournament_id, team_id)`.
- `player_stats` is unique on `(match_id, player_id)`.
- Scores are non-negative.
- `start_at < end_at` on every slot.
- Prevent overlapping court slots with an application transaction or a PostgreSQL exclusion constraint using `tstzrange`.
- Financial and audit rows use `ON DELETE RESTRICT`; dependent child rows may cascade.
- Every mutable table has `created_at` and `updated_at`.
- `audit_logs` is append-only.
- `winner_team_id` must match the score or be derived.
- Standings are computed, not manually edited.

### 11.8 Migration note

Do not build the final schema all at once. Create migrations in the order of the roadmap, starting with users, tournaments, divisions, teams, players, registrations, brackets, matches, and match slots. Add payments, stats, notifications, and audit when their phases begin.

---

## 12. Target Architecture

### 12.1 Recommended shape

A **modular monolith** with a separate worker process.

```text
[Browser / PWA]
      |
      | HTTPS
      v
[Web app + API] ---- [Auth / RBAC]
      |       \
      |        +---- [Object storage: logos, documents]
      |
      +---- [PostgreSQL]
      |
      +---- [Worker: jobs, notifications, exports]
```

Why this shape:

- Two developers can build and operate it.
- One deployable unit is easier to debug.
- A worker keeps bracket/schedule generation and email out of the request cycle.
- PostgreSQL handles the relational data and constraints well.
- Object storage avoids data loss on redeploys.

### 12.2 Stack options

Pick one and stay with it. The best stack is the one the team can finish.

**Option A — TypeScript (recommended if comfortable with JS/TS)**

- Frontend: Next.js + React + Tailwind CSS.
- Backend: Next.js route handlers or a small NestJS/Express service.
- Database: PostgreSQL (Supabase or Neon).
- ORM: Prisma or Drizzle.
- Auth: Supabase Auth or Auth.js.
- Storage: Cloudflare R2 or Supabase Storage.
- Jobs: `jobs` table + cron, or BullMQ + Redis.
- Hosting: Vercel or Render.
- Errors/logs: Sentry + structured logs.

**Option B — PHP/Laravel (recommended if the team knows PHP)**

- Laravel + Blade/Livewire or Inertia + Vue/React.
- MySQL or PostgreSQL.
- Laravel Breeze for auth.
- Queues + scheduler for jobs.
- S3-compatible storage.
- Render, Railway, or a VPS with Forge.

**Option C — Python/Django (recommended if the team knows Python)**

- Django + templates/HTMX or Django REST Framework + React.
- PostgreSQL.
- Celery + Redis for jobs.
- S3-compatible storage.
- Render or Railway.

**Do not use for v1:** microservices, Kubernetes, GraphQL, event sourcing, native mobile apps, or a separate desktop app. They add cost without helping the demo.

### 12.3 Environments

- **Local:** Docker Compose or a local Postgres, seed data, hot reload.
- **Staging:** production-like, used for demos and migration tests.
- **Production:** managed Postgres, object storage, backups, monitoring.

### 12.4 Security checklist

- Hash passwords with bcrypt or Argon2.
- Use secure, HTTP-only cookies or short-lived tokens.
- Enforce CSRF protection on state-changing requests.
- Validate input on the server.
- Rate-limit login and registration.
- Restrict file uploads by type and size.
- Use least-privilege database credentials.
- Keep secrets in environment variables, never in the repo.
- Force HTTPS.
- Audit score edits, bracket overrides, schedule changes, and role changes.
- Keep dependencies updated.

### 12.5 Operations checklist

- Daily database backups; test a restore monthly.
- Error tracking with Sentry or equivalent.
- Structured logs with request IDs.
- Health check endpoint.
- Uptime monitor for the public pages.
- Migration rollback plan.
- Seed script for demo data.

### 12.6 Performance checklist

- Index foreign keys and date/time columns.
- Paginate public lists.
- Optimize and cache team logos.
- Cache public tournament pages for a short TTL.
- Avoid N+1 queries in brackets and standings.
- Generate brackets/schedules in a worker for large divisions.

---

## 13. Non-Functional Requirements

These are draft targets. Confirm them with the team and the rubric.

| Area | Draft target |
|---|---|
| Availability | 99.5% during tournament weekends |
| Public read latency | p95 under 500 ms |
| Write latency | p95 under 1 second |
| Bracket generation | Under 10 seconds for 32 teams |
| Schedule generation | Under 30 seconds for 32 teams, 8 courts |
| Scale | 1,000 teams, 5,000 players, 200 concurrent users |
| Browser support | Modern mobile browsers, low-end Android |
| Accessibility | WCAG 2.1 AA basics; 44px tap targets; readable contrast |
| Backup | Daily; RPO 24 hours; RTO 4 hours |
| Security | OWASP Top 10 basics; RBAC; audit log |
| Privacy | Guardian consent; retention policy; RA 10173 if PH |
| Maintainability | Core logic tested; migrations; documentation |
| Localization | English only for v1 |

---

## 14. Testing Strategy

### 14.1 Unit tests

- Bracket generation for 2, 3, 5, 8, 16, and 17 teams.
- Bye placement and auto-advance.
- Seeding order.
- Round-robin pairings.
- Standings and tiebreakers.
- Scheduling constraints: court overlap, team overlap, rest, dependencies.
- RBAC: each role can and cannot perform the expected actions.
- Payment balance calculations.
- Eligibility age calculation at the tournament date.

### 14.2 Integration tests

- Registration → approval → bracket → schedule → score → standings.
- Payment recorded → registration balance updated.
- Schedule change → notification created.
- Score correction → bracket and standings recomputed.

### 14.3 End-to-end tests

- Organizer creates a tournament and publishes it.
- Coach registers a team and submits a roster.
- Organizer approves the team and generates the bracket.
- Organizer generates and publishes the schedule.
- Scorekeeper enters a result.
- Public page shows the updated bracket and standings.
- A coach cannot enter a score.

### 14.4 Test data

- 16 teams.
- 4 courts.
- 30-minute slots.
- 15-minute minimum rest.
- One U12 division and one U15 division.
- One team with a bye.
- One forfeit.
- One score correction.

### 14.5 Definition of done

A feature is done when:

- Acceptance criteria are met.
- Unit and integration tests pass.
- Lint, typecheck, and build pass.
- The happy path and at least one error path are manually verified.
- Responsive behavior is checked on a phone-sized viewport.
- Audit events are verified.
- The dossier and issue register are updated.

---

## 15. Development Roadmap

The plan is deliberately slow and steady. One phase at a time. Do not start the next phase until the current phase's exit criteria are met.

### Phase 0 — Documentation repair and decisions (1-2 weeks)

**Goal:** Make the academic document and the product plan consistent before writing code.

Tasks:

- [ ] Reconcile the survey data or label it as a pilot.
- [ ] Add the raw-data appendix.
- [ ] Approve the glossary and apply it everywhere.
- [ ] Approve the RBAC matrix.
- [ ] Approve the v2 data model.
- [ ] Approve the v1 scope and defer list.
- [ ] Choose the stack (D-009).
- [ ] Choose the deployment target (D-010).
- [ ] Create the repository, README, issue tracker, and CI skeleton.
- [ ] Add NFRs, acceptance criteria, assumptions, risks, and a traceability matrix.
- [ ] Compress the document to under 10 MB.
- [ ] Fix the cover year, TOC letters, figure numbers, and typos.

**Exit criteria:** The document is internally consistent; the decisions log has no blocking "Pending" items; the repository exists.

### Phase 1 — Walking skeleton (2 weeks)

**Goal:** A deployed app with authentication and one protected page.

Tasks:

- [ ] Create the app shell and design tokens.
- [ ] Set up PostgreSQL and migrations.
- [ ] Implement User, Role, and UserRole.
- [ ] Implement sign-up (Coach only) and login.
- [ ] Seed an Organizer account.
- [ ] Add role guards and a 403 page.
- [ ] Add health check, logging, and error tracking.
- [ ] Add CI: lint, typecheck, test, build.
- [ ] Deploy to staging.
- [ ] Write the first end-to-end test: login and access a protected page.

**Exit criteria:** A coach can sign up, log in, and see a protected dashboard; an organizer account is seeded; staging is live.

### Phase 2 — Tournament setup and public shell (2 weeks)

**Goal:** An organizer can create a tournament and the public can view it.

Tasks:

- [ ] Tournament, Division, Venue, and Court models.
- [ ] Organizer create/edit tournament.
- [ ] Configure division age range, format, entry fee, and rules.
- [ ] Public tournament page.
- [ ] Publish/unpublish behavior.
- [ ] RBAC tests.

**Exit criteria:** Organizer creates a U15 tournament with 4 courts; the public page shows the correct details; a coach cannot edit it.

### Phase 3 — Registration, roster, and eligibility (2-3 weeks)

**Goal:** A coach can register a team, and an organizer can approve it.

Tasks:

- [ ] Team and TeamMembership models.
- [ ] Player model with birthdate, guardian, consent, eligibility.
- [ ] Registration model and status workflow.
- [ ] Coach registration form and roster editor.
- [ ] Eligibility document upload.
- [ ] Organizer review screen.
- [ ] Manual payment recording.
- [ ] Notifications for submitted/approved/rejected.
- [ ] Audit events.
- [ ] Tests for age validation and duplicate registration.

**Exit criteria:** End-to-end registration works; an ineligible player is rejected; a duplicate team registration is blocked; the organizer can approve and record a payment.

### Phase 4 — Bracket generation (2 weeks)

**Goal:** Generate and publish a correct single-elimination bracket.

Tasks:

- [ ] Bracket and BracketSlot models.
- [ ] Seeding and bye logic.
- [ ] Bracket generation service and unit tests.
- [ ] Organizer preview and publish.
- [ ] Manual override with audit.
- [ ] Public bracket view with zoom/scroll.
- [ ] Auto-advance on score.

**Exit criteria:** A 16-team bracket generates correctly; byes advance; the public view matches the organizer view.

### Phase 5 — Scheduling (2-3 weeks)

**Goal:** Generate a conflict-free schedule for courts and teams.

Tasks:

- [ ] MatchSlot model and court availability.
- [ ] Scheduling constraints and service.
- [ ] Schedule generation with deterministic output.
- [ ] Conflict and rest validation.
- [ ] Organizer preview, manual adjustment, and publish.
- [ ] Public schedule view with date/team filters.
- [ ] Schedule-change notifications.
- [ ] Tests for the 16-team/4-court scenario.

**Exit criteria:** The acceptance criteria in [P1-01](#p1-01--scheduling-schema-cannot-enforce-court-conflicts-or-rest-rules) pass; a manual change creates a new version and notifies affected teams.

### Phase 6 — Scorekeeping and standings (2-3 weeks)

**Goal:** A scorekeeper can enter a result and the bracket and standings update.

Tasks:

- [ ] Scorekeeper assignment.
- [ ] Score entry form with validation.
- [ ] Match completion and winner derivation.
- [ ] Bracket auto-advance.
- [ ] Standings view or materialized view.
- [ ] Score correction with organizer approval and audit.
- [ ] Public results and standings.
- [ ] Tests for corrections and tiebreakers.

**Exit criteria:** A score updates the bracket and standings; a coach cannot enter a score; a correction is audited.

### Phase 7 — Notifications and announcements (1-2 weeks)

**Goal:** Users are informed without manual messaging.

Tasks:

- [ ] Notification model and outbox.
- [ ] In-app notification center.
- [ ] Email provider for transactional messages.
- [ ] Announcement publisher with audience targeting.
- [ ] Notification preferences.
- [ ] Delivery status and retry.
- [ ] Tests for event-to-notification mapping.

**Exit criteria:** Registration, schedule change, and score events create notifications; delivery is logged.

### Phase 8 — Hardening and demo (2 weeks)

**Goal:** A reliable demo and a submission-ready package.

Tasks:

- [ ] End-to-end test suite.
- [ ] Security review against the checklist.
- [ ] Backup and restore test.
- [ ] Performance check with seed data.
- [ ] Accessibility and responsive check.
- [ ] Privacy policy and consent screens.
- [ ] Documentation update.
- [ ] Demo seed script and rehearsal.
- [ ] Known-issues list.

**Exit criteria:** The demo runs clean from start to finish; the checklist is complete; the document and dossier are current.

### Optional extension after Phase 8 — Lightweight live scoreboard (v1.1)

**Goal:** A public score view that looks live without the cost of full live score entry.

**Precondition:** Phases 1-6 are stable. Do not start this before scorekeeping and standings work.

Tasks:

- [ ] Add `matches.score_updated_at` and `matches.version`.
- [ ] Add a scorekeeper-only score update endpoint with an assignment check and audit.
- [ ] Add a public live-score endpoint that returns only in-progress matches.
- [ ] Add a public UI that polls every 20-30 seconds and pauses when the tab is hidden.
- [ ] Add optimistic concurrency: reject stale updates with a version mismatch.
- [ ] Test a score update, a stale update, and an unauthorized update.
- [ ] Add the live view to the demo only if it is stable.

**Exit criteria:** A score update appears on the public page within 30 seconds; there is no game clock or play-by-play; every update is audited.

### Timeline estimate

Part-time, two students: about **14-18 weeks**. The estimate assumes 8-12 hours per person per week and no major scope changes. School requirements take priority; adjust the timeline rather than skipping quality gates.

---

## 16. Working Agreement

1. **One vertical slice at a time.** Do not build three features halfway.
2. **Every feature has an issue ID and acceptance criteria.**
3. **Tests first for logic-heavy code.** Brackets, scheduling, standings, and RBAC must have automated tests.
4. **Update this dossier after every phase.**
5. **New ideas go to the Later list.** They do not interrupt the current phase.
6. **Record decisions.** Use the Decisions Log now; create `docs/decisions/ADR-XXXX.md` when the repository exists.
7. **Commit messages:** `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`.
8. **Weekly review:** 30 minutes. Demo what works, list blockers, choose the next slice.
9. **No hidden scope.** If a task grows beyond its phase, stop and update the plan.
10. **Keep academic deliverables separate from product truth.** The source document is for class; this dossier is for development. They should agree, but they serve different purposes.

---

## 17. Immediate Next Actions

| # | Action | Owner | Done when |
|---|---|---|---|
| 1 | Confirm the tech stack | Jeward + Rusty | D-009 updated |
| 2 | Confirm single-league vs multi-tenant | Jeward + Rusty | D-008 updated |
| 3 | Confirm manual payments vs gateway | Jeward + Rusty | D-007 updated |
| 4 | Confirm public fan access vs accounts | Jeward + Rusty | D-005 updated |
| 5 | Reconcile or relabel the survey data | Jeward + Rusty | P0-01 Done |
| 6 | Approve the RBAC matrix | Jeward + Rusty | P0-02 Done |
| 7 | Approve the v2 data model | Jeward + Rusty | P1-01, P1-02, P1-03 designed |
| 8 | Create the repository and CI skeleton | Jeward | Repo exists |
| 9 | Build the walking skeleton | Jeward + Rusty | Phase 1 exit criteria met |
| 10 | Compress the source document | Jeward | PDF under 10 MB |

---

## 18. Demo Script

A 10-minute demo for the class or a pilot organizer.

1. **Organizer login.** Show the dashboard and role badge.
2. **Create a tournament.** "HoopFlow Summer Cup," U15, 16 teams, 4 courts, 30-minute slots, 15-minute rest.
3. **Coach registration.** Register a team, add 12 players with birthdates and guardian consent, upload one eligibility document, submit a GCash reference.
4. **Organizer review.** Approve the team, record the payment, show the audit entry.
5. **Generate the bracket.** Show seeds, byes, and the public view.
6. **Generate the schedule.** Show that no court or team is double-booked and that rest is respected.
7. **Scorekeeper entry.** Enter a final score; show the winner advancing and standings updating.
8. **Permission check.** Log in as the coach and show that score entry is denied.
9. **Public page.** Show the bracket, schedule, results, and standings on a phone-sized viewport.
10. **Close.** Show the audit log and summarize the time saved versus the manual process.

Demo seed:

- 16 teams.
- 4 courts.
- 30-minute slots.
- 15-minute rest.
- One bye.
- One forfeit.
- One score correction.

---

## 19. Open Questions

1. Which stack is the team most comfortable with: TypeScript/Next.js, PHP/Laravel, or Python/Django?
2. Is HoopFlow for one league/club, or a SaaS for many organizers?
3. Is online payment required, or is manual recording enough for v1?
4. Do fans need accounts, or are public pages sufficient?
5. Which formats must v1 support: single elimination, double elimination, round robin, or pool play?
6. Is there a hard deadline or rubric? Please share it.
7. Confirm post-game score entry for v1. Do we need a lightweight live scoreboard for the demo, or are final results enough?
8. Is offline score entry required? If yes, plan for local storage and conflict resolution.
9. Who are the real pilot users? Can the team test with one league?
10. Is referee assignment required in v1?
11. Does the school require a specific database or stack?
12. What is the expected deployment environment: free tier, school server, or paid hosting?

---

## 20. Glossary

| Term | Definition |
|---|---|
| **Organizer** | The person who runs the tournament and configures it |
| **Coach** | The person who registers and manages a team |
| **Scorekeeper** | The person who enters final scores and stats |
| **Fan / Parent** | A public viewer; parents/guardians also consent for minors |
| **Player** | A minor participant managed by a coach |
| **Tournament** | The top-level event |
| **Division** | An age/gender group inside a tournament |
| **Bracket** | The match structure for a division |
| **Match** | A single game between two teams |
| **Slot** | A court and time assignment for a match |
| **Roster** | The list of players on a team |
| **Eligibility** | Whether a player meets age/residency/document requirements |
| **Seeding** | The ranked placement of teams in a bracket |
| **Bye** | A team advances without playing a round |
| **Auto-advance** | The winner automatically moves to the next bracket slot |
| **Standings** | The ranked table for a division or pool |
| **Tiebreaker** | The rule used to rank teams with equal records |
| **RBAC** | Role-based access control |
| **Audit log** | An append-only record of sensitive actions |
| **PWA** | Progressive web app; a web app that behaves more like an app |

---

## 21. Source Inventory

### Primary source

- `C:\Users\JewardMahinay\Downloads\Basketball_Tournament_System all for one.docx`
- 24.7 MB, about 2,255 words, 62 embedded images.
- Last modified 2026-10-06.
- Contains Deliverables 1-4.

### Design source

- Figma: https://www.figma.com/design/QDqY8HSXs7jCnqHbEbvDmi/HoopFlow?node-id=0-1&t=uzoC1HzX3UeMEv76-1

### Source sections reviewed

- Cover and table of contents.
- Deliverable 1: problem, solution, objectives, stakeholders, survey, proof, user stories, use cases, feasibility.
- Deliverable 2: wireframes, flowcharts, context diagram, DFD Level 1.
- Deliverable 3: ERD.
- Deliverable 4: system architecture diagram.

### Known source defects

- Survey denominators do not reconcile.
- Role field on sign-up.
- Missing scorekeeper.
- Missing birthdate/guardian/consent.
- No venues/courts/slots.
- No bracket topology.
- Stored derived data.
- Game odds.
- Missing notifications.
- Missing referee design.
- Incomplete payment ledger.
- Thin architecture.
- No privacy program.
- Scope contradictions.
- Typos and numbering issues.

---

## 22. Change Log

| Date | Version | Change | By |
|---|---|---|---|
| 2026-10-26 | 1.0 | Created the project dossier from the document review. Recorded all P0/P1/P2 issues, decisions, scope, target data model, architecture, roadmap, and next actions. No source document or code changes yet. | Hank |
| 2026-10-26 | 1.1 | Recorded the live-score decision: full live score entry is deferred; an optional lightweight polling scoreboard is a v1.1 extension. Added Section 10.9, D-017, scope notes, and the optional roadmap extension. | Hank |

---

## Appendix A — Review Scorecard

| Area | Score | Notes |
|---|---|---|
| Problem understanding | Strong | Real pain, clear lifecycle |
| Artifact coverage | Strong | Requirements through architecture |
| Survey rigor | Weak | Denominators and contradictions |
| Requirements rigor | Needs work | NFRs, acceptance criteria, traceability |
| UML/DFD craft | Needs work | Boundaries, labels, numbering |
| Data model | Good bones | Eligibility, venues, notifications, audit missing |
| Architecture | Too thin | Auth, jobs, storage, operations missing |
| UX coverage | Good | States and desktop missing |
| Writing quality | Needs cleanup | Typos, terminology, numbering |
| Build readiness | Not yet | Phase 0 decisions pending |

## Appendix B — First Five Things to Build

1. Authentication and RBAC.
2. Tournament/division/venue/court setup.
3. Registration, roster, and eligibility.
4. Single-elimination bracket generation.
5. Score entry, auto-advance, and public standings.

Do not build payments, odds, live scores, or notifications before these five work end to end.

---

*End of dossier.*
