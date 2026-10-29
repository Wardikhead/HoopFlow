# 10. Target Product Design

> **Part of:** [[00 HoopFlow]]

## 10.1 Core lifecycle

1. **Organizer creates a tournament.** Name, dates, division, venue, courts, format, rules, entry fee.
2. **Coach registers a team.** Team profile, roster, player birthdates, guardian consent, documents, payment reference.
3. **Organizer reviews.** Eligibility, roster, payment. Approve or reject with a reason.
4. **Organizer generates the bracket.** Seeded or random, byes, published.
5. **Organizer generates the schedule.** Courts, slots, rest, dependencies, published.
6. **Scorekeeper enters the final score.** The system validates, advances the winner, and updates standings.
7. **Public pages update.** Bracket, schedule, results, standings, rosters.
8. **Notifications go out.** Registration status, schedule changes, results, announcements.
9. **Organizer closes the tournament.** Export results, archive data per retention policy.

## 10.2 Core workflows

### Workflow A — Registration

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

### Workflow B — Bracket generation

1. Organizer selects the division and the approved teams.
2. Organizer chooses seeded or random.
3. System builds a bracket of the next power of two.
4. Top seeds receive byes.
5. Organizer previews and publishes.
6. Manual changes are allowed before publishing and are audited.

### Workflow C — Scheduling

1. Organizer sets venue hours, courts, slot length, buffer, and minimum rest.
2. System assigns every match to a court and slot.
3. System checks hard constraints.
4. Organizer previews, adjusts manually if needed, and publishes.
5. A schedule change creates a new version and notifies affected teams.

### Workflow D — Score entry

1. Scorekeeper opens an assigned match.
2. Scorekeeper enters the final score and optional stats.
3. System validates the score and marks the match complete.
4. Winner advances to the next bracket slot.
5. Standings recompute.
6. A correction after lock requires an organizer and is audited.

## 10.3 Role-permission matrix

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

## 10.4 Scheduling rules

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

## 10.5 Bracket rules

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

## 10.6 Payments

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

## 10.7 Notifications

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

## 10.8 Privacy and consent

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

## 10.9 Live score view vs live score entry

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
