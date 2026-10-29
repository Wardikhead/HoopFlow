# 12. Target Architecture

> **Part of:** [[00 HoopFlow]]

## 12.1 Recommended shape

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

## 12.2 Stack options

Pick one and stay with it. The best stack is the one the team can finish.

> **Decided (2026-10-27, D-009):** Option B, fixed to PHP 8.4, Laravel 13, Blade, Livewire 4, Alpine.js 3, Bootstrap 5.3, and PostgreSQL 17. Full rationale in [[ADR-0001-stack]]. Options A and C remain below as the record of what was rejected.

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

**Option B — PHP/Laravel (chosen — see [[ADR-0001-stack]])**

- Laravel + Blade/Livewire or Inertia + Vue/React. **Chosen: Blade + Livewire 4, Bootstrap 5.3 for CSS, Alpine.js for client behaviour.**
- MySQL or PostgreSQL. **Chosen: PostgreSQL 17, for the `tstzrange` exclusion constraint in [[11 Target Data Model#11.7 Key constraints|11.7]].**
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

## 12.3 Environments

- **Local:** Docker Compose or a local Postgres, seed data, hot reload.
- **Staging:** production-like, used for demos and migration tests.
- **Production:** managed Postgres, object storage, backups, monitoring.

## 12.4 Security checklist

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

## 12.5 Operations checklist

- Daily database backups; test a restore monthly.
- Error tracking with Sentry or equivalent.
- Structured logs with request IDs.
- Health check endpoint.
- Uptime monitor for the public pages.
- Migration rollback plan.
- Seed script for demo data.

## 12.6 Performance checklist

- Index foreign keys and date/time columns.
- Paginate public lists.
- Optimize and cache team logos.
- Cache public tournament pages for a short TTL.
- Avoid N+1 queries in brackets and standings.
- Generate brackets/schedules in a worker for large divisions.
