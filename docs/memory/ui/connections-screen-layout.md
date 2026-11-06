---
name: connections-screen-layout
description: Connected accounts screen (connections.php) on the wide layout: provider cards in the form column, a Summary of every provider in the status column, application fields as form-field boxes, no save bar
---

## How is the screen laid out?
- Form column (`lg:col-8 2xl:col-9`): one card per provider (`id="connections-<provider>"`), one under the other; before, two cards shared a row from `2xl`.
- Status column (`lg:col-4 2xl:col-3`, sticky box at `top:3.3rem`), written after the form column: the Summary card (`#connections-summary`), one row per provider with a jump to its card, the count of connected accounts, a warning badge with the count that must be connected again, and the application badge (Ready, Off, Not set up).
- No sticky save bar: each provider card saves its own application through its own form (a code comment says so).

## How are the application fields written?
- Redirect address, Client ID, Client secret and the provider options are v6 `form-field` boxes (`form-label`, the control, a `form-text` hint inside), at the regular control size (no `form-control-sm`).
- The copy status (`.connections-copy-status`) stays the element right after the `.input-group`: the Copy script finds it with `nextElementSibling`.
- Field ids, names and what is posted are unchanged.

Tests: `tests/connections_layout_test.php`.
