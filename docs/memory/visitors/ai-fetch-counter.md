---
name: ai-fetch-counter
description: AI fetch counter: ai_fetch_daily (day|bot|page_id), written at request end by waf.php for AI user fetchers and search indexers the firewall let through; unverified claims as a count only.
---

## What is counted?
- A request whose user agent is one of `waf_ai_bots()` (ChatGPT-User, Claude-User, Perplexity-User, OAI-SearchBot, Claude-SearchBot), decided by `waf_ai_fetch_bot()` in `waf_handle_bot()` right after `waf_verify_bot()`.
- Verdict `verified` counts under the catalogue name (`waf_ai_bots()[token]['name']`); `spoofed`, `unknown`, or verification switched off (`waf_verify_bots` 0) counts under `unverified` (#5040 question 2).
- Training crawlers (GPTBot, ClaudeBot...), search engines and other bots are never counted.
- Nothing is counted while the firewall is off, for an excluded path, an allow-listed address, or the API/MCP/OAuth endpoints (`waf_handle_bot()` returns before the good-bot branch there).

## When is the row written, and when not?
- At request end (`register_shutdown_function('waf_ai_fetch_flush')`), so the page is known: one `INSERT ... ON DUPLICATE KEY UPDATE fetches = fetches + 1` on `id = sha1(day|bot|page_id)`, `day` from PHP `date('Y-m-d')`.
- `waf_deny()` cancels the count (`waf_ai_fetch_state('cancel')`) when it really refuses: a blocked request (forged claim in Block mode, unknown-bot block, rate limit, country) writes no row. In Monitor mode the request passes and is counted.
- Before the 2026.11.0 step 11.0.150 the table is missing and the failed insert is ignored.

## Where does the page id come from?
- `get_page.php` calls `waf_ai_fetch_page($page_id)` beside `pg_rendered_page()`; a page-cache hit calls it in `pg_page_cache_send()` from the stored copy's meta `page_id` (kept by `pg_page_cache_pack()` since this step; an older copy reads 0). First page named wins.
- Anything else (a file, robots.txt, a feed) is page 0. An `unverified` row is always page 0 (`waf_ai_fetch_page_for()`): a forged user agent writes no page list.

## Which rules hold for the table?
- Derived data (`visitor-rollup-consistency.md`): a wrong bucket is truncated, never fixed in place; read with `SUM(fetches)`.
- Retention: 400 days, swept by the general job (`ai-visibility-figures.md`).
- Tests: `tests/ai_visibility_fetch_counter_test.php`. PR: #5398.
