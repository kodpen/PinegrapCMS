---
name: workflow-tables-api
description: Workflow tables in the external API and MCP (api_wtables.php, T1d1, #2890): routes, which are tools, dry run, errors, rows paging, Claude's reach
---

## Where are the routes?
- `includes/workspace/api_wtables.php`: `ws_wtable_api_routes()`, merged at the end of `ws_api_routes()`. Paths `/workspace/tables`, `…/{id}/columns/{column_id}`, `…/{id}/rows/{row_id}` (+ `/move`); ids `workspace.tables.*`, `workspace.table_columns.*`, `workspace.table_rows.*`. Scopes `workspace:read`/`workspace:write`, no new scope. Tests `tests/workspace_api_wtables_test.php`.
- Objects `WorkspaceTable` (columns null in the list), `WorkspaceTableColumn` (options an object), `WorkspaceTableRow`. A row is built by `ws_wtable_row_present()` (wtable_rows.php, cells and formula_errors as objects even when empty) so code outside the API can hand it out the same; `ws_api_wtable_row_present()` wraps it beside its schema.
- Shares are not in the API yet (T1d2 in the plan).

## Which are MCP tools?
- Reads, and writes of tables, columns and rows (row delete too), as notes are. No tool (`'mcp' => false`): table delete and column delete, which cannot be undone.
- The Claude application reaches a table that the owner reaches only through a channel share just where Claude may be asked (`ws_api_wtable_reachable()`), as for notes.

## How are writes checked and refused?
- Each handler: the store's `*_check()`, `api_dry_run_stop()`, then the write. `ws_wtable_column_delete_check()` was added for this.
- A view-only table answers 403 on writes (`ws_api_wtable_writable()`), not the store's 404.
- Store codes (`ws_api_wtable_refused()`): 404 per thing (Table, Table column, Table row); 403 forbidden; 409 with the store's code as the error code (`too_many_tables/columns/rows`, `changed_meanwhile` with the revision now in the message); 422 `validation_failed` naming the field with the code in the message; `cells_invalid` names `cells.<column id>` and lists every refused column with its code.
- Row delete takes `revision` in the query (a DELETE body is not relied on).

## Who is in the list?
- `ws_wtables_reached($viewer, all|mine|shared)` (wtable_shares.php): own tables plus those shared with the person or in a channel they read, access by `ws_wtable_access()`, latest changed first.
- Rows page by position (`ws_wtable_rows_page()`, cursor = last position, limit 1-250), `total` = `row_count`.
