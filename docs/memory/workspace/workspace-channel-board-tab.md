---
name: workspace-channel-board-tab
description: A channel's one Board tab (BM1 #11555): Tasks and Board as one tab with the Board and List views, the shared toolbar, the kept view, ?view=board/list/tasks, a room without the board
---

## Which tabs does a channel show?
- With the board (`hasBoard()` in workspace.js): Messages, Board, Decisions, Files, Notes, Summary, Memory. Board carries the open tasks' count (`drawTabMarks()`, `counts.tasks`).
- Without it (a guest room, a discussion, an earlier version): the old list with its Tasks tab, unchanged (`drawTasks()`).
- `self.tab = 'tasks'` from anywhere (the catch-up line's late tasks) becomes Board in the List view in `drawCenter()`; Board asked where there is none becomes Tasks.

## What are the two views?
- Board: the columns of `ws_channel_board`. List: the Tasks pane's task cards with "Mark done", drawn by `taskListInto()` (workspace.js), the same function `drawTasks()` uses, handed to the board as `hooks.listInto`.
- The view is kept per browser in `localStorage` `ws-board-view`; `hooks.view` (from `self.boardView`) opens one view without asking it.

## What does the toolbar hold?
- One `pg-toolbar ws-bar ws-cb-bar` drawn once a mount (`toolbar()` in workspace_board_channel.js), so the search keeps focus: the Segmented control "Board" / "List"; the grouping chips (Board) or open / closed / all (List); the search (`barSearch()`); "Only mine"; "Filter" (`barFilter()`: urgency, person, "Show the cancelled ones" only while grouped by status); the filled "New task".
- The bar parts come from workspace.js through `PGWsKit.bar` (views, search, filter, label, selectedText).
- It wraps, unlike the other screen bars: at phone width three lines (Segmented control with New task; grouping or status; search with "Only mine" and "Filter" as icons).

## How does the List view filter?
- `ws_channel_tasks` reads `q` (title search), `priority`, `person` (0 all, -1 nobody, a user id) and `mine`; `ws_tasks_for_person()` (tasks.php) narrows by person, tested in `tests/workspace_channel_board_tab_test.php`.

## Which address opens it?
- `?view=board` the board, `?view=list` the List view; `?view=tasks` (the Tasks tab's) the List view, or the Tasks tab of a room without the board. `tabAddress()` writes it.
