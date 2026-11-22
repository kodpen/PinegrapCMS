---
name: waf-ai-training-crawlers
description: SEO's "Allow AI training" in the firewall: on, the training crawlers leave the bad-bot match only (class training, no good-bot privilege); off, refused (#11023, poll 216)
---

## What does "Allow AI training" do in the firewall?
- On (`config.robots_ai_training`, read by `waf_ai_training_allowed()`), `waf_classify_bot()` skips the tokens of `waf_ai_training_bots()` (GPTBot, ClaudeBot, CCBot, meta-externalagent, Amazonbot, Bytespider) in the built-in bad-bot match only and gives them class `training`.
- `waf_handle_bot()` lets that class through as a visitor: IS_BOT is set (no visitor count), it is never verified, so rate limits, bans, IP lists, the country restriction and the signature scan apply to it.
- Off, or before the column exists: refused as bad bots, as before (#11023).
- No new field: the firewall reads the SEO switch; the Bots row's state line adds "AI training crawlers let in" and its card says which way they go, with "Change under AI Crawlers".

## What is still refused while it is on?
- A second bad name in the same user agent, an attack tool naming GPTBot, and a training crawler the operator typed into Blocked Bots. Allowed Bots still makes one `good`, as before.
- The class sits after Blocked Bots and before the residual signal: meta-externalagent's user agent ends on "/crawler)", which the residual signal would refuse.

## Who decided it?
- Owner (2026-11-21): poll 216, `workflow/decisions-made-product-97.md`. User fetchers and AI search keep their own switches and range check (`waf-ai-bots-ranges.md`).

## How is the list kept?
- `waf_ai_training_bots()` repeats the crawling names of `pg_ai_crawler_catalog()['train']` (waf.php cannot load functions.php); Google-Extended and Applebot-Extended crawl nothing. `tests/waf_ai_training_test.php` holds the two together.

Related: docs/memory/waf/waf-ai-bots-ranges.md, docs/memory/waf/waf-score-calibration-and-bot-order.md, docs/memory/visitors/ai-crawlers-llms-txt.md
