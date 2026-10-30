---
name: site-log-language
description: Plan: the Site Log writers still passing bare English to log_activity() (issue #3332, after part 4)
---

## Where do parts 1 to 3 leave it?
- #3642: `pg_log_actor()`, `pg_log_lang()`, 39 files. #3661: `get_file.php`, the `lang()` loader (`core/lang-loader.md`) and the first `lang('UNKNOWN')` users.
- #3716 (3a, 3b, part of 3c): no writer passes `lang('UNKNOWN')` any more (the test scans every file; `view_waf_log.php` only reads it). `submit_order.php` (recurring profile and PayPal Express lines), `includes/payments/callback.php` (`pg_payment_notify_line()` builds each line from the service's line, the order and the result), `includes/fn/order_completion.php` (refusal reasons), the Stripe, PayPal and PayTR driver lines, the auto-refund lines (`includes/fn/payments.php`), `retrieve_order.php`, `shipping_method.php`, `express_order.php`.
- `tests/site_log_language_test.php` lists the files moved over; add each file the next part moves.

## What did part 4 (#3738) do?
- `includes/payments/iyzico.php`: every technical line (3DS and Pay with iyzico start, return, fraud, amount mismatch, `pg_payment_iyzico_void()`, renewal) through `pg_log_lang()`. The void reason still goes to iyzico in English; the log translates it (each reason is a shard key).
- `submit_order.php` card gateways: `$payment_gateway_error` holds the parts; the visitor sees `lang()`, the log gets `pg_log_lang()` (test counts one log line per error shown).
- Subscriptions: the `'log'` callbacks only ever get lines built in `subscriptions_account.php` / `subscription_renewal.php`; those, the run summaries and the mail failure are `pg_log_lang()`. Drivers' renewal `log` lines are not written by the engine.
- `includes/fn/mail.php` (undelivered e-mail, often in a visitor's request) and `includes/fn/mail_queue.php` (give-up line): `pg_log_lang()`.
- Checked, already `lang()` in panel, API or job requests (no change, listed in the test): `includes/workspace/changes.php`, `ai.php`, `api_resources.php` (all 52 callers pass `lang()`), `includes/explorer/handle_*`, `includes/panel/shared_component.php`, `includes/fn/designer.php`, `import_key_codes.php`, `delete_users.php`, `delete_styles.php`, `includes/settings/*`, `mail_queue.php`, `includes/fn/privacy.php` (no log lines).
- `includes/fn/returns.php`: `pg_return_refund()` runs only from `view_return.php` (panel), so `lang()` is right there.

## What is left?
- `includes/fn/layouts.php` (136): bare literal. Held by draft #3141; start after it lands.
- `email_a_friend.php` 158: bare English on purpose: the spam limit at 84 counts rows `LIKE '%e-mailed a link%'`. A count that does not read the log text needs a marker column or table (a schema change, owner's decision); left as it is.
- `includes/settings/commerce.save.php` 693 logs `parasut_connect_report()`'s line: part of the owner's ERP work, not checked.
- Not checked, held by other work: `custom_form.php`, `includes/fn/custom_form.php`, `includes/fn/forms.php`, `includes/migrations/*`, `includes/erp/*`, `parasut_connect.php`, `add_erp_quote.php`, `add_erp_receipt.php`, and the files of draft #3298 (`includes/api/*`, `includes/fn/cart_quotes.php`, `add_cart_quote.php`, `view_cart_quotes.php`).

## What was left as it is?
- `assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php` and `.../advimage/add_file.php`: `tools/check_lang.php` skips `assets/lib`, so a `lang()` key there would never be checked against the shards; they stay bare English.
