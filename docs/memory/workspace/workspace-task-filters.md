---
name: workspace-task-filters
description: My Tasks, planning board and Work Calendar filters (workspace.js): named department selects, My Tasks' views that follow a department pick, pressed week buttons, the announced task count
---

## How do the task filters say what they show?
- The department select on the planning board (`startBoard`) and the Work Calendar (`startCalendar`) carries `aria-label` `t('department')`; `select()` sets no name itself. On My Tasks it stands behind "Filter", named by its field's label (`workspace-screen-bar.md`, #11104).
- My Tasks' views (`barViews()`: a `pg-seg`, a select on a narrow bar) carry `aria-pressed`; `setScope(name)` presses the view with `views.set(name)`.
- The board's 1-week/2-week buttons carry `aria-pressed` moved with `.active`.
- A visually hidden `role="status"` line between the My Tasks toolbar and the list gets `t('task_count', n)`, or `t('nothing_found')`, after each load whose answer is still the latest (the `serial` check). My time clears it.

## Why does a department pick move the scope?
- Picking a department sets the scope to `department`, so `setScope('department')` presses My departments; before, Mine stayed filled while the list showed the department's tasks.
- `ws_tasks_list()` reads `department_id` only in the department scope (`includes/workspace/tasks.php`), so choosing any other view resets the select and `state.department_id` to 0; before, the select kept a department the list ignored.

## What is left alone?
- `taskRows()` and its "All clear" empty state are shared with a channel's task tab and the drawers. My Tasks draws its own rows (`workspace-my-tasks-screen.md`) and its own empty screen (`emptyScreen()`, "Nothing found" with Clear when filtered); the status line says when a search finds nothing.

## What pins it?
- `tests/workspace_task_filters_test.php` (source pins). Added in #3353; the bar since #11104.
