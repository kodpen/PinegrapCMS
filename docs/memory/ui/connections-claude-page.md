---
name: connections-claude-page
description: Claude's page on Connections (CX11k2): the routine's address and token in Connection details, Save and try; Claude's row in Assistants and agents and set-up step 4 only lead there
module: connections_screen
---

## Why did the routine's address and token move?
- Owner (2026-11-24, poll 222, `workflow/decisions-made-product-100.md`): Connections is the one place an outside service is set up; the row keeps a line leading there. Step CX11k2 of `connections-one-place.md`, #11265. The MCP servers are CX11k3.

## What does Claude's page show?
- `connections.php?service=claude`: Status, "Connection details" (`ws_claude_connection_card_html()` in `includes/workspace/claude_setup.php`), Where it is used (one row: "Claude in the channels, through a routine", Site Settings › Assistants and agents › Claude, Connected while `ws_claude_ready()`).
- Details: Routine address (`claude_routine_url`, drawn back), Token (`claude_token`, never drawn back; the eye; the bin ticks `claude_token_clear` while one is stored, bound by `ws_claude_fold_script()`), "Save" filled, "Save and try" quiet. Notices for a site without HTTPS and one that cannot seal (then both buttons are off).
- Gate: `connections_claude_may_manage()`: may open Assistants and agents and is the Workspace's administrator (role 0), as the row's fields were; card `details` with `$can['claude']`.

## What does a save do?
- `claude_save`: `ws_claude_routine_input()` (pure) takes the switch, the application and the summary as stored, so `ws_claude_settings_save()` writes only the address and token (into Claude's `ws_agents` row) and clears the last error and the wait. A wrong address is marked on `claude_routine_url`.
- The stored application is passed on with `app_kept`, so `ws_claude_settings_app_checked()` skips its check: a linked application deleted in Application Access refused every Save with a field the page lacks (fixed in the connections-merges hunt).
- `ws_claude_routine_decision()` writes only a changed address or token; an unchanged Save wrote anyway and cleared the last error and the wait. "Save and try" still tries.
- "Save and try" then starts the routine once (`ws_claude_fire(true)`) when `ws_claude_missing()` is empty; else its first line is the error.

## What stays in Site Settings?
- The row (`settings-claude-row.md`): switch, application, summary, Try, set-up drawer, Key and secret. `ws_claude_settings_input()` gives `routine_url` null when not posted, and save and `ws_claude_settings_changed()` then leave the routine alone; the save module now enters on `ws_claude_app_id`.
- Set-up step 4 is "Paste the routine address and the token in Connections" with "Open Claude in Connections" (same tab; the set-up drawer opens again on the way back, `connections-lead-keeps-pane.md`).

## What did the registry change?
- `claude`: `place` `connections.php`, `found_at` `#settings/workspace_assistants/pgset-ws-claude` (gate and settings search kept). No table, column or data step.

Tests: `tests/connections_claude_page_test.php`, `settings_claude_row_test.php`, `settings_claude_setup_drawer_test.php`, `settings_workspace_assistants_test.php`.
