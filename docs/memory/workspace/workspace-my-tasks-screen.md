---
name: workspace-my-tasks-screen
description: My Tasks list (TM1): open tasks under Overdue, Today, This week, Later, No date labels that fold (ws-tasks-folded), due_group from ws_task_due_group() on the reader's day, the one-line row
---

## When is the list gathered by date?
- With "Open tasks" as the status and no search typed, in every view (Mine, Given by me, My departments, Everyone): the rows stand under `.pg-group-label` buttons with their count, in this order: Overdue, Today, This week, Later, No date. A group with no task is not drawn.
- Finished tasks, All tasks and a search draw one list without labels, in the server's order. My time keeps its own view (`workspace_task_time.js`).
- `startTasks()` in `assets/js/workspace.js` hands the list to `PGWsMyTasks.list(container, tasks, grouped, helpers, parts, onChange)` in `assets/js/workspace_my_tasks.js` (in `workspace.min.js`); `parts` lends the row's menu and progress bar. Without the part file it falls back to `taskRows()`.

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
- Left out of this row, still in the task drawer and in `taskRows()`: the status dot and label, the estimate, the time and link tags, the assistant tag, the notes count, the repeat icon.
- A click or Enter opens the task drawer; the right-click menu is `taskMenu()` with the direct-message lines. On a phone (under 576 px) the title takes its own line and the rest goes under it. Finished and cancelled tasks are dimmed (`is-read`).

## Which lists keep the old rows?
- A channel's Tasks tab and a record's window keep `taskRows()` (`workspace-channel-panes.md`).
