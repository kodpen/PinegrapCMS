---
name: list-table-quick-edit-arrival-dates
description: All Shipping Arrival Dates list quick edit (includes/fn/arrival_date_list_edit.php): the fields, the arrival date screen's check and save behind each change, the cut-off guard on the dates
module: arrival_date_list_edit
---

## Which cells change?
- Every list value `edit_arrival_date.php` changes (`pg_arrival_date_quick_edit_columns()`): Name (at most 50, the screen's box), Arrival Date, Status (Enabled/Disabled), Start Date, End Date. Text: Last Modified. `tests/arrival_date_list_edit_test.php` fails for a new column that is neither.
- Dates are typed in the site's date format, as the screen shows them (`pg_arrival_date_form_date_text()`).
- The Arrival Date of a record the customer chooses (`custom`) stays text, "Chosen by the customer"; the write refuses it (`pg_arrival_date_quick_cell_editable()`).

## How is a change checked and written?
- The list builds what the screen would post from the stored record plus the one change (`pg_arrival_date_quick_post()`): the switches post 1 or nothing, the code, description, sort order, default and the custom dates go back as stored.
- `pg_arrival_date_form_errors()` checks it like a save from the screen (name, real dates, end not before start); the first message is the refusal. `pg_store_arrival_date_save()` with `pg_arrival_date_form_values()` writes it; a log line as on the screen.
- The cut-offs are not posted: a new start or end date that would leave a stored shipping cut-off outside the dates is refused (`pg_arrival_date_quick_cutoff_refusal()`), where the screen would drop that cut-off with a notice the list cannot show.

## Who may change them, and what does the answer redraw?
- The screen's rights (`role < 3` or `manage_ecommerce`); `view_arrival_dates.php` answers `pg_list_edit_request()` before `validate_ecommerce_access()`, so a refusal is JSON.
- Every cell of the row read again (`pg_arrival_date_quick_saved()`): the name's colour and "Inactive" follow the status and the dates.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/arrival-date-shared-form.md
