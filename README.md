# Rentara workspace foundation and application shell

This repository contains the Rentara application, the implemented RENTARA-FEAT-002 identity/workspace foundation, the RENTARA-FEAT-003 operational/platform shell, the RENTARA-FEAT-004 audit baseline, and the RENTARA-FEAT-005 local demo seed.

## Implemented foundation

- Laravel 12.69.2 (PHP 8.2+), with the Breeze Livewire authentication starter kit, Livewire 3, and Volt routes.
- Tailwind CSS v3/PostCSS and Vite asset tooling.
- Breeze Livewire authentication, email verification, password reset/confirmation, profile, and active-user enforcement.
- User identity status, global platform-only Super Admin, workspace and membership schema, current-workspace resolution and switching, workspace authorization, and member management.
- A config-driven Rentara shell for guarded operational (`/app`) and platform (`/admin`) dashboards, with accessible desktop and mobile navigation.
- Laravel migrations for users, workspace data, audit logs, password-reset tokens, sessions, cache, and jobs.
- Rentara brand SVG source assets at `resources/images/brand/`.
- Append-only audit baseline (`audit_logs` with nullable user/workspace `nullOnDelete` relations and nullable polymorphic auditable): allowlisted events for registration, workspace creation, login success/denied, logout, member lifecycle, and platform-dashboard access; critical writes are transaction-coupled and roll back on audit failure; denied logins are generic (`auth.login_denied`, null relations/values) and IP-bounded at 20/min; request context stores only validated IP plus a `sha256:` UA fingerprint, with secrets/raw UA excluded and Eloquent `user_agent` writes rejected (logger-only query-builder path). There is no audit viewer, listing/API, or audit package.
- Local-only demo seed (`RentaraDemoSeeder`, via `php artisan db:seed` in non-production): active verified `admin@rentara.test` (platform `super_admin`, no workspace membership), `owner@rentara.test` (canonical owner), `manager@rentara.test`, and `staff@rentara.test` in the `Rentara Demo Property` workspace (`rentara-demo-property`, `Asia/Jakarta`, `IDR`). Password defaults to `password` locally and is overridden by `DEMO_PASSWORD`. The seeder is guarded out of production at both seeder levels and is idempotent on rerun. There is no tenant demo account (deferred).

## Scope and documentation

The shell does not implement a tenant portal, dashboard metrics, notifications, a usable workspace-switching interface, property actions or property management, or later property, unit, tenant/penyewa, billing, and platform-review modules. Dashboard figures and relevant controls are explicitly later-release placeholders. Invitations, ownership transfer, co-owners, and property-level assignments are also not implemented. There is no tenant demo account or tenant demo data. Audit coverage is limited to the baseline above; there is no audit viewer/listing/API, retention/purge, or audit package, and no Spatie Permission package is installed.

See [docs/workspace-foundation-implementation.md](docs/workspace-foundation-implementation.md) for the verified implementation, shell behavior and boundaries, deployment note, and test/build evidence. The supporting authorization decision is at [docs/authorization-architecture-decision.md](docs/authorization-architecture-decision.md).

## Development and hosting

See [docs/environment-development-and-hosting-baseline.md](docs/environment-development-and-hosting-baseline.md) for local setup, test-database behavior, and shared-hosting requirements.
