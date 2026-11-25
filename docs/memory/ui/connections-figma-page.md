---
name: connections-figma-page
description: Figma's page on Connections (#11558): the personal access token pasted there by a manager, Save and try asking Figma whose token it is, the token's states, Disconnect, where it is used
module: figma
---

## Why a pasted token and not a Connect button?
- Owner (2026-11-24, `workflow/decisions-made-product-107.md`): a public Figma app needs Figma's review and fixed return addresses, every site has its own address; so a personal access token made in Figma's settings. No app, no return address, no button that leaves the site.

## What does the page show?
- `connections.php?service=figma`, registry row `figma` (group Other, `place` `connections.php`, state kind `service` key `figma`). It is a new service: `pg_connections_count()` went to 85.
- "Status": "No access token is stored"; or "Connected as <name>" (or "Connected" for a token without "Current user") with "Not tried yet" / "Last tried …"; or the amber "Figma no longer accepts the stored token" and the list row's pill "Needs attention". "Disconnect" (`figma_remove`) with its confirm text.
- "Connection details" (card gate `$can['figma']`, a manager, role 2 or above): "Personal access token", a password field never filled back, placeholder `figd_…` or "Stored. Leave empty to keep it."; the help on where it is made and the scopes, "Open Figma's settings", "More" with the Enterprise variables line. "Save" filled, "Save and try" quiet.
- "Where it is used": "Page designer: start from a Figma file", leading to `view_system_styles.php?start=figma`, pill Off (no token), Paused (refused), On.

## What does a save do?
- `figma_save`: the field's form only (`pg_figma_token_valid()`), then "Save" stores it as `untried`; "Save and try" asks `GET /v1/me` first and stores nothing when Figma refuses (field red) or does not answer. A 403 for the scope (no "Current user") is accepted with no name.
- Stored sealed with `pg_connect_service_put('figma', …)`: token, name (only the handle; never e-mail or picture), state `untried|ok|refused`, tried time. No table or column.
- A file read, or the import's nodes read, that Figma refuses for the token marks it `refused` (`pg_figma_connection_refused()`); the next successful read marks it `ok`.

Tests: `tests/figma_test.php` (`test_figma_on_connections`), the count in the `connections_*` tests.
