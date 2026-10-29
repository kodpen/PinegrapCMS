---
name: waf-fail2ban-log
description: data/firewall.log fail2ban line format (path= on every line, the rule on BAN), which code paths write it, ip= is always the visitor address, rotation; a line for every new deny path.
---

## What does `data/firewall.log` contain?
- Format: `YYYY-MM-DD HH:MM:SS pinegrap-firewall DENY|BAN ip=<address> k=v... path=<path>`, local time.
- `path=` ends every line: `waf_text_log()` appends `waf_text_log_path()` (request path without the query, one token, `-` outside a request); the shield writes the same with `pg_ban_shield_text_log_path()`. A rule alone could not tell a probe from a page full of images (#1989, #2157).
- Every DENY line carries `status=`; the rate callers in `consent.php`, `stock_notify.php`, `wishlist.php` and `shipment_lookup.php` (#2157) and the form limits' `pg_cf_throttle_log()` in `custom_form.php` (#2933) write `status=429`: it marks the rate-refusal class fail2ban filters read, though the form refusal itself answers with the form and an error.
- The BAN line's `rule=` is the rule of the refusal that crossed the threshold (`waf_last_rule()` read in `waf_register_offence()` before its own log rows), `auto-ban` only when none is known; the same rule goes into the ban note (`waf_ban_note()`: `Automatic: <rule>, 5+ firewall events`) and the end of the `ban` row's matched text.
- `ip=` is ALWAYS the visitor address, never a range (so `<HOST>` matches); a /64 ban goes in `subject=`.
- Writers: `waf_deny()` (rule via `waf_last_rule()`; `waf_log_event()` remembers the last rule on each call), auto-ban (`waf_register_offence`), login lock, and the shield (`pg_ban_shield_text_log`, a twin copy with `ref=`; reads its key from the mirror's `L` line).
- Copy-rotated every 5 MB.
- RULE: when adding a new deny path, add the log line too.

Related: docs/memory/waf/waf-ban-shield-mirror.md
