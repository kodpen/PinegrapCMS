---
name: waf-false-positives-and-reference
description: view_waf_log.php likely false positive evidence and Allow/Remove actions, and the stored waf_reference() number shown on block pages.
---

## How does `view_waf_log.php` flag likely false positives?
- It supplies the data for the Monitor -> Block decision: which flagged addresses are real people. Four pieces of evidence, cheap to costly: `waf_log.user_id > 0`; `auth_tokens` (remember me; resolved address); a named user in `log` (`log_user` not in `{'', 'UNKNOWN', lang('UNKNOWN')}`); a completed order (`orders.ip_address` via INET_ATON, IPv4 only).
- `log` and `orders` are not indexed by address, so only the last 20,000 rows (PK range) are checked. Both store `REMOTE_ADDR`, so behind a CDN they stay silent (never mismatch).
- Works with the period filter, not with category/search.
- "Izin Ver" (Allow) writes the same row as `firewall.save.php` (`allow` / `manual`), deletes auto bans covering the address and refreshes the shield; the allowed address drops from the list.
- Remove and Allow refresh the shield IMMEDIATELY (`waf_ip_lists(true)` + `waf_ban_shield_refresh(true)`), not left to the mtime throttle, and forget the address's offences (`waf_offence_clear()`, `waf-offence-fold-and-staff-address.md`).

## How is the reference number handled?
- `waf_reference()` is `static` per request, so the code on the block page equals the code in the log row. Before, it was generated at deny time and stored nowhere: the visitor got a code the operator could not look up.
- Column `waf_log.reference`; searchable in the log screen.

Related: docs/memory/waf/waf-ban-shield-mirror.md
