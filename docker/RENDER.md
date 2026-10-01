# Render deployment notes

Not executed yet — Render account is locked out (see project memory,
`portfolio-infra-plan.md`, Phase 3). This documents the settings to apply
once access is restored and `taskora-db` (Supabase Postgres) is wired up.

## Service type
Web Service, Docker runtime, this repo's `Dockerfile` at the project root.

## Environment variables (Render's own secrets store, never a committed `.env`)
- `APP_KEY` — generate with `php artisan key:generate --show`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` — the Render-issued service URL
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — from the `taskora-db` Supabase project's connection info
- `DB_SSLMODE=require`
- `LOG_CHANNEL=stack`, `LOG_LEVEL=error`
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`

## Pre-Deploy Command
```
php artisan migrate --force
```
Runs against `taskora-db` before each new deploy goes live. Do not run this
locally against the real database — only inside Render, after `DB_*` env
vars point at the real `taskora-db` instance.

## Deploy trigger
GitHub-connected auto-deploy, restricted to `main`.

## After first successful deploy
Update the hardcoded ngrok URL in the Flutter app's
`lib/core/services/remote/dio_client.dart` to the new Render URL. Not done
until deploy is verified live (see project memory).
