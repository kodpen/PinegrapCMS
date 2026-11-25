---
name: workspace-channel-board-tab
description: A channel's one Board tab (BM1 #11555, BM2): Tasks and Board as one tab with the Board and List views, tasks only, the shared toolbar, the kept view, ?view=board/list/tasks, a room without the board
---

## Which tabs does a channel show?
- With the board (`hasBoard()` in workspace.js): Messages, Board, Decisions, Files, Notes, Summary, Memory. Board carries the open tasks' count (`drawTabMarks()`, `counts.tasks`).
- Without it (a guest room, a discussion, an earlier version): the old list with its Tasks tab, unchanged (`drawTasks()`).
- `self.tab = 'tasks'` from anywhere (the catch-up line's late tasks) becomes Board in the List view in `drawCenter()`; Board asked where there is none becomes Tasks.

## What are the two views?
- Board: the columns of `ws_channel_board`. List: the Tasks pane's task cards with "Mark done", drawn by `taskListInto()` (workspace.js), the same function `drawTasks()` uses, handed to the board as `hooks.listInto`.
- The view is kept per browser in `localStorage` `ws-board-view`; `hooks.view` (from `self.boardView`) opens one view without asking it.

## What does the board hold?
- Tasks only (BM2): grouped by status, by person or by date (`ws_channel_board_groupings()`). "By kind" went with its columns of decisions, files, the pinned message and the summary, and with `ws_channel_board_decisions()` / `_files()` (owner 2026-11-24, `workflow/decisions-made-workspace-51.md`).
- A former `group_by=type` is read as status: the server falls back for an unknown grouping, the script through `knownGroup()`. The stamp (`ws_channel_board_stamp()`) reads the channel's tasks only.
- The API's group_by enum follows the groupings by itself, so `group_by=type` now answers 400; a card's `message_id` and `url` stay in the schema, always null. The `.ws-cb-thumb` / `.ws-cb-kind-icon` rules in `backend.src.css` were left for BM3 (CI1 #11617 held that file).
- The pinned message is read in the Summary tab instead (`workspace-channel-summary-board-memory.md`).

## What does the toolbar hold?
- One `pg-toolbar ws-bar ws-cb-bar` drawn once a mount (`toolbar()` in workspace_board_channel.js), so the search keeps focus: the Segmented control "Board" / "List"; the grouping chips (Board) or open / closed / all (List); the search (`barSearch()`); "Only mine"; "Filter" (`barFilter()`: urgency, person, "Show the cancelled ones" only while grouped by status); the filled "New task".
- The bar parts come from workspace.js through `PGWsKit.bar` (views, search, filter, label, selectedText).
- It wraps, unlike the other screen bars: at phone width three lines (Segmented control with New task; grouping or status; search with "Only mine" and "Filter" as icons).

## How does the List view filter?
- `ws_channel_tasks` reads `q` (title search), `priority`, `person` (0 all, -1 nobody, a user id) and `mine`; `ws_tasks_for_person()` (tasks.php) narrows by person, tested in `tests/workspace_channel_board_tab_test.php`.

## Which address opens it?
- `?view=board` the board, `?view=list` the List view; `?view=tasks` (the Tasks tab's) the List view, or the Tasks tab of a room without the board. `tabAddress()` writes it.

## What must the tab keep?
- No table, column or right of its own; a change that needs one asks the architect first. A rule is a flow (`workspace-channel-board-rules.md`).
- Moving a card does what it did (status, people, due date), by pointer and by keyboard; the workflow table's board (`mountSource()`) is not touched.
- Without the flows' gate and right there is no "Automations" button and no rule line.
