# Rentara environment, development, and hosting baseline

This document describes the infrastructure baseline only. Workspace features and Rentara UI are not implemented yet.

## Local development

1. Copy `.env.example` to `.env` if it is not already present.
2. Generate an application key with `php artisan key:generate`.
3. Set the MySQL 8+ or compatible MariaDB database name and credentials in your local `.env`. Do not commit this file.
4. Start the database server yourself before running migrations. No project script creates a database or runs migrations, and this baseline does not start database software.
5. Install dependencies with `composer install` and `npm install`.
6. Build assets with `npm run build`, or use `npm run dev` while developing.

The ignored local `.env` and the non-secret `.env.example` provide the Rentara application and brand defaults, plus MySQL-compatible connection defaults: host `127.0.0.1`, port `3306`, database `rentara`, and blank username/password. Supply local credentials only in `.env`.

The default automated tests use in-memory SQLite through `phpunit.xml`; they do not require MySQL to be running.

The application defaults to the database queue connection, but none of the project scripts starts a queue worker. Start and supervise a worker separately only when implemented features dispatch queued work.

## Brand environment values

`APP_BRAND_NAME` and `APP_BRAND_TAGLINE` provide the approved Rentara values. `APP_SUPPORT_EMAIL` is intentionally blank until an approved support address exists. These values are configuration inputs for later application branding; no custom layout is included in this baseline.

## Shared hosting baseline

- Configure the web document root to the Laravel `public` directory.
- Deploy the application source, Composer dependencies as appropriate for the host, and prebuilt `public/build` assets. Do not run a Node runtime or `npm run dev` on the host.
- Create the production MySQL 8+ or compatible MariaDB database and set production-only credentials and `APP_KEY` directly in the host environment or uncommitted `.env` file.
- Use `APP_ENV=production` and `APP_DEBUG=false` in production.
- Make `storage` and `bootstrap/cache` writable by the web-server user.
- Run `php artisan migrate --force` only after a reviewed migration/deployment plan. No database is created by this baseline.
- Configure mail and scheduled jobs only when the corresponding approved product features require them.

Never commit `.env`, database credentials, `APP_KEY`, or production configuration.
