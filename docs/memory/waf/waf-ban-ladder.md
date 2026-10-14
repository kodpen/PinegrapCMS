---
name: waf-ban-ladder
description: waf_auto_ban() escalation ladder: hit_count is the number of bans, 60-240-960-3840-10080 min, cap waf_auto_ban_max_minutes, expired rows kept for ladder memory.
---

## How does the auto-ban ladder work?
- `waf_auto_ban()` is one `INSERT ... ON DUPLICATE KEY UPDATE`. If the previous ban has EXPIRED: `hit_count + 1` and duration `base x 4^(hit_count-1)` (60 -> 240 -> 960 -> 3840 -> 10080 min); cap `waf_auto_ban_max_minutes` (default 7 days). While a ban is still running it only ensures "at least base minutes remain".
- So `hit_count` is the NUMBER OF BANS, not events: concurrent requests in one wave do not climb the ladder.
- The assignment order relies on MySQL's left-to-right evaluation; reversing it drops a step lower (shorter, never longer).
- The return value is the effective duration in minutes; the log row writes it.

## Are expired auto rows deleted at once?
- No. `waf_sweep()` keeps them for the log retention period (30 days when retention is 0); that is the ladder's memory. `waf_ip_lists()` already filters `expires_at > now`, so enforcement is unaffected.
- The Remove button deletes the row, which resets the ladder (deliberate).

Related: docs/memory/waf/waf-ipv6-ban-column-width.md
