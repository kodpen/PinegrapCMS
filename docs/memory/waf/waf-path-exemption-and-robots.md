---
name: waf-path-exemption-and-robots
description: waf_is_excluded() matches the path only; waf_path_is_always_served() exempts /robots.txt and /.well-known/ from bot policy; feed readers are good bots.
---

## What does the exemption look at?
- `waf_is_excluded()` DISCARDS the query string and matches only the path. Matching the whole `REQUEST_URI` made the entire firewall skippable: anyone can put the pattern in a parameter. With `api2` exempt, `/hesabim?x=1&foo=api2&q=' OR 1=1--` hit the exemption and signature scan, rate limit and IP list were all skipped.
- Consequence: you cannot exempt a feed by query parameter (`?rss=true`), that is exactly the string an attacker would add. To exempt, exempt the path.

## Which paths are always served?
- `waf_path_is_always_served()`: `/robots.txt` and `/.well-known/` are exempt from bot policy.
- Blocking `robots.txt` undermines itself: it tells crawlers what not to crawl; without it the crawler cannot learn the rule and keeps guessing. A live log showed AhrefsBot, SemrushBot and DotBot turned away from `/robots.txt` repeatedly, missing every chance to tell them "go".
- `.well-known/` carries ACME certificate renewal. Blocking it does not fail loudly: ninety days later the certificate expires and the whole site fails with it.
- The exemption is from the BOT POLICY ONLY; the IP blacklist and rate limit still apply.

## Which feed readers are good bots?
- Feedly, Inoreader, Feedbin, NewsBlur, Miniflux, Netvibes, The Old Reader, FeedFetcher. Each request represents a real subscribed person; blocked, the feed silently empties and nobody reports "my feed stopped".

Related: docs/memory/visitors/robots-txt-disallow-rules.md
