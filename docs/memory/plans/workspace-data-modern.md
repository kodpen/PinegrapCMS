---
name: workspace-data-modern
description: Plan (owner 2026-11-27): a Data table's screen modernised: the Workspace bar, one-line cells, the row in a drawer, cells changed in the grid, a column menu; steps DM1 to DM5
priority: 6
---

## What is asked?
- Owner (2026-11-27): the Workspace's own screens modernised (`plans/workspace-areas-modern.md`, whose rules every step here keeps). This plan is one table opened on `workspace_wtables.php?id=N` (`workspace/workflow-tables-screen.md`); the Data list is already the list table.
- The screen stays drawn by the server and every write stays the store's (`ws_wtable_*`): its rights, limits, refusals and the `changed_meanwhile` answer.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- DM1 (M) The bar and the page. The buttons of `#button_bar` and the filter's row of selects become the Workspace's one-line bar (`ui/workspace-screen-bar.md`, the same classes drawn by the server):
  - New row filled, the Table and Board views as a segmented control, a "Filter" box holding the query bar's column, comparison, value and order (`workspace/workflow-tables-query.md`) with a count of what is set, and a "⋯" menu: Show on canvas, Run from table, Download as Excel, Import, Columns, Shares, Belongs to, Deleted rows, Delete table.
  - The Columns, Shares and Belongs to cards leave the page: each opens as a form drawer from the menu. The grid takes the page's width.
- DM2 (M) The grid and the row drawer. A cell is one line, cut with an ellipsis and whole in its title; widths follow the type (number, money, date and checkbox narrow, text wide); numbers and money stand right in tabular figures. The grid scrolls sideways in its own box with the head row, the "#" column and the totals rows staying in view; on a phone the same.
  - A row's click or Enter opens the row in a form drawer, fields in the columns' order with long text whole, in place of the card under the grid; New row opens the same drawer empty. A refusal brings the drawer back with the typed values and the refused cells named, as today.
- DM3 (M) Cells changed in the grid. A double click, Enter or F2 on a cell of a text, number, money, date, checkbox or status column a person may write turns it into its field; Enter or leaving it saves that one cell with the row's `revision` through one new panel action over `ws_wtable_row_update()`; Esc gives it up; Tab and Shift+Tab save and go to the next cell.
  - A refusal stands under the cell in the store's words; `changed_meanwhile` draws the row as it is now and says so. Formula, lookup, rollup, person, record link and list cells open the row drawer instead. The script is a file of its own, loaded on this screen only; the action's reading of a typed cell is the row form's (`wtable_screen.php`), with tests.
- DM4 (M) The column's menu and the rows' order. A column's head opens a menu: Sort ascending, Sort descending, Filter by this column, Rename, Change type, Move left, Move right, Delete (the confirm it has today). A "+" head after the last column opens New column in its drawer. Move left and right write the columns' `position` through the store. With no order chosen, a row is moved by a handle or by its menu's "Move up" and "Move down" through `ws_wtable_row_move()`.
- DM5 (S) Close-out: `workspace/workflow-tables-screen.md` corrected (the bar, the drawers, what is no longer left out), new `workspace/workflow-tables-grid.md` (cells in the grid, the action, the column menu, the rows' order), this file deleted.

## What is left out?
- A record link chosen from a list, a person chosen from a picker in the grid, saved views of a table and a calendar or gallery view: later steps, the saved views needing a table.
- The board view (`workspace/workflow-table-board.md`) and the Data list are unchanged.
