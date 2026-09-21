# Authorization architecture decision

**Status:** Owner-approved architecture decision. **Not implemented in RENTARA-INFRA-001.**

This document records the approved target authorization architecture. It does not describe current application behavior and must not be read as evidence that workspace authorization, roles, memberships, invitations, or safeguards exist in the baseline.

## Approved model

- `super_admin` is a global, platform-only role. It is not a workspace role.
- `owner`, `manager`, and `staff` are the authoritative roles on an active workspace membership.
- A user can have only one membership in a given workspace.
- Operational authorization requires an active membership in the applicable workspace.
- Release 0 uses an existing-user membership lifecycle: memberships are for users already known to the application. Invitations are not part of that lifecycle.
- Canonical owner safeguards are required so that a workspace retains its canonical owner; membership and role changes must not bypass those safeguards.

## Implementation boundary

The current database has no workspace or membership schema, and the current routes and authorization middleware do not implement this decision. The current starter-kit authentication and its email-verification requirement on the dashboard are separate from the approved future workspace-authorization model.
