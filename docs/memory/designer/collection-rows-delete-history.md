---
name: collection-rows-delete-history
description: Collection "Rows" field on delete and in History: a deleted record's rows go with it, the delete confirmations name the count, a loaded revision keeps only rows that exist
module: collection_rows
---

## What happens to a record's rows when it is deleted?
- Owner (2026-11-16): the rows are deleted with the record. `pg_submitted_form_delete()` reads the row ids first (`pg_coll_rows_held_ids()`), deletes the record, then each row through `pg_submitted_form_delete()` (`pg_coll_rows_delete_rows()`); the rows' file names join the returned list.
- A row that lists rows of its own takes them with it; a list pointing back at a deleted record finds nothing, so a loop ends.
- Every path (record screen, Delete Selected, Delete All Forms, the API, the rows writer's removed row) goes through it.

## How do the delete confirmations name the rows?
- `pg_coll_rows_delete_question($question, $count, $many)` (pure) adds "Its N rows of Record rows fields are deleted with it." (or "their … with them") to the question; with no rows the question stays.
- Delete on Edit Submitted Form counts the record's rows when the screen is drawn.
- Delete All Forms on View Submitted Forms counts the rows of every form the filters match, only when a rows field exists (`pg_coll_rows_in_use()`).
- Delete Selected: the button carries `data-pg-rows-count` on a site with rows fields; the list's script posts the selection with `rows_count=1` to `delete_submitted_forms.php`, which answers `{question}` for the forms the user may delete and deletes nothing. Until the answer comes the plain question stands.
- A selected row whose parent is also selected is counted once (left out of the count).

## What does a loaded revision do with the rows?
- The History keeps the rows field's value (the codes in order) like any field; a row's own changes are in the row's History.
- Load (`pg_record_revision_load()` → `pg_coll_rows_revision_load()`): each rows editor gets the revision's list as far as its rows still exist (`pg_coll_rows_revision_keep()`, pure: the current rows in the revision's order), with each row's values of now. A removed row was deleted with its removal, so it is counted and the notice says "N rows of this revision have been deleted since and cannot be brought back."
- Saving the loaded revision is the editor's normal save: that order is written and the rows the revision did not list are deleted.
- The API's restore (`pg_record_revision_restore_values()`) does the same without the form (`pg_coll_rows_revision_restore()`): the kept list is set from the list stored before, the rows no longer listed are deleted.

Tests: `tests/collections_rows_delete_test.php`.
