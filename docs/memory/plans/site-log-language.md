---
name: site-log-language
description: Plan: the Site Log writers still passing bare English to log_activity() (issue #3332, part 3)
---

## Where do parts 1 and 2 leave it?
- #3642: `pg_log_actor()`, `pg_log_lang()`, 39 files. #3661: `get_file.php` (with the `lang()` loader, `core/lang-loader.md`), `includes/panel/software.php`, `includes/fn/update.php`, `add_field.php`, `view_order.php`, the job and shipping messages built in the same file, and `lang('UNKNOWN')` users in `index.php`, `captcha.php`, `passkeys.php`, `api/resources/account.php`. `tests/site_log_language_test.php` lists the files moved over; add each file this part moves.

## Step 3a: files held by open PRs during part 2
- `registration_entrance.php` (124, 126) and `includes/fn/auth.php` (3603, 3611, 4388): pass `lang('UNKNOWN')` as the user; pass `'UNKNOWN'` instead. Held by #3660 (SMS consent).
- `includes/fn/layouts.php` (136): bare literal. Held by draft #3141.
- Line numbers are from `development` at the time; re-scan before starting.

## Step 3b: messages built in bare English in the writer's own file
- `submit_order.php`: `$payment_gateway_error_message` (the recurring profile messages, about 10 assignments near 4376-4747, logged at 2112-2950 and 4762) and `$paypal_express_checkout_error_message` (3191-3199, logged at 3203). Visitor requests: `pg_log_lang()`.
- `includes/payments/callback.php`: `$line` is built in pieces (208-226, 313) on top of the provider's own log line, and 374 rewrites a finished line with `str_replace()` on its English text: change that to building the line from its parts before translating.

## Step 3c: messages built elsewhere (not checked)
- About 90 calls pass a variable whose text comes from another function or file (`$message`, `$what`, `$line`, `$created`, `$report` ...) in `mailchimp.php`, `edit_pages.php`, `edit_files.php`, `add_file.php`, `retrieve_order.php`, `optimize_image.php`, `includes/workspace/*`, `includes/fn/returns.php`, `includes/fn/mail.php`, `includes/fn/ecommerce.php` and others. Check where each is built; list the bare ones per file.

## What was left as it is?
- `assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php` and `.../advimage/add_file.php`: `tools/check_lang.php` skips `assets/lib`, so a `lang()` key there would never be checked against the shards; they stay bare English.
