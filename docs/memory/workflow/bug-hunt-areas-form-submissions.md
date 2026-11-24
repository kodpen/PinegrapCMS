---
name: bug-hunt-areas-form-submissions
description: The bug hunt of site form submissions (area 18, 2026-11-24): the three faults fixed, what was read clean, what was not run
module: custom_form, form_mails
---

## Which area was hunted?
- Area 18 "form-submissions": the custom form submit path (`custom_form.php`, `includes/fn/custom_form.php`, `form_mails.php`, `validate_captcha_answer()` and `pg_form_spam_check()` in `includes/fn/auth.php`), the panel's submitted forms list (`includes/fn/submitted_form_list.php`, `submitted_form_list_edit.php`) and its CSV export (`view_submitted_forms.php`).

## What did the hunt fix?
- An office use only file upload field was skipped by validation but not by the placing loop: a visitor could post a file for it (a `.php` included) and have it copied into the files folder without the name, error code and size checks. `pg_cf_undrawn_upload_ids()` (`platform/custom-form-upload-placement.md`).
- A field's maximum length was held only by the browser's `maxlength`. `pg_cf_value_too_long()` (`designer/custom-form-max-length.md`).
- An option's administrator address list ending in a comma added a blank To at submit (`designer/custom-form-mails.md`).

## What was read clean?
- CSRF: `validate_token_field()` before any read but the payment service's return; the pay-retry path after it.
- Required fields: values are trimmed by liveform, so spaces alone count as empty; a check box group, a pick list and a radio take only their option values; dates, times, e-mail and patterns are checked on the server; a field "Shown when" hid is neither required nor stored.
- Uploads: `pg_upload_name_blocked()` for every inner extension, `pg_upload_file_refusal()` with `pg_upload_limits()`, an array upload counted as none, every file placed before the forms row.
- Spam: the signed issued time (too fast, expired), the honeypot (an array too), the CAPTCHA question spent once, StopForumSpam once per request and failing open, the per-address counters taken before the hook code; staff are exempt by design.
- Mail: `email()` hands one To address to PHPMailer, which refuses a typed comma list, so the sender's copy cannot be turned into a relay.
- Panel list and quick edit cells are escaped with `h()`; the CSV export runs every value through `csv_cell()`.

## What was not run?
- No database or mail here: the fixes are tried by unit tests of the pure rules and `php -l`.
