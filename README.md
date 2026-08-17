# MovieMate

MovieMate is a Laravel application for cinema operations and online ticket booking. It covers movie and showtime management, room layouts, seat reservations, payments, ticket delivery, counter sales, and operational reporting.

## Requirements

- PHP 8.3 or newer
- Composer 2
- Node.js and npm
- MySQL 8 or newer

## Local setup

```bash
composer install
npm install
```

Create the local environment file and application key:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Configure the MySQL connection in `.env`, then initialize the database:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

## Development

Run the application services in separate terminals:

```bash
php artisan serve
npm run dev
php artisan schedule:work
```

When queued jobs are enabled, also run:

```bash
php artisan queue:work --tries=3 --timeout=120
```

## Verification

```bash
php artisan test
npm run build
```

See [`docs/TEAM_SETUP.md`](docs/TEAM_SETUP.md) for team environment rules, payment sandbox configuration, and demo setup details.
