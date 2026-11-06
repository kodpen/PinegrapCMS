---
name: workspace-task-zero-dates
description: A task's start or due date of 0000-00-00 is no date when the board or /postpone moves it and when the start-after-due check runs (ws_task_move_dates(), ws_task_starts_after_due())
---

## How are a task's empty dates read when it moves?
- `ws_task_date_value()` (includes/workspace/tasks.php) gives '' for NULL, '' and `0000-00-00`.
- `ws_task_move_dates($task, $new_due)` builds the fields for a new due date: with both dates the start moves by the same days (the task keeps its length); a start without a due date is cleared. The board's drag (`ws_task_move`) uses it as is; `/postpone` uses it but keeps a lone start where it was.
- `/postpone +2d` counts from the due date, or from today when the task has none (NULL or `0000-00-00`).
- `ws_task_validate()` refuses a start after the due date through `ws_task_starts_after_due()`, which ignores a missing date on either side.

## What did it fix?
- A `0000-00-00` date was truthy and a real date to `strtotime()`: `/postpone #12 +2d` on an undated task computed `-0001-12-02` and failed with "Write the date as day.month.year."; moving a task with a zero start shifted the start to year -1 and the move failed the same way; giving a start date to a task whose due date was `0000-00-00` was refused with "The task cannot start after it is due."

## Where is it tested?
- tests/workspace_task_zero_dates_test.php.
