---
name: workflow-tables-import
description: The import route workspace.tables.import (TX2, #8427): a site file or a note's table into a workflow table over the API and MCP, the pairing, the dry run through ws_wtable_import_check(), refusals
---

## What does the route do?
- Beside the other table routes (`workflow-tables-api.md`): `POST /workspace/tables/import` (`includes/workspace/api_wtables.php`, `ws_api_wtables_import()`), scope `workspace:write`, `dry_run`, an MCP tool. Since #8427; before it a file or note table came in on the panel only (`workflow-table-import.md`).
- Source, one of: `file_id`, a site file `GET /files` lists (public folder, not an attachment), the caller also holding `files:read` (`api_require_scope()`), read through `pg_file_local_copy()` (object storage, `tests/object_storage_workspace_test.php`), at most `pg_upload_limits()['file_max']`; or `note_id` + `note_table` (0-based), the note through `ws_api_note_or_404()` (Claude's channel reach) and `ws_wtable_import_note_read()` (access, lock).
- Target, one of: `table_id` (`ws_api_wtable_writable()`, 403 on view) with `map` (source column name => column id, 0 leaves out) or pairing by name (`ws_wtable_import_match()`); or `name`, a new table, with `types` (source name => import type, '' leaves out) or types guessed (`ws_wtable_import_plan()`).
- Sending both or neither of a pair, `map` with `name`, `types` with `table_id`, an unknown source name or column: 422 naming the field.

## How is it checked before writing?
- `ws_wtable_import_check($viewer, $table, $read, $choice)` (wtable_import.php) asks what the panel's import asks: the table writable or the new table and its columns allowed (`ws_wtable_create_check()`, `ws_wtable_column_input_check()`, stand-in ids for unmade columns), `no_rows`, `map_empty`; then `ws_wtable_import_rows_check()` (pure) runs every row through `ws_wtable_row_cells_input()` and the rows left (`too_many_rows` for the rest).
- The panel's `import_write` calls it too, both branches; its codes are worded in `ws_wtable_screen_error_text()`.
- The dry run answers `detail`: `table_id`, `columns` (source, column_id, name, type, new), `rows`, `refused_count`, `refused` (first 100: line with the header as 1, error, errors by column name), `more`.

## What does the real call answer?
- `WorkspaceTableImport`: `table` (with columns), `added`, `refused_count`, `refused` (`WorkspaceTableImportRefusal`), `more`; 201 for a new table, 200 for rows added. Written inside `ws_api_as_owner()`; no `workspace.wtable.row.*` event, as on the panel.

## What is left?
- An export route in the API, a CSV download, a download of the filtered rows (`workflow-tables-later.md`).
