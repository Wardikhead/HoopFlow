# 1. Executive Summary

> **Part of:** [[00 HoopFlow]]

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
