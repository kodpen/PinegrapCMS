---
name: arrival-date-shared-form
description: The arrival date create and edit screens share includes/forms/arrival_date_form.php: what it holds, what stays in the screens
---

## Where is the arrival date form?
- `includes/forms/arrival_date_form.php` (gate `PG_FUNCTIONS_DIR`). `add_arrival_date.php` and `edit_arrival_date.php` stay at the root as thin wrappers and `require_once` it.
- `pg_arrival_date_form_cards($values, $cutoffs_js = null, $status_rows = '')` prints the form column and the sticky status column; `pg_arrival_date_form_layout($cards, $buttons)` wraps them in the row with the sticky save bar; `pg_arrival_date_form_status_row($label, $value, $last)` is one Summary row (edit passes Last Modified, `arrival_dates.timestamp` and `user`). `$values` is the raw stored row (`array()` on create: enabled, not default, not custom); escaping and the date order are inside, and every unset date shows empty (`pg_arrival_date_form_date_text()`: `0000-00-00` was `0/0/0000`); the list uses it too and writes "Chosen by the customer" for a custom record's arrival date (#5555). `$cutoffs_js` (edit) fills `shipping_cutoffs` and draws its rows on load.
- `…_shipping_method_options_js()` builds the `shipping_method_id_options` lines.

## How is the screen laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11, PR #6704): form column with Preview Options (name, code, description, sort order), Dates (start, end, arrival date, the customer-entered switch with its latest allowed date) and Shipping Cut-offs (the whole card hidden for a custom date).
- Status column: Availability (Enabled, Selected by Default) and a Summary (name, arrival date, "As soon as possible" or "Chosen by the customer", start, end, cut-off count) that follows the fields; the count follows the table rows through a `MutationObserver`. Edit's Delete is `formnovalidate`.
- Start and End Date are `required`; before, an empty submit reached the error screen (#6728). Arrival Date may stay blank: as soon as possible (`commerce/arrival-date-blank-as-soon-as-possible.md`). A new record's start date is today.
- The sort order box is `maxlength="6"` with inputmask min -32768 / max 32767, the smallint range the server takes; at 3 digits a stored 1000 showed as 100 and was saved back as 100 (#6727).
- `show_or_hide_custom()` (`backend.src.js`) toggles `custom_maximum_arrival_date_row`, `shipping_cutoff_heading_row` and `shipping_cutoff_row` through `style.display`, so they keep an inline display, not `d-none`.

## What does a save share?
- `pg_arrival_date_form_errors($post)` (#5554), run by both screens before any write (edit: before its cut-off DELETE, not for Delete); a refusal ends on the error screen with Go back; `pg_arrival_date_form_error_text($errors)` ends every message in one full stop (they ran together, #6729).
- Required: the name, a real start and end date (`pg_date_input_iso()`), the end not before the start, an arrival date blank or real (with `custom`, the latest allowed date too), a sort order that is a whole smallint. Before, an unreadable date was stored as `0000-00-00` and a zero start kept the record off the checkout.
- `pg_arrival_date_form_status($post)` ('enabled' / 'disabled'), `…_values($post, $user_id)`: the twelve column values in INSERT/UPDATE order (a list posted for a field saves `''`, a blank date `0000-00-00`).
- `…_save_cutoffs($id, $post)` replaces the stored cut-offs with the posted rows that pass and returns whether one was left out; the screen then shows the notice on the edit screen (`arrival-date-cutoff-rows.md`).

## What stays in the screens?
- Create: the INSERT and the edit-screen notice after left-out cut-offs.
- Edit: the not-found guard, the record and cut-off load, delete, the UPDATE.
- The list's quick edit posts through the same check and save (`list-table-quick-edit-arrival-dates.md`).
- Tests: `tests/arrival_date_form_test.php`; `db_q_shipping_import_test.php` and `db_q_zones_currencies_jobs_test.php` read the screens and the form.
