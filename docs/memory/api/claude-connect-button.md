---
name: claude-connect-button
description: The "Connect to Claude" button on the panel's OAuth connection guide: Claude's install link with the site's name and MCP file address, who sees it, the https rule
---

## Where is the "Connect to Claude" button?
- At the top of the OAuth connection guide, `api_settings_oauth_guide()` in `api_settings.php`, under the label "Claude". The guide is printed in an OAuth application's drawer and on the sheet shown once OAuth is switched on, so the button is seen by whoever may manage API access there, no one else.
- A link styled as a button; it opens in a new tab with `rel="noopener"`.

## What does it open?
- Claude's "Add custom connector" dialog filled in: `https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=NAME&connectorUrl=URL` (`claude-connector-listing.md`).
- Built by `pg_oauth_claude_connect_link($mcp_address, $name)` in `includes/oauth/rules.php`, a pure function: both values `rawurlencode()`d, the address's own query and fragment left out, an address with a user name refused. The guide prints it through `h()`.
- URL: the MCP file's address in the software folder, `pg_oauth_resource_addresses()['mcp'][1]`, the one the guide gives first because it works on every server; built from the configured host (`pg_oauth_host()`), never the request's Host header. The host is passed as the site names it (no lowercasing, an IDN host encoded byte by byte), since the client compares it with the resource the site names.
- NAME: `ORGANIZATION_NAME`; an empty one gives "Pinegrap".

## What if the site is not on https?
- The function returns '' for any scheme but https, and the guide shows one line instead of the button: "Claude connects only to an https address. Switch the site to https to connect it to Claude."

## What is not part of it?
- No listing, no URL pattern, no MCP Registry entry: those are CB4 and CB4b of `plans/claude-plugin-bundle.md`, after 2026.11.0.
- Tests: `tests/claude_connect_link_test.php`. Added in #10130; owner (2026-11-17, agent channel message 8096): the look as shown.
