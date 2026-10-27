---
name: waf-legit-traffic-auto-ban-findings-writes
description: Review findings of 2026-10-26 for plans/waf-legit-traffic-auto-ban.md step 3: the ban note without a rule, log lines without a path, the shield's bare answer, the mirror line outliving the ban
---

## Where do these come from?
- The same sweep as `waf-legit-traffic-auto-ban-findings-counting.md`; line numbers as of #2011's head.

## What a ban writes (step 3)?
- The ban note is the fixed `Automatic: 5+ firewall events` (waf.php ~4931) and the `ban` log row names no rule, so the Active Automatic Bans table cannot say why. `waf_last_rule()` still holds the rule there: write it into the note and the row.
- No `data/firewall.log` line carries the request path; the BAN line and four callers (`custom_form.php`, `stock_notify.php`, `consent.php`, `wishlist.php`) lack `status=`; the shield line has no reference.
- The shield answers text `Forbidden` with no reference and the drained rows carry `reference ''` (step 3 as planned).
- The mirror's `B` line outlives the ban on a quiet site: only a request that has a database and is not banned refreshes it (never cron, `waf_run()` returns on CLI), and `B` lines are read for 6 hours, so the operator alone on the site waits out a 60-minute ban for up to 6 hours and deleting the row changes nothing (the "had to delete the `B` line" of #1989). `B <ip> <expires>` with expired lines skipped in `pg_ban_shield_list()`, plus a refresh from the general job, closes it.
