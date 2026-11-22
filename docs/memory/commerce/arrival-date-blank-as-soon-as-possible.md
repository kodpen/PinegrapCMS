---
name: arrival-date-blank-as-soon-as-possible
description: A blank arrival date (stored 0000-00-00, the starter AT-ONCE row) means deliver as soon as possible; the arrival date screen, the list's quick edit and the API accept and keep it
---

## What does a blank arrival date mean?
- Deliver as soon as possible. It is stored `0000-00-00`; `get_shipping_methods()` (`shipping.php`) reads that, or no arrival date at all, as the default At Once, with no date to meet.
- Both starter dumps ship such a row: arrival date 1, code `AT-ONCE`, not custom, named "En kısa sürede" in the Turkish dump (`platform/starter-site-defaults.md`).

## Where is it set?
- The arrival date screen (`includes/forms/arrival_date_form.php`): the Arrival Date box is never `required`; its help line reads "Leave blank to deliver as soon as possible."; the Summary writes "As soon as possible" for a blank box and follows typing.
- All Shipping Arrival Dates: the cell reads "As soon as possible" and the quick edit lets the date be cleared (`pg_arrival_date_quick_edit_columns()`, no `required`).
- The API: `arrival_date` empty or null on create and update (`includes/api/schema.php` says so).

## How is it checked and stored?
- `pg_arrival_date_form_errors()`: a blank arrival date passes, and so does a posted `0000-00-00`; a value given must still be a real date (`pg_date_input_iso()`): an unreadable one was once stored as `0000-00-00` without a word (#5554).
- `pg_arrival_date_form_values()` writes every blank date (arrival date, latest allowed date) as `0000-00-00` itself, not `''` left to the connection's non-strict SQL mode.

## What was the fault?
- The box was `required` while the custom switch was off (#6728) and the check wanted a real date (#5554), so the stock AT-ONCE row could not be saved: the browser stopped Save, the server answered "Please enter a valid date for Arrival Date", the list's quick edit refused any change of the row and an API update answered 422 (fixed in #11053).

## Tested?
- `tests/arrival_date_form_test.php`, `tests/arrival_date_list_edit_test.php`, `tests/api_store_lookups_test.php`. In a sandbox, row 1 saved unchanged from the screen, landed on the list and kept `0000-00-00`.

Related: docs/memory/ui/arrival-date-shared-form.md, docs/memory/ui/list-table-quick-edit-arrival-dates.md, docs/memory/api/api-store-arrival-dates.md
