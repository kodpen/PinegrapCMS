---
name: settings-firewall-https-logs
description: Site Settings › Firewall and HTTPS, the HTTPS row (Secure Mode first, the headers, the policy) and the Logs row (both logs, fail2ban files under a details): lines, fields, anchors
module: firewall_settings_fold
---

## What does the HTTPS row say and hold?
- Row `pgset-headers`, first of the pane, so the pane arrives with it open when no row asks for attention: Secure Mode is the pane's first field.
- Line from `pg_firewall_fold_https()`: "Secure Mode on · security headers sent · HSTS · policy reported only", or shorter ("Secure Mode off · no security headers"). HSTS is named only with Secure Mode on, as `waf.php` sends it only then; pill On or Off by Secure Mode.
- Holds Secure Mode (`pg-switch`) with its trusted-proxy offer and its warning box; then, once the 2026.4.4 columns exist (`$security_ready`), always drawn: Send security headers, Refuse framing by other sites, Always HTTPS (HSTS, disabled while Secure Mode is off), Content security policy as `pg-seg` (Off, Report only, Enforce, one line each), Policy, and the quiet "Policy reports" (`view_waf_log.php`).

## What does the Logs row say and hold?
- Row `pgset-waf-logs`. Line from `pg_firewall_fold_logs()`: "Kept 14 days, at most 20,000 rows · plain-text block log written" (or "no plain-text block log"; nothing about it before its column exists). No pill.
- Holds Log Retention, Log Size Limit, the quiet "Open the firewall log", then the Plain-text block log switch and, under the `details` "Log file and fail2ban", the log file's path and the fail2ban filter and jail to paste.
- Why: the firewall log's fields sat in the Firewall card and the text log in the headers' popover inside HTTPS, so the two logs were in two places (plan S2).

## What stayed and what leads here?
- Field names, `firewall.save.php` and its refusals (Log Retention 1 to 3650, Log Size Limit 500 to 5,000,000) are unchanged; the "Firewall Log" button left the Firewall row's head for "Open the firewall log".
- Old anchors `waf_log_retention_days`, `waf_log_max_rows`, `waf_text_log` land on Logs (`pg_settings_legacy_anchors()`); `pgsub-headers` on HTTPS. Privacy's "How long we keep data" opens Logs for the firewall log (`pg_privacy_retention_overview()`).
- Search: "HSTS", "secure mode", "csp" find HTTPS; "fail2ban", "log", "retention" find Logs (`registry.php` keywords). Test: `tests/settings_firewall_fold_test.php`.

Related: docs/memory/ui/settings-firewall.md, docs/memory/waf/waf-fail2ban-log.md
