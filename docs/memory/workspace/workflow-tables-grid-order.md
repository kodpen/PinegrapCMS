---
name: workflow-tables-grid-order
description: A Data table's column menu (workspace_wtables.php?id=N, DM4): sorts, Filter, Rename, Change type, Move left/right, Delete, the "+" head; rows moved by a handle or Move up/down; ws_wtable_column_move()
---

## What does a column's head open?
- Every head is a menu button (`ws_wtable_grid_menu()`, `.ws-wt-col-button`, the sort's arrow beside the name): Sort ascending, Sort descending (the one in force marked), Filter by this column; for a writer Rename, Change type (only when `ws_wtable_screen_retype_choices()` offers a type), Move left, Move right (not at that end) and Delete in red with the confirm the Columns drawer asks.
- Entries: `ws_wtable_grid_column_menu_items()`. A lookup or rollup sorts nothing and takes no condition: a reader's head of one is the plain name. The old head click cycling asc → desc → table order is gone; the tag under the bar takes a sort off.
- The grid's menus are `data-bs-strategy="fixed"` and `data-bs-container="body"`: absolute, the grid's scrolling box cut them; inside the sticky head, the sticky totals rows drew over them.
- Filter by this column opens the bar's Filter box with the column chosen on its empty line and the comparison focused (the screen's inline script).
- Rename and Change type open the Columns drawer at the column's `details#ws-wt-col-N`, opened, focusing its name or New type field (`data-ws-wt-goto`, `data-ws-wt-focus`). The "+" head after the last column (writers) does the same for New column.
- Delete and the moves post through the screen's one action form with `target=rows`, so they come back to the grid, not to the Columns drawer.

## How does a column move?
- `column_move` posts `wt_column` and `position` (its new place, 1-based) to `ws_wtable_column_move()` in `includes/workspace/wtables.php`, with its `_check()` (write access, a whole place above 0, else `position_invalid`).
- It reads the columns locked (`ws_wtable_columns_locked()`), puts the column at its place (`ws_wtable_column_order_moved()`, pure, tested) and numbers them 1..n again, writing only the ones that change, in one transaction; then the table's revision goes up. Positions left with gaps by a delete are closed this way. No API route moves a column yet.

## How does a row move?
- Only while the rows stand in the table's order (no filter, no sort in force): each writer's row has a grip handle before its number and `data-ws-wt-position`; its "⋯" menu (`ws_wtable_grid_row_menu_items()`, in place of the lone Delete button) holds Move up and Move down (not at that end of the table) and Delete.
- `row_move` posts `wt_row`, `position` and the page (`after`, `after_id`) to `ws_wtable_row_move()` and comes back to that page at `#ws-wt-row-N`.
- Dragging the handle onto another row takes that row's place (`assets/js/workspace_wtable_order.js`, `dropPlace()` tested in Node, in the `workspace_wtable_grid.min.js` bundle); a line shows above the row when moving up, under it when moving down. The menu is the keyboard's and the phone's way.

## What pins it?
- `tests/workspace_wtable_grid_test.php`, `tests/workspace_wtables_test.php`, `tests/workspace_wtable_order_js_test.php`.
