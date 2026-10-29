---
name: workflow-tables-api
description: Workflow tables in the external API and MCP (api_wtables.php, T1d1 #2890, T1d2 #2931): routes, which are tools, dry run, errors, rows paging, Claude's reach, shares, workspace.wtable.row.* webhooks
---

## Where are the routes?
- `includes/workspace/api_wtables.php`: `ws_wtable_api_routes()`, merged at the end of `ws_api_routes()`. Paths `/workspace/tables`, `…/{id}/columns/{column_id}`, `…/{id}/rows/{row_id}` (+ `/move`), `…/{id}/shares/{share_id}`; ids `workspace.tables.*`, `workspace.table_columns.*`, `workspace.table_rows.*`, `workspace.table_shares.*`. Scopes `workspace:read`/`workspace:write`, no new scope. Tests `tests/workspace_api_wtables_test.php`.
- Objects `WorkspaceTable` (columns null in the list), `WorkspaceTableColumn` (options an object), `WorkspaceTableRow`. A row is built by `ws_wtable_row_present()` (wtable_rows.php, cells and formula_errors as objects even when empty) so code outside the API can hand it out the same; `ws_api_wtable_row_present()` wraps it beside its schema.
- `WorkspaceTableShare` (`ws_api_wtable_share_present()`): `level` as given, `effective_level` what it gives now ('' for a person who left the team).

## Which are MCP tools?
- Reads, and writes of tables, columns and rows (row delete too), as notes are. No tool (`'mcp' => false`): table delete and column delete, which cannot be undone, and the share writes (set, delete, delete_post). The share list is a tool.
- The Claude application reaches a table that the owner reaches only through a channel share just where Claude may be asked (`ws_api_wtable_reachable()`), as for notes; its share list leaves those channels out (`ws_api_wtable_shares_seen()`).

## How are shares written?
- As flow shares (#2931): `ws_api_own_words_only()` (team devices session only) and `ws_api_is_assistant()` refused before the rehearsal, then `ws_wtable_share_set_check()` / `_remove_check()`, `api_dry_run_stop()`, the write inside `ws_api_as_owner()` (Site Log name). New share 201, level change 200; `too_many_shares` 409, `share_not_found` 404 "Table share".

## How are writes checked and refused?
- Each handler: the store's `*_check()`, `api_dry_run_stop()`, then the write. `ws_wtable_column_delete_check()` was added for this.
- A view-only table answers 403 on writes (`ws_api_wtable_writable()`), not the store's 404.
- Store codes (`ws_api_wtable_refused()`): 404 per thing (Table, Table column, Table row); 403 forbidden; 409 with the store's code as the error code (`too_many_tables/columns/rows`, `changed_meanwhile` with the revision now in the message); 422 `validation_failed` naming the field with the code in the message; `cells_invalid` names `cells.<column id>` and lists every refused column with its code.
- Row delete takes `revision` in the query (a DELETE body is not relied on).

## Who is in the list?
- `ws_wtables_reached($viewer, all|mine|shared)` (wtable_shares.php): own tables plus those shared with the person or in a channel they read, access by `ws_wtable_access()`, latest changed first.
- Rows page by position (`ws_wtable_rows_page()`, cursor = last position, limit 1-250), `total` = `row_count`.

## When do row webhooks go out?
- `workspace.wtable.row.created`, `.updated` (with `changed`, the column ids written), `.deleted` (row only id and position), from `ws_wtable_row_create/_update/_delete()` through `ws_wtable_row_announce()` (wtable_shares.php, #2931). A move announces nothing.
- Only when the table has a share in a public, unarchived channel (`ws_wtable_event_channels()`, oldest first); a person or private-channel share announces nothing (every member reads a public channel).
- One event per change: `channel_id` the oldest public share (what a channel-group subscription matches), `channel_ids` all, `table` id and name, `row` as `ws_wtable_row_present()`, `by` the writer. Each `pg_announce()` call names the event and `channel_id` literally: `tests/webhook_channel_group_test.php` reads the calls.
- An import announces no row; flows need no share (`workspace-flows-row-box.md`).
