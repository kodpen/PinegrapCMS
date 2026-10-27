---
name: waf-ban-notice
description: The operator's e-mail per automatic ban (queued by waf.php in data/temp/, sent after waf_run init), one notice per rule burst, ban rows linked to the filtered firewall log (#2167)
module: waf_notice
---

## Why does it exist?
- #1989: nothing told anyone of an automatic ban (no e-mail, no log entry, a dashboard count), a run of false-positive bans by one rule raised no alarm, and the operator retyped a banned address into the log's search box.

## How does a ban reach the operator?
- `waf_register_offence()` calls `waf_ban_notice_queue()` after a ban that was written (`waf_auto_ban()` > 0). waf.php has no mail and no `functions.php`, so it appends `when \t ip \t subject \t rule \t minutes` (`waf_ban_notice_line()`, read by `waf_ban_notice_parse()`) to `data/temp/waf_ban_notice.log`, capped at 64 KB.
- `pg_waf_ban_notice_send()` (`includes/fn/waf_notice.php`) runs in `init.php` right after `waf_run('init')`, so on the next request and on every tick of the general job (which loads `init.php`). One `is_file()` when nothing is queued. It claims the file by renaming it, so a line is sent once.
- Mail goes through `email()` with `queue => true`, to the operations alert recipients (`pg_ops_alert_recipients()`: `ops_alert_recipients` or the site address), only while `OPS_ALERTS` and `OPS_ALERT_EMAIL` are on (`pg_waf_ban_notice_enabled()`); otherwise the queued lines are dropped. No new setting.
- The ban e-mail names the banned subject, the refused address when it differs (an IPv6 /64), the rule, the minutes, the last five `waf_log` events of the address in the 15 minutes before the ban (`pg_waf_ban_notice_samples()`), and a link on `HOSTNAME_SETTING` to the log filtered by the address. Every value through `h()`, `lang()` vars included.

## When is it one notice instead of many?
- `pg_waf_ban_notice_plan()`: the first ban of a rule within an hour is mailed alone; a second address banned by the same rule within the hour sends one notice naming every address the rule banned that hour (log link searched by the rule, `range=1`), then nothing more for that rule until an hour after the notice. Bans drained together join the run's burst notice.
- State per rule (ban times and subjects of the last hour, at most 100, and the burst time) in `data/temp/waf_ban_notice_state.json`, read and written under one `flock()`. A cache: losing it costs at most one more e-mail.

## Where is a ban linked to its log?
- `pg_waf_ban_log_url()`: `view_waf_log.php?range=7&search=…`; an address is searched as is, a range by the part its prefix keeps (`pg_waf_ban_log_search()`: `2001:db8:1:2::/64` → `2001:db8:1:2:`, `/24` → three octets and a dot). Used by the Automatic Temporary Bans badges in Settings › Firewall (`includes/settings/prep.php`) and the Active Automatic Bans table of `view_waf_log.php`.

## How is it tested?
- `tests/waf_ban_notice_test.php`: line round trip and rejects, the plan (alone, burst across runs and in one run, pruning, caps), the log search and both mail bodies' escaping.

Related: docs/memory/waf/waf-fail2ban-log.md, docs/memory/platform/ops-alerts.md
