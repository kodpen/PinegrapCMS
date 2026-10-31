---
name: site-log-language
description: Plan: the Site Log writers still passing bare English to log_activity() (issue #3332, after part 4)
---

## Where do parts 1 to 4 leave it?
- #3642: `pg_log_actor()`, `pg_log_lang()`, 39 files. #3661: `get_file.php`, the `lang()` loader (`core/lang-loader.md`).
- #3716: no writer passes `lang('UNKNOWN')` (the test scans every file; `view_waf_log.php` only reads it); checkout, payment callback, order completion refusals, Stripe, PayPal, PayTR, auto-refund, `retrieve_order.php`, `shipping_method.php`, `express_order.php`.
- #3738: iyzico lines (the void reason goes to iyzico in English, the log translates it), the checkout's card gateway errors (one log line per error shown), the subscription engine, `includes/fn/mail.php`, `mail_queue.php`: `pg_log_lang()`. Checked, `lang()` in panel, API or job requests: `includes/workspace/*`, `includes/explorer/handle_*`, `includes/settings/*` and others listed in the test.
- `tests/site_log_language_test.php` lists the files moved over; add each file the next part moves.

## What did parts 5 to 7 do?
- #3818: `email_a_friend.php` (the limit is the WAF rate scope `eaf`). #3800: `custom_form.php`, `logout()`, the quote guest signature. #3826: the checkout auto-invoice path and the order completion lines, so a checkout logs in one language.
- Part 7 done #3916: `includes/fn/layouts.php` (the last bare literal; panel, so `lang()`); `pg_cart_quote_apply()` (the visitor's `cart_quote.php`; the other cart quote lines are panel or API, `lang()`); `pg_log_lang()` in `mfa.php`, `passkey.php`, `account_security.php`, `device_limit.php`, `signin.php`, `reserve_calendar_event.php`, `order_return.php`, `email_preferences.php`, `add_or_remove_watcher.php`, `erp_accountant_download.php`, `index.php` (page sign-in forms post there), `delete_order.php` (`order_history_delete_order.php`), `pg_chat_close()` (the site widget calls it), `includes/fn/captcha.php`, `includes/fn/mfa.php`, `passkeys.php`, `signin.php`, `signature.php` (a form signature's stamp), and in `auth.php` only `pg_device_limit_gate()`, `validate_login()`, `initialize_user()`, `check_banned_ip_addresses()`.
- Checked in part 7, no change: `includes/api/**` (API requests, jobs, `marketplace_settings.php`; the test scans the folder), `pg_chat_delete()` and `pg_return_create_label()` (panel only), the `validate_*_access()` gates (panel), the Paraşüt connect line (`includes/settings/accounting.save.php` 394, `parasut_connect.php` 83: `parasut_connect_report()` builds it with `lang()` in a panel request). `includes/migrations/*` has no writer (`2026.11.0.php` 908 is a comment).

## What is left?
- `add_comment.php` (after #3901), then the close-out.

## What was left as it is?
- `assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php` and `.../advimage/add_file.php`: `tools/check_lang.php` skips `assets/lib`, so a `lang()` key there would never be checked against the shards; they stay bare English.
