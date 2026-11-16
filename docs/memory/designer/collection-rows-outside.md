---
name: collection-rows-outside
description: Collection "Rows" field outside its editor: the API read (codes, rows, kind), the check of an API or Workspace write, the import leaving the column out
module: collection_rows
---

## What does the API read give?
- A record's read on the API: each value carries `codes` (a rows field's codes in order, null on other fields) and `rows` (on a single read, also after an update, create or restore: each row's id, reference_code and values in the child's field order, no file or signature; empty on a list read). `api_form_submission_values_out()`, pure parts `api_form_values_rows()` and `api_form_row_present()`.
- `GET /forms/{id}/fields` and `GET /forms/{id}` give `kind` (a collection kind or null) and `child_form_id` (rows only).

## Which writes are checked, and how?
- A value written from outside the editor (API create and update, a Workspace change to a form's answers) goes through `pg_coll_rows_outside_refusal()`: codes of the child form's records only, at most 100, none that another list holds (`pg_coll_rows_held_sql()`, `pg_coll_rows_taken()`; the record's own list may stay). The API answers 422 before `api_dry_run_stop()`. A code taken off the list this way is not deleted.

## What does the import do?
- Import Submitted Forms leaves a rows column out (`pg_coll_is_rows_field()`) and names it in a warning; the export writes the codes as they are stored. (#9899)

Tests: `tests/collections_rows_api_test.php`.
