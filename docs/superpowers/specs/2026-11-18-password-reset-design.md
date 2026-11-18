# Forgot and Reset Password Design

Date: 2026-11-18
Status: Approved for implementation

## Goal

Add a production-ready forgot-password and reset-password flow to the HoopFlow Laravel
application in `src/`, using the existing authentication design language and Laravel's
native password broker.

## Non-goals

- Changing passwords from the authenticated profile page.
- Two-factor authentication.
- SMS or in-app password reset delivery.
- Creating the user's Mailtrap account or verifying their sending domain.

## Decisions

- A successful reset redirects to the sign-in page with a success message. The user is
  not signed in automatically.
- A successful reset sends a security confirmation email to the account owner.
- Email delivery uses Laravel's mail system. Local development keeps `MAIL_MAILER=log`;
  production is documented and prepared for Mailtrap Email Sending over SMTP.

## User flows

### Request a reset link

1. Guest visits `/forgot-password` from the sign-in page.
2. Guest enters an email address.
3. The app normalizes the address to lowercase and trims it.
4. The app asks Laravel's password broker to send a reset link.
5. The page always shows the same generic success message, whether or not the account
   exists. This prevents account enumeration.
6. If the account exists and the broker is not throttling the request, a reset email is
   sent.

### Reset the password

1. Guest opens the emailed link: `/reset-password/{token}?email=...`.
2. The page shows a token-bound form with email, new password, and confirmation.
3. The app validates the token, email, password policy, and confirmation.
4. On success the app updates the password, rotates the remember token, revokes all
   sessions for that user, records an audit entry, sends a confirmation email, and
   redirects to sign-in with a success message.
5. On an invalid, expired, or reused token the page shows one generic error and a link
   to request a new reset link.

## Routes

All routes are guest-only. They live in `routes/web.php` beside the existing auth routes.

| Method | Path | Name | Middleware |
|---|---|---|---|
| GET | `/forgot-password` | `password.request` | `guest` |
| POST | `/forgot-password` | `password.email` | `guest`, `throttle:password-email` |
| GET | `/reset-password/{token}` | `password.reset` | `guest` |
| POST | `/reset-password` | `password.update` | `guest`, `throttle:password-reset` |

## Backend components

### `App\Http\Controllers\PasswordResetController`

- `showLinkRequestForm()` renders `auth.forgot-password`.
- `sendResetLink(Request)` normalizes the email, validates it, calls
  `Password::sendResetLink`, and returns the generic status. Broker outcomes such as
  unknown account and broker throttling are not exposed.
- `showResetForm(Request, string $token)` renders `auth.reset-password` with the token
  and the email query parameter.
- `reset(Request)` validates and calls `Password::reset`. The callback updates the user,
  rotates the remember token, revokes sessions, records an audit entry, and captures the
  user for post-reset notifications.

### `App\Services\SessionRegistry`

Add `revokeAll(User $user): int` to delete every session row for the user. The existing
`revokeOthers` behavior stays unchanged.

### `App\Models\User`

Override `sendPasswordResetNotification(string $token)` to send
`App\Notifications\ResetPasswordNotification` instead of the framework default.

### `App\Providers\AppServiceProvider`

Register two named rate limiters:

- `password-email`: per-IP and normalized-email limits for link requests.
- `password-reset`: per-IP limit for reset submissions.

Values live in `config/rate_limits.php`.

### Audit logging

Write one `auth.password_reset` audit entry through `AuditLogger` after a successful
reset. The entry uses the user as both entity and actor and must not contain the
password, token, or reset URL.

## Validation and password policy

- Email: required, valid email format, maximum 255 characters.
- Token: required string.
- Password: required, string, minimum 8 characters, maximum 255 characters, confirmed,
  and `NotCommonPassword`.
- Custom messages match the existing registration copy:
  - `Use at least 8 characters.`
  - `Keep the password under 255 characters.`
  - `That password is too common. Add a word only you would use.`
  - `The passwords do not match.`
- Reset requests treat invalid or expired tokens as one generic error:
  `This reset link is invalid or has expired. Request a new one.`

## Security requirements

- No account enumeration in the forgot-password response.
- Laravel's hashed, single-use reset tokens and configured 60-minute expiry.
- Rate limiting at the HTTP layer, in addition to the broker's 60-second throttle.
- CSRF protection on both POST routes.
- Guest-only access.
- Rotate `remember_token` on successful reset.
- Revoke all existing sessions for the user on successful reset.
- Do not log reset tokens, passwords, or full reset URLs.
- Keep all sensitive fields out of validation error output and old input.

## Email

### `App\Notifications\ResetPasswordNotification`

- Mail channel only.
- Subject: `Reset your HoopFlow password`.
- Body explains that a reset was requested, includes a `Reset password` action button,
  states the link expiry in minutes, and tells the recipient to ignore the email if it
  was not them.
- URL: `route('password.reset', ['token' => $token, 'email' => $email])`.

### `App\Notifications\PasswordChangedNotification`

- Mail channel only.
- Subject: `Your HoopFlow password was changed`.
- Body confirms the change, states that all other sessions were signed out, and tells the
  recipient to reset the password immediately if they did not make the change.

### Deliverability

- Local `.env` stays on `MAIL_MAILER=log` until Mailtrap credentials exist, so local
  development and tests are never blocked by missing credentials.
- `.env` and `.env.example` include a commented Mailtrap Email Sending block:
  `live.smtp.mailtrap.io`, port `587`, username `api`, blank password/token.
- `docs/email-setup.md` documents domain verification, SPF/DKIM/DMARC, the required
  `MAIL_*` variables, and how to confirm a send in Mailtrap Email Logs.

## Frontend

### `resources/views/auth/forgot-password.blade.php`

- Extends `layouts.public` and uses `hf-auth`, `hf-auth-shell`, and `hf-auth-card`.
- Email field with `autocomplete="email"`, required marker, error summary, inline error,
  and `data-testid="forgot-password-*"`.
- Generic success callout after submission.
- Link back to sign-in.

### `resources/views/auth/reset-password.blade.php`

- Same auth shell and card treatment.
- Hidden token plus prefilled email field.
- New password and confirmation fields with `autocomplete="new-password"`.
- Reuses the existing `hfPasswordMeter` Alpine component and show/hide toggles.
- Error summary and inline errors with explicit `aria-describedby` targets.
- Link to request a new reset link.
- `data-testid="reset-password-*"`.

### `resources/views/auth/login.blade.php`

- Add a `Forgot password?` link next to the password label.
- Render a success callout for the flashed reset status.
- Keep existing demo-account behavior unchanged.

No new JavaScript, Tailwind, or Bootstrap JavaScript. No new CSS is required unless a
small layout helper is unavoidable; prefer existing utility classes.

## Configuration

`config/auth.php` reads the reset expiry and throttle from the environment:

- `PASSWORD_RESET_EXPIRE` default `60` minutes.
- `PASSWORD_RESET_THROTTLE` default `60` seconds.

`config/rate_limits.php` adds:

- `password_reset.request_per_minute` default `5` per client IP.
- `password_reset.request_per_hour` default `20` per client IP.
- `password_reset.request_per_email_per_minute` default `3` per normalized email.
- `password_reset.submit_per_minute` default `10` per client IP.

`.env.example` documents these values without secrets.

## Tests

`tests/Feature/Auth/PasswordResetTest.php` covers:

1. Forgot-password page loads for guests.
2. Reset-password page loads with token and email.
3. Known email returns the generic status and sends the reset notification.
4. Unknown email returns the same status and sends nothing.
5. Email normalization works for link requests.
6. Forgot-password HTTP rate limiting returns 429 after the configured limit.
7. Invalid email format shows a validation error and sends nothing.
8. Successful reset changes the password, rotates the remember token, and revokes
   sessions.
9. The old password no longer authenticates and the new password does.
10. Successful reset sends the confirmation notification.
11. Reset writes the `auth.password_reset` audit entry without sensitive values.
12. Weak, common, and mismatched passwords are rejected.
13. Invalid, expired, and reused tokens are rejected with the generic message.
14. Authenticated users are redirected away from all four guest routes.
15. The login page shows the forgot-password link and the flashed success message.

## Verification

Run `composer quality` from `src/` and report the actual Pint, Larastan, and Pest output.
Do not claim completion without that output. A live Mailtrap delivery check is documented
but requires the user's API token and verified domain.

## Risks and operational notes

- Synchronous mail sending can add latency to the reset request. Volume is low, so this
  avoids requiring a queue worker for a security-critical email.
- Mailtrap Email Sending requires a verified domain and API token. Without both, the app
  can only log mail locally.
- Suspended accounts may still receive a reset link, but sign-in remains blocked by the
  existing `status = active` check.
