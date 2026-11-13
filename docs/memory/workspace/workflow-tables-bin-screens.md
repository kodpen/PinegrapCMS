---
name: workflow-tables-bin-screens
description: Where the workflow table bin shows (TB2): Deleted tables view and Deleted rows card on workspace_wtables.php, the delete confirmations, and the deleted-tables / deleted-rows API routes
---

## Which cards does the screen draw?
- `workspace_wtables.php`: the Data list's Deleted tables view (`?view=deleted`, `#ws-wt-deleted`, own tables and those of channels the person manages; `workflow-tables-data-list.md`) with Restore per row.
- A table's "Deleted rows" card (`#ws-wt-deleted-rows`, edit access) with Restore and Delete for good. Actions `table_restore`, `row_restore`, `bin_row_delete` post `wt_table` / `wt_bin` through the shared action form. The delete confirmations name the bin's days (`ws_wtable_screen_delete_confirm()`); a binned row is shown by its first cells (`ws_wtable_screen_bin_summary()`).

## Which API routes reach the bin?
- `GET /workspace/deleted-tables` (`workspace.tables.deleted`) and `POST /workspace/deleted-tables/{id}/restore` (`workspace.tables.restore`), both `'mcp' => false` like the table delete; `GET /workspace/tables/{id}/deleted-rows` (`workspace.table_rows.deleted`, objects `WorkspaceTableBinRow`) and `POST …/deleted-rows/{bin_id}/restore` (`workspace.table_rows.restore`), MCP tools as the row delete is. The bin paths are separate from `/workspace/tables/{id}`, whose `{id}` is an integer.

## Why are the table routes no MCP tools?
- The table delete is none (it takes a whole table from everybody), so its bin and restore are the owner's own as well; a row's restore is a tool because the row delete is one.

Related: docs/memory/workspace/workflow-tables-bin.md
