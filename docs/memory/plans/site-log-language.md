---
name: site-log-language
description: Plan: the Site Log writers still passing bare English to log_activity() (issue #3332, part 4)
---

## Where do parts 1 to 3 leave it?
- #3642: `pg_log_actor()`, `pg_log_lang()`, 39 files. #3661: `get_file.php`, the `lang()` loader (`core/lang-loader.md`) and the first `lang('UNKNOWN')` users.
- #3716 (3a, 3b, part of 3c): no writer passes `lang('UNKNOWN')` any more (the test scans every file; `view_waf_log.php` only reads it). `submit_order.php` (recurring profile and PayPal Express lines), `includes/payments/callback.php` (`pg_payment_notify_line()` builds each line from the service's line, the order and the result), `includes/fn/order_completion.php` (refusal reasons), the Stripe, PayPal and PayTR driver lines, the auto-refund lines (`includes/fn/payments.php`), `retrieve_order.php`, `shipping_method.php`, `express_order.php`.
- `tests/site_log_language_test.php` lists the files moved over; add each file the next part moves.

## Step 3a remainder
- `includes/fn/layouts.php` (136): bare literal. Held by draft #3141; start after it lands.

## Step 3c remainder, per file
- `includes/payments/iyzico.php`: the technical lines (`Iyzipay 3DS init error: status=…`, `Iyzipay payment error: …`, fraud, mismatch, `pg_payment_iyzico_void()` lines, renewal error) are bare English; the four `$log[] = …` sentences already use `pg_log_lang()`.
- `submit_order.php` 2102-2951: the gateway errors use `lang()`, which also shows them to the visitor; the log gets the visitor's language. Split into a visitor text (`lang()`) and a log text (`pg_log_lang()`) like the PayPal Express block.
- `email_a_friend.php` 158: bare English on purpose: the spam limit at 84 counts rows `LIKE '%e-mailed a link%'`. Needs a counter that does not read the log text before it can be translated.
- `includes/fn/subscriptions_account.php`, `includes/fn/subscription_renewal.php`: the `'log'` callbacks write what the subscription engine passes; trace those messages.
- Not checked yet: `includes/workspace/*` (`changes.php` `$what`, `ai.php` `$what`, `api_resources.php`), `includes/explorer/handle_*`, `includes/panel/shared_component.php`, `includes/fn/designer.php` `$draft_log`, `parasut_connect.php`, `import_key_codes.php`, `delete_users.php`, `delete_styles.php`, `includes/fn/returns.php` (`pg_return_refund_unclear_lines()` uses `lang()`, visitor or panel).
- Checked, already translated: `shipping.php`, `mailchimp.php`, `import_email_campaign_profiles.php`, `add_file.php`, `add_design_file.php`, `edit_pages.php`, `edit_files.php`, `edit_products.php`, `optimize_image.php`, `job.php`, `workspace_settings.php`, `includes/fn/prototype_links.php`, `includes/workspace/scheduled_convert.php`, `includes/fn/captcha.php`, `email_campaign_job.php`, `includes/fn/ecommerce.php` (cancel lines come from `pg_payment_cancel_outcome()`).
- Skipped, held by other work when part 3 ran: `includes/fn/mail.php`, `includes/fn/mail_queue.php`, `mail_queue.php`, `custom_form.php`, `includes/fn/custom_form.php`, `includes/fn/forms.php`, `includes/fn/privacy.php`, `includes/migrations/*`, `includes/erp/*`, `add_erp_quote.php`, `add_erp_receipt.php`, `includes/settings/*`, and the files of drafts #3141 and #3298 (`includes/api/*`, `includes/fn/cart_quotes.php`, `add_cart_quote.php`, `view_cart_quotes.php`, `clean_up.php`, `includes/fn/output.php`, `includes/panel/actions.php`, `content.php`, `designer.php`, `page_designer.php`, `toolbar.php`).

## What was left as it is?
- `assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php` and `.../advimage/add_file.php`: `tools/check_lang.php` skips `assets/lib`, so a `lang()` key there would never be checked against the shards; they stay bare English.
