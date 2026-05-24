# Freelance Manager

Freelance Manager is a Laravel and Filament application for running a small freelance operation from one tenant-aware workspace. It tracks clients, projects, tasks, invoices, sales, currencies, units, categories, bank details, account members, and email templates.

## Stack

- PHP 8.3
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

- Temporary invoice preview routes are available under `/temp/invoices/*` for testing and debugging.
- The root `/` route currently renders the default welcome view.
- Filament resources live under `app/Filament/App/Resources` and `app/Filament/Admin/Resources`.
