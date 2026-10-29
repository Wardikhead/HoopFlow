# 3. Problem and Vision

> **Part of:** [[00 HoopFlow]]

## Problem

Running a youth basketball tournament involves many manual and scattered tasks. Organizers commonly use spreadsheets, group texts, and paper brackets. The same problems recur:

- **Registration is error-prone.** Rosters, player eligibility, and payments are hard to track. Incomplete and duplicate sign-ups are common.
- **Brackets are manual and slow.** Organizers physically build brackets and update them after every game.
- **Scheduling is fragile.** Double-booked gyms, insufficient rest, and delays cascade through the day.
- **Rules are inconsistent.** Different leagues and venues do not standardize game rules.
- **Score reporting is manual.** Standings are updated by hand, and game statistics are often not recorded.
- **History is lost.** Player and team data are not preserved beyond the current tournament.

## Solution

A configurable tournament management system that automates the full lifecycle:

1. Teams register online.
2. The system generates a bracket.
3. The system generates a schedule that respects courts and rest.
4. Games are played.
5. A scorekeeper enters final results.
6. Winners advance automatically.
7. Teams and fans are notified.
8. Results and history are stored.

## Product principles

1. **Post-game entry before live tracking.** Entering final scores after the game is much simpler than live play-by-play. This is the right v1 choice.
2. **One source of truth.** Registration, schedule, results, and standings should not live in separate spreadsheets.
3. **Fairness is visible.** Publish seeding and tiebreaker rules. Keep an audit log. Do not rely on hidden manual decisions.
4. **Mobile-first for fans and coaches.** Most parents and coaches will use a phone at the venue.
5. **Simple before clever.** A modular monolith and a relational database are enough. No microservices, no Kubernetes, no native apps for v1.
6. **Privacy by default.** Youth players are minors. Collect only what is needed and record consent.
