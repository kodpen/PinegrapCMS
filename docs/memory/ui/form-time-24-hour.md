---
name: form-time-24-hour
description: Form time values: validate_time() and prepare_form_data_for_input() read 12- and 24-hour time (PR #1698); prepare_form_data_for_output() and the time help follow the site clock
---

## Which time formats does a form value accept?
- 12-hour with AM/PM (`5:00 PM`, seconds optional), as the panel and site forms print it, read exactly as before.
- 24-hour `H:MM` or `HH:MM`, seconds optional, white space around it allowed (`17:00`, `00:15:00`): what `<input type="time">` posts. Hour 0-23, minute and second 0-59, two-digit minute; other text around it makes it invalid.
- Helpers in `includes/fn/forms.php`: `pg_time_24_hour_parts()` (hour, minute, second or null), `pg_form_input_time_parts()` (the parts `prepare_form_data_for_input()` converts).

## Why was this added?
- B9c of `plans/bootstrap-6-components.md` puts `<input type="time">` on time fields. Before PR #1698 its `17:00` failed `validate_time()` and `prepare_form_data_for_input()` stored it as `00:00`.

## How is a time printed?
- `prepare_form_data_for_output()` prints a `time` value, and the time of a `date and time` value without an explicit format, on the site's clock through `pg_form_output_time()`: `17:05` on a 24-hour site, `5:05 PM` otherwise (as before). Until the 24-hour fix it always printed 12-hour, so mail queue, audit log, campaign, ERP screens and documents, order custom field answers and site receipts showed AM/PM on 24-hour sites. Both forms read back here, and `pg_fields.js` `parseTime()` takes either into the native control.
- The help under a time field follows the clock too: `pg_time_format_help()` (`HH:mm` / `h:mm AM/PM`, `includes/fn/core.php`), and the `(Format: HH:mm)` and campaign schedule texts on panel and site forms; the submitted form editor's `Format: h:mm AM/PM` is still fixed (test: `tests/time_format_output_test.php`).

## What did not change?
- Stored values and the 12-hour reading, including its quirks: a 12-hour time with seconds (`5:00:30 PM`) is converted as `12:30` by `prepare_form_data_for_input()`; a value that is no time (a search term) still gives `00:00`.
- `validate_date_and_time()` splits at the first space, so `03/03/2016 17:00` is valid now too.
