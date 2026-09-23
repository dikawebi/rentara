# Rentara — Start Here

> **Historical/bootstrap handoff:** this document describes the Release 0 package used to bootstrap Rentara. The current repository is no longer at Release 0; it includes `RENTARA-FEAT-002` through `RENTARA-FEAT-012`. Use the repository implementation and current documentation as the source of truth for present behavior.

## What This Folder Is

This is a **historical coding-agent documentation package**, not a runnable Laravel application.

At the time of this handoff, the project source code did not exist. First create a Laravel project, then copy this package into that project and instruct the coding agent to implement Release 0 only. That bootstrap instruction is historical and must not be read as the current repository scope.

## Package Contents

```text
00-START-HERE.md
docs/
├── rentara-master-ai-coding-handoff-v1.0.md
├── rentara-release-0-implementation-playbook-v1.0.md
├── rentara-messaging-spec-v1.0.md
└── rentara-brand-guideline-v1.0.md
assets/
├── rentara-logo-07a-primary.svg
└── rentara-logo-07a-mark.svg
```

## Final Product Decisions

- Brand: Rentara.
- Logo: 07A Balanced Link.
- Wordmark: lowercase `rentara`, Manrope 800.
- Tagline: **Kelola properti. Temukan hunian.**
- Stack: Laravel full-stack, Blade, Livewire, Alpine.js, Tailwind CSS, MySQL/MariaDB, PWA.
- Deployment target: shared hosting first.
- Do not use Docker, Next.js, Node.js runtime in production, WebSocket/Reverb, Horizon, permanent queue workers, or a payment gateway in the MVP.

## Step 1 — Prepare Windows Development Environment

Open PowerShell and verify the required tools:

```powershell
php -v
composer -V
node -v
npm -v
git --version
mysql --version
```

Minimum target:

- PHP 8.2+
- Composer 2+
- Node.js LTS (used locally for Vite/Tailwind builds only)
- MySQL 8.0.16+ or MariaDB 10.6+ for supported application/production use; SQLite is test-only and PostgreSQL is not production-supported.
- Git

If one command is unavailable, resolve that prerequisite before creating the project.

## Step 2 — Create the Laravel Project

Choose a parent folder for source code, then run:

```powershell
composer create-project laravel/laravel rentara
cd rentara
git init
```

If an existing Laravel project has already been created for Rentara, do not create another one. Use the existing project after confirming its PHP/Laravel version and Git status.

## Step 3 — Create the Database

Create a local MySQL database named `rentara`:

```powershell
mysql -u root -p -e "CREATE DATABASE rentara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Update the new project `.env` file:

```dotenv
APP_NAME=Rentara
APP_URL=http://127.0.0.1:8000
APP_BRAND_NAME=Rentara
APP_BRAND_TAGLINE="Kelola properti. Temukan hunian."

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rentara
DB_USERNAME=root
DB_PASSWORD=
```

Set `DB_USERNAME` and `DB_PASSWORD` according to the local MySQL installation. Never commit the `.env` file.

## Step 4 — Copy This Package into the Project

Inside the Laravel project, create a project documentation folder:

```powershell
New-Item -ItemType Directory -Force -Path .\docs\rentara
```

Copy the contents of this package into `docs/rentara/`, preserving this structure:

```text
rentara/
├── app/
├── database/
├── resources/
├── routes/
├── docs/
│   └── rentara/
│       ├── 00-START-HERE.md
│       ├── docs/
│       └── assets/
└── ...
```

Do not copy older Huniva documents or early brainstorming notes into the project context. The files in this package are the final Rentara source of truth.

## Step 5 — Start the Coding Agent

Open the **Laravel project root** in VS Code/OpenCode. The agent must work in the project root, not inside the `docs` folder.

Send this first prompt:

```text
Read every file in docs/rentara/ before writing code.

This is Rentara, an Indonesian rental-property management SaaS that will later include a marketplace. The final brand is Rentara 07A Balanced Link, lowercase Manrope 800 wordmark, and the tagline “Kelola properti. Temukan hunian.”

Use Laravel full-stack with Blade, Livewire, Alpine.js, Tailwind CSS, MySQL/MariaDB, and PWA. The deployment target is shared hosting. Do not use Docker, Next.js, a Node.js runtime, WebSocket/Reverb, Horizon, permanent workers, or payment gateway for the MVP.

Implement only Release 0: authentication, role/permission, workspace/membership, Rentara layout and brand configuration, dashboard placeholders, audit log, demo seeders, and automated tests.

Before writing code, report:
1. Existing-project assessment.
2. Files to create/change.
3. Dependencies with purpose.
4. Migration/relationship plan.
5. Routes, middleware, policies, and permissions.
6. Test plan.

Wait for my approval before changing code.
```

## Step 6 — Review the Agent Plan

Approve the plan only if it does all of the following:

- Starts from Release 0 only.
- Uses workspace isolation from the start.
- Uses custom Rentara UI, not Filament as the main application UI.
- Uses MySQL/MariaDB and shared-hosting-safe patterns.
- Includes migrations, policies, seeders, factories, and tests.
- Does not build Property, Unit, Contract, Invoice, Marketplace, or messaging UI yet.

Messaging must remain planned according to `rentara-messaging-spec-v1.0.md`: tenant/provider messaging is Release 4 and marketplace inquiry is Release 6.

## Step 7 — Run Locally After Release 0 Is Implemented

In one terminal:

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

In a second terminal:

```powershell
npm install
npm run dev
```

Then open:

```text
http://127.0.0.1:8000
```

Use the development demo credentials generated/documented by the agent. Do not use demo passwords in production.

## Step 8 — Release 0 Acceptance Review

Test each role:

- Super Admin → `/admin/dashboard`
- Owner/Manager/Staff → `/app/dashboard`
- Tenant → `/tenant/dashboard`

Confirm:

- Login, logout, verification, and password reset work.
- Tenant cannot open owner/admin routes.
- Staff cannot open Super Admin routes.
- Two workspaces cannot access each other by direct URL.
- Rentara logo, Manrope wordmark, tagline, and responsive navigation are present.
- Audit records are generated for login, registration, workspace creation, and role/member changes.
- Test suite passes.

Historically, only after this review would the agent begin Release 1: Property and Unit Management. The current repository already includes property, unit, contract, invoice, and maintenance implementations through `RENTARA-FEAT-012`.

## Step 9 — Build for Shared Hosting

Do this only after the project runs correctly locally.

```powershell
npm run build
php artisan optimize
```

For production shared hosting:

1. Upload/deploy the Laravel project, including `public/build`.
2. Set the hosting document root to the Laravel `public/` directory.
3. Create a production MySQL 8.0.16+ or MariaDB 10.6+ database and production `.env` values. PostgreSQL is not production-supported.
4. Set `APP_DEBUG=false` and `APP_ENV=production`.
5. Ensure `storage/` and `bootstrap/cache/` are writable.
6. Configure SMTP before enabling email verification/reminders.
7. Configure the Laravel scheduler cron job when billing/reminders are introduced in later releases.

Do not run `npm run dev` on shared hosting. Shared hosting receives the prebuilt `public/build` assets from local development or CI.

## File Priority for the Coding Agent

Read in this order:

1. `00-START-HERE.md`
2. `docs/rentara-master-ai-coding-handoff-v1.0.md`
3. `docs/rentara-release-0-implementation-playbook-v1.0.md`
4. `docs/rentara-brand-guideline-v1.0.md`
5. `docs/rentara-messaging-spec-v1.0.md`
6. `assets/rentara-logo-07a-primary.svg`
7. `assets/rentara-logo-07a-mark.svg`
