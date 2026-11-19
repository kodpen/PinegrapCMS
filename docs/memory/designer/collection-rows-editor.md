---
name: collection-rows-editor
description: The rows editor of a collection's Record rows field on Add and Edit Submitted Form: the table, the one JSON field rows_<id>, check before save, write after, Open the row and the Row of link back
---

## What does the record screen draw for a rows field?
- `pg_submitted_form_fields_markup()` hands a text box of kind `rows` to `pg_coll_rows_field_markup()` (`includes/fn/collection_rows.php`). The writer and spec: `collection-rows-writer.md`.
- A box `[data-pg-rows]` holding `pg_coll_rows_editor_config()` as JSON (columns, rows, refusals, limit) and `pg_coll_rows_editor_words()`; `assets/js/pg_collection_rows.js` (served as written, with the vendored SortableJS tag) draws a plain table: one column per field the table takes, the grip (drag, or arrow keys on it), Move up, Move down, Remove, "Add row" (off at the limit).
- Look A (`ui/look-new-parts.md`): the row actions are `pg-icon-button pg-icon-button-sm` (an end of the list adds `opacity-50` to its disabled Move up or Move down), "Add row" is `pg-button pg-button-quiet pg-button-sm`.
- A field that cannot draw (`pg_coll_rows_spec()` refused) shows its reason and posts nothing, so its rows stay.
- Inputs: text box and text area as text, pick list as a select (several when multiple), check box group as boxes.
- Date and time are the panel's one date control, as the record's own field (`ui/panel-date-field-opt-in.md`): a text box with `data-pg-date` / `data-pg-time` from the column's `hook`, `pgFields.init()` on every draw; the screen prints the picker options and `pg_fields_script()` (`pg_coll_rows_picker_kinds()`).
- Stored dates and times show through `prepare_form_data_for_output()`, so a row holds the same value whichever screen wrote it.

## How does the list travel?
- Only the hidden `rows_<field id>` is named: the script writes the whole list as JSON on every change, so PHP's `max_input_vars` never cuts a long list (R9). `pg_coll_rows_posted()` reads it; an empty or broken value means "not sent" and the rows are left as they are.
- The rows field's own `form_data` value is never posted by the screen: Edit skips it (not in the post), Add writes it empty and the writer's set replaces it.

## When are the rows checked and written?
- Before anything is saved: `pg_coll_rows_screen_check()` (Add after `pg_submitted_form_create_check()`, Edit after its field checks) runs `pg_coll_rows_check()` and marks `rows_<id>`; the screen comes back with the posted list and each refused cell or row marked.
- After the record is written: `pg_coll_rows_screen_write()`; on Edit before `pg_record_revision_after()`, so the record's History holds the list as saved. A write that fails midway leaves a warning on the next screen.
- Required row fields count only when the record is saved complete (Save for later / Incomplete ask none).
- Office use only rows fields follow the screen's own rule (Add: `$office_use_only_access`; Edit: the save loop's rule, form editor included).

## How is a field the table does not take filled?
- An existing row gets "Open the row" (its Edit Submitted Form in a new tab) when the child has fields the table does not take (a file, rich text, a radio group); below the table "Filled on the row's own screen:" names them.
- The row's own screen shows "Row of" in its Summary card with a link back: `pg_coll_rows_parent_of()` finds the record whose rows field holds the code (`pg_coll_reference_holds_sql()`), `pg_coll_rows_parent_row()` draws the line.

The same editor on a designed site form: `collection-rows-site-form.md`.

Tests: `tests/collections_rows_test.php` (posted list, config, one named field, the parent line).
