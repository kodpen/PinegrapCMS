---
name: connections-screen-drawers
description: Connections screen's drawers (CN4): a provider's drawer with its application and accounts, the one service drawer filled from server templates, the last error only where stored, the other side's block
module: connections_screen
---

## What do the drawers hold?
- A provider row opens its drawer (`dialog#connections-<provider>`, the old card id: that hash, a refused save and the return from every write open it): the application (state, setup steps folded once ready, fields, Save, console, Google's fallback), then its connected accounts (Check, Connect again, Disconnect, the stored error); foot Connect an account, Close. Field ids, names and posts are as before.
- A service row opens the one drawer `#connections-detail`, filled from the row's `<template id="connections-detail-<id>">` drawn on the server: state, the accounts it stands on and the last error on file, why it shows no state, Used by, where its settings are, Open its settings. No field of a setting is repeated. Without the script the row is a link to its place; with it the click is caught in the capture phase, before the panel's link handlers open Site Settings behind the drawer.
- A last error only where one is stored: a connected account (`pg_connections_screen_keeps_errors()`); no other reader has a place for one and none is added. Paraşüt's account name and a "key stored" line of CN2's sheet are not drawn for that reason.
- 480 px floating off the right edge (the report settings drawer's shape); below 576 px a sheet from the bottom (the script swaps `drawer-end` for `drawer-bottom`).

## What is the other side's block?
- The side column "What connects to the site", for a user whose tools hold Application Access: its four counts (`pg_connections_screen_other_side()`: applications without the documentation's `__test__` ones, team devices, OAuth clients, webhook subscriptions) and one button to it. Nothing of that screen is copied.

Related: docs/memory/ui/connections-screen-layout.md
Tests: `tests/connections_screen_test.php`, `tests/connections_layout_test.php`.
