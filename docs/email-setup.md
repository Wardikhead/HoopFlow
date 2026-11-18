# Production email setup (Mailtrap Email Sending)

HoopFlow sends password reset and password changed messages through Laravel's
mailer. Local development defaults to `MAIL_MAILER=log`; production uses
Mailtrap Email Sending over SMTP.

## 1. Verify the sending domain

1. Sign in to Mailtrap and open **Email Sending → Sending Domains**.
2. Add the domain HoopFlow will send from, for example `hoopflow.ph`.
3. Add the SPF, DKIM, and DMARC records Mailtrap provides to your DNS.
4. Wait for the domain status to show as verified.

Mail must be sent from an address on this verified domain. `MAIL_FROM_ADDRESS`
must match it.

## 2. Get SMTP credentials

Open **Email Sending → Sending Setup**, choose the verified domain, select the
**Transactional** stream, and switch the view to **SMTP**.

Use the credentials shown there:

| Variable | Value |
|---|---|
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `live.smtp.mailtrap.io` |
| `MAIL_PORT` | `587` |
| `MAIL_SCHEME` | `null` |
| `MAIL_USERNAME` | `api` |
| `MAIL_PASSWORD` | your Mailtrap API token |

Mailtrap creates a separate token for each sending domain. Creating a new token
invalidates the old one.

## 3. Configure the application

```ini
APP_URL=https://your-production-domain.example
MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=api
MAIL_PASSWORD=your-mailtrap-api-token
MAIL_FROM_ADDRESS=no-reply@your-verified-domain.com
MAIL_FROM_NAME="HoopFlow"
```

Clear the cached config after changing values:

```powershell
php artisan config:clear
```

## 4. Verify a send

From the application, open `/forgot-password`, submit a real account email, and
open the message in Mailtrap **Email Logs**. The reset button must point at
`APP_URL`, not `localhost`.

Without the API token or a verified domain, delivery fails. The application
keeps the failure in `storage/logs/laravel.log`; it does not silently discard it.

## Local development

Keep `MAIL_MAILER=log` for local work. Reset emails are written to
`storage/logs/laravel.log` and no external account is needed.

To inspect real messages locally without a verified domain, use a Mailtrap Email
Sandbox inbox instead of Email Sending: create a sandbox, copy its SMTP
credentials, and keep the token in local `.env` only.

## Never commit secrets

`.env` is ignored by git. Keep API tokens out of `.env.example`, source files,
tests, and chat transcripts.
