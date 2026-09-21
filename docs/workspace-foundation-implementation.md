# Workspace foundation implementation

**Status:** `RENTARA-FEAT-002` foundation, `RENTARA-FEAT-003` application shell, `RENTARA-FEAT-004` audit baseline, and `RENTARA-FEAT-005` local demo seed implemented; QA PASS recorded for all four.

This document describes the current, implemented identity/workspace foundation and application shell. It does not describe future property, tenancy, or invitation functionality.

## Identity and authentication

- Users have `active`, `inactive`, or `suspended` status. Only active users can authenticate or use routes protected by `active-user`; an inactive or suspended authenticated user is logged out, their session is invalidated, and they are redirected to login.
- The Laravel Breeze Livewire/Volt flow provides registration, login, logout, password reset, password confirmation, and profile routes. Email is normalized to lowercase and trimmed for registration and login.
- `User` implements Laravel email verification. Newly registered users are authenticated but sent to the verification notice; workspace and platform dashboards require a verified email. Verification uses signed, throttled links and redirects to the applicable dashboard.
- Login attempts include `status=active`, use a generic credential error, are throttled after five attempts per normalized email/IP key, regenerate the session on success, and record `last_login_at`.

## Data model and roles

`users` adds nullable `phone`, `avatar_path`, `last_login_at`, and `platform_role`, plus `status` (default `active`). `platform_role` currently permits only nullable `super_admin`.

`workspaces` has a restricted `owner_id` foreign key to `users`, unique `slug`, status (`active`, `inactive`, or `suspended`), timezone (default `Asia/Jakarta`), currency (default `IDR`), and soft deletion. A workspace belongs to its owner and has many `WorkspaceMember` records; a user has many workspace memberships and owned workspaces.

`workspace_members` belongs to a workspace and a user. It stores the authoritative workspace role (`owner`, `manager`, or `staff`), membership status (`active` or `suspended`), and `joined_at`. The database enforces one membership per user per workspace with unique `(workspace_id, user_id)`.

`super_admin` is global and platform-only, not a workspace role and not a workspace-authority bypass. Super Admins use the guarded platform dashboard; ordinary users cannot use it. A Super Admin is denied workspace routes, workspace switching, and workspace member-policy actions.

## Workspace context and authorization

Workspace routes require authentication, an active user, verified email, and a resolved workspace context. Context is held server-side in the `current_workspace_id` session key. It resolves only an active membership in an active, non-deleted workspace. A valid session selection is used; an invalid, stale, or deleted selection is cleared and the first valid membership (by workspace ID) is selected. If none exists, the session selection is cleared and the application returns the no-active-workspace view with HTTP 409.

Switching accepts only an active membership in an active workspace; an unauthorized or invalid workspace ID receives HTTP 404. Super Admin switching is denied. The session value is not authority: route middleware, current-context resolution, workspace-scoped member lookup, and server-side policies enforce access.

Owners and managers may manage members. Managers may manage staff only; staff have no member-management authority. Member mutations are scoped to the current workspace. The canonical owner is `workspaces.owner_id`; that membership cannot be role-changed, suspended, or removed. New owner memberships and assigning the owner role are rejected, so ownership transfer and co-ownership are not available.

## Application shell (`RENTARA-FEAT-003`)

- `x-brand-logo` obtains its accessible brand name and `full`/`mark` SVG asset paths from `app.brand`. Invalid variants fall back to `full`. The `dark` theme applies a reversed treatment (`brightness-0 invert`) for the navy (`rentara-navy`) desktop sidebar and mobile drawer; the mark is used in the mobile header.
- Shell tokens are `rentara-navy` `#16324F`, `rentara-blue` `#2563EB`, `rentara-teal` `#0F766E`, white surface, `#F8FAFC` background, `#172033` foreground, and success/warning/danger colors. The shell uses Inter for body text and Manrope for display headings. Its shell and placeholder copy is Indonesian.
- On desktop, the fixed navy sidebar contains the contextual operational or platform navigation and the header shows the active workspace context for operational routes. On mobile, the sidebar becomes a dialog drawer opened from the header. The drawer moves focus to its close button when opened, traps Tab/Shift+Tab within its focusable controls, closes on Escape or backdrop click, and returns focus to the opening control.
- A visible-on-focus Indonesian skip link (“Lewati navigasi”) targets `#main-content`, which is programmatically focusable. The profile control is a disclosure, not a menu: it exposes Profile and Logout in a list, closes on outside click or Escape, and restores focus to its trigger after Escape.
- The operational dashboard is at `/app/dashboard` (within the guarded `/app` route group); it displays current workspace context and static, later-release placeholders for properties, units, tenants, and outstanding bills. “Tambah properti · Rilis 1” is disabled. Workspace settings and member-management navigation remain policy/context-dependent existing foundation functionality.
- The platform dashboard is at `/admin/dashboard` and remains guarded by the server-side Super Admin middleware. It is a static platform-administration placeholder with later-release counters and review state; it is not a platform-management implementation.
- The notification control is disabled and labels notifications as unavailable. The displayed operational workspace “Ganti nanti” control is also disabled: it is not a workspace-switch UI. Server-side workspace switching from the foundation remains separately routed and authorized.
- `/profile` deliberately continues to use the guest layout and is outside this new application shell. Its access remains the existing authenticated, active-user route. Shell presentation does not replace or weaken server-side authorization.

## Audit baseline (`RENTARA-FEAT-004`)

Implemented append-only audit baseline. There is no audit viewer, listing route, API, or management UI.

### Schema and relationships

- `audit_logs` (`2026_09_21_000003`): `id`, nullable `user_id` (`nullOnDelete`), nullable `workspace_id` (`nullOnDelete`), `event` string, nullable polymorphic `auditable_type`/`auditable_id` (`nullableMorphs`), nullable JSON `old_values`/`new_values`, nullable `ip_address(45)`, nullable `user_agent` text, and `created_at` only (`AuditLog::UPDATED_AT = null`). Indexes on `[workspace_id, created_at]` and `[user_id, created_at]`.
- Audit history survives deletion of the related user or workspace (`nullOnDelete`). `AuditLog` belongs to `User` and `Workspace` and morphs to `auditable`; `User` and `Workspace` each have many `AuditLog` records.
- `auditable` is restricted to `User`, `Workspace`, or `WorkspaceMember` with paired type/id; mismatched or unknown references are rejected.

### Event names

Allowlisted events only (`AuditLogger` constants, enforced by `AuditLog::assertPersistable`):

- `identity.registered`, `workspace.created`
- `auth.login_succeeded`, `auth.login_denied`, `auth.logout`
- `workspace.member_added`, `workspace.member_role_changed`, `workspace.member_status_changed`, `workspace.member_removed`
- `platform.dashboard_accessed` (written by `EnsureSuperAdmin` middleware after authorization, once per authorized platform-dashboard entry)

### Transaction-coupled critical writes

- Registration writes `identity.registered` and `workspace.created` inside the same `DB::transaction` as user/workspace/membership creation; an audit failure rolls back the whole registration.
- Each member lifecycle mutation (`add`, `changeRole`, `suspend`, `remove`) writes its audit row inside its own `DB::transaction` (with `lockForUpdate`); an audit failure rolls back that mutation.
- Successful login writes `auth.login_succeeded` (with `last_login_at`) inside the same `DB::transaction` as the `last_login_at` update.
- Denied-login writes are deliberately non-critical and outside the authentication transaction; they are IP-bounded (see below). Logout and platform-dashboard writes are direct, post-action records.

### Safe actor, workspace, and request context

- Actor (`user_id`) and workspace (`workspace_id`) are nullable and derived server-side only: registration/workspace/member writes use the created/locked models; denied logins use `null`/`null`; login/logout/platform writes use the authenticated user. `AuditLogger` never accepts request payloads.
- The only request-derived data is `AuditRequestContext::fromRequest`: validated IP (`FILTER_VALIDATE_IP`, else `null`) and a `user_agent` fingerprint. Raw header content is never persisted.

### Secret, raw-document, and raw-UA exclusion

- `old_values`/`new_values` allow only keys `user_id`, `workspace_id`, `member_id`, `role`, `status`, `last_login_at`, with type/range checks (positive-integer IDs, valid role/status enums, ISO-8601 `last_login_at`). Secret-bearing keys or values matching `password|token|remember|api[_-]?key|credential|document|content|path|secret|bearer` (plus `authorization` for UA) are rejected rather than partially recorded.
- Request UA is stripped to printable ASCII, trimmed, capped at 512 bytes, discarded when empty or credential-like, otherwise stored only as `sha256:<hex>` (71 chars). Invalid IPs are stored as `null`; persisted `ip_address` must validate as an IP and persisted `user_agent` must match `^sha256:[a-f0-9]{64}$`.

### Eloquent rejection and trusted logger-only fingerprint path

- `AuditLog::$guarded = ['*']`, so `create`/`fill` without override throws `MassAssignmentException`. `setAttribute('user_agent', ...)` always throws; `creating`/`updating` hooks reject any Eloquent-persisted `user_agent` (including `forceFill` and `setRawAttributes` paths) and re-validate the full record.
- The sole trusted persistence path is `AuditLogger::write` via `DB::table('audit_logs')->insert()`. `AuditLogFactory` follows the same rule: it creates without a fingerprint, then sets the `sha256:` value with a query-builder update in `afterCreating`.

### Denied-login generic auditing with IP bound

- Every rejected credential attempt (unknown email, inactive/suspended user, or bad password) records the single generic event `auth.login_denied` with `null` user/workspace/auditable/values, so responses do not enumerate account state.
- Denied-login audit rows are throttled independently of the 5-attempt authentication lockout: RateLimiter key `audit:denied-login:{ip|unknown}`, max 20 per 60 seconds per IP. Bursts beyond 20/min are not recorded.

## Demo seed (`RENTARA-FEAT-005`)

Local-only demo data via `database/seeders/RentaraDemoSeeder.php`, invoked by `DatabaseSeeder` in non-production environments. Run with `php artisan db:seed` (after migrate) in local/testing only; never in production.

- Accounts (all active, email-verified, lowercase email): `admin@rentara.test` (platform `super_admin`, holds no workspace membership by design), `owner@rentara.test` (canonical owner), `manager@rentara.test` (`manager`), and `staff@rentara.test` (`staff`). There is no `tenant@rentara.test` demo account; tenant role/domain remains deferred and no Property/Unit records or invitations are seeded.
- Workspace: `Rentara Demo Property` (`slug: rentara-demo-property`, `owner_id` = demo owner, `status: active`, `timezone: Asia/Jakarta`, `currency: IDR`) with 3 memberships (owner/manager/staff, all `active`).
- Password: local-only default `password` (matches factories); override with the `DEMO_PASSWORD` environment variable. No production secret is hardcoded.
- Non-production guards (defense-in-depth): `RentaraDemoSeeder::run()` returns early when `app()->environment('production')`, and `DatabaseSeeder::run()` only creates the `test@example.com` user and calls `RentaraDemoSeeder` when not in production.
- Idempotent rerun: users via `updateOrCreate`, workspace and canonical owner membership via `firstOrCreate` with self-heal (ownership/role/status corrected on rerun), manager/staff memberships reused when present or created through `ManageWorkspaceMember::add()`, and any stray `super_admin` workspace memberships deleted. Rerunning the seeder does not duplicate users, workspaces, or memberships.

## Lifecycle and containment

Registration is one database transaction: it creates the user, a personal active workspace named from the user, and that user's active canonical `owner` membership. The registration event/email-verification notification occurs after the transaction commits.

Member management is an existing-user lifecycle only. Owners/managers select an already-existing user ID; eligible users must be active and must not be Super Admins. A manager can add or assign only `staff`; owner is never assignable through member management. Invitations are explicitly excluded.

Promotion to Super Admin is rejected for any user who has a workspace membership (including suspended memberships) or owns a workspace, including corrupt owner data. Legacy or corrupt Super Admin memberships are not deleted or repaired by this feature. They fail closed: platform-only users are denied operational workspace routes and switching, and `WorkspaceMemberPolicy` denies every member action even if such a membership exists.

## Known limitations

There is no tenant shell or tenant portal, dashboard metrics, notification delivery or inbox, interactive workspace-switch UI, property action or property-management workflow, property/unit/tenant/billing module, platform review workflow, ownership transfer, co-owner model, invitation workflow, or property-level assignment in this scope. The shell's dashboard values and relevant controls are placeholders only. The demo seed likewise contains no tenant demo account and no Property/Unit/invitation data (deferred).

Audit scope is limited to the baseline above: there is no audit viewer, listing/search/export API, retention/purge job, queued/async audit path, or audit coverage for password reset/confirmation, email verification, profile updates, workspace settings, or workspace switching. Denied-login auditing is sampled under burst traffic (20/min per IP). No audit package or Spatie Permission package is installed.

## Existing-deployment migration note

The migrations add the identity columns and create `workspaces` and `workspace_members`; they do not create a workspace or membership for pre-existing users, assign Super Admins, or repair/delete legacy data. Before deploying to an existing environment, back up the database, rehearse the migration on representative data, and decide which existing active users require a workspace and active membership. Until valid membership data exists, a verified non-Super-Admin user receives the no-active-workspace response on workspace routes. Run production migrations only through the reviewed deployment process (for example, `php artisan migrate --force`).

The audit migration (`2026_09_21_000003`) creates `audit_logs` only; it does not backfill history for pre-existing users, workspaces, memberships, or past authentication events. Existing deployments start with an empty audit trail from migration time onward.

## Test evidence

QA PASS was provided for `RENTARA-FEAT-002`, `RENTARA-FEAT-003`, `RENTARA-FEAT-004`, and `RENTARA-FEAT-005`. Verification on 2026-09-21 passed: `php artisan test` reported **71 tests passed, 319 assertions** (including `DemoSeederTest`: **3 passed, 35 assertions**); `npm run build` completed successfully. Coverage includes identity status and login behavior, verification, atomic registration/rollback, membership uniqueness and lifecycle, canonical-owner protection, workspace selection/switching and stale/deleted cleanup, server-side workspace scoping, Super Admin containment, fail-closed legacy-membership policy behavior, configured branding, shell placeholders, dashboard authorization, shell accessibility markup, the audit baseline: same-transaction registration/member/login-success writes with rollback on audit failure, generic IP-bounded denied-login auditing without secrets or account-state enumeration, logout/platform-dashboard writes, safe old/new values, UA normalization and credential-like UA discard, Eloquent fingerprint rejection with logger-only persistence, and factory/logger safe-record behavior, plus demo-seed creation, idempotent rerun, and demo-owner login.
