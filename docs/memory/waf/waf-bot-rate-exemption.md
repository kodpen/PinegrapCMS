---
name: waf-bot-rate-exemption
description: Only a crawler waf_bot_verified() proved skips the global, sensitive, api.php and in-flight limits (waf_bot_rate_exempt()); IS_BOT buys no rate relief (#2731).
---

## Which bots skip the rate limits?
- Only a crawler whose address proved its name: `waf_verify_bot()` answered `verified` and `waf_handle_bot()` set `waf_bot_verified(true)`. The four handlers `waf_handle_rate_global()`, `waf_handle_rate_sensitive()`, `waf_handle_rate_api()` and `waf_handle_inflight()` ask `waf_bot_rate_exempt(waf_bot_verified())`, a pure helper (`=== true`), tested in `tests/waf_bot_rate_test.php`.
- curl, an empty user agent, any `bot` token and a good-bot name that could not be verified (no PTR, WhatsApp, Stripe, a forged Googlebot) count like a browser.
- With `waf_verify_bots` off nothing is ever verified, so no bot is exempt.

## What did it fix?
- The handlers returned on `IS_BOT`, which `waf_handle_bot()` also defines for unverifiable names and, with `block_unknown_bots` off (the default), for curl, an empty UA or a `bot` token. 36 POSTs a minute to `index.php` drew six 429s for Firefox and none for curl or a fake Googlebot (security finding 4, decided by the owner 2026-10-28, PR #2731).

## Is IS_BOT still used?
- Yes, unchanged and defined in the same three places of `waf_handle_bot()`: it feeds the consent notice, the language redirect, the page cache, `pg_request_is_automated()` (`includes/fn/seo.php`) and visitor tracking. Do not test a handler by defining it: `tools/test.php` runs every test in one process and the constant would leak.
- The help text under Settings > Firewall > Rate Limiting says only crawlers whose address proves their name are exempt.

Related: docs/memory/waf/waf-ai-bots-ranges.md, docs/memory/waf/waf-bot-policy-credential-endpoints.md, docs/memory/waf/waf-inflight-request-cap.md
