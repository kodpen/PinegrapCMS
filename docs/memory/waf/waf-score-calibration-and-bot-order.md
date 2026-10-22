---
name: waf-score-calibration-and-bot-order
description: WAF signature score bands (thresholds 15/10/6, dead zone 7-9), targets key, and the fixed bot classification order with token-boundary residual signal.
---

## How are signature rules scored?
- Thresholds: `low` = 15, `medium` = 10 (default), `high` = 6.
- RULE: a rule that is conclusive alone gets 10 (blocks alone). An ambiguous one gets 4-6 (needs confirmation). 7-9 is the DEAD ZONE: it neither blocks nor can be safely ignored; do not put rules there. (`rce-ssti` = 7 is a deliberate exception: Pinegrap does not render Twig/Jinja, so it is a probe for discovery.)
- The `'targets'` key narrows a rule to targets (`ARGS` / `BODY` / `COOKIE` / `REQUEST_URI` / `REFERER` / `USER_AGENT`). `xss-jsuri` is exempt from BODY: "javascript:" in a product review is legitimate, in a redirect parameter it is not.

## What is the bot classification order?
- DO NOT CHANGE. `waf_classify_bot()`, first match wins: attack tools -> the operator's Allowed Bots (`allowed_bots`) -> bad bots -> good bots (`waf_good_bots()`, with the `waf_ai_bots()` tokens whose switch is on) -> generic clients -> the operator's Blocked Bots (`waf_blocked_agents`) -> residual signal.
- Attack tools first: the old filter checked its allow list first, so adding "googlebot" to a scanner's UA bypassed blocking completely. Allowed Bots can let in a crawler the file classes as bad or generic, never an attack tool.
- Bad before good: a UA naming both AhrefsBot and Googlebot is lying about Googlebot. Blocked Bots after the built-in lists: a site keyword never overrides a payment gateway or a search engine.
- `waf_handle_bot()` acts on the class: an attack tool is refused on every endpoint (switch `waf_block_attack_tools`, on by default); the credential-checked endpoints (`integration.php`, `mcp.php`, `oauth.php`, `workspace_ical.php`, `feed.php`, not `api.php`) skip the rest of the bot policy (docs/memory/waf/waf-path-exemption-and-robots.md).
- `waf_good_bots()` tokens and `waf_bad_bots()` / `waf_attack_tools()` tokens must NOT overlap, as substrings either way. No test in `tests/` checks it; check by hand when adding a token.
- The residual signal regex is token-bounded (`(?:^|[^a-z0-9])(?:bot|crawler|spider|scraper|fetcher)(?:[^a-z0-9]|$)`): a plain `bot` substring blocked CUBOT phones.

Related: docs/memory/waf/waf-ai-bots-ranges.md
