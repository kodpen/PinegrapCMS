---
name: site-log-language
description: Plan: the Site Log writers still passing bare English to log_activity() (issue #3332, part 2) and the language loader get_file.php needs first
---

## Where does part 1 leave it?
- #3642: `pg_log_actor()` for rows without a user, `pg_log_lang()` for front-end writers, 78 calls in 39 files wrapped (`ui/site-log-paging.md`). `tests/site_log_language_test.php` lists those files; part 2 adds each file it moves over.

## Step 2a: get_file.php
- `get_file.php` (318, 324, 823, 835, 1476, 1496; 835 is "User viewed private file." from the issue). The router dispatches it without `init.php`, so no config and no language constants are loaded: first move the language loading `lang()` depends on out of `includes/fn/core.php` into a shared loader (SOFTWARE_LANGUAGE from ENFORCEMENT / DEFAULT, as `init.php` and `router.php` both set it), call it on that path, then wrap the lines with `pg_log_lang()` (visitor requests).

## Step 2b: files held by open PRs at the time of part 1
- `includes/panel/software.php` (12 calls: 110, 236, 387, 395, 437, 450, 578, 591, 602, 614, 736, 760) and `includes/fn/update.php` (2724, 2747): held by #3632.
- `add_field.php` (77): held by #3638.
- `includes/fn/layouts.php` (136): held by #3141 (draft).
- `view_order.php` (3216, 3324): another worker was on it.
- Line numbers are from `development` at the time; re-scan before starting.

## Step 2c: what part 1 did not scan
- `assets/lib/tiny_mce_3_5_10/plugins/advlink/add_file.php` and `.../advimage/add_file.php` (3 calls each): under a vendored library path, which `check_lang.php` may skip; check before wrapping.
- About 115 calls pass a variable (`$message`, `$payment_gateway_error_message`, `$line` ...) built elsewhere; whether each is built with `lang()` was not checked.
- `index.php` and other writers pass `lang('UNKNOWN')` as the user, which stores the translated word; `pg_log_actor()` only knows the literal `UNKNOWN`. Pass `''` or `'UNKNOWN'` instead.
