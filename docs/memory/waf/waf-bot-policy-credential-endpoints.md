---
name: waf-bot-policy-credential-endpoints
description: Which endpoints skip the WAF bot policy because they admit a caller by its credential (integration, mcp, oauth, workspace_ical, feed, marketplace_webhook), why, where the return sits, why not api.php.
---

## Which endpoints skip the bot policy?
- Those that admit a caller by its credential, not its user agent: `integration.php`, `mcp.php`, `oauth.php`, the Workspace calendar subscription `workspace_ical.php`, the product feed `feed.php`, the marketplace order webhook `marketplace_webhook.php` (key in a header, `commerce/marketplace-webhook-receiver.md`), the ShipWorks endpoint `shipworks.php` (a password the sign-in throttle counts; listed only while that throttle is on) and the guest room `workspace_guest.php` (the room's token; `waf-counter-subjects-and-endpoints.md`). The country restriction's API exemption keeps its own, shorter list (`waf_country_api_scripts()`). `waf_handle_bot()` returns for them right after the attack-tool check, the only check that still runs; the sensitive limit and the in-flight cap step aside for them too.
- Why: an AI assistant working through the MCP server sends its fetcher's name (`Claude-User`) from its operator's API egress, which is not in the crawler ranges that operator publishes; judged as a crawler it was `bot-spoof`, refused and counted towards a ban right after it had been given a token.
- The return sits BEFORE the good-bot branch, so a crawler is never verified there and its name buys no relief from the rate limits; only `waf_bot_verified()` does (`waf-bot-rate-exemption.md`).
- `api.php` is not among them (`waf_is_site_api_request()`): the site's own pages call it without a credential, so the bot policy still guards it.
- `waf_is_api_request()` asks `waf_request_names_script()` (`waf_names_script_in()`): the running script, and the request path only when `router.php` runs, the way IIS hands `/integration.php/products/138` over. `/index.php/integration.php/x` and `/index.ph%70/integration.php/x` answer `index.php`, so appending a name claims nothing (#1202, waf-path-exemption-and-robots.md).

Related: docs/memory/waf/waf-path-exemption-and-robots.md, docs/memory/waf/waf-ai-bots-ranges.md, docs/memory/api/external-api-iis-environment.md
