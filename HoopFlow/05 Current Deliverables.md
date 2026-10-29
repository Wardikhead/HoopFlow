# 5. Current Deliverables

> **Part of:** [[00 HoopFlow]]

The source document contains four deliverables.

## Deliverable 1 — Requirements Gathering

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

## Deliverable 2 — Wireframes, Flowcharts, and DFDs

Contents:

- Mobile wireframes: splash, login, admin, coach, fan.
- Flowcharts for Admin, Coach/Team Leader, and Fans.
- Context flow diagram.
- Data Flow Diagram Level 1.

**Status:** Good coverage. Needs readable layouts, system boundaries, consistent numbering, error/rejection paths, and a scorekeeper flow.

## Deliverable 3 — ERD

Contents:

- Entities: `users`, `teams`, `players`, `player_stats`, `tournaments`, `tournament_registrations`, `payments`, `brackets`, `matches`, `schedules`, `standings`, `announcements`, `fan_follows`, `game_odds`.
- Relationships described in prose.

**Status:** Good first pass. Missing venues/courts, divisions, referees, guardians, eligibility documents, notifications, and audit logs. Several derived fields are stored as source data.

## Deliverable 4 — System Architecture Diagram

Contents:

- Cross-platform mobile and desktop front ends.
- API layer.
- Web server and "HoopFlow Logic."
- File system for team logos.
- Relational database.

**Status:** Too thin to build from. Needs authentication/authorization, background jobs, object storage, backups, observability, and a deployment target.
