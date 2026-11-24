---
name: connections-mcp-connector-page
description: A ready-made MCP connector's page on Connections (CX11k3): Add, the server's address and sign-in, Connect and Disconnect; Site Settings' MCP servers row keeps the tools and leads there
module: connections_screen
---

## Why did the connectors' address and sign-in move?
- Owner (2026-11-24, poll 222, `workflow/decisions-made-product-100.md`): Connections is the one place an outside service is set up; the tab's row keeps a line leading there. Step CX11k3 of `connections-one-place.md`. Claude's half: `connections-claude-page.md`.

## What does a connector's page show?
- `connections.php?service=mcp_<key>` for the 12 rows of `ws_mcp_catalogue()` (`pg_connections_screen_mcp_connector()` gives the key the row covers). Status, "Connection details" (`ws_mcp_connection_card_html()`, `includes/workspace/mcp_catalogue.php`), Where it is used (one row, "Tools for the Workspace's assistants", leading to Site Settings › Assistants and agents › MCP servers, with the server's state pill).
- None added: "Not added yet.", what it asks for, its address and "Add" (`mcp_catalogue_add`). A token connector (GitHub) comes back with the form filled at the token, kept only for its own page (`kept.catalogue`).
- Added: `@alias`, host, state pill, Connect / Connect again / Disconnect for a sign-in, the last error, then the server's form (`ws_mcp_settings_form()`, address, sign-in kind, token; secrets never drawn back).
- Gate: `connections_mcp_may_manage()`: may open Assistants and agents and is the Workspace's administrator (role 0), as on the row; card `details` with `$can['mcp']`.

## How do its forms post?
- They post `ws_action` (`mcp_save`, `mcp_catalogue_add`, `mcp_connect`, `mcp_disconnect`) to the page's own address; the POST reads the connector from `$_GET['service']` and runs `ws_mcp_settings_post()` with `ws_mcp_settings_place()` set to the page, `hold` false, so a sign-in leaves at once.
- `ws_mcp_settings_return_url()` adds `&mcp_connected=` to a return that has a query; the page calls `ws_mcp_settings_returned()` and reads `ws_mcp_settings_kept()` before the screen prints its notices and empties the form.

## What stays in Site Settings?
- The MCP servers row: the servers, tools, refresh, off/on, remove, Connect on the row, and servers added by their address (their Connection settings stay in the drawer).
- The card and Add a server say "The ready-made connectors are added, and their address and sign-in kept, on their pages in Connections." with Open in Connections (`ws_mcp_connections_line_html('')`, `#connections-group-mcp`); Add a server has no catalogue tiles there (the old Workspace Settings path, form null, keeps them).
- A connector's server drawer has its tools and "Its address and sign-in are on its page in Connections." with Manage in Connections instead of Connection settings (`ws_mcp_catalogue_entry_for_url()`).

## What did the registry change?
- The 12 `mcp_*` rows: `place` `connections.php`, `found_at` `#settings/workspace_assistants/pgset-ws-mcp`. No table, column or data step.

Tests: `tests/connections_mcp_page_test.php`.
