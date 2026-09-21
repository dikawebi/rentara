# Rentara — Master AI Coding Handoff v1.0

> This is the single source of truth for implementation. It supersedes earlier working documents that use the name Huniva or earlier Rentara directions.

## 1. Locked Product Decisions

| Area | Decision |
|---|---|
| Product name | Rentara *(working brand; verify trademark/domain before public launch)* |
| Tagline | **Kelola properti. Temukan hunian.** |
| Logo | **07A · Balanced Link** |
| Wordmark | lowercase `rentara`, **Manrope 800** |
| Product type | Rental-property management SaaS, followed by marketplace |
| Initial market | Indonesia, Bahasa Indonesia, Rupiah |
| Initial property focus | Kost; data model must support house rental, apartment, kios, and ruko |
| User platform | Responsive web + installable PWA |
| Hosting | Shared hosting first; move to VPS only when needed |
| Architecture | Laravel full-stack: Blade + Livewire + Alpine.js + Tailwind CSS |
| Database | MySQL/MariaDB preferred for shared hosting; PostgreSQL allowed if reliably provided |
| Production constraint | No Docker dependency, no Next.js, no Node.js runtime, no WebSocket server, no permanent queue worker |

## 2. Product Positioning

Rentara helps owners and property managers manage rental units, tenants, contracts, billing, payments, maintenance, and reporting in one system. A later marketplace enables prospective tenants to find units and submit booking requests.

### Primary audiences

1. Property owners with one or multiple properties.
2. Professional property managers who manage properties for multiple owners.
3. Operational staff or caretakers.
4. Tenants.
5. Platform Super Admin.
6. Prospective tenants on the marketplace (post-MVP).

### Product promise

Rentara makes rental operations more organised and transparent for owners, managers, and tenants.

## 3. Brand and UI System

### Logo

Use `rentara-logo-07a-primary.svg` for full lockups and `rentara-logo-07a-mark.svg` for favicon, PWA icon, avatar, and small mobile contexts.

The mark represents two connected sides of a residence:

- Left/right sides: owner and tenant.
- Teal center point: a clear, trusted rental relationship.
- Residence structure: units, properties, and rental activity.

### Typography

- Brand wordmark: Manrope 800, lowercase, slightly tight tracking.
- Headings: Manrope 600–800.
- UI body/tables/forms: Inter or Plus Jakarta Sans 400–600.
- Never use more than these two visual font families in the product UI.

### Core tokens

```css
:root {
  --brand-navy: #16324F;
  --brand-blue: #2563EB;
  --brand-teal: #0F766E;
  --surface: #FFFFFF;
  --background: #F8FAFC;
  --foreground: #172033;
  --muted-foreground: #64748B;
  --border: #E2E8F0;
  --success: #15803D;
  --warning: #B45309;
  --danger: #B91C1C;
}
```

### UI rules

- Desktop operational portal: navy sidebar, white header, cloud background.
- Tenant portal: mobile-first, concise, and task-oriented.
- Use tables for operational data, not cards by default.
- Use 8px/12px/16px/24px spacing rhythm.
- Use a moderate 8px–16px border radius; avoid overly rounded “playful” UI.
- Primary button is blue; destructive action requires confirmation.
- Every status needs a label plus color.
- All UI copy is clear Bahasa Indonesia. Never expose database or API terminology to users.

### Configuration

Do not hardcode product identity throughout views.

```php
'brand' => [
    'name' => env('APP_BRAND_NAME', 'Rentara'),
    'tagline' => env('APP_BRAND_TAGLINE', 'Kelola properti. Temukan hunian.'),
    'support_email' => env('APP_SUPPORT_EMAIL'),
],
```

## 4. Personas and Access Model

### Super Admin

Manages the complete platform: users, workspaces, property moderation, subscriptions, marketplace moderation, support, global settings, and audit logs.

### Owner

Owns a workspace and can manage its properties, units, tenants, contracts, billing, payments, maintenance, reports, and members.

### Manager

Manages one or multiple properties assigned by an owner.

### Staff

Runs daily operations such as tenant data entry, payment recording, check-in/check-out, and maintenance. Staff access must be limited to assigned properties.

### Tenant

Can access only their own profile, active unit, contract, invoices, payments, documents, notifications, and maintenance tickets.

### Platform access rules

- Super Admin is platform-scoped, not automatically entitled to view every sensitive tenant document.
- Owner/Manager/Staff are workspace-scoped and then property-scoped as applicable.
- Tenant is self-scoped.
- Every sensitive action must be auditable.

## 5. Functional Scope

### MVP modules (P0)

1. Authentication and account lifecycle.
2. Roles, permissions, workspace membership, and access boundaries.
3. Property and unit management.
4. Tenant management and documents.
5. Rental contracts, deposit, check-in, and check-out.
6. Recurring/manual billing and invoice items.
7. Manual payment recording, proof upload, and verification.
8. Overdue status, reminders, and configurable penalty rules.
9. Maintenance ticket workflow.
10. Owner/Manager/Staff portal.
11. Tenant portal.
12. Super Admin portal.
13. Dashboard and core reports.
14. Private messaging between tenant/candidate and property provider.
15. In-app notifications and email notifications.
16. Audit log.

### Post-MVP modules (P1/P2)

- Marketplace listings and moderation.
- Search, filters, map, favorites, booking requests, and reviews.
- Subscription plans and platform fees.
- Payment gateway/virtual account.
- WhatsApp notifications.
- E-signature.
- Native mobile app.
- Shared occupancy per unit.
- Smart lock/IoT, smart pricing, and accounting integration.

Detailed messaging rules, privacy boundaries, data model, and acceptance criteria are defined in `rentara-messaging-spec-v1.0.md`.

## 6. Core Workflows

### Owner onboarding

Register → verify email → create workspace → create property → create property structure → add units → invite Manager/Staff.

### Tenant onboarding

Admin creates tenant or sends invitation → tenant completes profile → admin verifies documents → tenant receives/accepts contract → unit is occupied after contract activation.

### Contract lifecycle

Draft → pending review → active → expiring → renewed/completed/terminated/cancelled.

Rules:

- A unit cannot have overlapping active contracts.
- Activating a contract changes the unit status to `occupied`.
- Termination must record a reason.
- Check-out calculates unpaid charges, damage notes, and deposit settlement.

### Invoice lifecycle

Draft → issued → partially paid → paid / overdue / cancelled.

Rules:

- Do not duplicate an invoice for the same contract and billing period.
- Invoice total is the sum of invoice items, discount, and penalty.
- Payment verification is required before invoice balance is reduced.
- Overdue and penalty rules are configurable per property.

### Maintenance lifecycle

Submitted → reviewed → assigned → in progress → waiting → resolved → closed/rejected.

Rules:

- Ticket includes title, description, priority, unit, and optional photos.
- Every status change must be logged.
- Actual cost must record whether it is charged to owner or tenant.

### Messaging lifecycle

Open → active → closed / blocked.

Messaging supports two contexts:

1. **Tenant conversation:** an active tenant communicates with the assigned provider side (Owner, Manager, or authorised Staff) for the related property.
2. **Marketplace inquiry:** a registered prospective tenant asks a provider about a published listing.

Rules:

- Conversations are private and contextual: linked to a property and optionally to a listing, booking request, contract, or maintenance ticket.
- Only conversation participants may read or send messages.
- Marketplace inquiry requires a registered, verified account in the MVP; guest messaging can be considered later with email/OTP verification.
- Provider recipients are derived from authorised property access, never from client-supplied user IDs alone.
- Messages support text and controlled private attachments; identity documents may not be sent or exposed publicly through chat.
- In-app notifications and email notification are sent for unread messages. Use polling while a chat is open; do not depend on WebSocket/Reverb for shared hosting.
- Participants can close a conversation; providers and Super Admin can block abusive accounts/conversations with audit reasons.

## 7. Data Model

### Multi-workspace rule

Use one database. All operational records must have `workspace_id` where applicable. Records related to a specific site must also have `property_id`.

Every query must be filtered by the current workspace. Never rely on UI filtering alone.

### Core entities

```text
users
roles / permissions
workspaces
workspace_members
properties
buildings
floors
blocks
unit_types
units
amenities
amenity_unit
media
tenants
tenant_documents
rental_contracts
check_ins
check_outs
invoices
invoice_items
payments
maintenance_tickets
maintenance_comments
conversations
conversation_participants
messages
message_attachments
message_reads
conversation_events
notifications
audit_logs
```

### Key table fields

#### `workspaces`

`id, owner_id, name, slug, status, timezone, currency, timestamps`

#### `properties`

`id, workspace_id, name, property_type, address, city, province, postal_code, latitude, longitude, phone, email, status, created_by, timestamps, soft_deletes`

#### `units`

`id, workspace_id, property_id, building_id, floor_id, block_id, unit_type_id, unit_number, name, area, rental_price, rental_period, capacity, status, timestamps, soft_deletes`

#### `tenants`

`id, workspace_id, user_id, identity_number, date_of_birth, gender, occupation, emergency_contact, blacklist_status, blacklist_reason, timestamps, soft_deletes`

#### `rental_contracts`

`id, workspace_id, property_id, tenant_id, unit_id, contract_number, start_date, end_date, rental_period, rental_price, deposit_amount, payment_due_day, status, document_path, terminated_at, termination_reason, timestamps, soft_deletes`

#### `invoices`

`id, workspace_id, property_id, contract_id, tenant_id, invoice_number, period_start, period_end, due_date, subtotal, discount, penalty, total_amount, paid_amount, status, timestamps, soft_deletes`

#### `payments`

`id, workspace_id, invoice_id, tenant_id, payment_number, payment_date, amount, payment_method, reference_number, proof_path, verified_by, verified_at, status, notes, timestamps`

#### `maintenance_tickets`

`id, workspace_id, property_id, unit_id, tenant_id, title, description, category, priority, assigned_to, estimated_cost, actual_cost, charged_to, status, resolved_at, timestamps, soft_deletes`

## 8. Technical Architecture

### Stack

- Laravel 12/13 according to environment compatibility.
- PHP 8.2+.
- Blade + Livewire + Alpine.js.
- Tailwind CSS.
- MySQL/MariaDB preferred for shared hosting.
- Laravel Scheduler.
- SMTP for email.
- PWA manifest and service worker.
- Spatie Permission or equivalent for role/permission management.

### Folder direction

```text
app/
├── Actions/
├── Livewire/
│   ├── Admin/
│   ├── App/
│   └── Tenant/
├── Models/
├── Policies/
├── Services/
├── Notifications/
└── Support/

resources/views/
├── layouts/
├── components/
├── livewire/
├── admin/
├── app/
├── tenant/
└── public/

routes/
├── web.php
├── admin.php
├── app.php
├── tenant.php
└── public.php
```

### Route areas

```text
/
/marketplace                     # Post-MVP public pages
/login
/register
/admin/*                         # Super Admin
/app/*                           # Owner / Manager / Staff
/tenant/*                        # Tenant
```

### Shared hosting requirements

- Web root must point to Laravel `public/`.
- `storage/` and `bootstrap/cache/` must be writable.
- PHP 8.2+ and required Laravel extensions.
- Cron access for `php artisan schedule:run`.
- SMTP credentials.
- Local build or CI build for Vite assets; upload/commit `public/build` because Node runtime is not assumed.

### Shared hosting limitations

Do not depend on:

- Laravel Reverb/WebSocket server.
- Laravel Horizon.
- A permanent queue worker.
- Node.js server-side rendering.

Use Scheduler plus database queue/synchronous fallback and email for the MVP. Move to VPS once the scale needs Redis, workers, real-time updates, or object storage.

## 9. Release Plan

### Release 0 — Foundation

- Project setup.
- Authentication, verification, password reset.
- Brand configuration and Rentara app shell.
- Role and permission setup.
- Workspace and membership.
- Dashboard placeholders for Super Admin, App, and Tenant.
- Audit log baseline.
- Demo data, factories, seeders, and initial tests.

### Release 1 — Property and Unit

- Property CRUD.
- Building/floor/block structure.
- Unit type, unit CRUD, amenities, media.
- Unit availability and occupancy dashboard.

### Release 2 — Tenant and Contract

- Tenant CRUD, invitation, documents, and blacklist.
- Contract lifecycle, deposit, check-in/check-out.
- Unit/contract consistency rules.

### Release 3 — Billing and Payment

- Invoice and invoice item CRUD.
- Recurring generation through Scheduler.
- Utilities, discount, penalty, and overdue rules.
- Payment recording, proof upload, verification, and email reminders.

### Release 4 — Maintenance and Tenant Portal

- Maintenance ticket workflow.
- Tenant dashboard, invoice, payment, contract, document, and ticket views.
- Notifications and core operational reports.
- Tenant–provider messaging, unread state, attachments, polling refresh, and message notifications.

### Release 5 — Super Admin

- User/workspace management.
- Platform dashboard.
- Support ticket and verification flows.
- Global settings and audit log viewer.

### Release 6 — Marketplace

- Listing, moderation, search, filter, favorite, booking request, review, and prospective-tenant/provider inquiry messaging.

## 10. Coding Rules

1. Work only on the requested release; do not build marketplace early.
2. Before coding, list files, migrations, dependencies, risk, and tests.
3. Create migration, model, factory, seeder, policy, request/component validation, and feature tests together.
4. Use PHP enums for stable lifecycle/status values.
5. Use Policies for authorization. Never trust client-side navigation alone.
6. Use services/actions for contract activation, invoice generation, payment verification, deposit settlement, and other business transactions.
7. Use database transactions for multi-record business operations.
8. Use eager loading and pagination for operational lists.
9. Store money as integer Rupiah consistently; never mix float money calculations.
10. Keep sensitive documents outside the public directory and validate file type/size.
11. Prefer soft deletes for business records.
12. Record critical activity in audit logs.
13. Do not hardcode demo data as production logic.
14. Do not remove migrations, records, or unrelated code without explicit instruction.
15. Keep the UI Indonesian and apply Rentara tokens consistently.
16. Conversation access must always be participant-checked and property/workspace-scoped.

## 11. Required Tests

- Registration/login/password reset.
- Role and route access.
- Workspace isolation.
- Property/unit CRUD and duplicate unit validation.
- Property-scoped staff access.
- Contract overlap protection.
- Contract activation updates unit status.
- Invoice generation uniqueness.
- Payment verification updates balance/status only once.
- Tenant privacy.
- Maintenance transition authorization.

## 12. Definition of Done

A feature is done only when:

- Functional requirements are implemented.
- Responsive UI exists.
- Loading, empty, success, and error states exist.
- Validation and authorization exist.
- Relevant migration/model/factory/seeder/policy exist.
- Relevant tests pass.
- Workspace isolation is verified.
- Audit behavior is in place where relevant.
- No unrelated regression appears in tests/logs.
- The documentation and agent report are updated.

## 13. Initial Seed Data

Create a demo workspace with:

- 1 Super Admin.
- 1 Owner.
- 1 Manager.
- 1 Staff.
- 1 Tenant.
- 1 Kost demo.
- 1 building and 2 floors.
- 10 units: 2 occupied, 8 available.

Never commit production passwords. Demo credentials must be environment-defined or documented only for local development.

## 14. Initial Agent Prompt

```text
Read Rentara — Master AI Coding Handoff v1.0 in full before writing code.

Build Rentara, an Indonesian rental-property management SaaS that will later add a marketplace. The final brand uses logo 07A Balanced Link, the Manrope 800 lowercase wordmark, and the tagline “Kelola properti. Temukan hunian.”

Use Laravel full-stack with Blade, Livewire, Alpine.js, Tailwind CSS, MySQL/MariaDB, and PWA. The deployment target is shared hosting. Do not use Next.js, Docker, WebSocket/Reverb, Horizon, a permanent worker, or payment gateway in the MVP.

Start only Release 0. Implement authentication, role/permission, workspace/membership, Rentara layout and brand configuration, dashboard placeholders, audit-log baseline, demo seeders, and feature tests.

Before coding, provide:
1. Understanding summary.
2. Files to create/change.
3. Dependencies and reasons.
4. Migration/relationship plan.
5. Route/middleware/permission plan.
6. Test plan.

Do not proceed to Release 1 until Release 0 has passed tests and been reviewed.
```

## 15. Agent Completion Report Format

```text
## Completed
- ...

## Files Changed
- ...

## Database Changes
- ...

## Routes, Middleware, and Permissions
- ...

## Tests
- Command:
- Result:

## Manual Verification
- ...

## Known Issues / Decisions Needed
- ...

## Next Recommended Task
- ...
```
