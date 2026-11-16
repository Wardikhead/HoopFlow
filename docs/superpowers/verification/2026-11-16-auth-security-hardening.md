# Authentication and security hardening verification - 2026-11-16

## Environment
- OS: Windows 11 (win32), PHP 8.5.2, Composer 2.9.7.
- Application: `src/` Laravel 13, Blade, Livewire 4, Bootstrap 5.3 (per ADR-0001).
- Database for tests: SQLite `:memory:`; cache and session drivers: `array`.

## Automated checks
- `composer quality` (Pint, PHPStan, full Pest suite):
  - Pint: `{"tool":"pint","result":"passed"}`
  - PHPStan: `[OK] No errors` (188 files)
  - Pest: `599 passed (3077 assertions)` in `558.88s`
- Security tests: `38 passed (132 assertions)` in `40.58s`
  - `AuthenticationHardeningTest` - 7 tests
  - `InputOutputSecurityTest` - 4 tests
  - `SessionManagementTest` - 5 tests
  - `SessionSecurityTest` - 8 tests
  - `LivewireAuthorizationTest` - 14 tests (including datasets)
- Regression subset (`Auth`, `Pages`, `Accessibility`, `Policies`): `338 passed` after updating the escaped-URL assertion in `GameStatsTest`.

## Deliverables

### Sign-in and registration
- Passwords stored through the `hashed` model cast; registration keeps the existing
  minimum-8-character and `NotCommonPassword` policy.
- Login normalizes the email, validates input, rejects inactive accounts, and always
  returns a generic `Invalid credentials.` message.
- Failed sign-ins are rate limited per normalized email + IP
  (`AUTH_LOGIN_MAX_ATTEMPTS`, default 5; `AUTH_LOGIN_DECAY_SECONDS`, default 60).
  Successful sign-in clears the counter.
- Session ID is regenerated on login and registration; logout invalidates the session
  and regenerates the CSRF token.
- Quick-fill demo credentials are hidden unless `config('app.demo_accounts')` is true;
  the default is disabled in production.

### Session management
- `VerifySessionFingerprint` binds authenticated sessions to a SHA-256 hash of the
  browser user agent and ends sessions when the fingerprint changes.
- `EnforceSessionTimeout` expires sessions after `AUTH_SESSION_IDLE_TIMEOUT` minutes
  (default 60) of inactivity.
- `EnsureUserIsActive` ends sessions for suspended or otherwise inactive accounts.
- Session cookies default to `http_only = true`, `same_site = lax`,
  encryption enabled, and `secure` enabled in production.
- `SessionRegistry` lists a user's active sessions (device, IP, last activity,
  current-device marker) and supports revoking every other session from
  `/profile` through a CSRF-protected `DELETE` form.

### Authorization
- The `role` middleware rejects guests, inactive users, and users without one of the
  required roles; it now uses `User::hasAnyRole()`.
- Sensitive Livewire components extend `App\Livewire\SecureComponent`, which
  re-checks authentication, active status, and role on every component boot/hydrate.
  This closes the gap where a Livewire action could bypass page route middleware.
- Protected components: organizer payments, submissions, audit log, announcement
  creation, tournament creation, game management; coach my-team and team registration.
- Shared components (for example the announcements feed) remain available to every
  signed-in role.

### Output, CSRF, and headers
- The remaining unescaped `{!! route(...) !!}` in the game-stats view now uses
  escaped Blade output; the corresponding page test compares the escaped URL.
- All authentication forms and the session-revocation form include `@csrf`.
- `SecurityHeaders` adds `X-Content-Type-Options`, `X-Frame-Options: DENY`,
  `Referrer-Policy`, `Permissions-Policy`, HSTS when appropriate, and a CSP with
  `default-src 'self'`, `object-src 'none'`, and `frame-ancestors 'none'`.
  Authenticated responses are marked `Cache-Control: no-store, private`.
- SQL search paths continue to use bound parameters; the only raw fragments use
  constants, cast integers, or bound placeholders.

## Known limits and follow-ups
- The CSP allows inline scripts and `eval` because Livewire 4/Alpine require them.
  A nonce-based CSP is a possible follow-up once the frontend no longer needs them.
- User-agent fingerprinting is defense in depth, not an authentication factor; it can
  end a legitimate session when a browser upgrades its user agent.
- Deployment must set `SESSION_ENCRYPT=true` (now the `.env.example` default) and keep
  `DEMO_ACCOUNTS=false` in production.
- A browser-based manual pass of the login, profile, and session-revocation flows was
  not performed in this environment; behavior is covered by Feature tests.
