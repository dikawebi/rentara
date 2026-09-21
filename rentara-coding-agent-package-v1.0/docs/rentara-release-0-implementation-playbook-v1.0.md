# Rentara — Release 0 Implementation Playbook v1.0

## Objective

Build a secure, branded, multi-workspace foundation for Rentara. Release 0 is complete only when a user can authenticate, receive correct role-based access, work within an isolated workspace, see the Rentara app shell, and use demo data safely.

Do not start Property, Unit, Tenant, Contract, Billing, Marketplace, Payment Gateway, or Maintenance modules in this release.

## Fixed Constraints

- Laravel full-stack: Blade + Livewire + Alpine.js + Tailwind CSS.
- MySQL/MariaDB target for shared hosting.
- No Docker.
- No Next.js or Node.js runtime in production.
- No WebSocket/Reverb, Horizon, or permanent queue worker.
- Build frontend assets locally or in CI; commit/upload `public/build` for shared hosting.
- Brand: Rentara 07A Balanced Link, Manrope 800 wordmark, tagline “Kelola properti. Temukan hunian.”

## Deliverables

1. Laravel project baseline and environment configuration.
2. Authentication with registration, login, logout, email verification, and password reset.
3. Role and permission system.
4. Workspace and workspace membership.
5. Role-specific dashboard placeholders.
6. Rentara brand configuration, logo asset registration, and responsive app shell.
7. Audit log baseline.
8. Factories, demo seeders, and automated tests.
9. Local setup and deployment notes.

## 1. Setup Decisions

### Laravel version

- If starting a new project, use the latest Laravel release compatible with the local PHP version and selected shared host.
- If a Laravel 12 project already exists, do not upgrade it solely for this release.
- PHP minimum is 8.2.

### Required packages

Use only packages with a clear purpose. Before installation, the agent must list package name, version, reason, and impact.

Expected categories:

- Authentication starter kit or Laravel authentication components.
- Livewire.
- Role/permission package, preferably Spatie Laravel Permission.
- Optional audit logging package, or an internal audit log implementation.

Do not add Filament as the public/operational UI. The app shell must be custom Rentara UI. A future administrative helper can be assessed separately.

### Environment files

Maintain:

- `.env.example` — no credentials.
- `.env` — local only, never committed.
- `docs/environment.example.md` — plain-language required variables for development, staging/demo, and production.

Required brand variables:

```text
APP_BRAND_NAME=Rentara
APP_BRAND_TAGLINE="Kelola properti. Temukan hunian."
APP_SUPPORT_EMAIL=
```

## 2. Database Design

### Required migrations

#### Extend `users`

Add only fields needed at foundation stage:

```text
phone                 nullable
avatar_path           nullable
status                active|inactive|suspended
last_login_at         nullable timestamp
```

Use the framework standard fields for name, email, password, verification, and remember token.

#### `workspaces`

```text
id
owner_id              foreign key users.id
name
slug                  unique
status                active|inactive|suspended
timezone              default Asia/Jakarta
currency              default IDR
created_at
updated_at
soft_deletes
```

#### `workspace_members`

```text
id
workspace_id          foreign key
user_id               foreign key
role                  nullable descriptive snapshot only if needed
status                invited|active|inactive
joined_at             nullable timestamp
created_at
updated_at
unique(workspace_id, user_id)
```

Global role/permission remains the authority. Do not use `workspace_members.role` to bypass permission checks.

#### `audit_logs`

```text
id
user_id               nullable foreign key
workspace_id          nullable foreign key
event                 string
auditable_type        nullable polymorphic type
auditable_id          nullable polymorphic id
old_values            nullable json
new_values            nullable json
ip_address            nullable string
user_agent            nullable text
created_at
```

Do not log passwords, password reset tokens, raw documents, or other secrets.

### Relationships

```text
User hasMany ownedWorkspaces
User belongsToMany workspaces through workspace_members
Workspace belongsTo owner (User)
Workspace belongsToMany members (User) through workspace_members
AuditLog belongsTo user
AuditLog belongsTo workspace
```

## 3. Roles and Permissions

### Roles

```text
super_admin
owner
manager
staff
tenant
```

### Initial permissions

#### Platform

```text
platform.view_dashboard
platform.manage_users
platform.manage_workspaces
platform.view_audit_logs
platform.manage_settings
```

#### Workspace

```text
workspace.view_dashboard
workspace.manage_profile
workspace.manage_members
workspace.view_reports
```

#### Placeholder module permissions

Create permission namespaces now, but do not build their screens in Release 0:

```text
property.view / property.manage
unit.view / unit.manage
tenant.view / tenant.manage
contract.view / contract.manage
invoice.view / invoice.manage
payment.view / payment.verify
maintenance.view / maintenance.manage
```

#### Tenant self-service

```text
tenant.view_dashboard
tenant.view_profile
tenant.view_contract
tenant.view_invoice
tenant.submit_payment_proof
tenant.create_maintenance_ticket
```

### Role assignment baseline

| Role | Access baseline |
|---|---|
| Super Admin | All platform permissions; no implicit reading of all sensitive documents |
| Owner | Workspace management and all future operational permissions in own workspace |
| Manager | Operational permissions only for assigned workspace/property; no workspace ownership changes |
| Staff | Future daily-operational permissions; no member management or platform actions |
| Tenant | Self-service permissions only |

## 4. Authentication and Account States

### Required flows

1. Registration.
2. Login/logout.
3. Email verification.
4. Password reset.
5. Session invalidation after password change if supported by the chosen auth baseline.
6. Suspended/inactive account denial with a user-friendly message.

### Registration behavior

- A self-registering user starts as `owner` only after the system creates a personal workspace in a safe transaction.
- Tenant accounts are not created through owner self-registration flow; they will be invited in a future release.
- Store email as unique and normalize consistently.

### Redirect rules

```text
super_admin → /admin/dashboard
owner/manager/staff → /app/dashboard
tenant → /tenant/dashboard
```

If a user has no permitted workspace, show a clear empty/invitation state rather than an error.

## 5. Middleware, Policies, and Current Workspace

### Middleware

```text
auth
verified
active-user
role-or-permission
workspace-context
audit-context
```

### Current workspace service

Implement one explicit `CurrentWorkspace` resolver/service. It must:

1. Obtain workspace from route/session selection.
2. Confirm the authenticated user is a member or owner.
3. Reject inactive/suspended workspace access.
4. Expose a stable workspace ID to services and Livewire components.

Do not infer the workspace from arbitrary query string data without authorization.

### Policies

Create policies for `Workspace` and `User` now. Future models must receive a policy at creation time.

## 6. Routes and Screens

### Public/auth routes

```text
/login
/register
/forgot-password
/reset-password/{token}
/verify-email
```

### Super Admin routes

```text
/admin/dashboard
/admin/users                 placeholder, guarded
/admin/workspaces            placeholder, guarded
/admin/audit-logs            placeholder, guarded
```

### Operational routes

```text
/app/dashboard
/app/workspace/settings
/app/members                 placeholder, guarded
```

### Tenant routes

```text
/tenant/dashboard
/tenant/profile
```

All non-public routes require authentication and active account checks. Apply email verification to operational pages after an appropriate welcome/verification screen.

## 7. Rentara UI Shell

### Global requirements

- Add Manrope and Inter/Plus Jakarta Sans through the asset pipeline.
- Use `rentara-logo-07a-primary.svg` and `rentara-logo-07a-mark.svg`.
- Create a reusable `BrandLogo` component with `full` and `mark` variants.
- Use a navy sidebar for desktop operational/admin layout.
- Use a collapsible navigation or drawer on mobile.
- Use an accessible profile menu, notification placeholder, and workspace switcher placeholder.
- Keep tenant layout simpler and mobile-first.

### Dashboard placeholders

#### Super Admin

Show:

- Platform dashboard heading.
- Placeholder cards: Users, Workspaces, Properties, Pending review.
- Empty-state language explaining that platform data arrives after later releases.

#### Owner/Manager/Staff

Show:

- “Ringkasan properti Anda”.
- Placeholder cards: Properties, Units, Tenants, Outstanding invoices.
- Primary action placeholder “Tambah properti” disabled or linked to clear “coming in Release 1” state.

#### Tenant

Show:

- “Beranda Saya”.
- Placeholder cards: Unit, Contract, Invoice.
- Clear text explaining that an administrator will add tenancy details in Release 2.

### Required UX states

- Loading state for Livewire actions.
- Empty state.
- Validation error state.
- Success feedback.
- Permission denied state.
- Workspace not selected state.

## 8. Audit Log Baseline

Log:

- Login successful.
- Login denied due to inactive/suspended account.
- Logout.
- Registration.
- Workspace created.
- Member added/removed/status changed.
- Role changed.
- Super Admin access to protected platform pages.

Logging must be non-blocking where possible, but critical business updates must not silently lose their audit record.

## 9. Seeders and Factories

Create factories for User, Workspace, WorkspaceMember, and AuditLog.

Create a development/demo seeder with:

```text
Super Admin      admin@rentara.test
Owner            owner@rentara.test
Manager          manager@rentara.test
Staff            staff@rentara.test
Tenant           tenant@rentara.test
Workspace        Rentara Demo Property
```

Passwords must come from an environment variable or development-only default documented in the README. Never place live credentials in source code.

## 10. Test Plan

### Authentication

- User can register.
- User receives/uses verification flow.
- User can log in and log out.
- Suspended user cannot access protected application pages.

### Authorisation

- Tenant cannot open `/app/*` or `/admin/*`.
- Staff cannot open `/admin/*`.
- Owner cannot open `/admin/*`.
- Super Admin can open platform routes.

### Workspace isolation

- Owner of workspace A cannot retrieve workspace B.
- Manager/staff only resolve workspace membership assigned to them.
- A direct URL to another workspace is denied.

### Brand/UI smoke tests

- Authenticated layout renders Rentara brand configuration.
- Correct dashboard is selected per role.
- Mobile navigation exists in rendered operational/tenant layouts.

### Audit tests

- Registration creates audit record.
- Workspace creation creates audit record.
- Login creates audit record without secret data.

## 11. Manual Review Checklist

- Registration creates an Owner, Workspace, and WorkspaceMember atomically.
- Manrope wordmark appears correctly; fallback font works when the web font is unavailable.
- Logo mark is sharp at small size.
- Sidebar and dashboard are usable at 360px width.
- A user cannot switch to an unauthorized workspace.
- Laravel logs contain no errors during normal flow.
- `.env` is not committed.
- Production debug is disabled in deployment documentation.

## 12. Agent Execution Prompt

```text
Read Rentara Master AI Coding Handoff v1.0 and Rentara Release 0 Implementation Playbook v1.0 before changing code.

Implement Release 0 only. Do not build Property, Unit, Contract, Invoice, Maintenance, Marketplace, Payment Gateway, or unrelated modules.

Before making changes, provide:
1. Existing-project assessment.
2. Planned migrations, models, middleware, policies, screens, and tests.
3. Dependency list with purpose.
4. Any decision requiring user approval.

Build in small verified increments. After each increment, run focused tests. At the end, run the full relevant test suite and provide the required completion report.

Preserve these choices:
- Rentara brand, 07A Balanced Link.
- Manrope 800 lowercase wordmark.
- Tagline: “Kelola properti. Temukan hunian.”
- Laravel + Blade + Livewire + Alpine + Tailwind.
- Shared-hosting compatibility; no Docker or Node runtime.
```

## 13. Release Gate

Do not begin Release 1 until the owner confirms all of the following:

- Release 0 tests pass.
- Role redirects and permission boundaries are manually verified.
- Workspace isolation is tested with at least two workspaces.
- Rentara brand shell is accepted on desktop and mobile.
- Seeder/demo experience is usable.
