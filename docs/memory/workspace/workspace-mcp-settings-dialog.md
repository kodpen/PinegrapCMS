---
name: workspace-mcp-settings-dialog
description: The MCP servers card in Site Settings (Assistants and agents) and on Workspace Settings: drawers, ws_mcp field and button names, the relay, the sign-in handed over as pg_settings_go()
---

## Where is the MCP servers card?
- PR #8844 (SW5 of `plans/settings-workspace-move.md`): in Assistants and agents, section `pgset-ws-mcp`, after Pinegrap AI and before Bulk changes. The category's "On the Workspace Settings screen" link card is gone with it. The old card (`#ws-mcp`) keeps working on `workspace_settings.php` until SW7.
- `pg_settings_legacy_anchors()` maps `ws-mcp` to the card. "My MCP connections" still links `workspace_mcp.php`.

## What opens in a drawer?
- Each server's line keeps its badge, details, own-account line, Connect or Disconnect and the icon buttons (ask again, turn off or on, remove); "Tools and settings" opens `pgset-ws-mcp-<id>`: its last error, the tools with the agent choice, then address and sign-in under a closed "Connection settings".
- "Add a server" opens `pgset-ws-mcp-add`: the ready-made connectors, Check the address and its short form, the whole form under More settings. Both from `ws_settings_mcp_drawer()`.

## Which names does the dialog post?
- Fields `ws_mcp[<key>][<field>]` (`ws_mcp_settings_name()`), key the server's id, 0 for a new one, `probe` for the short form after a check, `check` for the address to check; a tool is `ws_mcp[<id>][tool][<name>]` (`lists` entry `ws_mcp` 3). No `required`, no `autofocus`.
- Named submit buttons `ws_mcp_<button>`: save, tools, declare_all, refresh, toggle, delete, connect, disconnect, connect_me, disconnect_me (value the key), catalogue_add (value the connector's key), probe (value `check`). Nothing runs on a plain pane Save.

## How do the two screens share the save?
- `ws_mcp_settings_pressed($_POST)` turns the button into the old action and that form's fields under their `mcp_` names (`ws_mcp_settings_dialog_input()`); `ws_mcp_settings_post($viewer, $action, $liveform, $input)` runs it as on the old screen. `ws_mcp_settings_save()`, `ws_mcp_catalogue_post()`, `ws_mcp_agent_settings_save()` and `_preamble_save()` take the input instead of `$_POST`.
- The save module passes a `ws_mcp_settings_relay`: a refusal becomes a warning (the pane is stored by then), and what a refused form or an address check keeps goes to `$_SESSION['software']['ws_mcp_kept']`; the next draw reads it once (never the search index) and presses the drawer it belongs to (`data-ws-mcp-reopen`).

## How does a sign-in leave the dialog?
- The dialog answers in JSON, so nothing may send a Location header. `ws_mcp_settings_place()` holds the sign-in address (`ws_mcp_settings_go()`) and sets the address the service comes back to (`settings_workspace_assistants.php?mcp_connected=<id>#pgset-ws-mcp`); the save module hands it to `pg_settings_go()`.
- `pg_settings_go()` (`includes/settings/registry.php`): `settings_pane.php` answers it as `reload`, which the dialog follows; the page path redirects to it. The page path calls `ws_mcp_settings_returned()` when `mcp_connected` comes back.
- The old screen keeps redirecting at once and coming back to `workspace_settings.php#ws-mcp-<id>`.

Tests: `tests/settings_workspace_mcp_test.php`.
