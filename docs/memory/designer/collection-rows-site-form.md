---
name: collection-rows-site-form
description: The rows editor on a designed site form: Bootstrap 5.3.8 look, the list checked before the record is written and written after it, the 256 KB bound
module: collection_rows
---

## What does a site form draw for a rows field?
- `pg_cf_apply_field_bindings()` replaces the control of a rows field with a `custom_html` node from `pg_coll_rows_site_editor()`; the designed label goes, since the editor prints its own (`span.form-label`, `pg_tr_label()`).
- An office-use-only rows field goes for a visitor and draws for staff, as other office-use-only fields. A field whose child cannot be read (`pg_coll_rows_spec()` refused) draws nothing, label and empty wrapper gone.
- `pg_cf_field_map()` carries `page_id` for the spec's own-form check; a caller holding the spec may pass it as `rows_spec` on the row (the tests do).

## What does the editor look like there?
- The same script as the panel, `assets/js/pg_collection_rows.js`: the config says `look: 'site'` (`pg_coll_rows_site_config()`), and the script takes `SITE_LOOK` (marked `/* @pg-site-output */`): `btn btn-sm btn-outline-secondary` row actions, `btn-outline-primary` Add row, `form-select`, `form-check` boxes, `text-body-secondary`.
- A date or time is the browser's own `type="date"` / `type="time"` input (ISO, accepted by `validate_date()`); the panel keeps its picker (`nativeDates: false`).
- No row opens on a record screen from a site, so neither "Open the row" nor "Filled on the row's own screen:" shows.
- Markup: `pg_coll_rows_site_markup()`, a `mb-3` block, the box, one hidden `rows_<field id>` and the two script tags with `defer`.

## How does custom_form.php check and write the rows?
- Before the field checks: `pg_coll_rows_site_check()` blanks each rows field's own posted value (the writer sets it, so a visitor never names another record's rows), refuses a list over `pg_coll_rows_posted_max_bytes()` (256 KB) before decoding it, asks one row of a required field when submitted complete (`pg_coll_rows_site_refusal()`), and runs `pg_coll_rows_check()` against an empty stored value: every code posted is refused, only new rows are made.
- It returns the rows field ids, which the field loop leaves out of its own required check.
- A field "Shown when" hid has its list dropped, so nothing is written for it.
- After the record's values are stored, before signatures: `pg_coll_rows_screen_write()`, as on Add Submitted Form. Save for later writes the rows with no required check (R7). A failed write leaves the record and logs "the rows of submitted form (code) could not be saved".
- A refused submission comes back with the posted list redrawn and, when `rows_<id>` carries the error, each refused cell marked.

Panel editor: `collection-rows-editor.md`; writer: `collection-rows-writer.md`. Tests: `tests/collections_rows_test.php`.
