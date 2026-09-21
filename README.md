# Rentara infrastructure baseline

This repository currently contains the Rentara application foundation, not the Rentara workspace product.

## Implemented baseline

- Laravel 12.69.2 (PHP 8.2+), with the Breeze Livewire authentication starter kit, Livewire 3, and Volt routes.
- Tailwind CSS v3/PostCSS and Vite asset tooling.
- Standard authentication, email verification, password reset/confirmation, profile, and authenticated dashboard routes supplied by the starter kit.
- Initial Laravel migrations for users, password-reset tokens, sessions, cache, and jobs.
- Rentara brand SVG source assets at `resources/images/brand/`.

## Not implemented in this task

There are no workspace, membership, role/permission, platform-super-admin, product dashboard, invitation, audit-log, or custom Rentara UI implementations. Audit logging is intended to be an internal implementation when that feature is built; no audit package or Spatie Permission package is installed.

The approved, future authorization design is recorded in [docs/authorization-architecture-decision.md](docs/authorization-architecture-decision.md). That decision is not an implementation claim.

## Development and hosting

See [docs/environment-development-and-hosting-baseline.md](docs/environment-development-and-hosting-baseline.md) for local setup, test-database behavior, and shared-hosting requirements.
