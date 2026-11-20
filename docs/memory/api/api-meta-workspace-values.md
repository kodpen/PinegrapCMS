---
name: api-meta-workspace-values
description: The workspace object on GET /meta: default_task_minutes (WS_DEFAULT_TASK_MINUTES) and day_minutes (WS_DAY_MINUTES), for a team device's task form (issue #10345, PR #10388)
---

## What does `workspace` on GET /meta say?
- `default_task_minutes`: `WS_DEFAULT_TASK_MINUTES`, the minutes the planning board counts for a task with no estimate (`estimate_minutes` 0); 60 when unset.
- `day_minutes`: `WS_DAY_MINUTES`, the working day a person's profile falls back to; 480 when unset.
- Both constants are read in `init.php`; no route and no schema step.

## When is it there?
- Only while the Workspace is on (`api_meta_workspace()` returns null and the key is left out otherwise). A client without the key keeps the wording "the site's default" without a number.

## Where is it declared and tested?
- `api_meta_workspace_values()` (pure) in `includes/api/resources/meta.php`; the object `MetaWorkspace` (`api_meta_workspace_schema()`, listed in `api_openapi_objects()`), `'workspace' => 'MetaWorkspace?'` in `api_meta_schema()`. The task routes' `estimate_minutes` text names it.
- `tests/api_meta_workspace_test.php`.
