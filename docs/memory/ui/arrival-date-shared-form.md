---
name: arrival-date-shared-form
description: The arrival date create and edit screens share includes/forms/arrival_date_form.php: what it holds, what stays in the screens
---

## Where is the arrival date form?
- `includes/forms/arrival_date_form.php` (gate `PG_FUNCTIONS_DIR`). `add_arrival_date.php` and `edit_arrival_date.php` stay at the root as thin wrappers and `require_once` it.
- `pg_arrival_date_form_cards($values, $cutoffs_js = null, $status_rows = '')` prints the form column and the sticky status column; `pg_arrival_date_form_layout($cards, $buttons)` wraps them in the row with the sticky save bar; `pg_arrival_date_form_status_row($label, $value, $last)` is one Summary row (edit passes Last Modified, `arrival_dates.timestamp` and `user`). `$values` is the raw stored row (`array()` on create: enabled, not default, not custom); escaping and the date order are inside, and every unset date shows empty (`pg_arrival_date_form_date_text()`: `0000-00-00` was `0/0/0000`); the list uses it too and writes "Chosen by the customer" for a custom record's arrival date (#5555). `$cutoffs_js` (edit) fills `shipping_cutoffs` and draws its rows on load.
- `…_shipping_method_options_js()` builds the `shipping_method_id_options` lines.

## How is the screen laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11, PR #6704): form column with Preview Options (name, code, description, sort order), Dates (start, end, arrival date, the customer-entered switch with its latest allowed date) and Shipping Cut-offs (the whole card hidden for a custom date); status column with Availability (Enabled, Selected by Default) and a Summary (name, arrival date or "Chosen by the customer", start, end, cut-off count) that follows the fields; the count follows the table rows through a `MutationObserver`. Edit's Delete is `formnovalidate`.
- `show_or_hide_custom()` (`backend.src.js`) toggles `custom_maximum_arrival_date_row`, `shipping_cutoff_heading_row` and `shipping_cutoff_row` through `style.display`, so they keep an inline display, not `d-none`.

## What does a save share?
- `pg_arrival_date_form_errors($post)` (#5554), run by both screens before any write (edit: before its cut-off DELETE, not for Delete); a refusal ends on the error screen with Go back. Required: the name, a real start and end date (`pg_date_input_iso()`), the end not before the start, a real arrival date unless `custom` (then the latest allowed date is blank or real), a sort order that is a whole smallint. Before, an unreadable date was stored as `0000-00-00` and a zero start kept the record off the checkout.
- `pg_arrival_date_form_status($post)` ('enabled' / 'disabled'), `…_values($post, $user_id)`: the twelve column values in INSERT/UPDATE order (a list posted for a field saves `''`).
- `…_save_cutoffs($id, $post)` inserts the posted cut-offs (a method once, a valid time between the start and end dates) and returns whether one was left out; the screen then shows the notice on the edit screen.

## What stays in the screens?
- Create: the INSERT and the edit-screen notice after left-out cut-offs.
- Edit: the not-found guard, the record and cut-off load, delete, the UPDATE.
- The edit screen had one `</div>` too many in the custom field block, which closed the card's row before the cut-off rows; the shared markup nests them as the create screen did.
- Tests: `tests/arrival_date_form_test.php`; `db_q_shipping_import_test.php` and `db_q_zones_currencies_jobs_test.php` read the screens and the form.
