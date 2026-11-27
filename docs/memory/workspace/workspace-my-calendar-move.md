---
name: workspace-my-calendar-move
description: My calendar's drag (CM3): which plans and tasks lift, ws_personal_plan_move and ws_task_move with an hour, the repeat question, Undo, "Move to…" on the item's menu
---

## What may be moved?
- A plan of the reader's own (the placed event's `mine`) and a task with `can_move` (a due day, and `ws_can_edit_task()`); never a coming copy of a repeating task. A booking, an own-iCal event, a plan item and a plan shared by somebody else do not lift and keep the day's menu.
- Month: dropped on another day. Week: on a day of the strip, or on a day's hours, where the chip's top gives the hour (`minuteAt()`). Only with a mouse (HTML drag): on a phone and from the keyboard the item's right-click menu (long press, the menu key) has "Move to…" with a date field.

## Which save does a move go through?
- A plan: action `ws_personal_plan_move` (plan_id, day of the time, days, hour, mode) calls `ws_personal_plan_move()` in `personal_plans.php`, which reads the plan through the gate, moves its days and times with `ws_personal_plan_moved()` (pure, tested in `tests/workspace_my_calendar_move_test.php`) and sends everything else the plan holds to `ws_personal_plan_save()` as the drawer does. A series ending on a day ends as many days later.
- A plan moved to an hour keeps its minutes and its length; a whole-day plan moves by days alone; a later day of a plan over several days moves it by days alone. A roadmap's steps keep their days, as in the drawer.
- A task: `ws_task_move`, the Planning Board's move (start day shifted along, the clash question first, `h.moveTask()` from `featureHelpers()`). On the hours its due time takes the hour, minutes kept (`ws_task_moved_time()`, `tasks.php`); its block is the half hour before the due time, so the hour is read 30 minutes below the block's top.

## What does a repeating plan ask?
- "This one" or "This and the following", under the drawer's question (`repeat_change_q`), before anything is saved; the answer goes as `mode` to the plan's own save (`workspace-personal-plan-repeat.md`).

## What does Undo do?
- The saved notice ("The plan is saved." / "The task was saved.") holds Undo for 8 seconds. A plan's Undo is the move back that the action answered (`undo`: the saved plan, the days back, the old hour), so a time moved alone stays a plan of its own back at its old place. A task's Undo moves it back to its old due day and time.

## Where is it built?
- `assets/js/workspace_my_calendar_move.js` (`window.PGWsMyCalendarMove`, in the workspace bundle before `workspace_my_calendar.js`): `kit(h, s, reload, dayMenu)` gives `lift()`, `menu()`, `target()`. Drop places carry `data-ws-date`; classes `.ws-mycal-movable`, `.ws-mycal-dragging`, `.ws-mycal-drop` in `backend.src.css`.

Related: docs/memory/workspace/workspace-my-calendar.md, docs/memory/workspace/workspace-my-calendar-week.md
