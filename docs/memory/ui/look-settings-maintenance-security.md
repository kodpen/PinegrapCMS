---
name: look-settings-maintenance-security
description: Site Settings Maintenance, Firewall, Security and Features in look A (S5b of panel-old-screens): quiet small buttons, group labels, the auto-bans table, what stayed
---

## Which files moved?
- `includes/settings/maintenance.php`, `firewall.php`, `security.php`, `features.php` (step S5b of the plan panel-old-screens). Their `*.save.php` handlers are unchanged: no field, name or save changed.
- `tools/check_look.php` counts 0 old buttons in all four; their baseline entries are gone.

## What changed on them?
- Every `btn btn-sm btn-outline theme-*` (card header links such as Setup Instructions, Backup Manager, Firewall Log, Policy Reports, Sessions, Open it; the test, connect and rebuild buttons in rows) is `pg-button pg-button-quiet pg-button-sm`; icons inside lost `me-1`, the button's gap spaces them. Names, values, `form` attributes and confirmations stay.
- Number inputs in an adorned field lost the inline `text-align: right` for the class `text-end`.
- Help lines lost `text-end` (the cron jobs' Recommended Schedule lines): help runs from the field's left edge (`look-sheet-silent.md`).
- Group headings: the cron jobs drawer's job names (`h5`) are `h4.pg-group-label`, Firewall's "Always let in" (`h6.fg-secondary`) is `h3.pg-group-label`, Security's "Form Submission Limits" (`div.form-label.fw-bold`) is `div.pg-group-label`. Their ids stay; `tests/settings_card_names_test.php` and `settings_group_names_test.php` read them.
- Firewall's Active Automatic Bans table is `table table-hover mb-0 pg-embedded-table` (`look-embedded-table.md`). Its Release button, drawn in `includes/settings/prep.php`, is a quiet small button since S5c.
- The Allowed and Banned IP badges are `pg-pill pg-tone-teal` and `pg-pill pg-tone-rose`.
- Plain `small` text outside alerts (the log file path, the fail2ban snippet, the sign-in buttons line, Apple's steps) is `pg-text-meta`; the Apple private key box lost `small` and takes the 13 px settings field.

## What stayed as it was?
- Alerts keep `alert … small`: no recipe draws an alert. The popovers' arrows keep their inline position (scripts and Bootstrap place them), as in S3.
- The fail2ban `<pre>` keeps `white-space: pre-wrap` inline: no utility class gives it.
- Icon tiles stay on the rows SH9 gave them (Firewall, Unknown Bots, Device, the three sign-in providers); no new tile was added, as the other cards mix rows or would need icons nobody has chosen.
- Card titles were already the settings recipe (`pg-title-section` on the header or its span).
