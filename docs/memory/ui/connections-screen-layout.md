---
name: connections-screen-layout
description: Connections screen (connections.php, CN3): registry services in groups with their state, the attention block, search and filter, rows under their place's gate
module: connections_screen
---

## What is the screen?
- System tools › Connections › Connections (`connections.php`), "Bağlantılar", for a manager; the redraw of Connected accounts as CN2's sheet shows it (owner 2026-11-17, `workflow/decisions-made-product-70.md`). Tool label and search words in `pg_settings_tool_groups()` / `pg_settings_tool_keys()` of `includes/settings/registry.php`.
- Rows: the account providers first (group "Account providers", from `pg_connect_providers()`, sign-in-only Apple left out: it is the Sign in with Apple row), then every row of `pg_connections_registry()` in `pg_connections_groups()` order.

## How is a row's state read?
- `pg_connections_screen_facts()` reads what is stored once (accounts, `connect_apps`, constants, readers, marketplace accounts, the active e-document driver, model assistants, MCP servers, chat targets); `pg_connections_screen_state()` turns it into attention, connected, not_connected, off or none. Pure, in `includes/fn/connections_screen.php`. No outside call while the list is drawn.
- Needs attention only from an account marked `reauth` on a row with `'attention' => true`. Off is an application switched off; the filter counts it as Not connected. None (no reader) shows "State cannot be read" and is listed only under All.
- A provider row: attention for a site account in `reauth`, connected with an active one, off when its application is switched off.

## In what order, and who sees what?
- Inside a group: needs attention, connected, not connected, off, no state; each in the registry's order (`pg_connections_screen_order()`).
- A row shows only to a user who may open its place (`pg_connections_screen_may_see()`): a card's category under `pg_settings_category_open()`, a tool in the user's `pg_settings_tool_groups()`, `search_console.php` for a manager.
- A long group folds after its attention and connected rows (at least two; never one row alone): "Show all (N more)". No fold while a search or filter is on.

## How do the search and filter work?
- Both in the address: `?q=` (every word in the name, group, uses or place, Turkish letters folded: `pg_connections_screen_search()`), `?state=attention|connected|not_connected` as link segments with counts. No match: what was searched, where, Clear search.

## Where are the drawers and the other side?
- `connections-screen-drawers.md`.
- The attention block on top lists every account in `reauth`: the site's with Connect again, a person's with who reconnects it.

## What does a change to the screen keep?
- Today a service row shows no field and leads to its setting's place. Owner (2026-11-17, poll 146, message 9372; narrowed by poll 148, message 9374): Connections becomes the one management place, connection fields in each service's own panel here (e-mail sending stays a Site Settings category), feature cards holding no key; nothing moves before his yes to the drawing (`plans/connections-merges.md`).
- No table and no column for a state, a history of states or an error; no outside call while the list is drawn. A key is never shown.
- No catalogue of add-ons to install (`plans/addon-hook-system.md`), no copy of Application Access, no one-click broker (`plans/reverse-oauth-leftovers.md`).

Tests: `tests/connections_screen_test.php`, `tests/connections_layout_test.php`.
