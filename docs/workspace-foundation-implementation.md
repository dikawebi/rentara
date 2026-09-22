# Workspace foundation implementation

**Status:** `RENTARA-FEAT-002` foundation, `RENTARA-FEAT-003` application shell, `RENTARA-FEAT-004` audit baseline, `RENTARA-FEAT-005` local demo seed, `RENTARA-FEAT-006` property management baseline, `RENTARA-FEAT-007` property structure/unit/assignment baseline, `RENTARA-FEAT-008` amenity/media baseline, `RENTARA-FEAT-009` basic tenant registry, and `RENTARA-FEAT-010` contract/occupancy baseline implemented; QA PASS recorded for the preceding eight, and FEAT-010 QA/reviewer approval is not yet recorded.

This document describes the current, implemented identity/workspace foundation, application shell, property baseline, property structure/unit/assignment baseline, amenity/private-media baseline, basic tenant registry, and internal contract/occupancy baseline. It does not describe a tenant portal, tenant documents, messaging, marketplace, billing, or invitation functionality.

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
- The operational dashboard is at `/app/dashboard` (within the guarded `/app` route group); it displays current workspace context and static placeholder counts and links to property management. Tenant registry routes are implemented separately under `/app/tenants`; tenant portal and billing functionality are not implemented. Workspace settings and member-management navigation remain policy/context-dependent existing foundation functionality.
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

## Property management baseline (`RENTARA-FEAT-006`)

Implemented workspace-scoped Property CRUD. There is no building/floor/block/unit model, no property structure hierarchy, and no unit management in this scope.

### Schema, enums, and relations

- `properties` (`2026_09_22_000004_create_properties_table`): `id`, `workspace_id` FK (`constrained`, `cascadeOnDelete`), `name`, `property_type` enum (`kost`, `house`, `apartment`, `kios`, `ruko`, default `kost`), nullable `address` (text), nullable `city(100)`/`province(100)`, nullable `postal_code(20)`, nullable `latitude`/`longitude` (`decimal(10,7)`), nullable `phone(30)`, nullable `email`, `status` enum (`active`, `inactive`, `archived`, default `active`), nullable `created_by` FK to `users` (`nullOnDelete`), timestamps, and soft deletes. Constraints: unique `(workspace_id, name)`; indexes `(workspace_id, status)` and `(workspace_id, property_type)`.
- Enums: `App\Enums\PropertyType` (`kost`, `house`, `apartment`, `kios`, `ruko`) and `App\Enums\PropertyStatus` (`active`, `inactive`, `archived`), cast on the model; `latitude`/`longitude` cast to `decimal:7`.
- Relations: `Property` belongs to `Workspace` and belongs to creator (`User`, `created_by`); `Workspace` has many `Property` records; `User` has many created `Property` records. Deleting a workspace cascades to its properties; deleting a user nulls `created_by`, preserving property history.

### Interim role matrix (`PropertyPolicy`, now assignment-aware under FEAT-007)

- Owner: bypass — full `view`/`update`/`delete`/`restore` on any property in the workspace; property index lists all workspace properties.
- Manager: `create` on the workspace plus assigned-only read (`view`) and `update`; `delete`/`restore` denied (403). Creating a property self-assigns the creating manager; the property index lists only assigned properties for non-owners.
- Staff: assigned-only read-only — `view` only on assigned properties; `create`/`update`/`delete`/`restore` denied (403). The property index lists only assigned properties.
- `super_admin`: denied on every property ability (policy `role()` returns `null` for platform role, including fail-closed legacy memberships); property routes return 403.
- `forceDelete` always returns `false`: no permanent deletion path exists.
- Role is the active workspace membership role in the property's workspace only; manager/staff access additionally requires a `property_assignments` row for that property (owner bypasses the assignment check).

### Workspace-scoped IDOR behavior

- All lookups scope by current workspace (`Property::where('workspace_id', $workspaceId)->findOrFail($id)`). Cross-workspace show/edit/update/delete returns HTTP 404 with the record untouched; trashed records are excluded from show/edit/update/delete (404) and are only reachable via restore (`withTrashed`).
- `workspace_id` and `created_by` are assigned server-side from the current workspace and authenticated user; client-supplied `workspace_id`/`created_by` values are ignored (not validated fields).
- Routes require `auth`, `active-user`, `verified`, and `workspace-context`. Guests redirect to `/login`; unverified users redirect to verification.

### Routes and views (Indonesian)

- Routes under `/app` (`app.properties.*`, `whereNumber('property')`): `GET /app/properties` (index, ordered by name, 15/page), `GET /app/properties/create`, `POST /app/properties` (store), `GET /app/properties/{property}` (show), `GET /app/properties/{property}/edit`, `PUT /app/properties/{property}` (update), `DELETE /app/properties/{property}` (destroy), `POST /app/properties/{property}/restore`.
- Views (`resources/views/properties/`): `index` (`Daftar properti`, `Belum ada properti` empty state, `Tambah properti`, status badges `Aktif`/`Nonaktif`/`Diarsipkan`, type labels `Kost`/`Rumah`/`Apartemen`/`Kios`/`Ruko`), `create` (`Tambah properti` / `Simpan properti` / `Batal`), `edit` (`Ubah properti` / `Simpan perubahan`), `show` (detail list with `—` fallbacks, `Kembali ke daftar`, `Ubah properti`, `Hapus properti`), shared `_form` (labels `Nama properti`, `Jenis properti`, `Status`, `Alamat`, `Kota`, `Provinsi`, `Kode pos`, `Lintang (latitude)`, `Bujur (longitude)`, `Telepon`, `Surel`). Flash copy: `Properti berhasil ditambahkan.` / `Perubahan properti berhasil disimpan.` / `Properti berhasil dihapus.` / `Properti berhasil dipulihkan.`

### Soft delete and owner restore

- Delete is a soft delete (`SoftDeletes`); only owners may delete. After delete, show returns 404.
- Restore (`POST .../restore`) revives a trashed record and redirects to show; only owners may restore (managers receive 403 and the record stays trashed). If an active row already uses the trashed name, restore returns HTTP 422 and the record stays trashed. Restoring a non-trashed record is a no-op redirect to show. There is no trashed listing and no force delete.

### Validation summary (`PropertyController::rules`)

- `name`: required, string, max 255, unique per workspace including trashed rows (`Rule::unique(...)->where(workspace_id)->ignore(id)` on update, no `whereNull(deleted_at)`); duplicate in the same workspace is rejected even when the conflicting row is trashed, keeping the record's own name on update is allowed, and the same name in another workspace is allowed.
- `property_type` / `status`: required, must match `PropertyType` / `PropertyStatus` enums.
- `address`: nullable string max 2000; `city`/`province`: nullable string max 100; `postal_code`: nullable string max 20; `phone`: nullable string max 30; `email`: nullable email max 255.
- `latitude`: nullable numeric between -90 and 90; `longitude`: nullable numeric between -180 and 180.

### Exclusions (FEAT-007 implements structure/units/assignments below)

- No property audit events (property writes are outside the `RENTARA-FEAT-004` allowlisted events).
- No trashed-property listing, no force delete.
- Amenities and media were outside the FEAT-006 property CRUD baseline; they are implemented in the FEAT-008 section below.

### Known non-blocking edges (FEAT-006 baseline)

- Trashed-name reuse (FEAT-006 only, resolved by FEAT-007): FEAT-006 form validation scoped uniqueness to non-deleted rows, so a trashed name passed validation, while the database unique `(workspace_id, name)` still covered trashed rows. FEAT-007 validation now includes trashed rows and restore returns 422 on conflict.
- Quote-name confirm: the delete `confirm('Hapus properti {{ $property->name }}? ...')` interpolates the raw name, so a name containing a single quote can break the inline confirm dialog.
- `@can` affordances: `Tambah`/`Ubah`/`Hapus` links and buttons render unconditionally (no `@can` gating); unauthorized roles see the affordance and are denied server-side by the policy (403). Authorization itself is not weakened.

## Property structure, unit types, units, and assignments (`RENTARA-FEAT-007`)

Implemented property structure hierarchy, unit-type catalog, unit inventory, and property-level assignments. Amenity/private-media behavior is documented in the FEAT-008 section below.

### Schema, enums, and relations

- `buildings` / `floors` / `blocks` (`2026_09_22_000005`): `id`, `workspace_id` FK (`cascadeOnDelete`), `property_id` FK (`cascadeOnDelete`), `name`, `sort_order` (unsigned, default `0`), nullable `notes`, timestamps, soft deletes. Constraints per table: unique `(property_id, name)`; index `(workspace_id, property_id)`.
- `unit_types` (`2026_09_22_000006`, workspace-level): `id`, `workspace_id` FK (`cascadeOnDelete`), `name`, nullable `description`, nullable `default_capacity` (unsigned), timestamps, soft deletes. Constraints: unique `(workspace_id, name)`; index `(workspace_id)`.
- `units` (`2026_09_22_000007`, full handoff columns): `id`, `workspace_id` FK (`cascadeOnDelete`), `property_id` FK (`cascadeOnDelete`), nullable `building_id`/`floor_id`/`block_id` (`nullOnDelete`), nullable `unit_type_id` (`nullOnDelete`), `unit_number`, nullable `name`, nullable `area` (`decimal(8,2)`), `rental_price` (unsigned bigint), `rental_period` enum (default `monthly`), `capacity` (unsigned, default `1`), `status` enum (default `available`), timestamps, soft deletes. Constraints: unique `(property_id, unit_number)`; indexes `(workspace_id, property_id)` and `(property_id, status)`.
- Enums: `App\Enums\UnitStatus` (`available`, `occupied`, `maintenance`) and `App\Enums\RentalPeriod` (`daily`, `monthly`, `yearly`), cast on `Unit`; `area` cast `decimal:2`, `rental_price`/`capacity` integers.
- `property_assignments` (`2026_09_22_000008`): `id`, `workspace_id` FK (`cascadeOnDelete`), `property_id` FK (`cascadeOnDelete`), `user_id` FK (`cascadeOnDelete`), nullable `created_by` FK to `users` (`nullOnDelete`), timestamps (no soft deletes). Constraints: unique `(property_id, user_id)`; indexes `(workspace_id, user_id)` and `(property_id)`.
- Relations: structures and units belong to workspace/property (units optionally belong to building/floor/block/unit type); `Property` show counts `units`/`buildings`/`floors`/`blocks`/`assignments`. Deleting a workspace/property cascades; deleting a referenced structure/unit-type nulls the unit FK; deleting a user nulls assignment `created_by` (assignments themselves cascade on user delete).

### Per-property duplicate rules including trashed-reuse rejection

- Structures: `name` required max 255, unique per property including trashed rows (`Rule::unique(...)->where(property_id)->ignore(id)`); same name in another property is allowed. Message: `Nama gedung/lantai/blok sudah digunakan pada properti ini (termasuk data yang telah dihapus).`
- Unit types: `name` required max 255, unique per workspace including trashed rows; same name in another workspace is allowed. Message: `Nama tipe unit sudah digunakan di ruang kerja ini (termasuk data yang telah dihapus).`
- Units: `unit_number` required max 50, unique per property including trashed rows; same number in another property is allowed. Message: `Nomor unit sudah digunakan pada properti ini (termasuk data yang telah dihapus).` Properties likewise validate `name` unique per workspace including trashed rows.
- Sibling-FK same-property validation: optional `building_id`/`floor_id`/`block_id` must exist and belong to the same `property_id` (Indonesian failures); optional `unit_type_id` must exist and belong to the same `workspace_id`.
- Unit field rules: `name` nullable max 255; `area` nullable numeric `0–999999.99`; `rental_price` required integer `0–999999999999`; `rental_period` required `RentalPeriod` enum; `capacity` nullable integer `1–100` (defaults to `1` on store); `status` nullable `UnitStatus` enum (defaults to `available` on store). `workspace_id`/`property_id` are assigned server-side.
- Restore conflict returns HTTP 422 and leaves the record trashed when an active row already uses the trashed name/number (`Property`, `Building`, `Floor`, `Block`, `UnitType`, `Unit` restore actions); restoring a non-trashed record is a no-op redirect. There is no trashed listing and `forceDelete` always returns `false`.

### Property assignments, backfill, and owner-only management

- Migration `2026_09_22_000008` runs `BackfillPropertyAssignments` inline: idempotent (`insertOrIgnore`) assignment of every active manager/staff member (active user, non-`super_admin`) to every non-trashed property in the same workspace; suspended memberships are skipped and owner rows are never created.
- Creating a property as manager self-assigns the creator (`PropertyAssignment` with server-side `workspace_id`/`property_id`/`created_by`).
- Assignment management is owner-only: `PropertyAssignmentPolicy::create`/`delete` require the workspace `owner` role (`update`/`restore`/`forceDelete` always `false`). Store candidates are limited to active, non-`super_admin` users with an active manager/staff membership in the property's workspace and no existing row for the property; duplicate `user_id` per property and inactive/ineligible users are rejected (Indonesian messages). Index lists assignments with `user`/`creator` (15/page).

### Assignment-aware authorization matrix

- Shared base `PropertyScopedPolicy`: `super_admin` maps to `null` role (denied everywhere); only active membership roles count; owner bypasses the assignment check while manager/staff require an existing `property_assignments` row for the property.
- Property (`PropertyPolicy`): owner full (`view`/`update`/`delete`/`restore`, all-workspace index); manager workspace `create` plus assigned-only `view`/`update` (assigned-only index); staff assigned-only `view` (assigned-only index); `super_admin` denied.
- Structures/units (`StructurePolicy`, `UnitPolicy`): `viewAny`/`view` for owner plus assigned manager/staff; `create`/`update`/`delete`/`restore` for owner plus assigned manager only (staff read-only); `super_admin` denied. All lookups scope by current workspace and property (`workspace_id` + `property_id`, `findOrFail` → 404 cross-scope).
- Unit types (`UnitTypePolicy`, workspace-level, no assignments): any active member may `viewAny`/`view`; owner/manager may `create`/`update`/`delete`/`restore`; staff read-only; `super_admin` denied.
- Assignments (`PropertyAssignmentPolicy`): `viewAny`/`view` mirror property view (owner plus assigned manager/staff); `create`/`delete` owner-only.

### Exclusions

- No property/structure/unit audit events, no amenity/media audit events, no trashed listings, and no force delete.

## Amenities and private property/unit media (`RENTARA-FEAT-008`)

FEAT-008 implements a workspace-scoped amenity catalog and private images for active properties and units. It does not implement tenant documents, messaging, marketplace/public media, image processing, or cloud storage.

### Amenities and unit assignments

- `amenities` (`2026_09_22_000009`) stores `workspace_id`, `name`, optional `description`, `created_by`, timestamps, and soft deletes. Names are unique per workspace; an active amenity may be assigned to any unit in the same workspace through `amenity_unit`.
- `amenity_unit` (`2026_09_22_000010`) stores `workspace_id`, `amenity_id`, `unit_id`, `created_by`, and timestamps. The composite foreign keys require the amenity and unit to belong to the pivot workspace, and `(amenity_id, unit_id)` is unique. Cross-workspace IDs and deleted amenities are rejected; syncing replaces the unit's current amenity set transactionally.
- All active workspace members may read the amenity catalog. Owners and managers may create, update, soft-delete, and restore amenities; staff is read-only; `super_admin` is denied. Unit amenity editing follows the unit/property policy: owners and assigned managers may write, while assigned staff may read only.
- Amenity deletion is soft deletion and does not delete the unit assignment rows. Restore is owner/manager-authorized and returns 422 when an active amenity already uses the name. There is no trashed-amenity listing or force delete.

### Private media model and behavior

- `media` (`2026_09_22_000011`) stores `workspace_id` and exactly one parent: `property_id` or `unit_id` (database XOR check). Composite foreign keys enforce that the parent belongs to the same workspace; deleting a workspace/property/unit cascades its media rows. A unit media record therefore also belongs to its unit's property.
- Uploads accept only JPG/JPEG/PNG/WEBP images and are limited to 10,240 KB (10 MB). Files use the `local` private disk and are written under `workspaces/{workspace}/properties/{property}/media/` or `workspaces/{workspace}/units/{unit}/media/`, with a generated UUID filename rather than the original filename. The database records original name, detected MIME type, extension, byte size, SHA-256 checksum, caption, ordering, and uploader.
- Media is never exposed as a public URL. The stream route first scopes the row to the current workspace, validates the active property/unit parent and UUID path layout, checks the file's detected MIME type, and authorizes the viewer. It returns the file inline with `X-Content-Type-Options: nosniff`; unauthorized, cross-workspace, inactive-parent, missing, tampered-path, and unsupported-content requests are denied/not found.
- Owner can view/upload/delete media for the workspace's properties. An assigned manager can view/upload/delete, and an assigned staff member can view only. Unassigned members and `super_admin` are denied. Property and unit media use the same assignment-aware property scope.
- If storage fails before the row is created, the upload cleanup path attempts to remove the file and no media row remains. Delete removes the database row before attempting physical-file deletion; a disk-cleanup failure is logged and reported as a server error, so the row is not retained as a retry queue. There is no background cleanup job or image transformation.

### Integrity and audit boundary

Workspace/property scoping is applied to every catalog, assignment, upload, stream, and delete lookup. Database constraints supplement policy checks for workspace-parent identity, one media parent, and unique amenity-unit pairs. FEAT-008 does not add audit events; amenity and media writes remain outside the allowlisted audit baseline.

## Basic tenant registry (`RENTARA-FEAT-009`)

FEAT-009 implements a private, internal, workspace-scoped tenant registry. It is not a tenant-facing portal and does not implement contracts, tenant documents, billing, messaging, or tenant audit events.

### Schema and lifecycle

- `tenants` (`2026_09_22_000012`) stores `workspace_id`, nullable `unit_id`, `name`, contact fields (`phone`, `email`), sensitive fields (`identity_number`, `date_of_birth`, `gender`, `occupation`, `emergency_contact_name`, `emergency_contact_phone`), `status` (`active` or `inactive`), timestamps, and soft-delete metadata. Workspace and status/unit indexes support scoped registry access and active-capacity checks.
- A tenant has zero or one current unit. A unit may have multiple tenants. Tenant assignment is validated against a non-deleted unit in the current workspace; the database nullable reference uses `nullOnDelete`, so a physically deleted unit does not orphan a tenant row.
- Capacity counts only non-deleted tenants whose status is `active`. Creating or moving an active tenant into a full unit is rejected. Changing a unit's capacity below its current active-tenant count is rejected. Inactive tenants do not consume capacity, so another active tenant may be assigned after an existing tenant is made inactive.
- Tenant deletion is a soft delete and owner/authorized manager restore is available. Unit deletion is also soft delete and is blocked while the unit has active tenants; inactive tenants do not block deletion. There is no force-delete path. Tenant records remain workspace-scoped throughout their lifecycle.

### Data protection and search

- `identity_number`, date of birth, gender, occupation, and emergency-contact fields use Laravel encrypted casts and are not searchable. The registry search is limited to non-encrypted contact/index fields: tenant name, phone, email, and assigned unit number. Phone inputs are normalized before validation/storage.
- Cross-workspace tenant and unit IDs are rejected/not found. Client input cannot set the tenant workspace; it is assigned from the resolved current workspace.

### Authorization and routes

- Owners can view and manage tenants across their workspace, including unassigned tenants. Managers and staff can access only tenants assigned to units in properties assigned to them; managers can create/update/delete/restore, while assigned staff are read-only. Super Admins are denied because the registry is operational workspace functionality, not a platform module. An unassigned tenant is not visible to assigned managers or staff.
- The registry is available under the guarded `/app/tenants` resource routes, with a separate restore action. Server-side workspace context, active membership, assignment checks, and tenant policies enforce authorization; the UI does not replace those checks.
- Tenant writes are outside the existing allowlisted audit baseline. No tenant portal, contract/document store, billing, messaging, or tenant audit viewer/API is implemented.

## Contract and occupancy baseline (`RENTARA-FEAT-010`)

FEAT-010 implements a private, internal, workspace-scoped rental contract and occupancy baseline. Contracts are shared multi-tenant records: one contract belongs to one workspace, property, and unit and may be attached to multiple existing tenants. This is an operational internal contract record, not a tenant-facing contract/document feature.

### Schema, statuses, and lifecycle

- Migration `2026_09_22_000013` adds workspace-safe `rental_contracts`, `contract_tenant`, `check_ins`, and `check_outs` tables. Contracts have a workspace-unique contract number, dates, rental price, deposit amount, notes, optional termination reason, soft-delete metadata, and statuses `draft`, `pending`, `active`, `expiring`, `completed`, `terminated`, and `cancelled`.
- Draft contracts can be submitted to `pending` and activated; draft/pending contracts can be cancelled. Active/expiring contracts can be terminated with a required non-blank reason, or checked out to become completed. Renewal creates a new draft historical contract and carries the existing contract tenants forward after rechecking tenant, capacity, and overlap rules. Only draft/cancelled contracts may be soft-deleted or restored, and restore rechecks historical consistency and overlap.
- Contract numbers cannot be reused within a workspace, including after soft deletion; reuse in another workspace is allowed. Historical contract, tenant-pivot, check-in, and check-out rows are retained. Restrictive foreign keys prevent physical deletion of a property or unit referenced by contract history, and contract history cannot be removed by deleting its parents.

### Occupancy, unit status, and operations

- Activation requires at least one active, non-deleted tenant, same-workspace property/unit/tenant consistency, no overlapping active/expiring contract for the unit, and capacity after taking the union of existing active unit tenants and contract tenants. A tenant already assigned to the unit is not double-counted. Activation sets the unit status to `occupied` and synchronizes attached active tenants to that unit.
- Ending an active/expiring contract through termination, cancellation, or check-out releases the unit to `available` only when no other active/expiring contract remains. Tenant current-unit assignments are cleared only when no other active/expiring contract still keeps that tenant on the unit. The contract lifecycle is the authority for occupied/available status: a unit with an active/expiring contract cannot be manually changed to `available` or `maintenance`, and `occupied` cannot be manually assigned through unit editing; non-occupancy unit status remains the unit-level status field.
- Check-in is recorded once for an active/expiring contract and records time, deposit received, and notes. Deposit received cannot exceed the contract deposit. Check-out requires check-in first, records unpaid/damage amounts and a single exact manual deposit settlement, and completes/releases the contract: `deposit_returned + deposit_deduction` must equal the deposit actually received, with neither component exceeding it. No payment processing is implemented.

### Authorization and audit boundary

- Owners have workspace-wide contract access. Assigned managers may create, view, update, delete/restore, attach tenants, and perform lifecycle actions only for assigned properties. Assigned Staff may view contracts but are read-only for contract structure, lifecycle changes, and tenant attachment; Staff may perform only the operational check-in, check-out, and manual deposit receipt/settlement actions. Super Admins are denied. All lookups and writes enforce workspace/property/unit scope server-side.
- Contract lifecycle, tenant attachment, renewal, check-in/out, deposit settlement, soft-delete, and restore audit events use the allowlisted safe audit writer and are transaction-coupled to the critical operation. Audit values contain identifiers/statuses only; secrets, sensitive tenant fields, raw documents, and raw request data are excluded. There is no audit viewer or contract audit API.

### Exclusions

FEAT-010 does not implement billing, payment processing, document storage, a tenant portal, tenant-facing contract access, or messaging.

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

There is no tenant shell or tenant portal, tenant contracts/documents, messaging, marketplace, dashboard metrics, notification delivery or inbox, interactive workspace-switch UI, billing module, platform review workflow, ownership transfer, co-owner model, or invitation workflow in this scope. Property scope is the FEAT-006 baseline plus the FEAT-007 structure/unit/assignment and FEAT-008 amenity/private-media baselines above: no public media, image processing, cloud storage, trashed listing, force delete, or property/amenity/media audit events. The shell's dashboard counts remain placeholders. The tenant registry is private and internal; it does not include tenant billing, messaging, contracts/documents, or audit functionality. The demo seed likewise contains no tenant demo account or invitation data.

Audit scope is limited to the baseline above: there is no audit viewer, listing/search/export API, retention/purge job, queued/async audit path, or audit coverage for password reset/confirmation, email verification, profile updates, workspace settings, or workspace switching. Denied-login auditing is sampled under burst traffic (20/min per IP). No audit package or Spatie Permission package is installed.

## Existing-deployment migration note

The migrations add the identity columns and create `workspaces` and `workspace_members`; later migrations create the property, structure/unit, assignment, amenity, pivot, media, tenant, rental contract, contract-tenant, check-in, and check-out tables. The tenant migration does not backfill tenant records or assign existing tenants to workspaces/units, and the FEAT-010 contract migrations do not backfill contracts, tenant attachments, occupancy, check-in, or check-out history. These migrations do not create a workspace or membership for pre-existing users, assign Super Admins, backfill amenities/media, or repair/delete legacy data. Before deploying to an existing environment, back up the database, rehearse all migrations on representative data, verify local private-disk storage permissions, and decide which existing active users require a workspace and active membership. Until valid membership data exists, a verified non-Super-Admin user receives the no-active-workspace response on workspace routes. Run production migrations only through the reviewed deployment process (for example, `php artisan migrate --force`).

The audit migration (`2026_09_21_000003`) creates `audit_logs` only; it does not backfill history for pre-existing users, workspaces, memberships, or past authentication events. Existing deployments start with an empty audit trail from migration time onward.

## Test evidence

QA PASS was recorded for `RENTARA-FEAT-009`. The current verification run passed: `php artisan test` reported **144 tests passed, 711 assertions**; `npm run build` completed successfully. Coverage includes workspace-scoped tenant CRUD, active/inactive lifecycle, one-unit assignment and multiple tenants per unit, active-only capacity enforcement, encrypted sensitive fields, contact/unit search boundaries, owner and assigned manager/staff authorization, cross-workspace isolation, soft delete/restore, unit deletion constraints, and the private registry boundary. No tenant portal, contracts/documents, billing, messaging, or tenant audit behavior is claimed.

The preceding FEAT-008 verification also passed: `php artisan test` reported **131 tests passed, 658 assertions**; `npm run build` completed successfully. Coverage included workspace-scoped amenity CRUD/restore, same-workspace unit assignment and pivot integrity, role restrictions, private property/unit image upload and authorized streaming, UUID path handling, image type/10 MB validation, private local storage, `nosniff`, cleanup/error paths, parent/workspace isolation, and media deletion. No public-media, cloud-storage, or image-processing behavior was claimed.

QA PASS was provided for `RENTARA-FEAT-002`, `RENTARA-FEAT-003`, `RENTARA-FEAT-004`, `RENTARA-FEAT-005`, `RENTARA-FEAT-006`, and `RENTARA-FEAT-007`. Verification for FEAT-007 passed: `php artisan test` reported **120 tests passed, 618 assertions**; `npm run build` completed successfully. Coverage includes identity status and login behavior, verification, atomic registration/rollback, membership uniqueness and lifecycle, canonical-owner protection, workspace selection/switching and stale/deleted cleanup, server-side workspace scoping, Super Admin containment, fail-closed legacy-membership policy behavior, configured branding, shell placeholders, dashboard authorization, shell accessibility markup, the audit baseline: same-transaction registration/member/login-success writes with rollback on audit failure, generic IP-bounded denied-login auditing without secrets or account-state enumeration, logout/platform-dashboard writes, safe old/new values, UA normalization and credential-like UA discard, Eloquent fingerprint rejection with logger-only persistence, and factory/logger safe-record behavior, demo-seed creation, idempotent rerun, and demo-owner login, plus property CRUD: owner happy path with server-side `created_by`/`workspace_id`, validation (missing name, invalid enums, bad coordinates/email), per-workspace name uniqueness and update keep-own-name, cross-workspace 404 IDOR scoping, assignment-aware role matrix (owner bypass, assigned-only manager/staff, staff read-only, `super_admin` denied), owner-only soft-delete restore with 422 restore conflict, guest/unverified guards, and the Indonesian empty state, plus FEAT-007 structures/unit-types/units/assignments: per-property and per-workspace duplicate rules including trashed-reuse rejection, sibling-FK same-property/workspace validation, scoped workspace/property lookups and indexes, manager self-assign on property create, migration backfill idempotency, and owner-only assignment management. This FEAT-007 run predates FEAT-008 and did not include amenity/media coverage. Earlier verification on 2026-09-21 (FEAT-002–005) reported **71 tests passed, 319 assertions** (including `DemoSeederTest`: **3 passed, 35 assertions**); FEAT-006 verification reported **82 tests passed, 388 assertions** (including `PropertyTest`: **11 passed, 69 assertions**).
Current FEAT-010 backend verification evidence: `php artisan test` passed **168 tests and 839 assertions**. This is test evidence only; QA PASS and reviewer approval for FEAT-010 have not yet been claimed.
