---
name: waf-fail2ban-log
description: data/firewall.log fail2ban line format, which code paths write it, ip= is always the visitor address, rotation; add a line for every new deny path.
---

## What does `data/firewall.log` contain?
- Format: `YYYY-MM-DD HH:MM:SS pinegrap-firewall DENY|BAN ip=<address> k=v...`, local time.
- `ip=` is ALWAYS the visitor address, never a range (so `<HOST>` matches); a /64 ban goes in `subject=`.
- Writers: `waf_deny()` (rule via `waf_last_rule()`; `waf_log_event()` remembers the last rule on each call), auto-ban (`waf_register_offence`), login lock, and the shield (`pg_ban_shield_text_log`, a twin copy; reads its key from the mirror's `L` line).
- Copy-rotated every 5 MB.
- RULE: when adding a new deny path, add the log line too.

Related: docs/memory/waf/waf-ban-shield-mirror.md
