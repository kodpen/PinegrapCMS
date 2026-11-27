---
name: workflow-tables-screen-bar
description: A Data table's bar (workspace_wtables.php?id=N, #12057): New row, Table/Board, "Filter" box and tags, "⋯" menu; the side cards as drawers, opened from it or by the hash
---

## What does the table's bar hold?
- The Workspace bar rule (`ui/workspace-screen-bar.md`) drawn by the server: `ws_wtable_screen_bar()` in `includes/workspace/wtable_screen_bar.php`, a `.ws-bar-frame.ws-wt-bar-frame` replacing `#button_bar`.
- Order: New row (`pg-button-fill`, to `#ws-wt-row-form` of the table view, also from the board); Table and Board (`pg-seg` links plus the `select[data-ws-bar-go]` a narrow bar shows) while a status column groups a board; the grow slot; "Filter" (table view, once the table has columns); "⋯".

## What does "Filter" hold?
- The query bar (`ws_wtable_screen_query_bar()`, `workflow-tables-query.md`) in a server-drawn box `#ws-wt-filter-box` under the button: a line per condition (column and comparison as a pair, the value, its "×"), the join, Sort by and its direction; foot Clear, Close, Apply. Still a GET form: every address of a filtered view is unchanged.
- The count is `ws_wtable_screen_query_count()`: a condition each, plus one when the order is not the table's own. The tags under the bar are `ws_wtable_screen_query_tags()` (a status by label, a checkbox Ticked/Not ticked, a person by name, a row link by its text; "Sorted by X, Ascending/Descending"), each "×" a link that keeps the rest and the totals switch.
- A refused query is a warning line under the bar, not in the box; the rows show unfiltered.
- The box: Enter or a click opens it and focuses the first field; Escape, Close or a click outside (a select2 or date picker excepted) closes it and gives the focus back to "Filter". Below 576 px it spans the bar's frame.

## What is in "⋯"?
- `ws_wtable_screen_menu()`: Show on a canvas (header, As a chart, As a table: `pg_image_editor_data_show_items()`), Run from table (its dialog), Download as Excel, Import; Columns, Shares, Belongs to, Name and description, Deleted rows (N); Delete table (red, with its confirm, managers only). An entry appears only when the screen offered its card or button before.

## Where did the side cards go?
- Each is a drawer (`ws_wtable_screen_drawer()`, `dialog.drawer.drawer-end.ws-wt-drawer`, 32 rem) keeping the card's anchor as its id: `ws-wt-columns`, `ws-wt-shares`, `ws-wt-home` (`ws_wtable_home_drawer()`), `ws-wt-details`, `ws-wt-import-rows` (rows from a file and from a note), `ws-wt-deleted-rows`. The grid card takes the page's width; the row form is a drawer too (`workflow-tables-grid-drawer.md`).
- The screen's script opens the drawer the address's hash names, or holds (a column's `ws-wt-retype-N`), so every post-redirect-get `$back` anchor lands in its drawer; the forms and `.ws-wt-action` posts inside are unchanged.

## What pins it?
- `tests/workspace_wtable_screen_bar_test.php`; tried on a sandbox at 1440 and 390 px, light and dark (#12057).
