---
name: workspace-priority-badge
description: The task priority pill of the Workspace screen: one priorityBadge() for Today, My tasks, a channel's Tasks tab and the record window; high in warning, urgent in danger (#5188)
---

## How is a task's priority drawn?
- `priorityBadge(priority)` in `workspace.js` draws `span.ws-priority-badge.ws-priority-badge-<priority>` with the `BOOT.priorities` label. `homeDue()` (Today) and `taskRows()` (a channel's Tasks tab, My tasks, the record window) both call it, for high and urgent only.
- CSS in `backend.src.css`: `.ws-priority-badge` is the warning-tinted pill (high), `.ws-priority-badge-urgent` the danger-tinted one.

## Why one helper?
- `taskRows()` drew high and urgent both as a red `badge theme-danger` while Today drew high yellow and urgent red, so the lists disagreed and an urgent task looked like a high one (#5188). The Today-only class `ws-home-priority` was renamed when it became shared.
- `ws-priority-<p>` (without `-badge`) is a different class: the task card's priority modifier in `includes/workspace/tasks.php`.

Test: `tests/workspace_priority_badge_test.php`.
