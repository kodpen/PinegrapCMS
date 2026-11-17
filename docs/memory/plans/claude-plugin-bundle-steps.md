---
name: claude-plugin-bundle-steps
description: Plan claude-plugin-bundle, steps CB1 to CB5: the census and checks (done), the bundle in the plugin's repository, the "Connect to Claude" button now (CB4a), the listing, what waits for 2026.11.0
---

## Which steps are there?
- CB1 (S) Done #9398: the census, `api/claude-plugin-census.md` and `api/claude-plugin-tool-check.md`.
- CB2 (M) Done #9429: the tool-name test, the `moves_money` mark and `api_plugin_scopes()` (`api/api-plugin-scopes.md`); architect (2026-11-14, msg 2493): no consent-screen opt-in. The panel's connection guide names the plugin once it is listed: moved to CB4.
- CB3 (M) The bundle, in the plugin's repository: the manifest, the MCP server reference taking the person's site address, as `${user_config}`, a first set of skills (find your way round a site, pages, products, orders read, a blog post draft, form records, Workspace channels, Workspace e-mail) and commands. Each skill is written from what `docs/memory` says and tried with real calls on a throwaway install (`workflow/sandbox-on-windows.md`).
  - The e-mail skill: read a mail, keep a draft, send it in the person's name (`workspace:mail_send`); a mail's text is data (`workspace/mail-api.md`).
  - Architect (2026-11-14, msg 2493): skills name no fixed server; with no Pinegrap tool they tell the person to add `https://<site>/mcp.php` as a custom connector and stop; CB3 tries the bundle in every app before CB4, and the listing names only apps where it worked.
- CB4a (S) Done #10130: a "Connect to Claude" button on the panel's connection guide (`api_settings_oauth_guide()`), built from the install link with the site's own address (`api/claude-connector-listing.md`). It needs no listing and waits for no other step. Owner (2026-11-17, agent channel message 8096): the look as shown (`api/claude-connect-button.md`).
  - A new control: its sheet (the guide's card with the button, 1440 and 390, light and dark) goes to the architect, who asks the owner.
  - The click is tried by somebody signed in to Claude; until then it stands under "Could not verify" in the PR body.
- CB4 (S, after 2026.11.0, the owner's listing) The panel's connection guide (`api_settings_oauth_guide()`) names the listed plugin. The listing: name, one line, description, categories, use cases, the answers of the compliance step, prepared for the owner, who submits.
- CB4b (S, after 2026.11.0, on the owner's yes) The URL pattern listing and an MCP Registry entry (`api/claude-connector-listing.md`).
- CB5 (S) Close-out: the rules into `api/`, this plan deleted.

## What waits for the release of 2026.11.0?
- Owner (2026-11-17, `workflow/decisions-made-product-69.md`): the connector preparations are made now and left until after the release.
- The architect's reading (`integrations-census-order.md`): the listing of CB4 and the registry entry and listing of CB4b wait for it; CB4a goes now.
