# Authorization architecture decision

**Status:** Owner-approved architecture decision; implemented for the RENTARA-FEAT-002 workspace-foundation scope.

This decision is implemented for identity status, platform and workspace roles, workspace context, existing-user membership management, and canonical-owner safeguards. The implementation details and boundaries are recorded in [Workspace foundation implementation](workspace-foundation-implementation.md). Features outside that document's scope remain unimplemented.

## Implemented model

- `super_admin` is a global, platform-only role. It is not a workspace role.
- `owner`, `manager`, and `staff` are the authoritative roles on an active workspace membership.
- A user can have only one membership in a given workspace.
- Operational authorization requires an active membership in the applicable workspace.
- Release 0 uses an existing-user membership lifecycle: memberships are for users already known to the application. Invitations are not part of that lifecycle.
- Canonical owner safeguards are required so that a workspace retains its canonical owner; membership and role changes must not bypass those safeguards.

## Current boundary

This implementation does not provide invitations, ownership transfer, co-owners, property-level assignments, an audit viewer/listing/API, or other product modules. Member lifecycle changes are recorded by the `RENTARA-FEAT-004` audit baseline (see implementation doc); `super_admin` remains platform-only and is never a workspace-role bypass.
