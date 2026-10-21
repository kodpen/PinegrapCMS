---
name: waf-path-exemption-and-robots
description: waf_is_excluded() matches the path only; /robots.txt, /.well-known/, health.php and the credential-checked endpoints skip the bot policy (api.php does not); feed readers are good bots.
---

## What does the exemption look at?
- `waf_is_excluded()` DISCARDS the query string and matches only the path. Matching the whole `REQUEST_URI` made the entire firewall skippable: anyone can put the pattern in a parameter. With `api2` exempt, `/hesabim?x=1&foo=api2&q=' OR 1=1--` hit the exemption and signature scan, rate limit and IP list were all skipped.
- Consequence: you cannot exempt a feed by query parameter (`?rss=true`), that is exactly the string an attacker would add. To exempt, exempt the path.

## Which paths are always served?
- `waf_path_is_always_served()`: `/robots.txt` and `/.well-known/` are exempt from bot policy.
- Blocking `robots.txt` undermines itself: it tells crawlers what not to crawl; without it the crawler cannot learn the rule and keeps guessing. A live log showed AhrefsBot, SemrushBot and DotBot turned away from `/robots.txt` repeatedly, missing every chance to tell them "go".
- `.well-known/` carries ACME certificate renewal. Blocking it does not fail loudly: ninety days later the certificate expires and the whole site fails with it.
- The exemption is from the BOT POLICY ONLY; the IP blacklist and rate limit still apply.
- `health.php` (uptime monitors) gets the same bot-policy and country exemption through `waf_is_health_request()`, which asks the running script, never the path: `/x/health.php/y` handed to `router.php` must not carry it. See `docs/memory/platform/maintenance-mode-health.md`.

## Which endpoints skip the bot policy?
- Those that admit a caller by its credential, not its user agent: `integration.php`, `mcp.php`, `oauth.php`, the Workspace calendar subscription `workspace_ical.php` and the product feed `feed.php`. `waf_handle_bot()` returns for them right after the attack-tool check, the only check that still runs.
- Why: an AI assistant working through the MCP server sends its fetcher's name (`Claude-User`) from its operator's API egress, which is not in the crawler ranges that operator publishes; judged as a crawler it was `bot-spoof`, refused and counted towards a ban right after it had been given a token.
- The return sits BEFORE the good-bot branch: after it, `IS_BOT` would be defined and a crawler name would buy relief from the rate limits.
- `api.php` is not among them (`waf_is_site_api_request()`): the site's own pages call it without a credential, so the bot policy still guards it.
- `waf_is_api_request()` asks the request, not only the file that ran: the running script, else the first path segment ending in `.php` (query string ignored). IIS hands `/integration.php/products/138` to `router.php`; `/index.php/integration.php/x` answers `index.php`, so appending a name claims nothing.

## Which feed readers are good bots?
- Feedly, Inoreader, Feedbin, NewsBlur, Miniflux, Netvibes, The Old Reader, FeedFetcher. Each request represents a real subscribed person; blocked, the feed silently empties and nobody reports "my feed stopped".

Related: docs/memory/visitors/robots-txt-disallow-rules.md, docs/memory/waf/waf-country-restriction.md, docs/memory/api/external-api-iis-environment.md
