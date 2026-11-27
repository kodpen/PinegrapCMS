---
name: workspace-my-tasks-screen
description: My Tasks (TM1-TM4): open tasks under Overdue, Today, This week, Later, No date labels that fold, due_group from ws_task_due_group(), the one-line row; where add line, Board, drawer live
---

## When is the list gathered by date?
- With "Open tasks" as the status and no search typed, in every view (Mine, Given by me, My departments, Everyone): the rows stand under `.pg-group-label` buttons with their count, in this order: Overdue, Today, This week, Later, No date. A group with no task is not drawn.
- Finished tasks, All tasks and a search draw one list without labels, in the server's order. My time keeps its own view (`workspace_task_time.js`) and the bar's filters (`workspace-task-filters.md`).
- `startTasks()` in `assets/js/workspace.js` hands the list to `PGWsMyTasks.list(container, tasks, grouped, helpers, parts, onChange, fresh)` in `assets/js/workspace_my_tasks.js` (in `workspace.min.js`); `parts` lends the row's menu and progress bar; `fresh`, a task id, opens its group (`workspace-my-tasks-add-line.md`). Without the part file it falls back to `taskRows()`.

## Where does a task's group come from?
- The `ws_tasks` action adds `due_group` to each task: `ws_task_due_group($due, $today, $first_day)` in `includes/workspace/tasks.php`, a pure function (`tests/workspace_task_due_group_test.php`).
- Values: `overdue` (due before today), `today`, `week` (after today up to the last day of this week), `later`, `none` (no date, `0000-00-00` or not a date). An unknown value stands under No date in the list.
- Today is the reader's day: `ws_task_reader_today()`, the user's own time zone when `pg_time_uses_user_zone()` says so, else the site's. The week starts on the language's first day (`pg_list_period_week_first_day()`: Monday for Turkish, Sunday otherwise).
- `ws_tasks` passes that day as the `today` filter of `ws_tasks_list()`, so the due text ("Today", "Tomorrow", the overdue form) and `overdue` read the same day as the group. Other callers (API, board, channel) leave `today` out and read the site's day.

## What is kept per browser?
- The folded groups: `localStorage` key `ws-tasks-folded`, an object of group keys set to 1. A label folds or opens on a click, Enter or Space (a real button with `aria-expanded`, `aria-controls` and a chevron); without storage the groups open again next time.

## What does a row show?
- `.pg-list-row.ws-mt-row`, one line: the Mark done circle (`pg-icon-button-sm`, a tick on hover or focus; a closed or read-only task shows its state there), the number in `--pg-fg-3`, the title (`.pg-list-row-title`), then the due text (rose, `--pg-tone-rose-text`, when overdue), the priority pill only for High (`pg-tone-amber`) and Urgent (`pg-tone-rose`), the people's initials (no photos, at most three).
- A second line (`.ws-mt-sub`) only for the agent channel, "Made by <application>" and the checklist's progress.
- Left out of the row, still in the drawer and `taskRows()`: status dot and label, estimate, time and link tags, assistant tag, notes count, repeat icon.
- Click or Enter opens the drawer; right-click is `taskMenu()`. Under 576 px the title takes its own line, the rest under it. Finished and cancelled tasks are dimmed (`is-read`).

## What else stands on the screen?
- Mine's "Add a task…" line (TM2): `workspace-my-tasks-add-line.md`. The List / Board layouts (TM4): `workspace-my-tasks-board.md`; the groups and the add line are List only.
- A task opens as a page, New task as a short form (TM3): `workspace-task-read-page.md`, drawn in `workspace-task-read-drawing.md`.
- A channel's task cards and a record's window keep `taskRows()` (`workspace-channel-panes.md`).

## Why, and what was left out?
- Owner (2026-11-27, agent channel message 14812): the Workspace's own screens modernised: My Tasks was one flat list of tall rows and a task a long form. Built in TM1 to TM4 (#12143 the last).
- Tasks under a task, own fields on a task and saved views each need a table or a column: the owner's to ask for.
