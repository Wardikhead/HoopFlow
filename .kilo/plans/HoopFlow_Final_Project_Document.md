# HoopFlow — Final Project Document

## 1. System Name

**HoopFlow**

HoopFlow is a web-based basketball tournament management system designed for barangay and grassroots youth leagues.

---

## 2. Community-Based Problem

Barangay and grassroots youth basketball leagues are typically run informally using paper sheets, spreadsheets, group chats, or in-person sign-ups. This creates several problems:

- **Disorganized registration:** Player and team sign-ups are hard to track, and duplicate or incomplete entries are common.
- **No eligibility enforcement:** Age, residency, and document requirements are checked manually, so ineligible players can slip through.
- **Bracket and schedule chaos:** Brackets (single-elimination, double-elimination, round-robin) and game schedules are drawn by hand, leading to errors, conflicts, and delays.
- **Manual scorekeeping:** Game scores are recorded on paper, making it easy to lose records or post incorrect results.
- **No live standings:** Teams, coaches, and fans have no real-time view of win-loss records, points, or rankings.
- **Poor communication:** Announcements (game postponements, venue changes, results) are scattered across chats and word of mouth.

HoopFlow solves these problems by digitizing the entire tournament lifecycle in one place: registration, eligibility verification, bracket generation, scheduling, score entry, standings, and announcements.

---

## 3. System Functionalities

| Area | Functionality |
|------|---------------|
| **Tournament Management** | Create, view, and manage basketball tournaments; define format, divisions, and venues. |
| **Registration** | Online team and player registration; organizers can review, approve, or reject registrations. |
| **Eligibility Verification** | Validate player eligibility (age, residency, documents) before allowing participation. |
| **Bracket Generation** | Generate single-elimination, double-elimination, and round-robin brackets automatically. |
| **Scheduling** | Produce game schedules that respect venue availability and team constraints. |
| **Scorekeeping** | Record game scores per quarter; correct scores when needed. |
| **Standings** | Compute and display win-loss records, points, and rankings per division. |
| **Announcements** | Post tournament announcements visible to organizers, coaches, teams, and fans. |
| **Dashboard** | Role-based dashboard: organizer KPIs, coach teams/records/matches, public announcements. |
| **Authorization** | Role-based access control (organizer, coach, fan) with ownership rules (coaches manage only their own team). |
| **Authentication** | Secure login and registration for organizers, coaches, and fans. |

---

## 4. Tech Stack

| Layer | Technology |
|-------|------------|
| **Framework** | Laravel 13 (PHP 8.4+, developed against PHP 8.5) |
| **Frontend (templating)** | Blade templates |
| **Interactivity** | Livewire 4, Alpine.js 3 |
| **Styling** | Bootstrap 5.3 compiled from SCSS via Vite (Bootstrap JavaScript is not loaded; Alpine owns behaviour) |
| **Database** | PostgreSQL 17 (SQLite acceptable for local/unit tests; scheduling tests run on Postgres) |
| **ORM** | Eloquent |
| **Testing** | Pest (unit, feature, policy, and parity tests) |
| **Code quality** | Pint (Laravel formatter), Larastan/PHPStan level 6 |
| **Build tool** | Vite |
| **Deployment target** | Hostinger / Railway (application) + Supabase (PostgreSQL database) |

---

## 5. Functions and Approaches Used in Writing the Program

### Architecture approach

- **Domain logic in plain PHP services:** Bracket generation, scheduling, standings computation, and eligibility rules live in `app/Services/` as plain PHP classes with no framework coupling, so they can be unit-tested without HTTP.
- **Thin controllers:** Controllers handle request/response only; business logic delegates to services.
- **Livewire for interactivity:** Stateful, interactive panels (brackets, schedules, score entry, standings) are Livewire components under `app/Livewire/{Organizer,Coach,Public}/`.
- **Policy-based authorization:** Model policies plus a `BasePolicy` with role-based defaults (authenticated users can view; organizers can create/update/delete; owners manage their own records). Domain actions (approving registrations, verifying payments, correcting scores, generating brackets/schedules) are covered by policies.
- **Feature-based routing:** Routes live in `routes/web.php` as a table of contents, with feature routes loaded from `routes/features/*.php`.
- **Database-first migrations and seeders:** Schema defined in migrations; demo data (organizer, coach, fan users, teams, registrations, players, matches) loaded via seeders.
- **Role-based navigation:** Navigation items are hidden when the current role cannot access them, matching prototype behavior.
- **No starter kits / no SPA:** No Tailwind, no Bootstrap JS bundle, no Vue or React; every page works without an account unless explicitly staff-only.

### Key functions/services

- **BracketService:** Single-elimination, double-elimination, and round-robin bracket generation.
- **SchedulingService:** Game schedule generation with venue/team constraints.
- **StandingsService:** Standings computation from recorded match results.
- **EligibilityService:** Player eligibility validation.
- **DashboardService:** Organizer KPIs, coach teams/records/matches, and announcements.
- **Policies:** One policy per model (25 models covered) plus explicit Gate definitions for non-model actions (manage-users, reset-demo, view-audit).

### Testing approach

- Unit tests for each domain service.
- Feature tests against seeded data.
- Policy tests asserting 200/403 responses per role.
- Parity tests porting prototype test scenarios to Pest (dashboard, tournament CRUD, registration, brackets, score entry, standings, announcements, auth/role access, seed reset).

---

## 6. Deployment Location

- **Application:** Hostinger or Railway (cloud application hosting).
- **Database:** Supabase (hosted PostgreSQL).

The application connects to the Supabase PostgreSQL database over the network. Environment variables (database credentials, `APP_KEY`, `APP_URL`) are configured per deployment environment and never committed to version control.

Deployment steps (summary):

1. Push code to the repository connected to Hostinger/Railway.
2. Provision a PostgreSQL database on Supabase and obtain the connection string.
3. Set environment variables (`DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_KEY`, `APP_URL`).
4. Run `composer install --optimize-autoloader --no-dev`.
5. Run `php artisan migrate --force` and seed as needed.
6. Build frontend assets (`npm run build`) and serve via the platform's web server.

---

*Prepared for the HoopFlow final project documentation.*
