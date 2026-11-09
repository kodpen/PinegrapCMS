---
name: workspace-saved-replies-team
description: Team saved replies (#8405, step 11.0.177): the manage_workspace_replies right, its own probe, where it is drawn and saved, the users API field
---

## Who may manage team replies?
- `ws_rights()['replies']` (`ws_rights_team_replies()`, pure): roles 0-2 by role, a role-3 member by `user.manage_workspace_replies` (TINYINT, step 11.0.177, `decisions-made-schema-14.md`), nobody outside the team. `ws_saved_reply_can_manage_team($viewer)` asks it.
- The column is read behind its own probe (`ws_team_replies_column()`, `pg_user_permission_ws_replies_column()`), never in the login query or `pg_user_has_ws_columns()`: it does not open the panel on its own, so the role-3 ladder files do not know it.
- Drawn as "Manage team replies" with the other Workspace switches on the three user screens (`pg_user_permission_ws_fields()` adds it to the values read and the SQL written once the column is there; the gate off clears it). The users API reads and writes it as `manage_workspace_replies` (optional column `workspace_replies`), so an API update does not clear it.

Related: docs/memory/workspace/workspace-saved-replies.md, docs/memory/core/permission-columns.md
