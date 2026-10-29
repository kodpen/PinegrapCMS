---
name: collection-history
description: History section of a collection item (C7b, #3099): list, compare with the current values, Load, the save with source restore, what happens to files and signatures
module: record_revisions
---

## What does the History section do?
- `edit_submitted_form.php` shows it under the Form Data card for a collection item (`pg_record_revision_history_enabled()`); the screen itself is only for users who manage the record (role 1–2, or `manage_forms` with edit rights to its folder). No API endpoints.
- List: the newest 50 revisions (number, when, who, source). `?compare=N` shows revision N beside the current values field by field, the address name first, changed fields marked (`pg_record_revision_compare()`, pure). `?revision=N` (Load) fills the form with N's values (`pg_record_revision_load()`) and adds the hidden `restore_revision`.
- Saving a loaded revision is the normal edit save (validation, required fields, hooks) with source `restore`; `restore_revision` counts only for a revision of this item and a record manager. A save that comes back with errors keeps `&revision=N`, and the posted values are not overwritten.
- Files (`pg_record_revision_restore_plan()`, pure): a revision's file whose `files` row still exists is pointed at again on save (`pg_record_revision_restore_files()`), unless this save uploaded or removed a file in that field; the current file is not deleted (newer revisions refer to it). A file deleted since is `gone`: not restored, the screen says so for that field. A revision without a file, and a signature (never redrawn), leave the current value (`kept`).

## Where are the tests?
- `tests/record_revision_history_test.php` (decode, compare, restore plan, form value, shown text). The screen itself has no test: it needs a database.

Related: docs/memory/designer/collection-revisions.md
