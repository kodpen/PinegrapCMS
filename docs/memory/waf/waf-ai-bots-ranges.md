---
name: waf-ai-bots-ranges
description: AI bot policy: training crawlers vs user fetchers, waf_ai_bots() verification by published IP ranges (waf_bot_ranges), verdict ladder, refresh path (2026.4.4).
---

## How are AI bots split?
- Two kinds, never mixed. Training crawlers (GPTBot, ClaudeBot, CCBot, PerplexityBot) stay in `waf_bad_bots()`. User fetchers (ChatGPT-User, Claude-User, Perplexity-User) and AI search indexers (OAI-SearchBot, Claude-SearchBot) are allowed via `waf_ai_bots()`, verified by the operator's published IP list (table `waf_bot_ranges`), not rDNS.
- Anthropic publishes its three bots in ONE list (`anthropic-bots`): the UA token proves intent, the IP proves origin. A `claudebot` UA is still blocked even if its IP is in the list.

## What are the verification rules?
- `waf_ai_bots()` tokens are ALWAYS verified, even with switches off and even when the operator typed the token into "Allowed Bots" by hand. `waf_verify_bot()`'s ranges branch comes BEFORE the `waf_good_bots()` lookup. A manual allow for these tokens is "verified allow", not "trust without verification".
- Fail-open ladder differs from rDNS: a fixed-range match (below) = `verified`; IP not matching a FRESH list (<=30 days) = `spoofed` (the list is complete by operator declaration); not matching a stale list = `unknown`; matching the list = always `verified`; no list = `unknown`.
- An AI token with verdict `unknown` does NOT get good-bot privilege: `waf_handle_bot()` turns it into class `unverified` and applies the unknown-bot policy, so while the list is not yet fetched (fresh upgrade, failed fetch) a fake "ChatGPT-User" UA cannot get in on text alone; behaviour equals pre-4.6. For rDNS bots (`googlebot` etc.) `unknown` stays fail-open; this rule is only for `waf_ai_bots()` tokens.

## Which addresses prove Claude-User besides the list?
- Anthropic's API egress (`160.79.104.0/21`, `153.61.208.0/20`, platform.claude.com/docs/en/api/ip-addresses): the fetch tool of claude.ai, cloud Claude Code and the API leaves from there, not from `crawling/bots.json`. `waf_ai_fixed_ranges()` holds them in code (no JSON to fetch); `waf_ranges_verdict()` checks them first, with no 30-day ageing. Claude-User only: Claude-SearchBot from there is still `spoofed` (#6725).
- A `spoofed` claim on robots.txt, `.well-known/`, health.php or the OAuth client metadata (`waf_request_skips_bot_policy()`) is served with no offence (#6725).

## Which Claude-User claim is never range-verified?
- One naming `claude-code/` (Claude Code on a person's computer, from their own address): `waf_handle_bot()` skips `waf_verify_bot()` via `waf_bot_claim_unverifiable()`, so it is never `spoofed` and registers no offence. With `waf_allow_ai_fetchers` on it passes unverified (counted as `unverified`, no `waf_bot_verified()`); with the token only hand-typed it gets the unknown-bot policy (#6693, `workflow/decisions-made-waf.md`).

## Where does fetching happen?
- There is NO HTTP in waf.php. Fetch only in `pg_waf_refresh_ai_ranges()` (`includes/fn/cron.php`, loaded through functions.php): the "Update Bot IP Lists" button on SEO › AI Crawlers (force, `seo.save.php`), dashboard WAF widget (6-hour attempt stamp), `waf_ranges_job.php` (cron catalog, daily).
- A failed or empty fetch KEEPS the old rows: overwriting a good list would turn every real AI bot into an impostor.

## How is a new provider added?
- `waf_ai_bots()` + `pg_waf_ai_range_sources()`, then check the new token by hand against the bad/attack lists, both ways as substrings (no test in `tests/` does it).
- `pg_ai_crawler_catalog()` too: robots.txt names the same bots per switch (docs/memory/visitors/ai-crawlers-llms-txt.md); `tests/seo_ai_crawlers_test.php` fails until it does.

Related: docs/memory/waf/waf-score-calibration-and-bot-order.md
