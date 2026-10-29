# 9. Scope

> **Part of:** [[00 HoopFlow]]

## 9.1 In scope for v1

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

## 9.2 Later

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

## 9.3 Out of scope

- Betting or odds.
- Live streaming or video.
- Full live score entry with a game clock, play-by-play, or shot tracking.
- Rigging detection or "unusual loss" alerts.
- Full accounting, payroll, or refunds beyond simple payment records.
- Public chat or social network.
- Multi-language UI.
- Kubernetes or microservices.

## 9.4 Scope guardrail

Any new idea goes into a **Later** list first. It does not enter the current phase unless the current phase is complete and the decision is recorded in the Decisions Log.
