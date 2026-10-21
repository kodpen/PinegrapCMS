---
name: waf-ai-bots-ranges
description: AI bot policy: training crawlers vs user fetchers, waf_ai_bots() verification by published IP ranges (waf_bot_ranges), verdict ladder, refresh path (2026.4.4).
---

## How are AI bots split?
- Two kinds, never mixed. Training crawlers (GPTBot, ClaudeBot, CCBot, PerplexityBot) stay in `waf_bad_bots()`. User fetchers (ChatGPT-User, Claude-User, Perplexity-User) and AI search indexers (OAI-SearchBot, Claude-SearchBot) are allowed via `waf_ai_bots()`, verified by the operator's published IP list (table `waf_bot_ranges`), not rDNS.
- Anthropic publishes its three bots in ONE list (`anthropic-bots`): the UA token proves intent, the IP proves origin. A `claudebot` UA is still blocked even if its IP is in the list.

## What are the verification rules?
- `waf_ai_bots()` tokens are ALWAYS verified, even with switches off and even when the operator typed the token into "Allowed Bots" by hand. `waf_verify_bot()`'s ranges branch comes BEFORE the `waf_good_bots()` lookup. A manual allow for these tokens is "verified allow", not "trust without verification".
- Fail-open ladder differs from rDNS: IP not matching a FRESH list (<=30 days) = `spoofed` (the list is complete by operator declaration); not matching a stale list = `unknown`; matching the list = always `verified`; no list = `unknown`.
- An AI token with verdict `unknown` does NOT get good-bot privilege: `waf_handle_bot()` turns it into class `unverified` and applies the unknown-bot policy, so while the list is not yet fetched (fresh upgrade, failed fetch) a fake "ChatGPT-User" UA cannot get in on text alone; behaviour equals pre-4.6. For rDNS bots (`googlebot` etc.) `unknown` stays fail-open; this rule is only for `waf_ai_bots()` tokens.

## Where does fetching happen?
- There is NO HTTP in waf.php. Fetch only in `pg_waf_refresh_ai_ranges()` (functions.php): settings button (force), dashboard WAF widget (6-hour attempt stamp), `waf_ranges_job.php` (cron catalog, daily).
- A failed or empty fetch KEEPS the old rows: overwriting a good list would turn every real AI bot into an impostor.

## Adding a new provider
- `waf_ai_bots()` + `pg_waf_ai_range_sources()` + the token overlap test (two-way substring against the bad/attack lists).
- `pg_ai_crawler_catalog()` too: robots.txt names the same bots per switch (docs/memory/visitors/ai-crawlers-llms-txt.md); `tests/seo_ai_crawlers_test.php` fails until it does.

Related: docs/memory/waf/waf-score-calibration-and-bot-order.md
