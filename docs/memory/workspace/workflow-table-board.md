---
name: workflow-table-board
description: Board view of a workflow table (T2, #2994): rows as cards by a status column via the channel board's mountSource(); a move writes the status cell with ws_wtable_row_update()
---

## Where is the board, and what draws it?
- `workspace_wtables.php?id=N&view=board&by=COL`: a Table/Board switch in the rows card header shows while the table has a status column with choices; more than one status column gives a "Group by" choice. `by` not a status column → the first one (`ws_wtable_board_column_id()`).
- The cards are drawn by `assets/js/workspace_board_channel.js` (the channel Board tab), not a second board: `PGWsChannelBoard.mountSource(pane, source)` with `source.load({all})`, `move(item, drop)`, `open(item)`, `texts`. A source board draws no toolbar of its own and, since the tables screen does not load `workspace.js` (no `PGWsKit`), brings a small kit (elements, select, toasts through `pgToast`). `mount()` for channels is unchanged.
- Card kind `row`: `#position`, title = first filled text cell (else "Row n"), up to three other filled cells as lines. A column carries its choice colour on its icon.

## How is the board read?
- `GET …&board_json=1&by=COL[&all=1]` answers `ws_wtable_board()` (pure, `includes/workspace/wtable_board.php`): one column per choice in the column's order, then "No status" for rows with no or a removed choice (shown only when a row is in it). 100 cards a column (`WS_WTABLE_BOARD_CARDS`), `more` counts the rest; the component's "more" button reads with `all=1`. The whole table is read (`ws_wtable_rows()`, bounded by the 5000-row limit).

## How is a move written?
- POST `wt_action=row_status` (token, `wt_table`, `wt_column`, `wt_row`, `revision`, `value`) answers JSON `{ok, message}`. `ws_wtable_board_move_input()` gives only the status cell (`null` for "No status") on the card's revision; `ws_wtable_row_update()` lays it over the other cells and keeps the rights, the 409 `changed_meanwhile` and the `workspace.wtable.row.updated` event. The board reads again after every move, refused or not.
- Only an editor (edit+) can drag; a viewer sees the cards. Opening a card goes to the table view's row form (`&row=M`).
- Tests `tests/workspace_wtable_board_test.php`.
