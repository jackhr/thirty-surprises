# Thirty Surprises (PHP + MySQL)

PHP app for scheduling and revealing surprises. Runtime is PHP/MySQL only. Node is not required.

## Requirements

- PHP 8.1+
- `pdo_mysql` extension
- MySQL 8+
- Apache (for cPanel deploy)

## Project layout

- `app/` controllers, services, core classes, views
- `config/` bootstrap + routes
- `public/` web root (front controller is `public/index.php`)
- `migrations/mysql/` SQL schema + seed files
- `scripts/` utility scripts

## Environment

1. Copy the template:
   `cp .env.example .env`
2. Fill in database values.

### Required keys

- `APP_ENV`
- `APP_TIMEZONE`
- `APP_LOG_ENABLED`
- `DB_DRIVER`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`

### Optional keys used by code

- `AUTH_SESSION_NAME` (default: `medicine_log_session`)
- `AUTH_SESSION_MAX_AGE` (ms or seconds)
- `SECRET` (used for signed remember tokens)
- `TESTING_PASSWORD` (used by `POST /surprises/testEmail`)
- `TO_EMAIL`, `MAIL_FROM`, `MAIL_USERNAME`, `MAIL_DRY_RUN`, `APP_PUBLIC_URL` (email behavior)
- `ADMIN_NAME`, `ADMIN_PASSWORD` (used by `scripts/init-db.php`)

## Database

Run SQL migrations in order:

1. `migrations/mysql/001_create_tables.sql`
2. `migrations/mysql/002_seed_from_mongodb_dump.sql` (optional seed)

```bash
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p "$DB_NAME" < migrations/mysql/001_create_tables.sql
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p "$DB_NAME" < migrations/mysql/002_seed_from_mongodb_dump.sql
```

Alternative setup script:

```bash
php scripts/init-db.php
```

This creates tables and optionally creates/updates an admin user if `ADMIN_PASSWORD` is set.

## Local run

```bash
php -S localhost:8000 -t public
```

Open `http://localhost:8000`.

## cPanel deploy

1. Upload repository files to your app directory.
2. Point the domain/subdomain document root to `public/`.
3. Ensure Apache rewrite support is enabled.
4. Add production `.env`.
5. Run schema migration(s) against production MySQL.

## Tailwind (no npm)

Compiled CSS lives at `public/stylesheets/output.css`.

To rebuild:

1. Download standalone Tailwind CLI binary to `bin/tailwindcss`
2. `chmod +x bin/tailwindcss`
3. `./scripts/build-tailwind.sh --minify`

## Routes

- `GET /`
- `GET /login`
- `POST /login`
- `GET /logout`
- `GET /surprises` (auth)
- `POST /surprises/testEmail`
- `PUT /surprises/{id}/viewed`
- `GET /admin` (auth)
- `GET /admin/surprise/{id}/notify` (auth)
- `POST /admin/surprise` (auth)
- `PUT /admin/surprise/{id}` (auth)
- `DELETE /admin/surprise/{id}` (auth)
