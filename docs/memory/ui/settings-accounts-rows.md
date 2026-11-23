---
name: settings-accounts-rows
description: Site Settings › Accounts and sign-in as folded rows: order, each row's line, Sign-in attempts and Deleting many at once on rows of their own, providers as connect rows, anchors
module: security_settings_fold
---

## How is the pane laid out?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`) in this order: Passwords and sessions, Sign-in attempts, Two-step verification, Devices, Sign in with Google, Microsoft and Apple, Forms and spam, Registration and membership, Audit trail, Deleting many at once, Encryption key. Order, names, icons and lines: `includes/fn/security_settings_fold.php` (`pg_security_fold_order()`, `pg_security_fold_face()`, `pg_security_fold_<part>()`, pure but for `lang()`).
- Sign-in attempts is drawn once the throttle's columns exist, Audit trail once its column does; a row not drawn is skipped.
- No popover and no `collapse-switcher` is left: the throttle's numbers, Max devices and the expiration warning's Subject, Page and Days before are always drawn under their switch. Every switch is `pg-switch`; labels are sentence case and none starts with "Enable" or "Allow".

## What moved, and why?
- The throttle (`login_throttle*`) left Session & Password for "Sign-in attempts" (`pgset-throttle`); its fields were drawn by `prep.php`, now by `security.php`. Allow Mass Deletion (`mass_deletion`) left it for "Deleting many at once" (`pgset-deletion`), switch "Delete everything a filter matches": a deletion gate sat beside passwords (plan S7).
- The three providers are connect rows (Design examples › Connect row) inside the Sign-in row: pill "Ready" or "Not set up", the provider's sentence, its switch, "Set up in Connections" quiet. Microsoft's row keeps the xms_edov line.
- Forms and spam ends with a line linking the chat's own puzzle (Communication › Chat on the site).

## What does each line say?
- Sign-in attempts: "10 failures in 15 minutes lock out for 60 minutes" or "Failed sign-ins are not limited"; pill On/Off. Its shared-address line links "the allowed list" to `#settings/firewall/pgset-iplists`, as `pg_login_throttle_address_subject()` skips allowed addresses.
- The form limits' help names no allowed list: `custom_form.php` counts allowed addresses too, so no link was added there.
- Sign-in: "Shown: Google, Apple" or "No sign-in button shown", then "waiting for setup: …"; a provider waiting opens the row. Two-step verification and Encryption key open their row when there is no key.

## What stayed, and which links lead here?
- Card ids, field names, `security.save.php` and the one Save are unchanged. Anchors `login_throttle`, `login_throttle_attempts` open Sign-in attempts, `mass_deletion` opens Deleting many at once (`pg_settings_legacy_anchors()`).
- Search: "throttle" finds Sign-in attempts, "passkey" Two-step verification, "bulk delete" Deleting many at once (`registry.php` keywords). Test: `tests/settings_security_fold_test.php`.

Related: docs/memory/ui/settings-security-cards.md, docs/memory/ui/settings-firewall.md, docs/memory/waf/waf-login-throttle.md
