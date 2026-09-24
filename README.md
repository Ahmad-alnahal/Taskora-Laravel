# Taskora API

A Laravel 12 REST API powering **Taskora**, a productivity, time-tracking, and earnings app for freelancers and students. Built as a portfolio project to demonstrate production-grade backend practices: clean layering, real security hardening, and a well-defined client contract — not just CRUD endpoints.

## Tech Stack

- **Laravel 12** + **Sanctum** (bearer token auth)
- **SQLite** (development)
- **PHPUnit / Pest** for feature tests
- **PHPOffice/PhpSpreadsheet** for Excel export

## Core Features

- **Projects & Tasks** — full CRUD with automatic project status recalculation, per-project hourly-rate overrides, and idempotent create requests (safe to retry after a dropped connection).
- **Dashboard** — a single aggregated endpoint for stats, a 6-month earnings chart, and upcoming tasks, computed from each task's effective hourly rate.
- **Earnings** — calculated per task (`actual_hours × effective rate`), where a project can override the user's default rate.
- **Excel export** — queued spreadsheet generation with a time-limited signed download link.

## Security & Auth Design

Security was treated as a first-class concern throughout, not bolted on at the end:

- **Per-device OTP confirmation** — every new token (register/login) starts unverified and must be confirmed via an emailed code before it can access any protected resource. A device already confirmed stays trusted until logged out.
- **Multi-step secure password change** — changing your password while logged in requires re-verifying the current password, then a separate emailed OTP, then a short-lived, user-bound token for the actual change — independent from the "forgot password" flow.
- A full category-by-category security audit was performed (access control, secrets, auth/session, injection, storage, misconfiguration, dependencies, logging, test coverage) with no unresolved critical or high findings.
- Ownership is enforced everywhere via Policies — no endpoint trusts a client-supplied `user_id`.

## API Design

- Uniform response envelope: `{ success, message, data }`, with a structured `errors` map on validation failures.
- **Idempotency-Key** header support on every write endpoint — a client can safely retry a request whose response was lost in transit; the server replays the original result instead of re-executing the action.
- Machine-readable `error_code` values (e.g. `otp_required`) so the client can react precisely instead of parsing error text.

## Architecture

Thin controllers delegate to Form Requests (validation), Policies (authorization), API Resources (response shape), and dedicated Services for OTP/token logic — keeping business rules out of the HTTP layer.

## Getting Started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Run these three together during development:

```bash
php artisan serve
php artisan queue:work      # required for OTP/reset emails to actually send
php artisan schedule:work   # prunes expired idempotency-key records daily
```

By default `MAIL_MAILER=log`, so OTP/reset codes are written to `storage/logs/laravel.log` instead of being emailed — set real SMTP credentials in `.env` to receive them for real.

## Testing

```bash
php artisan test
```
