# Freelance Manager

Freelance Manager is a Laravel and Filament application for running a small freelance operation from one tenant-aware workspace. It tracks clients, projects, tasks, invoices, sales, currencies, units, categories, bank details, account members, and email templates.

## Stack

- PHP 8.4
- Laravel 13
- Filament 5
- Livewire 4
- Tailwind CSS 4
- Pest 4
- Laravel Pint

## Main Features

- Multi-tenant account workspace through the Filament app panel.
- Client, project, and task management with account scoping.
- Project task assignment and dashboard task summaries.
- Invoice creation with invoice items, PDF rendering, email sending, and status actions.
- Sales tracking and revenue widgets.
- Team membership with account roles.
- Admin panel for currency management.
- Seeded demo data for local development.

## Panels

- App panel: `/app`
- Admin panel: `/admin`

The app panel supports registration, login, email verification, tenant registration, database notifications, and required email multi-factor authentication.

## Local Setup

Install dependencies and prepare the application:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

You can also run the project setup script, then seed demo data if needed:

```bash
composer run setup
php artisan db:seed
```

Update `.env` for your local database, mail, queue, and app URL settings before running migrations if you are not using the defaults.

## Development

Run the Laravel server, queue listener, and Vite dev server together:

```bash
composer run dev
```

Or run only the frontend dev server:

```bash
npm run dev
```

Build frontend assets for production:

```bash
npm run build
```

## Seeded Account

`php artisan migrate --seed` creates a demo owner, account, clients, projects, team members, and tasks.

- Email: `test@test.com`
- Password: `password`
- Account: `Freelance Studio`

## Testing and Formatting

Run the test suite:

```bash
php artisan test --compact
```

Format PHP changes:

```bash
vendor/bin/pint --dirty --format agent
```

## Useful Commands

```bash
php artisan route:list --except-vendor
php artisan config:show app.name
php artisan queue:listen --tries=1
php artisan pail
```

## Notes

- The root `/` route renders the default welcome view.
- Filament resources live under `app/Filament/App/Resources` and `app/Filament/Admin/Resources`.

## Production operations

### Hosting prerequisites

- The document root is `public/`. If the host cannot do that, add a root `.htaccess` that denies everything outside `public/`. This protects `.env`.
- PHP 8.4 in hPanel **and** for the SSH CLI (`php -v`, or `$DEPLOY_PHP_BIN -v`). `composer` must run on that interpreter too.
- `proc_open` and `exec` are enabled. Check with `php artisan schedule:run -v` once the cron below is in place.
- Create the production `.env` from `.env.production.example`. `APP_DEBUG` must be `false`.

### Cron

One entry runs everything (expired-token pruning and the queue worker):

```
* * * * * cd /home/<user>/domains/<domain>/<app-dir> && /path/to/php8.4 artisan schedule:run >> /dev/null 2>&1
```

The scheduler starts `queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=60` every minute, so queued mail (invoices, quotes, invitations) goes out within about a minute. Without `pcntl`, `--timeout` is not enforced (`php -m | grep pcntl`).

### Deploy

Pushes to `main` run the tests, then `.github/workflows/deploy.yml` deploys over SSH:

1. It checks that the PHP CLI is 8.4 or newer and that `APP_DEBUG` is not on in production. Nothing has changed yet if either check fails.
2. `artisan down`, then it fast-forwards the server checkout to the exact commit CI tested (`git merge --ff-only`), runs `composer install --no-dev`, `migrate --force`, `optimize` (config, routes, views, events, `filament:optimize` and `icons:cache`) and `queue:restart`, then `artisan up`.
3. If anything fails after `down`, the site is brought back up and the job fails. A failed migration therefore leaves the new code running against a partly migrated schema. Follow the rollback steps below.

GitHub settings:

- Secrets: `DEPLOY_SERVER`, `DEPLOY_USER`, `DEPLOY_KEY`, `DEPLOY_PATH`. Optional: `DEPLOY_MAINTENANCE_SECRET`, which lets you reach the site during a deploy at `https://<site>/<secret>`.
- Repository variables (not secrets): `DEPLOY_PORT` (Hostinger SSH is usually `65002`; the default is 22) and `DEPLOY_PHP_BIN` (set it if the CLI `php` is not 8.4).
- The server checkout must be on `main` with a clean working tree and a reachable `origin`.

The server runs no Node. `public/build` is committed, and CI fails when it differs from a fresh build. After any frontend change, build with `php artisan view:clear && npm run build` and commit `public/build`. Clearing the compiled views first matters because Tailwind also scans `storage/framework/views`.

After the first deploy, run `curl -I https://<site>` and check for the security headers (HSTS only appears when TLS reaches PHP as a secure request).

### Rollback

The deploy log prints `Previous release: <sha>`.

**(a) Code only.** Preferred: revert on `main` and let CI deploy. By hand: `php artisan down`, `git checkout <sha>` (detached), `composer install --no-dev`, `php artisan optimize`, `php artisan queue:restart`, `php artisan up`.

**(b) A migration ran.** If the migration is reversible and nothing depends on data written since, run `php artisan migrate:rollback --step=N` and then (a). Otherwise restore the most recent hPanel backup (daily; check its time in hPanel, Backups), then (a). The deploy does not take a backup of its own, so take an on-demand hPanel backup before a risky migration.

Phase B migrations: rolling back the invitation column loses only `expires_at`, and the task index rollback is safe on MySQL.

### Backups

The app takes no backups of its own. The hosting plan (hPanel) backs up the database and files daily, and that is the recovery path.

- Before a risky deploy or migration, take an on-demand backup in hPanel (Backups).
- Recommended before launch: restore one database backup to staging or a local database and check that `php artisan migrate:status` matches the code, and note the retention days and whether the copies are stored off the web server.

Restore:

1. `php artisan down`
2. Restore the most recent hPanel backup of the database from hPanel.
3. `php artisan migrate:status` should match the code's migrations.
4. `php artisan up`

### Logs

- `storage/logs/laravel-YYYY-MM-DD.log`: application log, warnings and above, kept 14 days (`LOG_DAILY_DAYS`).
- `storage/logs/business-YYYY-MM-DD.log`: business events (invoice sent and paid, quote accepted, API token created and revoked, invitation accepted), kept 30 days (`LOG_BUSINESS_DAYS`).
- Failed queue jobs are logged at `error` ("Queued job failed") and kept in `failed_jobs`: `php artisan queue:failed`, `php artisan queue:retry all`.

### Admin panel

`/admin` is limited to the emails in `ADMIN_EMAILS` (comma-separated, case-insensitive). Each admin must also enable email multi-factor authentication in their profile; an allowlisted user without it gets a 403.

### API notes

- Send `Accept: application/json`. `/api` and `/mcp` always answer errors as JSON.
- Rate limits are per token: `API_RATE_LIMIT_PER_MINUTE` for `/api` and `MCP_RATE_LIMIT_PER_MINUTE` for `/mcp` (60 each). Going over returns `429` with `Retry-After`.
- `GET /api/tasks` takes `limit` from 1 to 100 (default 25). Anything else returns `422`.
- New tokens expire after 30 days, 90 days (the default), 1 year, or never; the choice is on the API Tokens page and in `freelance:api-token --expires-in-days=`. Tokens created before expiry existed never expire. Expired tokens get `401` and are pruned daily.
