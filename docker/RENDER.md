# Render deployment notes

## Service type
Web Service, Docker runtime, this repo's `Dockerfile` at the project root.
Free instance type.

## Environment variables (Render's own secrets store, never a committed `.env`)
- `APP_KEY` — generate with `php artisan key:generate --show`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` — the Render-issued service URL
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — the Supabase **Session pooler** connection parameters for `taskora-db` (not the Direct connection host — Render's network is IPv4-only, and Supabase's Direct host is IPv6-only by default). `DB_USERNAME` includes the project ref suffix (`postgres.<project-ref>`).
- `DB_SSLMODE=require`
- `LOG_CHANNEL=stack`, `LOG_LEVEL=error`
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`

## Migrations
Render's Free instance type does not support Pre-Deploy Command or SSH
access (both are paid-plan only), so `docker/entrypoint.sh` runs
`php artisan migrate --force` itself on every container boot, right after
`config:cache`. This also re-applies on a Free-tier wake-from-sleep
restart, not just on a fresh deploy — safe, since Laravel only runs
migrations that haven't been applied yet.

## Deploy trigger
GitHub-connected auto-deploy, restricted to `main`.

## After first successful deploy
Update the hardcoded ngrok URL in the Flutter app's
`lib/core/services/remote/dio_client.dart` to the new Render URL. Not done
until deploy is verified live (see project memory).
