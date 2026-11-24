---
name: settings-firewall-limits
description: Firewall and HTTPS › Limits row: Rate limiting, Open at Once, Automatic temporary bans, value rows Sign-in attempts and Form submissions with Change to Accounts and sign-in
module: firewall_settings_fold
---

## What does the Limits row hold?
- Row `pgset-limits`, third of the pane. Line from `pg_firewall_fold_limits()`: "300 requests a minute from one address · banned after 5 events", or "No rate limit · no automatic bans".
- Rate limiting (All Requests, Sign-in Forms and Checkout, Live Chat and Store Pages), Open at Once, and Automatic temporary bans (Ban After, Ban Duration, Longest Ban), every box drawn while its switch is off. They came from the Firewall card and its popovers (#11000).
- Under them, the group "Limits kept in Accounts and sign-in": one value row each for "Sign-in attempts" and "Form submissions" (Design examples › Value rows), today's value and Change (#11237).

## What do the value rows say, and where does Change lead?
- Values from `pg_settings_limits_overview($throttle, $forms)` (`includes/fn/firewall_settings_fold.php`, pure but for `lang()`): "10 failed sign-ins in 15 minutes lock the address and the account out for 60 minutes." or "Failed sign-ins are not limited."; the form limits that are not 0 joined by " · ", or "Form submissions are not limited."
- Change opens `#settings/security/pgset-throttle` and `#settings/security/pgset-captcha` (Forms and spam). Before their columns exist the row says "Waits for the database upgrade." and has no Change.
- Tone blue or violet when the limit is on, amber when off. Test: `tests/settings_limits_overview_test.php`.

## Why are the Accounts limits only shown here?
- A category writes only its own columns (`settings-card-save-rules.md`): the throttle and the form limits stay saved by `security.save.php`; Limits gathers every limit on one address for reading, and the edit stays in one place (plan S5, census K8).
- The numbers themselves: `waf/waf-login-throttle.md`, `waf/form-submission-limits.md`.

Related: docs/memory/ui/settings-firewall.md, docs/memory/ui/settings-accounts.md
