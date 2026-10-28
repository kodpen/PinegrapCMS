---
name: arrival-date-shared-form
description: The arrival date create and edit screens share includes/forms/arrival_date_form.php: what it holds, what stays in the screens
---

## Where is the arrival date form?
- `includes/forms/arrival_date_form.php` (gate `PG_FUNCTIONS_DIR`). `add_arrival_date.php` and `edit_arrival_date.php` stay at the root as thin wrappers and `require_once` it.
- `pg_arrival_date_form_cards($values, $cutoffs_js = null)` prints the preview, availability and options cards with the shipping cut-off table. `$values` is the raw stored row (`array()` on create: enabled, not default, not custom); escaping and the date order are inside, and an unset latest allowed date (`0/0/0000`) shows empty. `$cutoffs_js` (edit) fills `shipping_cutoffs` and draws its rows on load.
- `…_shipping_method_options_js()` builds the `shipping_method_id_options` lines.

## What does a save share?
- `pg_arrival_date_form_status($post)` ('enabled' / 'disabled'), `…_values($post, $user_id)`: the twelve column values in INSERT/UPDATE order (a list posted for a field saves `''`).
- `…_save_cutoffs($id, $post)` inserts the posted cut-offs (a method once, a valid time between the start and end dates) and returns whether one was left out; the screen then shows the notice on the edit screen.

## What stays in the screens?
- Create: the INSERT and the edit-screen notice after left-out cut-offs.
- Edit: the not-found guard, the record and cut-off load, delete, the UPDATE.
- The edit screen had one `</div>` too many in the custom field block, which closed the card's row before the cut-off rows; the shared markup nests them as the create screen did.
- Tests: `tests/arrival_date_form_test.php`; `db_q_shipping_import_test.php` and `db_q_zones_currencies_jobs_test.php` read the screens and the form.
