---
name: settings-firewall
description: Site Settings › Firewall and HTTPS as folded rows: the eight rows in order and their state lines, the Limits row, the bans drawer, the segmented controls, what stayed (ids, names, the save)
module: firewall_settings_fold
---

## How is the pane drawn?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`) in this order: HTTPS, Firewall, Limits, Bots, Addresses, Countries, Trusted proxies, Logs; no card stands outside them. With no row asking for attention the pane arrives with HTTPS open, so Secure Mode is its first field. Order, names, icons and lines: `includes/fn/firewall_settings_fold.php` (`pg_firewall_fold_order()`, `pg_firewall_fold_face()`, `pg_firewall_fold_<part>()`, pure but for `lang()`).
- No popover, no `div.switch` and no plain radio in the rows: every switch is `pg-switch`, with its fields drawn under it whatever its state. Mode (Monitor, Block), Restriction (Off, Refuse the listed countries, Let in only the listed countries) and Content security policy (Off, Report only, Enforce) are `pg-seg` radios inside their `fieldset`/`legend`, each choice explained in a line under the control. Test: `tests/settings_firewall_fold_test.php`.

## What does each row say and hold?
- HTTPS (`pgset-headers`) and Logs (`pgset-waf-logs`): `ui/settings-firewall-https-logs.md`.
- Firewall (`pgset-waf`): "On, Monitor", "On, Block" or "Off"; attention when the operator's own address is on the banned list, or before the firewall's upgrade has run. Holds the notices, "Use the firewall", Mode, Sensitivity, the three check switches, the AI crawlers' state line and Excluded paths.
- Limits (`pgset-limits`, new): the rate limit and the bans, and value rows for the Accounts limits (`ui/settings-firewall-limits.md`).
- Bots (`pgset-bots`): whether unknown bots are refused, and how many the operator allowed or blocked; holds Block unknown bots, Allowed bots, Blocked bots.
- Addresses (`pgset-iplists`): the automatic bans sentence ("No address is banned by the firewall now", "2 addresses banned now"), then the hand-written counts. Holds the two IP lists and the same sentence with "See the bans", which opens the drawer `waf_bans_drawer` (`pg_settings_drawer()`) with the table of active automatic bans and their Release buttons.
- Countries (`pgset-countries`): the restriction and the number of countries; attention when the operator's own request would be refused. Trusted proxies (`pgset-proxies`): how many are listed; attention when a proxy staff came through is not listed.

## What moved, and why?
- The bans table left IP Lists for the drawer, as a live list sat among settings (plan S4); the logs left the Firewall card and the headers' popover for Logs (S2); the rate limit and bans left the Firewall card for Limits (S5). #11000, #11074, #11237.

## What stayed?
- Card ids, field names and `firewall.save.php`; the Release buttons (`prep.php`) still submit the hidden `pgset_waf_release` form to `view_waf_log.php`, which returns to `settings_firewall.php#pgset-iplists`. Old anchors (`pg_settings_legacy_anchors()`) land on the cards (`waf_log_retention_days`, `waf_log_max_rows` and `waf_text_log` on Logs) and the client opens the row a card or field sits in (`pgSettingsFoldReveal()`).
- The bans count is `$waf_auto_ban_count` (`prep.php`): the table shows the latest 25, a 25th row makes it count them all.
- The search finds "rate limit" and "ban" in Limits (`registry.php` keywords), "ban" and "release" in Addresses, "HSTS" in HTTPS, "fail2ban" and "log" in Logs.

Related: docs/memory/ui/settings-firewall-https-logs.md, docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-firewall-limits.md, docs/memory/ui/settings-accounts.md, docs/memory/waf/README.md
