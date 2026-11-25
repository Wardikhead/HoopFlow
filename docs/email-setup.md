# Production email setup (Brevo)

HoopFlow sends password reset and password changed messages through Laravel's
mailer. Local development defaults to `MAIL_MAILER=log`; production sends
through Brevo. Railway's Free, Trial, and Hobby plans block outbound SMTP, so
production uses Brevo's HTTPS API. The SMTP relay remains available for hosts
that allow outbound SMTP, including Railway Pro.

Brevo's free plan allows 300 emails per day and a single verified sender, so it
works without owning a domain. A verified custom domain is still strongly
recommended for production deliverability.

## 1. Verify a sender

1. Sign in to Brevo and open **Senders, Domains & Dedicated IPs → Senders**.
2. Add the address HoopFlow will send from, for example
   `no-reply@your-domain.com`.
3. Open the verification email and confirm the address.

`MAIL_FROM_ADDRESS` must match a verified sender. A freemail sender such as a
Gmail address works for demos, but Brevo flags it as "Freemail domain is not
recommended" and receivers are more likely to treat it as spam.

## 2. Get credentials

### HTTPS API (production on Railway)

Open **SMTP & API → API Keys** and create a key. API keys start with
`xkeysib-`.

| Variable | Value |
|---|---|
| `MAIL_MAILER` | `brevo` |
| `BREVO_API_KEY` | an API key starting with `xkeysib-` |

The `brevo` transport is registered in `AppServiceProvider` through
`symfony/brevo-mailer`, and sends over HTTPS to `api.brevo.com`.

### SMTP relay (Railway Pro or other hosts)

Open **SMTP & API → SMTP**. The page shows the server, port, login, and your
SMTP keys.

| Variable | Value |
|---|---|
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `smtp-relay.brevo.com` |
| `MAIL_PORT` | `587` (STARTTLS) |
| `MAIL_SCHEME` | `null` |
| `MAIL_USERNAME` | the **Login** shown on the SMTP page |
| `MAIL_PASSWORD` | an SMTP key, starting with `xsmtpsib-` |

Two details cause almost every `535 5.7.8 Authentication failed`:

- The login is **not always your Brevo account email**. Newer accounts get a
  generated login such as `123456001@smtp-brevo.com`. Copy the exact value from
  the SMTP page.
- SMTP keys and API keys are different. An API key (`xkeysib-`) will not
  authenticate over SMTP, and an SMTP key (`xsmtpsib-`) will not authenticate
  against the API.

## 3. Configure the application

```ini
APP_URL=https://your-production-domain.example
MAIL_MAILER=brevo
BREVO_API_KEY=xkeysib-your-brevo-api-key
MAIL_FROM_ADDRESS=no-reply@your-verified-domain.com
MAIL_FROM_NAME="HoopFlow"
```

`APP_URL` must be the public application URL. Reset links are built from it; if
it points at `localhost`, recipients cannot use the link.

Clear the cached config after changing values:

```powershell
php artisan config:clear
```

## 4. Verify a send

Open `/forgot-password`, submit a real account email, and confirm the message
arrives. Check the spam folder on the first send and mark the message as "not
spam" to improve later delivery.

Brevo records every message under **Transactional → Logs**. A missing log entry
means the application never reached Brevo; a log entry marked blocked or bounced
means Brevo rejected the recipient or sender.

Delivery failures are written to `storage/logs/laravel.log`; the application does
not silently discard them.

## Troubleshooting

- **`BREVO_API_KEY is not configured`** — set the API key in the environment and
  clear the cached config.
- **`Unable to send an email: ... (code 401)`** — the API key is invalid or
  revoked. Create a new key under **SMTP & API → API Keys**.
- **`Unable to send an email: ... (code 400)`** — usually an unverified sender
  address or a malformed recipient. Check the Brevo transactional log.
- **`Maximum execution time ... SocketStream.php`** — the host is blocking
  outbound SMTP. On Railway, switch to `MAIL_MAILER=brevo` with an API key.
- **`535 Authentication failed`** — only applies to SMTP. Copy the Login and
  SMTP key again from **SMTP & API → SMTP**, regenerate the key if it was
  revoked, and run `php artisan config:clear`.
- **Mail sends but never arrives** — confirm the sender is verified, check the
  Brevo transactional log, and inspect the recipient's spam folder.
- **Reset link points at `localhost`** — set `APP_URL` to the public URL and
  clear the config cache.

## Fallback: Mailtrap Email Sending

Mailtrap requires a verified sending domain, so it cannot send from a freemail
address. Use it only after you control a domain:

1. Open **Email Sending → Sending Domains** and add the domain.
2. Add the SPF, DKIM, and DMARC records Mailtrap provides, then wait for
   verification.
3. Open **Email Sending → Sending Setup**, select the **Transactional** stream,
   and switch to the **SMTP** view.
4. Use `live.smtp.mailtrap.io`, port `587`, username `api`, and the API token
   shown for that domain.

Mailtrap creates a separate token per sending domain; creating a new token
invalidates the old one.

## Local development

Keep `MAIL_MAILER=log` for local work. Reset emails are written to
`storage/logs/laravel.log` and no external account is needed.

To send real mail locally, set `MAIL_MAILER=brevo` and a `BREVO_API_KEY` in
`.env`. To inspect rendered messages without sending them, use a Mailtrap Email
Sandbox inbox: create a sandbox, copy its SMTP credentials, and keep the token
in local `.env` only.

## Never commit secrets

`.env` is ignored by git. Keep SMTP keys and API tokens out of `.env.example`,
source files, tests, and chat transcripts.
