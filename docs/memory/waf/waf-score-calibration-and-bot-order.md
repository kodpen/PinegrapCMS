---
name: waf-score-calibration-and-bot-order
description: WAF signature score bands (thresholds 15/10/6, dead zone 7-9), targets key, and the fixed bot classification order with token-boundary residual signal.
---

## How are signature rules scored?
- Thresholds: `low` = 15, `medium` = 10 (default), `high` = 6.
- RULE: a rule that is conclusive alone gets 10 (blocks alone). An ambiguous one gets 4-6 (needs confirmation). 7-9 is the DEAD ZONE: it neither blocks nor can be safely ignored; do not put rules there. (`rce-ssti` = 7 is a deliberate exception: Pinegrap does not render Twig/Jinja, so it is a probe for discovery.)
- The `'targets'` key narrows a rule to targets (`ARGS` / `BODY` / `COOKIE` / `REQUEST_URI` / `REFERER` / `USER_AGENT`). `xss-jsuri` is exempt from BODY: "javascript:" in a product review is legitimate, in a redirect parameter it is not.

## What is the bot classification order?
- DO NOT CHANGE: `attack tools -> bad bots -> good bots -> generic -> operator list -> residual signal`.
- The old filter checked the allow list FIRST, so adding "googlebot" to a UA bypassed blocking completely. The allow list is now last.
- `waf_good_bots()` tokens and `waf_bad_bots()` / `waf_attack_tools()` tokens must NOT overlap (a test verifies this).
- The residual signal regex is token-bounded (`(?:^|[^a-z0-9])bot(?:[^a-z0-9]|$)`): a plain `bot` substring blocked CUBOT phones.

Related: docs/memory/waf/waf-ai-bots-ranges.md
