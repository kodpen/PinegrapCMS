---
name: workspace-my-tasks-board
description: My Tasks' Board layout (TM4): List/Board icons kept in ws-tasks-layout, ws_tasks_status_board() columns by status through the channel board's mountSource(), moves via ws_task_status
---

## Where is the layout chosen?
- In My Tasks' bar, after the search: a `pg-seg` of two icons (`bi-list-ul` List, `bi-kanban` Board; the name is a visually hidden label and the title). Kept per browser in `localStorage` `ws-tasks-layout` (`list` or `board`, List when nothing is kept). Hidden in My time.
- `assets/js/workspace_my_tasks_board.js` (`PGWsMyTasksBoard.control()` and `.mount()`, in `workspace.min.js`); `startTasks()` in `workspace.js` calls them. The screen loads `workspace_board_channel.min.js` for the `tasks` mode too (`ws_screen_assets()`).

## What does the board show?
- The same tasks as the list: the bar's view, Filter and search are sent to `ws_tasks` with `layout=board`, whose answer adds `board` from `ws_tasks_status_board($tasks, $status, $today)` (`includes/workspace/channel_board.php`, pure, `tests/workspace_my_tasks_board_test.php`).
- A column a status in `ws_task_statuses()` order, each with its dot (`dot: true`); Cancelled only when the status filter is not "Open tasks". With open tasks the Done column is empty: it is there to drop a task in. Cards in the channel board's order (`ws_channel_board_compare()`: priority, due date, newer).
- A card is built by `ws_channel_board_task_card()`, shared with a channel's board; the due chip reads the reader's day. My Tasks' cards carry no reminder or series mark (not read here). No New task in a column, no figures, no Automations.
- The add line and the date groups belong to the List layout only; the count line and the view's count follow each board reading.

## How is a card moved?
- Dragged to another column, by the keyboard (Space, arrows, Enter), or by its menu's "Move to…" (a dialog with the other columns; only on a task the reader may change). All write `ws_task_status`, as the drawer's status control does; a refusal toasts the store's sentence and the board is read again, so the card goes back. Its other menu entries are `taskMenu()`'s.
- Click or Enter opens the task drawer; its save reloads the board.

## What did the board component get?
- `mountSource(pane, source)` takes, beside `load`, `move`, `open`, `texts`: `picker: false` (no column select on a phone; the columns scroll), `slots: true` (the dashed drop place), `menu(item, moveTo)` (a task card's menu, `moveTo(drop)` putting it in that column). A column with `dot: true` shows its status dot. A Data table's board passes none of them and is unchanged (`workflow-table-board.md`).

## How does it look on a phone?
- The pane is `.ws-pane-board.ws-mt-board`, the channel board's look. Under 768 px a column is the pane's full width and the columns snap as they scroll sideways (`.ws-mt-board` rules after the `.ws-pane-board` phone block of `backend.src.css`).

Related: docs/memory/workspace/workspace-my-tasks-screen.md, docs/memory/workspace/workspace-channel-board-look.md, docs/memory/workspace/workflow-table-board.md
