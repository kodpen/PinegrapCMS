---
name: form-date-whole-value
description: validate_date() takes a site-format date only when the whole value is one; prepare_form_data_for_input() trims a date before splitting it
module: forms
---

## What does validate_date() accept?
- ISO `YYYY-MM-DD`, or the site's day and month order (`DATE_FORMAT`) with `-`, `,` or `/` and a four-digit year, as the whole value; white space around it is allowed. A non-scalar value is not a date.
- Text before or after the date makes it invalid: `5/5/20271`, `15/5/2027x`, `5/5/2027 5:05 PM` (a date and time goes through `validate_date_and_time()`, which splits the time off first).
- The dotted `31.12.2026` is still refused here; `pg_date_input_iso()` reads it for the screens that use it.

## Why?
- The pattern was not anchored, so a date found inside longer text passed: `5/5/20271` was valid and `prepare_form_data_for_input()` stored the year `20271` (custom form and submitted form date fields, cart and express order dates, ERP screens). The gift card screen had worked around it with `pg_date_input_iso()` (#5530). Fixed in the forms bug hunt (PR in `plans/bug-hunt-modules-fn-1a.md`).

## What else changed?
- `prepare_form_data_for_input($data, 'date')` trims the value before splitting it, so ` 5/5/2027 ` is stored as `2027-05-05`, not with spaces in the year and day.
- Tests: `test_forms_validate_date_whole_value` in `tests/forms_test.php`.
