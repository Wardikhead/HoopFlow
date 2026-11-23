# Deploying HoopFlow to Railway + Supabase

This guide assumes the repository root is the Laravel app (`src/`).

## 1. Supabase (PostgreSQL)

1. Create a new project at https://supabase.com.
2. Go to **Project Settings → Database**.
3. Copy the **direct connection** details (host `db.<ref>.supabase.co`, port `5432`).
4. Note the database password you set during project creation.

Supabase requires SSL and exposes a single database (`postgres`). HoopFlow uses Laravel's
read/write split, so point both hosts at the same Supabase instance.

## 2. Railway

1. Create a new project → **Deploy from GitHub repo** → select `Wardikhead/HoopFlow`.
2. Railway will auto-detect `railpack.json` and build the app with PHP 8.4.
3. Open the service → **Variables** and set:

   | Variable | Value |
   |---|---|
   | `APP_NAME` | `HoopFlow` |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_KEY` | `base64:...` (generate locally with `php artisan key:generate --show`) |
   | `APP_URL` | `https://<your-railway-domain>` |
   | `DB_CONNECTION` | `pgsql` |
   | `DB_HOST` | `db.<ref>.supabase.co` |
   | `DB_PORT` | `5432` |
   | `DB_DATABASE` | `postgres` |
   | `DB_USERNAME` | `postgres` |
   | `DB_PASSWORD` | `<supabase password>` |
   | `DB_READ_HOST` | `db.<ref>.supabase.co` |
   | `DB_READ_PORT` | `5432` |
   | `DB_WRITE_HOST` | `db.<ref>.supabase.co` |
   | `DB_WRITE_PORT` | `5432` |
   | `DB_STICKY` | `true` |
   | `DB_SSLMODE` | `require` |
   | `QUEUE_CONNECTION` | `sync` |
   | `CACHE_STORE` | `database` |
   | `SESSION_DRIVER` | `database` |
   | `DEMO_ACCOUNTS` | `true` |

4. (Optional) Set `GEMINI_API_KEY` if you want AI announcement drafting.

5. Under **Settings → Networking**, generate a public domain.

## 3. Migrations & seeding

The `railpack.json` start command runs `php artisan migrate --force` (via Railpack's
Laravel start script) and seeds `DemoSeeder` only when the database is empty, so
redeploys do not wipe data. The first boot will take a little longer while the demo
dataset is seeded.

If you prefer to seed manually, set `RAILPACK_SKIP_MIGRATIONS=true` is not needed;
instead remove the seeding part from `railpack.json` and set the Railway **Release
Command** to:
```
php artisan db:seed --class=DemoSeeder --force
```
and remove the seeding part from the start command.

## 4. Notes

- **File uploads** (team logos, eligibility documents) are stored on the container's ephemeral
  disk and will be lost on redeploy. For a persistent demo, configure an S3-compatible disk
  (Supabase Storage or AWS S3) via `FILESYSTEM_DISK` and the `AWS_*` variables.
- **Queue workers**: with `QUEUE_CONNECTION=sync`, jobs run inline. For production scale, add a
  separate Railway service running `php artisan queue:work`.
- **Scheduler**: if you need scheduled tasks, add a service running `php artisan schedule:work`.
- Supabase's direct connection (port 5432) is used so Laravel migrations and prepared
  statements work without extra configuration. Do not use the pooled (pgbouncer) URL here.