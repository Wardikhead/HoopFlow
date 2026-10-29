# 7. Issue Register

> **Part of:** [[00 HoopFlow]]

**Important:** All issues below are **Open**. They were raised in review. This dossier records them; the source document and code have not been changed yet. When an issue is fixed, update its status to **Done** and add an entry to the [[22 Change Log|Change Log]].

## Severity definitions

| Severity | Meaning | When to fix |
|---|---|---|
| **P0** | Blocks submission credibility, or creates a security/ethical risk | Before the next submission or demo |
| **P1** | Blocks a credible build or a core feature | Before implementing that feature |
| **P2** | Quality, clarity, or polish | Before final submission or release |

## 7.1 Summary table

| ID | Severity | Area | Issue | Status |
|---|---|---|---|---|
| [[07.2 P0 Issues#P0-01 — Survey data does not reconcile\|P0-01]] | P0 | Evidence | Survey numbers do not reconcile with n = 50 | Open |
| [[07.2 P0 Issues#P0-02 — Role model allows privilege escalation; scorekeeper is missing\|P0-02]] | P0 | Security / roles | Role model allows privilege escalation; scorekeeper is missing | Open |
| [[07.2 P0 Issues#P0-03 — Age eligibility and guardian consent are not modeled\|P0-03]] | P0 | Eligibility / privacy | Age eligibility and guardian consent are not modeled | Open |
| [[07.3 P1 Issues#P1-01 — Scheduling schema cannot enforce court conflicts or rest rules\|P1-01]] | P1 | Scheduling | Schema cannot enforce court conflicts or rest rules | Open |
| [[07.3 P1 Issues#P1-02 — Bracket auto-advance has no topology; "any format" is overpromised\|P1-02]] | P1 | Brackets | Auto-advance has no bracket topology; "any format" is overpromised | Open |
| [[07.3 P1 Issues#P1-03 — Derived data will drift\|P1-03]] | P1 | Data integrity | Standings, winner, and odds are derived but stored as source data | Open |
| [[07.3 P1 Issues#P1-04 — "Game odds" is betting-adjacent in a youth product\|P1-04]] | P1 | Ethics / scope | "Game odds" is betting-adjacent in a youth product | Open |
| [[07.3 P1 Issues#P1-05 — "Notify" is promised but only announcements exist\|P1-05]] | P1 | Notifications | "Notify" is promised but only announcements exist | Open |
| [[07.3 P1 Issues#P1-06 — Referee assignment and game rules are promised but not designed\|P1-06]] | P1 | Traceability | Referee assignment and game rules are promised but not designed | Open |
| [[07.3 P1 Issues#P1-07 — Payment ledger is incomplete for cash reconciliation\|P1-07]] | P1 | Payments | Payment ledger is incomplete for cash reconciliation | Open |
| [[07.3 P1 Issues#P1-08 — Architecture lacks auth, jobs, storage, backups, and observability\|P1-08]] | P1 | Architecture | No auth, jobs, object storage, backups, or observability | Open |
| [[07.3 P1 Issues#P1-09 — No privacy or consent program for minors\|P1-09]] | P1 | Privacy | No consent, retention, or public-display policy for minors | Open |
| [[07.3 P1 Issues#P1-10 — Scope contradictions\|P1-10]] | P1 | Scope | Contradictions: post-game vs real-time, any format, standard rules, rigging detection | Open |
| [[07.4 P2 Issues#^p2-01\|P2-01]] | P2 | Requirements | No NFRs, acceptance criteria, assumptions, risks, or traceability matrix | Open |
| [[07.4 P2 Issues#^p2-02\|P2-02]] | P2 | UML / DFD | Missing system boundary, unlabeled include arrows, unbalanced DFD, huge flowcharts | Open |
| [[07.4 P2 Issues#^p2-03\|P2-03]] | P2 | UX | Wireframes lack loading, empty, error, offline, and validation states | Open |
| [[07.4 P2 Issues#^p2-04\|P2-04]] | P2 | Writing | Terminology drift and multiple typos | Open |
| [[07.4 P2 Issues#^p2-05\|P2-05]] | P2 | Submission | 24.7 MB file with 16k-pixel diagrams | Open |
| [[07.4 P2 Issues#^p2-06\|P2-06]] | P2 | Feasibility | No cost numbers, alternatives, risks, or schedule analysis | Open |
| [[07.4 P2 Issues#^p2-07\|P2-07]] | P2 | Testing | No test plan or quality gate | Open |
| [[07.4 P2 Issues#^p2-08\|P2-08]] | P2 | Formatting | Broken TOC letters, duplicate figure numbers, incomplete cover year | Open |
| [[07.4 P2 Issues#^p2-09\|P2-09]] | P2 | Consistency | Architecture claims desktop, but wireframes are mobile-only | Open |
