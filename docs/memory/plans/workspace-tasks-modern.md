---
name: workspace-tasks-modern
description: Plan (owner 2026-11-27, message 14812): My Tasks modernised: tasks gathered by date in one-line rows, a line that adds a task, the task opened to read, a board layout; steps TM1 to TM5
priority: 6
---

## What is asked?
- Owner (2026-11-27): the Workspace's own screens modernised (`plans/workspace-areas-modern.md`, whose rules every step here keeps). This plan is My Tasks (`workspace_tasks.php`, `startTasks()` and `taskRows()` in `assets/js/workspace.js`).

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- TM1 (M) The list gathered by date. With the open tasks shown and no search typed, the rows stand under group labels (`.pg-group-label` with a count): Overdue, Today, This week, Later, No date. A label folds its rows on a click or Enter; what is folded is kept per browser. The group comes from the server with each task (a pure function on the due date and the reader's day, with its test).
  - A row is one line: the Mark done circle first, the number in the quiet colour, the title, then the due text (rose when late), the priority pill only for High and Urgent, the people's initials. A second line is drawn only for what it alone says (the agent channel, "Made by", progress). Closed tasks and a search keep one list without labels.
  - The channel's Tasks tab and a record's window keep their rows (`workspace/workspace-channel-panes.md`).
- TM2 (S) A line that adds. Above the first group of the "Mine" view stands a field "Add a task…": Enter makes a task with that title, assigned to the reader, through the action New task uses; it appears in its group and the field is ready for the next one. Tab from a typed title opens the New task drawer with the title filled in. A refusal is said under the field in the store's words.
- TM3 (M, drawing) The task opened to read.
  - The drawer of an existing task starts as a page, not a form: the number and the status control in its head, the title as large text changed in place, then value rows (assignee, due date and time, priority, channel, repeat, reminder, estimate), each changed by its own picker and saved at once, the description as it reads with its checklist ticked in place, and the further parts (links, time, approval, history) folded under their names.
  - New task keeps a form, cut to title, description, assignee, due date and priority, with the rest under "More".
- TM4 (M) A board layout. The bar gets a layout control of two icons, List and Board (kept per browser).
  - Board draws the same tasks as cards in a column a status through `mountSource()` of `workspace_board_channel.js`, as a table's board does (`workspace/workflow-table-board.md`); a card dragged to another column, or moved by its menu's "Move to…", writes the status the way the drawer does and goes back with the store's reason when refused. On a phone the columns scroll sideways, one a screen.
- TM5 (S) Close-out: new `workspace/workspace-my-tasks-screen.md` (the groups, the row, the add line, the layouts), `workspace/workspace-channel-panes.md` and the task drawer's notes corrected, this file deleted.

## What is left out?
- Tasks under a task, own fields on a task and saved views: each needs a table or a column, so each is the owner's to ask for.
- The "My time" view (`workspace_task_time.js`) and the filters of the bar (`workspace/workspace-task-filters.md`) are unchanged.
