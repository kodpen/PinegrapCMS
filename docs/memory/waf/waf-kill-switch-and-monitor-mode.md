---
name: waf-kill-switch-and-monitor-mode
description: waf_enabled=0 stops everything (absolute kill switch), monitor mode guarantee in waf_deny(), and the IP ban list applying in every mode (2026.3.1).
---

## What does the kill switch (`waf_enabled = 0`) do?
- `waf_run()` returns UNCONDITIONALLY: no rule runs, including the bot filter, and no rule logs. Only the facts handled before the mode check still happen: the outage recovery row (`pg_db_guard_log_recovery()`), the mirror refresh and the shield drain (below).
- History: an earlier version kept `block_unknown_bots` working on its own switch ("the bot filter is older than the firewall"). It caused a production outage: turning the firewall off did not stop blocking, so an operator hunting false positives had no way to stop it. A switch that turns nothing off is worse than no switch.
- The `WAF_ENABLED` constant is defined in `init.php` from the config row, in the same block as `ECOMMERCE`, `ADS`, `VISITOR_TRACKING`; `waf_mode()` reads it first.
- `check_banned_ip_addresses()` is outside this: an older, separate feature the operator calls per form.
- `waf_ban_shield_refresh()` is called BEFORE the mode check: when off, the mirror is written without B lines, so the pre-DB shield also goes quiet within one request instead of waiting out the 6-hour staleness. That is why refresh was taken out of `waf_sweep()` (the sweep never runs in off mode, yet the mirror must be written in exactly that request).

## Does the IP ban list apply in monitor mode?
- Yes, in EVERY mode: the IP-list branch of `waf_run()` blocks with `waf_deny(403, ref, true)`. `$always` keeps the monitor latch at one point and is the only gate that bypasses it.
- Reason: monitor mode means "do not start rejecting people on your own decision, I will watch", not "release the addresses I banned". The list is the operator's DATA (typed by hand or seen on screen and left); an address that earned its place does not become acceptable because the mode changed.
- Two real uses: manually banning an address while monitoring, and switching to monitoring during an attack without opening the door to already-banned addresses.
- The kill switch is still absolute (above).

## How is monitor mode guaranteed?
- The first line of the `waf_deny()` body is the `waf_mode() !== 'block'` check. Callers all look at `$blocking` already, but "all" breaks exactly when a new branch is added. The monitor-mode promise is kept in one place.

## What do the panel texts say about the bot filter?
- Settings › Firewall › Bot Filtering says it works only while the firewall is enabled and refuses only in Block mode (Monitor mode records). It used to say "works on its own, whether or not the firewall is enabled", left over from the stand-alone version above.
- System Status, Unknown Bot Filtering: off = danger, full deduction; Monitor mode with Block Unknown Bots on = warning "only being recorded, not blocked", half deduction (it used to show a green "being blocked"); Block mode on = success; option off = warning, half deduction. Pinned by `tests/firewall_bot_filter_texts_test.php`.

Related: docs/memory/waf/waf-ban-shield-mirror.md
