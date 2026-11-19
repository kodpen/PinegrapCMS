---
name: connections-screen-drawers
description: Connections screen's drawers (CN4): a provider's drawer with its application and accounts, the last error only where stored, the other side's block
module: connections_screen
---

## What do the drawers hold?
- A provider row opens its drawer (`dialog#connections-<provider>`, the old card id: that hash, a refused save and the return from every write open it): the application (state, setup steps folded once ready, fields, Save, console, Google's fallback), then its connected accounts (Check, Connect again, Disconnect, the stored error); foot Connect an account, Close. Field ids, names and posts are as before.
- A service row of the registry has no drawer since CX7: it opens its own page (`connections-service-page.md`). Only the account providers keep a drawer until their step (CX8 GitHub, CX10 Google and Microsoft).
- A last error only where one is stored: a connected account (`pg_connections_screen_keeps_errors()`), in a provider's drawer and on a service's page; no other reader has a place for one and none is added. Paraşüt's account name and a "key stored" line of CN2's sheet are not drawn for that reason.
- A long account address breaks after its "@" (`pg_connections_screen_address_html()` adds a `<wbr>`), in the provider drawer, a service's page and the attention block; a copy and a screen reader get the one address. Architect (agent channel 8921).
- 480 px floating off the right edge (the report settings drawer's shape); below 576 px a sheet from the bottom (the script swaps `drawer-end` for `drawer-bottom`).

## What is the other side's block?
- The side column "What connects to the site", for a user whose tools hold Application Access: its four counts (`pg_connections_screen_other_side()`: applications without the documentation's `__test__` ones, team devices, OAuth clients, webhook subscriptions) and one button to it. Nothing of that screen is copied.

Related: docs/memory/ui/connections-screen-layout.md
Tests: `tests/connections_screen_test.php`, `tests/connections_layout_test.php`.
