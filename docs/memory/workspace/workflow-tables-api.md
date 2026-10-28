---
name: workflow-tables-api
description: Workflow tables in the external API and MCP (api_wtables.php, T1d, #2890): routes, which are tools, dry run, errors, rows paging, workspace.wtable.row.* webhooks on a public channel share
---

## Where are the routes?
- `includes/workspace/api_wtables.php`: `ws_wtable_api_routes()`, merged at the end of `ws_api_routes()`. Paths `/workspace/tables`, `…/{id}/columns`, `…/{id}/rows`, `…/{id}/shares`; ids `workspace.tables.*`, `workspace.table_columns.*`, `workspace.table_rows.*`, `workspace.table_shares.*`. Scopes `workspace:read`/`workspace:write`, no new scope. Tests `tests/workspace_api_wtables_test.php`.
- Objects `WorkspaceTable` (columns null in the list), `WorkspaceTableColumn` (options an object), `WorkspaceTableRow` (`ws_wtable_row_present()`/`ws_wtable_row_schema()` in wtable_rows.php, shared with the webhooks), `WorkspaceTableShare`.

## Which are MCP tools?
- Reads, and writes of tables, columns and rows (row delete too), as notes are. No tool: table delete and column delete (cannot be undone) and the share writes.
- Share writes: `ws_api_own_words_only()` and `ws_api_is_assistant()` refused before the rehearsal, written inside `ws_api_as_owner()`, as flow shares are.
- The Claude application reaches a table reached only through a channel just where Claude may be asked (`ws_api_wtable_reachable()`); its share list leaves those channels out (`ws_api_wtable_shares_seen()`).

## How are writes checked and refused?
- Each handler: the store's `*_check()`, `api_dry_run_stop()`, then the write. `ws_wtable_column_delete_check()` was added for this.
- A view-only table answers 403 on writes (`ws_api_wtable_writable()`), not the store's 404.
- Store codes (`ws_api_wtable_refused()`): 404 per thing (Table, Table column, Table row, Table share); 403 forbidden; 409 with the store's code as the error code (`too_many_*`, `changed_meanwhile` with the revision now in the message); 422 `validation_failed` naming the field and the code in the message; `cells_invalid` names `cells.<column id>` and every refused column.

## Who reaches the list?
- `ws_wtables_reached($viewer, all|mine|shared)` (wtable_shares.php): own tables plus those shared with the person or in a channel they read, access by `ws_wtable_access()`, latest changed first.
- Rows page by position (`ws_wtable_rows_page()`, cursor = last position), `total` = `row_count`.

## When do webhooks go out?
- `workspace.wtable.row.created`, `.updated` (with `changed` column ids), `.deleted` (row id and position), from `ws_wtable_row_create/_update/_delete()` through `ws_wtable_row_announce()`: only when the table has a share in a public, not archived channel (`ws_wtable_event_channels()`). A private channel or person share announces nothing; a move announces nothing.
- One event per change: `channel_id` is the oldest public share (what a channel-group subscription matches), `channel_ids` all of them, `by` the writer.
