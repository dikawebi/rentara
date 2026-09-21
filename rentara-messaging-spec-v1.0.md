# Rentara — Messaging Specification v1.0

## Purpose

Rentara provides private in-app communication so that:

1. A **calon penyewa** can ask a **penyedia kost/properti** about a published listing.
2. An active **penghuni/penyewa** can communicate with the **pemilik, pengelola, atau staff** responsible for their property.

Messaging is contextual, traceable, and private. It is not a public comment system.

## Product Decisions

| Topic | Decision |
|---|---|
| Tenant/provider messaging | Included in Release 4 with Tenant Portal |
| Marketplace inquiry messaging | Included in Release 6 with Marketplace |
| Anonymous chat | Not supported in MVP |
| Prospective tenant prerequisite | Registered and verified account |
| Real-time transport | Polling while the conversation screen is open |
| Notification channel | In-app + email |
| Attachments | Yes, controlled private files |
| Read receipts | Yes, per participant |
| Abuse controls | Report, close, block, audit log |

## Personas

### Seeker

Registered prospective tenant who asks about a marketplace listing.

### Tenant

Registered user with an active/valid contract.

### Provider

Owner, Manager, or Staff who has authorised access to the relevant property.

### Super Admin

May moderate reported/blocked cases under an audited, limited-support workflow. Super Admin is not an automatic participant in private conversations.

## Conversation Contexts

### 1. Marketplace inquiry

```text
Listing page
→ “Tanya penyedia”
→ Require login/verification if needed
→ Create or reopen inquiry conversation
→ Notify authorised provider recipients
→ Continue privately in Messages
```

Required context:

- Workspace
- Property
- Listing
- Seeker
- Provider participant(s)

### 2. Tenant-provider conversation

```text
Tenant portal
→ “Pesan pengelola”
→ Select relevant subject/context
→ Create or reopen property conversation
→ Notify authorised provider recipients
→ Reply and track read status
```

Optional context:

- Contract
- Unit
- Invoice
- Maintenance ticket

Maintenance tickets remain the source of truth for repair status. Chat may be linked to a ticket but must not replace ticket workflow/status history.

## Functional Requirements

### Conversation list

- Show counterpart/property/listing context.
- Show last message snippet and timestamp.
- Show unread count.
- Filter by open, closed, or blocked.
- Tenant sees only their own conversations.
- Providers see only property conversations within their authorised workspace/property scope.

### Conversation screen

- Text messages.
- Relative/absolute timestamp.
- Read state: sent, delivered/read as appropriate.
- Controlled attachment upload.
- Context badge, for example `Kost Melati · Unit A-101` or `Listing · Kamar Deluxe`.
- Close conversation action.
- Report conversation/user action.

### Attachments

Allow only safe, documented types such as JPG, PNG, PDF, and optionally WEBP.

- Validate MIME type and file size server-side.
- Store privately, outside public storage.
- Generate authorised temporary download/preview access.
- Do not accept executable files, archives, or unrestricted public URLs.
- Do not use chat to transmit KTP or other identity documents; those belong in secure document flows.

### Notifications

- Create an in-app notification when a new message arrives.
- Send email notification with a safe summary, without exposing sensitive message content by default.
- Avoid sending one email per rapid message; throttle/digest messages within a short interval.
- Use Laravel Scheduler/database queue/synchronous fallback as compatible with shared hosting.

### Polling

- When a conversation is open, Livewire polls for new messages on a reasonable interval, such as 20–30 seconds.
- Pause/reduce polling when the browser tab is not active when practical.
- Do not introduce Reverb/WebSocket dependency in MVP.

## Data Model

### `conversations`

```text
id
workspace_id
property_id
listing_id                   nullable, post-MVP
contract_id                  nullable
unit_id                      nullable
maintenance_ticket_id        nullable
type                         marketplace_inquiry|tenant_provider
status                       open|closed|blocked
subject                      nullable
initiated_by                 foreign key users.id
closed_by                    nullable foreign key users.id
closed_at                    nullable timestamp
blocked_by                   nullable foreign key users.id
blocked_reason               nullable text
last_message_at              nullable timestamp
created_at
updated_at
```

### `conversation_participants`

```text
id
conversation_id
user_id
participant_role             seeker|tenant|provider|support
joined_at
left_at                      nullable
last_read_at                 nullable timestamp
is_muted                     boolean default false
created_at
updated_at
unique(conversation_id, user_id)
```

### `messages`

```text
id
conversation_id
sender_id
message_type                 text|system
body                         nullable text
sent_at
edited_at                    nullable timestamp
deleted_at                   nullable timestamp
created_at
updated_at
```

Messages must have either non-empty text or an attachment. System messages record safe status events such as conversation closed; they cannot be manually authored by regular participants.

### `message_attachments`

```text
id
message_id
disk
path
original_name
mime_type
size_bytes
created_at
```

### `message_reads`

Optional if per-message read detail is needed beyond participant `last_read_at`:

```text
id
message_id
user_id
read_at
unique(message_id, user_id)
```

### `conversation_events`

```text
id
conversation_id
actor_id                     nullable
event                        opened|closed|blocked|reported|participant_added
metadata                     nullable json
created_at
```

## Security and Authorisation

1. A user may retrieve a conversation only if present in `conversation_participants`.
2. A provider participant may be added only after server-side verification of workspace/property access.
3. The client must never supply arbitrary provider participant IDs to grant access.
4. The conversation property/workspace must match the linked listing/contract/unit/ticket.
5. A Tenant conversation requires the tenant to have an active/valid relationship to the property.
6. A blocked/closed conversation cannot accept normal messages.
7. Sensitive access and moderation actions create audit logs.
8. Super Admin support access requires a documented reason and audit record.
9. Apply rate limits to message sending and attachment upload.
10. Escape/render messages safely; do not permit arbitrary HTML.

## Permissions

```text
message.view_own
message.send_own
message.close_own
message.report
message.manage_property
message.moderate_platform
```

Suggested role mapping:

| Role | Messaging capability |
|---|---|
| Tenant | View/send only in own conversations |
| Seeker | View/send only in own marketplace inquiries |
| Staff | Manage conversations for assigned properties |
| Manager | Manage conversations for assigned properties/workspace |
| Owner | Manage conversations for owned workspace/properties |
| Super Admin | Moderate only reported/escalated cases with audit trail |

## UI Requirements

### Desktop provider portal

- Messages menu with unread badge.
- Split list/detail layout for wide screens.
- Property/context filter.
- Clear status badges: Open, Closed, Blocked.

### Tenant mobile portal

- Messages accessible from bottom navigation or profile area.
- Single-column conversation list.
- Composer fixed above mobile safe area.
- Attachment action from camera/gallery/file picker.

### Marketplace

- CTA: `Tanya penyedia`.
- Login/verification gate before conversation creation.
- Listing context always visible in chat header.

## Acceptance Criteria

1. Tenant can create and reply only to conversations related to their accessible property/contract.
2. Provider receives a notification for a new tenant message.
3. Seeker can start a conversation from a published listing only after login/verification.
4. Unauthorised user receives 403/not-found and never sees message snippets or metadata.
5. Provider from another workspace cannot access the conversation by modified URL or request.
6. Attachment type/size validation works and files are private.
7. Closed/blocked conversation rejects a new normal message.
8. Read/unread state updates correctly.
9. Report/block actions create conversation event and audit log.
10. Polling displays new messages without page refresh and without WebSocket dependency.

## Implementation Order

### Release 4

1. Schema, models, policies, factories, seeders.
2. Tenant-provider conversation creation from tenant portal.
3. Provider message inbox and replies.
4. Read/unread and in-app/email notification.
5. Private attachments.
6. Close/report/block and audit log.
7. Authorisation, privacy, and rate-limit tests.

### Release 6

1. Listing-linked inquiry conversation.
2. Login/verification gate.
3. Provider routing from listing/property access.
4. Marketplace-specific filtering and moderation.
