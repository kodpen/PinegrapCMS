---
name: workspace-personal-plan-roadmap
description: The roadmap of a person's own plan (PC5): dated steps in ws_personal_plan_steps, the bar and its progress, "Make a task" and a step that follows its task
---

## Where does the roadmap live?
- `includes/workspace/personal_plan_steps.php` (loaded by `bootstrap.php`), on the table `ws_personal_plan_steps` of step 11.0.201 (`workspace-personal-plans.md`): `position`, `title`, `day` (`0000-00-00` none), `done_at`, `task_id`. No column was added.
- With `personal_plans.php` the only file naming the plan tables (`tests/workspace_personal_plans_test.php`). Every read starts from `ws_personal_plan()`, the gate; save and "Make a task" ask `ws_personal_plan_can_edit()` (the owner alone).

## What does the drawer show?
- Under the note: "Roadmap" with "2 of 5 steps done", a violet bar filled by the share done, each dated step a dot on its day between the plan's first and last day (filled when done), then the steps: done mark, title, day, "Make a task", remove. "Add a step"; a row with no title is dropped on save. At most 50 steps (`WS_PERSONAL_PLAN_STEP_MAX`).
- Save writes the plan, then the steps (`ws_personal_plan_steps_save`, the list sent is the roadmap). A reader the plan is shared with (PC4) sees it all, unchangeable.
- On the calendar a plan with steps shows "2/5" on its chip and "My plan · 2 of 5 steps done" in the list (`ws_personal_plans_progress_place()`).
- Script `assets/js/workspace_my_calendar_roadmap.js` (`window.PGWsRoadmap`), in the `workspace.min.js` bundle before `workspace_my_calendar.js`.

## How does a step follow a task?
- "Make a task" (on a saved plan, a step with a title) saves the drawer, closes it and opens the task drawer filled: the step's title, its day as due date, the person as assignee. When the task is saved, `ws_personal_plan_step_task` ties it (the task must be one the owner may see) and the plan drawer opens again.
- A tied step is done while its task's status is `done` (cancelled is not done), worked out on every read (`ws_personal_plan_steps_state()`, pure); its own mark is then not taken from the drawer. A deleted task leaves the step its own mark.
- The task stays the team's: its number and status (a pill that opens it) are shown only to a reader `ws_can_see_task()` lets through; another reader sees "Follows its task".

Tests: `tests/workspace_personal_plan_steps_test.php`.
