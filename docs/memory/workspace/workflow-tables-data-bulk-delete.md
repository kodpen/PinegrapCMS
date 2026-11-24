---
name: workflow-tables-data-bulk-delete
description: The bulk Delete of the Data list's selection bar (WL10 #11404): the button, its confirm dialog, the per-table right, the result notice and the tests
---

## What does the Data list's bulk Delete do?
- It is the single Delete (`ws_wtable_delete()`) done for each chosen table, nothing new in behaviour: each goes to Deleted tables (or for good while the bin is not installed) under the person's own right on that table.
- Architect (2026-11-24): bulk Delete is the single Delete for the chosen rows.

## Where is it drawn?
- `workspace_wtables.php` without `?id=`: a Delete button (`pg-button pg-button-danger pg-button-sm ws-wt-bulk-delete`) in the list driver's selection bar (`pg_list_table_html()`'s `$actions`), only when the person owns a table in the shown scope. Never in the Deleted tables view.
- It follows the driver's `pg-list-table:selection` event (`detail.payload.ids`; the list is printed whole, so Select all is ids too).

## What does it ask?
- One `<dialog id="ws_wt_bulk_delete">` naming the count ("Delete the N chosen tables? They stay in Deleted tables for D days…", one-table and no-bin wordings beside it); Cancel ghost, Delete danger. The count is put in by the script (`__COUNT__` in `data-many`).
- Delete posts `ws_wt_action_form` with `wt_action=table_bulk_delete`, the ids as `wt_tables[]` and the scope as `target`, so the screen comes back to the same scope.

## What is left, and what does the notice say?
- `ws_wtable_list_bulk_delete()` (`includes/workspace/wtable_list.php`): a table the person reads but may not delete (refusal `forbidden`) is left and named; one out of reach or gone (`not_found`) is only counted, never named. Each deleted table gets its Site Log line.
- `ws_wtable_list_bulk_delete_texts()`: a notice counting the deleted ("3 tables were deleted. They are in Deleted tables…"), then "Left, as only a table's owner deletes it: A, B." and the count not found; nothing deleted is an error with the same parts.
- At most `WS_WTABLE_LIST_BULK_MAX` (200) ids per request (`ws_wtable_list_bulk_ids()`).

## Where are the tests?
- `tests/workspace_wtable_bulk_delete_test.php`: the ids, the single delete per table with injected load/delete, the texts, the screen's wiring.
