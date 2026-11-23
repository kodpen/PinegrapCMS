---
name: workspace-settings-place-names
description: How a message, an assistant's prompt line or an API description names the place of a Workspace setting: the Site Settings path, never the old Workspace Settings screen; what keeps the old words
---

## How does a text name a Workspace setting's place?
- As the panel shows it: "Site Settings › Assistants and agents › MCP servers", the category, then the row (`ws_assistants_fold_face()` names the rows: Claude, Pinegrap AI, Workspace agents, MCP servers, Voice, Canvas templates, Bulk changes). Calls: "Site Settings › Channels › Calls"; link previews: "… › Channels › Link previews".
- Never "Workspace Settings" or "the Workspace settings" as a place: that screen only redirects (`workspace-settings-overview.md`). Census K9 fixed the messages, the assistants' prompt lines, the dashboard's Claude and agents cards and the API descriptions that still named it.
- A link keeps `pg_settings_workspace_link('ws-…')`; only its words change.

## What still says "Workspace settings", and why?
- The right: `ws_rights()['settings']`, "Change workspace settings", "You do not have access to Workspace settings." and the refusals naming who may change them. They name a right, not a screen.
- The Workspace menu entry and the screen name (`ws_nav_groups()`, `includes/workspace/screen.php`), the Site Settings links list of Integrations, the log line "access denied to the workspace settings", and the Integrations search keyword: they open or name the group of three categories.
- The routine's instructions (`ws_claude_routine_text()`): their words are hashed into the instructions version (`ws_claude_instructions_version()`), so a change makes every routine read them again; left until the text changes for its own reason.

## What is left for later?
- Mailboxes: "Site Settings › Mailboxes" until EM1 (#10983) made the E-mail category (`ui/settings-mail.md`); EM1 changed `system_status.php`'s mailbox line and the other texts naming Mailboxes to "Site Settings › E-mail › Mailboxes".
- An API description stays within the MCP tool budget of 600 characters (`tests/mcp_tool_budget_test.php`): the agent routes say "(Site Settings, Workspace agents)".
- `notes_notion_settings` ("Open Workspace settings") is drawn by no path: the Notion state never carries the anchor the script waits for.
