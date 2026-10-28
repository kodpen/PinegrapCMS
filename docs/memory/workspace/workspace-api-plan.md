---
name: workspace-api-plan
description: GET /workspace/plan: what a cell and a row carry (task entries with their flags, plan items, the undated count) and where they come from
---

## What does a row of GET /workspace/plan carry?
- `user_id`, `name`, `unscheduled` (open tasks with neither start nor due date, counted by `ws_plan_grid()`) and `days[]` (since #2310).

## What does a day carry?
- `date`, `workday`, `leave`, `holiday`, `capacity`, `minutes`, `load`, `tasks` (bare task ids, kept for old clients).
- `task_items[]` (#2310): `id`, `minutes` (the day's share of the estimate), `due` (the window's last day), `upcoming` (a projected copy of a repeating task, under the series task's id), `repeat` (`ws_recurrence_label()`), `moved_from` (the day a moved copy came from), `waits_for` (the task number of the overdue newest copy it waits for).
- `events[]` (#2310): the plan items placed on the day, holidays and leave included: `id`, `kind`, `title`, `time` (`''` all day), `minutes` that day, `meeting_url`.

## Where do the values come from?
- Only from the cells `ws_board()` / `ws_plan_grid()` build for the panel board; `ws_api_plan_present()` maps a board row and repeats none of the grid's logic (`ws_event_applies()`, `ws_event_minutes_on()`, projections). Why: a client placing `GET /workspace/plan-items` on days itself guessed repeat copies wrongly (owner issue #2081).
- A new field on the board's cell reaches the API only when added to the presenter and `ws_api_plan_row_schema()`; `tests/workspace_api_plan_test.php` checks the two agree.
