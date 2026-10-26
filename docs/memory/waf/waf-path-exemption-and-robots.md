---
name: waf-path-exemption-and-robots
description: waf_is_excluded() matches the resolved script path only (never query, PATH_INFO or encoded tricks); /robots.txt, /.well-known/ and health.php are exempt from bot policy; feed readers are good bots.
---

## What does the exemption look at?
- `waf_is_excluded()` DISCARDS the query string and matches only the path. Matching the whole `REQUEST_URI` made the entire firewall skippable: anyone can put the pattern in a parameter. With `api2` exempt, `/hesabim?x=1&foo=api2&q=' OR 1=1--` hit the exemption and signature scan, rate limit and IP list were all skipped.
- Consequence: you cannot exempt a feed by query parameter (`?rss=true`), that is exactly the string an attacker would add. To exempt, exempt the path.
- The path is the script the server runs (`waf_exclusion_path()`): `SCRIPT_NAME`, already decoded, dot segments resolved, PATH_INFO split off. PATH_INFO is the caller's too: with `iyzico_callback` excluded, `/index.php/iyzico_callback` ran the sign-in screen with no firewall and let a banned address in (#953). Cutting the written address at its first `.php` segment was not enough: `/index.ph%70/iyzico_callback`, `/index%2ephp/…`, `/index.php%2F…` and `/iyzico_callback/../index.php` still ran `index.php` exempt; since #1202 the match uses `SCRIPT_NAME`.
- Only a `router.php` request (a page address, or the IIS rewrite) is matched by its request path: query removed, decoded once, `\` as `/`, cut at the first `.php` segment; a `.`/`..` segment matches nothing (`waf_request_path_decoded()`).
- The API exemptions use the same rule (`waf_names_script_in()`): the path is read only for `router.php`, so `/index.ph%70/integration.php/x` is not an API call (#1202). Tests: `tests/waf_exclusion_test.php`.

## What happens to an exclusion written after a script name?
- It matches nothing any more: `integration.php/products` or `oauth.php/token` named PATH_INFO, which is no longer part of the path. Exclude the script name (`integration.php`) instead; a bare script name is still matched against the running script.

## Which paths are always served?
- `waf_path_is_always_served()`: `/robots.txt` and `/.well-known/` are exempt from bot policy.
- Blocking `robots.txt` undermines itself: it tells crawlers what not to crawl; without it the crawler cannot learn the rule and keeps guessing. A live log showed AhrefsBot, SemrushBot and DotBot turned away from `/robots.txt` repeatedly, missing every chance to tell them "go".
- `.well-known/` carries ACME certificate renewal. Blocking it does not fail loudly: ninety days later the certificate expires and the whole site fails with it.
- The exemption is from the BOT POLICY ONLY; the IP blacklist and rate limit still apply.
- `health.php` (uptime monitors) gets the same bot-policy and country exemption through `waf_is_health_request()`, which asks the running script, never the path: `/x/health.php/y` handed to `router.php` must not carry it. See `docs/memory/platform/maintenance-mode-health.md`.
- `connect_client.php` (the site's OAuth client document a service's sign-in server reads) the same, by `waf_is_client_metadata_request()` (#PR): its fetcher has a library's name and runs wherever the service does.

## Which endpoints skip the bot policy?
- The credential-checked ones (`integration.php`, `mcp.php`, `oauth.php`, `workspace_ical.php`, `feed.php`, `marketplace_webhook.php`), not `api.php`: `waf-bot-policy-credential-endpoints.md`.

## Which feed readers are good bots?
- Feedly, Inoreader, Feedbin, NewsBlur, Miniflux, Netvibes, The Old Reader, FeedFetcher. Each request represents a real subscribed person; blocked, the feed silently empties and nobody reports "my feed stopped".

Related: docs/memory/visitors/robots-txt-disallow-rules.md, docs/memory/waf/waf-bot-policy-credential-endpoints.md, docs/memory/waf/waf-country-restriction.md, docs/memory/api/external-api-iis-environment.md
