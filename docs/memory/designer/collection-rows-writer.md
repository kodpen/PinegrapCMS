---
name: collection-rows-writer
description: Collection "Rows" field kind: rows as child collection records, codes in order on the parent, the spec, the writer and its order of writes, the 100-row limit
module: collection_rows
---

## What is a rows field?
- A text box of a collection form with the Kind "Rows" (`rows` in `pg_coll_kinds()`, twin `_CF_KINDS`). It names a child collection in `form_fields.reference_page_id` (`pg_coll_field_reference()` keeps it as for a reference; no schema).
- The Form Field panel's Kind offers it with a collection picker: only forms used as a collection, never this form (`_swFormOptions(cur, skip, true)`; `list_custom_forms` carries `collection`).
- A row is a record of the child collection made by the rows writer; it stands in one record's list only. The parent's value is the rows' reference codes in order, joined ", " like a reference text box (`pg_coll_rows_value()`, read back by `pg_coll_rows_codes()`). The child needs no link or order field.
- Plan and owner's decision on delete: `plans/collection-rows.md` (until its close-out).

## What can a rows field draw?
- `pg_coll_rows_spec()` (pure; `pg_coll_rows_spec_for()` reads the child with `pg_coll_rows_child()`): the child's page and the fields the table takes (text box, text area that is not rich text, pick list, check box, date, time; never a rows field: no rows inside rows), the rest in `others`.
- Refusals, each with words in `pg_coll_rows_spec_message()`: `no_form`, `own_form`, `gone` (no form on that page), `not_collection` (the switch "Use as a collection" turned off later), `no_fields`.
- A record's rows in order: `pg_coll_rows_of()`; a code that names no child record is left out.

## How is a posted list written?
- `pg_coll_rows_write($spec, $stored_value, $posted, $complete, $ops)`. Each posted row is `code` ('' for new) and `values` (field id => value).
- Refused before anything is written: a list over `pg_coll_rows_max()` (100), a code the parent does not list (a row of another record is never taken over), a code listed twice, any row failing its fields' checks (`pg_coll_rows_check_row()`: required only when the parent is complete, number, date, time, options, a reference's records). Errors name the row index and field id.
- Then in this order: new rows created, listed rows written, the parent's value set, rows no longer listed deleted. A failed create, write or set deletes the rows this save created and writes nothing more; a removed row that fails to delete is named in `left`.
- The writes are passed in; `pg_coll_rows_db_ops()` gives the real ones: a row is created complete with a value row for every child field; a written row goes through its History as an edit (`pg_record_revision_before()` / `_after()`) only when a value changed; delete is `pg_submitted_form_delete()`.

## What does the site and canvas show?
- The site form draws nothing for a rows field, for staff too: `pg_cf_apply_field_bindings()` drops the control, its label and a wrapper left empty (as an office-use-only field for a visitor). The canvas shows three numbered "Sample row" lines in muted text (`_cfRowsSample()`); the kind is labelled "Record rows" (the "Rows" key is a text area's line count elsewhere).
- A value posted outside the editor (site form, panel text box) is checked by `pg_coll_check_field()`: only codes of the child form's records, at most 100 (`pg_coll_rows_value_ok()`).

- The API, a Workspace change and Import Submitted Forms: `collection-rows-outside.md`.

Tests: `tests/collections_rows_test.php`, `tests/collections_kinds_test.php` (the twin).
