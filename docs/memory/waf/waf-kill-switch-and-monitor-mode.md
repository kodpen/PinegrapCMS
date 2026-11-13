---
name: waf-kill-switch-and-monitor-mode
description: waf_enabled=0 stops everything (absolute kill switch), monitor mode guarantee in waf_deny(), hand-written bans apply in every mode, automatic bans not in Monitor mode.
---

## What does the kill switch (`waf_enabled = 0`) do?
- `waf_run()` returns UNCONDITIONALLY: no rule runs, including the bot filter, and no rule logs. Only the facts handled before the mode check still happen: the outage recovery row (`pg_db_guard_log_recovery()`), the mirror refresh and the shield drain (below).
- History: an earlier version kept `block_unknown_bots` working on its own switch ("the bot filter is older than the firewall"). It caused a production outage: turning the firewall off did not stop blocking, so an operator hunting false positives had no way to stop it. A switch that turns nothing off is worse than no switch.
- The `WAF_ENABLED` constant is defined in `init.php` from the config row, in the same block as `ECOMMERCE`, `ADS`, `VISITOR_TRACKING`; `waf_mode()` reads it first.
- `check_banned_ip_addresses()` is outside this: an older, separate feature the operator calls per form.
- `waf_ban_shield_refresh()` is called BEFORE the mode check: when off, the mirror is written without B lines, so the pre-DB shield also goes quiet within one request instead of waiting out the 6-hour staleness. That is why refresh was taken out of `waf_sweep()` (the sweep never runs in off mode, yet the mirror must be written in exactly that request).

## Does the IP ban list apply in monitor mode?
- The hand-written entries (`source = 'manual'`) do, in EVERY mode: the IP-list branch of `waf_run()` blocks with `waf_deny(403, ref, true)`. `$always` keeps the monitor latch at one point and is the only gate that bypasses it. Reason: monitor mode means "do not start rejecting people on your own decision, I will watch", not "release the addresses I banned"; banning an address by hand while monitoring depends on it.
- The firewall's automatic bans (`source = 'auto'`) do NOT apply in Monitor mode (owner, 2026-11-13, `workflow/decisions-made-waf-2.md`): an operator who switched to Monitor to find what refused them stayed shut out until the ban ended. Their rows stay and apply again in Block mode.
- How: `waf_ip_lists()` splits the rows (`waf_ip_lists_from_rows()`: `block`, `block_manual`); `waf_ip_block_entries($lists, $mode)` gives Monitor the `block_manual` ones, every other mode all. `waf_ip_is_blocked()` asks it with `waf_mode()`, so `waf_run()`, `api.php`, OAuth, MCP, `check_banned_ip_addresses()` and the Settings self-ban warning agree. The mirror writes the same entries, their end from the hand-written rows only (`waf_ip_block_expiries(true)`).
- Switching the mode reaches the mirror at its next refresh (5 minutes, or the job tick). `tests/waf_monitor_auto_ban_test.php`.
- The kill switch is still absolute (above).

## How is monitor mode guaranteed?
- The first line of the `waf_deny()` body is the `waf_mode() !== 'block'` check. Callers all look at `$blocking` already, but "all" breaks exactly when a new branch is added. The monitor-mode promise is kept in one place.

## What do the panel texts say about the bot filter?
- Settings › Firewall › Bot Filtering says it works only while the firewall is enabled and refuses only in Block mode (Monitor mode records). It used to say "works on its own, whether or not the firewall is enabled", left over from the stand-alone version above.
- System Status, Unknown Bot Filtering: off = danger, full deduction; Monitor mode with Block Unknown Bots on = warning "only being recorded, not blocked", half deduction (it used to show a green "being blocked"); Block mode on = success; option off = warning, half deduction. Pinned by `tests/firewall_bot_filter_texts_test.php`.

Related: docs/memory/waf/waf-ban-shield-mirror.md
