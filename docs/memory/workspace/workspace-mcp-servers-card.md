---
name: workspace-mcp-servers-card
description: Workspace "MCP servers" card (mcp.php, mcp_catalogue.php): ready-made connectors, adding by address, read-only tools on at first read, the form kept after an error, Pinegrap AI failing
---

## What does the card offer an administrator (role 0)?
- Added servers; "Ready-made connectors" (`ws_mcp_catalogue()`, bundled, no registry fetch: owner 2026-10-24); "Add a custom connector"; the whole form under More settings (`#ws-mcp-new`). GET /workspace/mcp-servers/catalogue lists the same (403 below role 0, `'mcp' => false`).
- A tile's Add (`mcp_catalogue_add`): none: saved, tools read; oauth: saved, then straight to Connect (as for every new OAuth server); bearer: the form opens filled, focused on the token, with the token page link (`mcp_kept_catalogue`). A taken alias gets 2..9 (`ws_mcp_alias_free()`); an entry whose address a server has (`ws_mcp_url_key()`) shows Already added.
- Check the address (`mcp_probe`, `ws_mcp_probe()`): initialize and tools/list with no credential; on a 401 `pg_mcp_client_discover_auth()`: oauth when the site can register (`ws_mcp_oauth_registration()`), else bearer; else the client's error. The short form (alias from `ws_mcp_alias_from_host()`, a token for bearer) posts `mcp_save`. A probe registers nothing.

## How is an entry added?
- A row in `ws_mcp_catalogue()`: an address that answers initialize with 200, or 401 with a challenge whose service registers the site (curl it first); a `bi-*` icon of assets/fonts/bootstrap-icons, no logos; an alias outside `ws_mcp_reserved_aliases()`; `tools` read_only; the description's Turkish text through `php tools/tr_add.php`. The tests read every row.
- 2026-10-24: 12 rows answered (5 open, 6 OAuth, GitHub by token: its sign-in server registers nobody). Stripe (OAuth) answered too but stayed out (~12 asked).

## Which tools are on after a server is added?
- Symptom "no tool on, @alias missing": tools started off, so the @ picker (`ws_mcp_boot()`), form run and agent mode skipped the server.
- Owner decision (2026-10-24): the first read (`ws_mcp_first_read()`, `ws_mcp_rules_after_read()`) turns on the tools marked `readOnlyHint: true` (rule `auto`); a server read before keeps its rules.
- "Not said" tools (DeepWiki) stay off; its Tools and settings is drawn open. "Declare the "Not said" ones read-only" (`mcp_declare_all`, behind `confirm()`) saves them `auto_declared`; "Ask before each call" keeps asking. Writing tools: OAuth only, approved per call.

## What comes back after a refused add or change?
- `ws_mcp_settings_keep()` keeps `ws_mcp_settings_keep_fields()` (no token, password, header value or client secret), the reason and the form (`mcp_kept_for`: `new`, `probe` or the id); the card opens that form filled; `remove_form()` empties it.

## What does the card say while Pinegrap AI fails?
- Gateway 502: the AI card stays Ready and each @alias fails after `WS_AI_MAX_ATTEMPTS`. With `ws_ai_config()['error']` set, an alert names it (a good answer or a Try clears it); queued requests read "Waiting: … Next try: HH:MM", the one tried "Pinegrap AI is working on it". Not set up: the "not connected yet" alert.
- Owner decision (2026-10-24): the form-run link (`ws_mcp_form_hint()`) is posted when Pinegrap AI is not set up (`ws_ai_after_send()`), and once at a request's first hold (`ws_ai_step()` → `ws_ai_hold_form_hint()`, gated by `ws_ai_form_hint_due()`), not on retries.

## How is it checked?
- tests/workspace_mcp_test.php: catalogue rows and icons, alias from a host, Already added, the probe with a stand-in fetch, first read, declare, kept fields, link due; workspace_api_test.php: the route. The API of the added servers: `workspace-mcp-servers-api.md`.
- CLI harness on a bare MariaDB, 2026-10-24: one-click adds and probes against the live servers. No browser session; OAuth registration and sign-in never run live.
