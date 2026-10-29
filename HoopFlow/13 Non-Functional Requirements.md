# 13. Non-Functional Requirements

> **Part of:** [[00 HoopFlow]]

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
