---
name: form-date-whole-value
description: validate_date() takes a site-format date only when the whole value is one; prepare_form_data_for_input() trims a date before splitting it
module: forms
---

## What does validate_date() accept?
- ISO `YYYY-MM-DD`, or the site's day and month order (`DATE_FORMAT`) with `-`, `,`, `/` or `.` and a four-digit year, as the whole value; white space around it is allowed. A non-scalar value is not a date.
- Text before or after the date makes it invalid: `5/5/20271`, `15/5/2027x`, `5/5/2027 5:05 PM` (a date and time goes through `validate_date_and_time()`, which splits the time off first).
- The dotted `13.11.2026` (the Turkish way, read so by `pg_datetime.js` and `pg_date_input_iso()` too) is a date, and `validate_date_and_time()` takes it before the time. `prepare_form_data_for_input()` splits a date and a date and time on `.` as well, so it is stored as `2026-11-13`. Before, a site form refused it with "Please enter a valid date" while only `13/11/2026` passed (bug #9025).

## Why?
- The pattern was not anchored, so a date found inside longer text passed: `5/5/20271` was valid and `prepare_form_data_for_input()` stored the year `20271` (custom form and submitted form date fields, cart and express order dates, ERP screens). The gift card screen had worked around it with `pg_date_input_iso()` (#5530). Fixed in #6044.

## What else changed?
- `prepare_form_data_for_input($data, 'date')` trims the value before splitting it, so ` 5/5/2027 ` is stored as `2027-05-05`, not with spaces in the year and day.
- Tests: `test_forms_validate_date_whole_value` and `test_forms_dotted_date` in `tests/forms_test.php`.
