---
name: waf-block-page-wording
description: The firewall's block page names the kind of refusal (ban with its end, too many requests, country, request content, automated client), never the rule or the matched text; the texts the owner approved, the shield page in English
---

## What does the block page say?
- `waf_deny()` takes the kind from `waf_deny_kind($status, waf_last_rule(), waf_last_category())` and the title and sentence from `waf_deny_texts($kind, $end)`: `ban` (rule `ip-list`) "Access Paused" with "…blocked by the site firewall until {time}…", `rate` (429) "Too Many Requests", `country` "Not Available in Your Region", `content` (sqli, xss, lfi, rce, protocol) "Request Blocked" asking to reword, `bot` (bot, tool) "Automated Access Blocked". The Reference line stays under each.
- `other-429` (rule `concurrent`, the open-at-once cap) and `other` (a wrong key and the rest) keep the texts from before.
- Owner (2026-11-13, agent channel message 2060, recorded on #9125): the five texts approved as written in English and Turkish; a ban without an end drops the time; the shield page stays English-only.

## Where does a ban's end come from?
- `waf_ban_end_for($ip, waf_ip_block_expiries($monitor))`: the latest end among the entries covering the address, 0 (no time shown) when one of them has none. Printed as `date('Y-m-d H:i T')`, the site's clock, like `pg_ban_shield_page()`.

## What about the request that earned a ban?
- `waf_register_offence()` logs `auto-ban` (category `ban`) after the refusal it counts, so `waf_last_category()` is `ban` when `waf_deny()` runs. Then `waf_deny()` names the ban when one now covers the address (lists read again), otherwise what refused it: `waf_refusal_category()`, the last category logged other than `ban` (a staff address spared a ban).

## Why is the shield page English?
- `pg_ban_shield_page()` (`includes/db_guard.php`) answers before the database and `lang()`; it carries the ban text in English, title "Access Paused".

Related: docs/memory/waf/waf-ban-shield-mirror.md, docs/memory/waf/waf-kill-switch-and-monitor-mode.md
