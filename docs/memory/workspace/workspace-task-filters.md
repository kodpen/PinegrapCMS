---
name: workspace-task-filters
description: My Tasks, planning board and Work Calendar filters (workspace.js): named department selects, scope buttons that follow a department pick, pressed week buttons, the announced task count
---

## How do the task filters say what they show?
- The department select on My Tasks (`startTasks`), the planning board (`startBoard`) and the Work Calendar (`startCalendar`) carries `aria-label` `t('department')`; `select()` sets no name itself.
- My Tasks scope buttons carry `data-scope` and `aria-pressed`; `setScope(name)` moves the fill and the attribute together.
- The board's 1-week/2-week buttons carry `aria-pressed` moved with `.active`.
- A visually hidden `role="status"` line between the My Tasks toolbar and the list gets `t('task_count', n)`, or `t('nothing_found')`, after each load whose answer is still the latest (the `serial` check). My time clears it.

## Why does a department pick move the scope?
- Picking a department sets the scope to `department`, so `setScope('department')` fills My departments; before, Mine stayed filled while the list showed the department's tasks.
- `ws_tasks_list()` reads `department_id` only in the department scope (`includes/workspace/tasks.php`), so clicking any other scope resets the select and `state.department_id` to 0; before, the select kept a department the list ignored.

## What is left alone?
- `taskRows()` and its "All clear" empty state are shared with a channel's task tab and the drawers; the status line says when a search finds nothing.

## What pins it?
- `tests/workspace_task_filters_test.php` (source pins). Not checked in a browser. Added in #3353.
