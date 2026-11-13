---
name: workflow-tables-data-list
description: The Data list (CO6, #8978): workflow tables named Data in the panel, workspace_wtables.php without ?id= on the list table driver, its scopes, windows, Deleted tables view, the channel seam
---

## What is named Data, and what keeps its name?
- The place is "Data" / "Veri": the Workspace tile (`ws_nav_groups()` key `wtables`), the screen's heading and breadcrumb, the record picker's group (`screen.php` string `wtables`), the personal data report's two labels (`privacy.php`). One of them is still a "table" / "tablo".
- Unchanged: the address `workspace_wtables.php`, the store (`ws_wtable_*`, `ws_wtables*`), the API routes `/workspace/tables` and their wording ("workflow table"), MCP tools, events `workspace.wtable.*`, scopes.

## How is the list drawn?
- `includes/workspace/wtable_list.php`, loaded by the screen after `includes/fn/list_table.php`: list `workspace_wtables` printed whole (`pg_list_table_printed()`, in `pg_list_table_printed_lists()`, so a heading sort is kept), default sort Updated, newest first. Columns: Name (tile, link, description in the second text), Rows, Columns (one grouped count, `ws_wtable_list_column_counts()`), Where, Updated, Action (Download as Excel; Delete for the owner).
- Toolbar (`pg_screen_toolbar()`): New table (filled) and Import from file (quiet) open `<dialog>` windows `#ws_wt_new` and `#ws_wt_import_new` holding the old cards' forms (same field ids and actions); second line search box, scope segments All / Mine / Shared with me (`?scope=`), at the right Tables / Deleted tables (`?view=deleted`). Heading "Data", the line under it "N tables · …". Look A only: `.pg-seg`, `.pg-button`, `.pg-icon-tile`, `.pg-icon-button`; no CSS of its own, no new bundle.
- The name cell's parts sit in one `<span>`: on a phone the list's card cell is a flex row under its label and loose parts became columns one letter wide.

## Where did the deleted tables go?
- `?view=deleted` prints `workspace_wtables_deleted` (no kept sort) inside `#ws-wt-deleted` with Restore per row; a delete from a row lands there (`table_delete` goes back to `?view=deleted` when the bin is ready). The bin's rules: `workflow-tables-bin-screens.md`.

## What waits for other steps?
- Channels: `ws_wtable_list_scopes(true)` adds the Channels segment. A table is a channel's only when the screen's `$channels` (table id => channel row as the store resolved it) holds it, never by `channel_id` alone: a `channel_id` naming no channel leaves the table its maker's. On since #9038: `$with_channels` is `ws_wtable_home_ready()` and `$channels` holds `ws_wtable_home($table)` of each reached table that has one (`workflow-table-channel.md`).
- The canvases that use a table (a column and a line under an opened table's title) need CO2's `ws_canvas_tables` (#8960); a follow-up step after CO2 adds them.

## Where are the tests?
- `tests/workspace_wtable_list_test.php`: scopes, counts, cells and escaping, owner-only delete, deleted cells, segments, the screen's wiring and the Data name elsewhere.
