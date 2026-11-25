---
name: bug-hunt-areas-connections-merges
description: The bug hunt in Connections after the CX11 merges (area connections-merges): Claude's, the chat services' and the MCP connectors' pages; the faults fixed and what was read clean
---

## Which area was it?
- The service pages that took over a card in CX11: Slack's, Teams' and Telegram's (CX11j2, `includes/fn/notify_channels_page.php`), Claude's (CX11k2, `includes/workspace/claude_setup.php`), the 12 MCP connectors' (CX11k3, `includes/workspace/mcp_catalogue.php`), their writes in `connections.php` and the lines left in Site Settings. Named by the architect as "connections-merges".
- Left alone: what `bug-hunt-areas-ai-connections.md` read (the AI provider pages, `ai_connections.php`, the voice card).

## What was fixed?
- Claude's page: a Save was refused with "That application could not be found." while the application linked to Claude had been deleted in Application Access; the page has no application field, so the routine's address could not be changed. The page now passes the stored application on unchecked (`app_kept`, `ws_claude_settings_app_checked()`).
- Claude's page: a Save that changed nothing wrote anyway, which cleared the routine's last error, its failures and the wait it was given, and logged a change. Now only a changed address or token is written (`ws_claude_routine_decision()`), as the row's save already did.
- A chat target's "Last failure" was dated `Y-m-d H:i` in the server's zone; it now uses `get_absolute_time()`, as Connections dates the rest (`ui/panel-dates-site-order.md`).

## What was read and found clean?
- Gates: chat targets under Updates and maintenance's gate, Claude and the MCP connectors under Assistants and agents' gate plus the Workspace's administrator (role 0), on the page and on each write; a Manager or Designer without the gate sees Status and Where it is used only.
- Secrets: a target's webhook address or bot token is never drawn back (scheme and host only), Claude's token is dots in an empty box, an MCP form refused by its check keeps no token, password, header value or client secret.
- A write touches only its own page: a target of another service is ignored, Claude's page writes only the address and token (switch, application and summary as stored), the row's save leaves the routine alone while it is not posted, Updates and maintenance's save writes no target.
- Nothing set up: a chat page shows only Add a target (or the encryption note), an MCP page "Not added yet." with Add, Claude's page empty boxes; Where it is used shows Not connected.
- Leading lines: the Chat notifications card, Claude's row and set-up step 4, the MCP servers card, Add a server and a connector's drawer all lead to the right page (`connections.php?service=…`, `#connections-group-mcp`); the old Workspace Settings screen redirects to Site Settings.
- At 390 px the forms' button rows wrap (`flex-wrap`), the alert check boxes go one a row, the token box keeps its eye and bin inside.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/connections-claude-page.md, docs/memory/ui/connections-chat-notify-page.md
