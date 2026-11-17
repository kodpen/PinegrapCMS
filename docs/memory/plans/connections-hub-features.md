---
name: connections-hub-features
description: Plan connections-hub, features H1 to H13: the registry of outside services, its states and count, the Connections screen and its drawer, what follows the registry, the merges asked one by one
---

## What is the registry?
- (H1) One list in code of every outside service the product connects to: its key, name and group, the one place its fields live (a card of Site Settings by its address `#settings/<pane>/<card>`, or a System tools screen), how its state is read, what uses it.
- (H2) The lists that exist stay the source of their own rows: payment drivers (`pg_payment_builtin_drivers()`), label drivers (`pg_label_builtin_drivers()`), marketplaces (`mp_providers()`), SMS (`pg_sms_providers()`), connected accounts (`pg_connect_definitions()`), model providers (`ws_model_providers()`), MCP connectors (`ws_mcp_catalogue()`).
- The registry gives each of them a place and a state reader, and lists what has no list of its own: mail transports, analytics, maps, captcha, storage and the rest of `integrations-census-today.md`.
- (H3) A test keeps it whole: a driver or provider of those lists without a row fails, and so does a place that is no card of `pg_settings_categories()` and no screen of `pg_settings_tool_groups()`.
- (H4) A state is read from what the site has stored, with no outside call while the list is drawn: connected, needs attention, not connected. Needs attention only where an error is on file (a connected account marked `reauth`). A service whose state cannot be read this way shows none; CN1 names each.
- (H5) The registry's count is the product's own figure, kept by a test and never typed.

## What is the screen?
- (H6) System tools › Connections › "Connected accounts" (`connections.php`) becomes the one screen of connections: a search box, the groups, what is connected first, and on top a block of what needs attention.
- (H7) A row: the service's name and group, its state, what uses it in a few words. A click opens its drawer.
- (H8) The drawer: the state, the account where there is one, what uses it, the last error on file, and one button that opens the setting's one place. No field of a setting is repeated in it.
- (H9) What the screen does today stays on it: for each provider its OAuth application and its accounts, with Connect, Check and Disconnect (`api/api-connected-accounts.md`), where the sheet puts them.
- (H10) The other side, what connects to the site, is one block that leads to Application Access with its counts (applications, devices, connected applications, subscriptions). Nothing of that screen is copied.
- (H11) The screen is in look A (`ui/README.md`).
- A row is shown only to a user who may open its place; a key is never shown, only that one is set.

## What follows the registry elsewhere?
- (H12) The search of Site Settings and the panel's palette find a service by its name and lead to its place; the Integrations category of Site Settings draws its links from the registry.

## Which merges are asked one by one?
- (H13) A setting that moves is a step of its own, each asked of the owner with its sheet: GitHub's five places, the AI keys' six, mail's four, a provider's client typed in one place, one meaning each for "Connections", "Connected accounts" and "Connected applications" (`integrations-census-screens.md`).
- A card's address and its old anchors stay when it moves (owner 2026-10-30, `workflow/decisions-made-product-14.md`).
