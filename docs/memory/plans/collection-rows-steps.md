---
name: collection-rows-steps
description: Plan collection-rows, steps RW1 to RW7: the kind and writer, the panel editor, delete and History, the API, the site form, the list on the record page, close-out
---

## Which steps are there?
- RW1 (M) The kind and the writer. No schema. Done: #9884.
  - Existing files: `includes/fn/collections.php` (`rows` in `pg_coll_kinds()`, the child form kept by `pg_coll_field_reference()`, the field's check in `pg_coll_check_field()`), `assets/js/style_designer.src.js` (the twin `_CF_KINDS`, the Kind panel, the canvas sample), `includes/fn/custom_form.php` (`pg_cf_apply_field_bindings()`: a rows control draws nothing).
  - New: `includes/fn/collection_rows.php` (the spec, a record's rows in order, the writer), `tests/collections_rows_test.php`; `tests/collections_kinds_test.php` covers the twin.
  - Tests, red first: the spec's refusals, the writer's order of writes, a foreign code refused, the limit, a refused row writing nothing, a failure midway leaving no row behind.
  - Features: R1 to R7, R15.

- RW2 (M) after RW1: the editor on the panel's record screens. Done: #9898.
  - Existing files: `includes/forms/submitted_form_form.php` (one branch in `pg_submitted_form_fields_markup()`), `add_submitted_form.php` and `edit_submitted_form.php` (the writer called once the record is written).
  - New: the renderer in the module, `assets/js/pg_collection_rows.js` (the table, the JSON field, the vendored SortableJS).
  - Pictures: an empty field, three rows, a refused row, a field the table does not take, a phone. To the owner; not marked ready before his look.
  - Features: R8, R9, R10.

- RW3 (S) after RW2: delete and History.
  - Existing files: `includes/fn/submitted_form_writes.php` (`pg_submitted_form_delete()`), the delete confirmations of the record list and screen, the History section's load (`designer/collection-history.md`).
  - Tests: a record's rows gone with it, the count in the confirmation's text, a loaded version keeping only rows that exist.
  - Features: R11, R12.

- RW4 (S) after RW1: the API. Done: #9899.
  - Existing files: `includes/api/resources/forms.php` (`api_form_submission_present()`, the field facts, the write check), the form rows of the API schema.
  - Also `import_submitted_forms.php` (the rows cell skipped and named).
  - Tests: `rows` on a single read, codes on a list read, a foreign code and a code of another record's list 422, the import's skipped cell; `php tools/check_api_schema.php`.
  - Features: R13, R14, R19.

- RW5 (M) after RW2: the site form. Done: #10455.
  - Existing files: `includes/fn/custom_form.php` (the branch of RW1 now draws the editor in Bootstrap 5.3.8 markup), `custom_form.php` (the writer called after its record is saved).
  - The pictures to the owner are dropped (agent channel message 9546); unit tests pin the behaviour.
  - Features: R16.

- RW6 (S) after RW1: the list on the record page. Done: #9897.
  - Existing files: the form list's dynamic values on the server and `_swFilterDynamicOptions()` in the editor (`designer/collection-lists.md`), with their twin test; the designer's "Rows of this record".
  - Tests: the list's rows and their order, an empty field, a code that names nothing left out.
  - Features: R17, R18.

- RW7 (S) close-out: every R number checked against the code with its PR; `designer/collection-rows.md` (named in the collection files' overview); this plan's four files deleted and its line in `plans/website-features.md` removed.

## Which steps run side by side?
- RW3, RW4 and RW6 may run beside RW5. RW2 and RW5 share the renderer: one after the other.
