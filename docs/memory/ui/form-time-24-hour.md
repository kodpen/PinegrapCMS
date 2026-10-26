---
name: form-time-24-hour
description: Time and date-and-time form values: validate_time() and prepare_form_data_for_input() read 12-hour and 24-hour time (PR #1698), why, what stays as before
---

## Which time formats does a form value accept?
- 12-hour with AM/PM (`5:00 PM`, seconds optional), as the panel and site forms print it, read exactly as before.
- 24-hour `H:MM` or `HH:MM`, seconds optional, white space around it allowed (`17:00`, `00:15:00`): what `<input type="time">` posts. Hour 0-23, minute and second 0-59, two-digit minute; other text around it makes it invalid.
- Helpers in `includes/fn/forms.php`: `pg_time_24_hour_parts()` (hour, minute, second or null), `pg_form_input_time_parts()` (the parts `prepare_form_data_for_input()` converts).

## Why was this added?
- B9c of `plans/bootstrap-6-components.md` puts `<input type="time">` on time fields. Before PR #1698 its `17:00` failed `validate_time()` and `prepare_form_data_for_input()` stored it as `00:00`.

## What did not change?
- Stored values, `prepare_form_data_for_output()` (still prints 12-hour), and the 12-hour reading, including its quirks: a 12-hour time with seconds (`5:00:30 PM`) is converted as `12:30` by `prepare_form_data_for_input()`; a value that is no time (a search term) still gives `00:00`.
- `validate_date_and_time()` splits at the first space, so `03/03/2016 17:00` is valid now too.
