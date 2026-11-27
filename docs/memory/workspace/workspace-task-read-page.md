---
name: workspace-task-read-page
description: The task drawer's read page (TM3): an existing task as a page whose rows save at once through ws_task_save and the hidden form; New task's five fields and More
---

## What does an opened task show?
- The task drawer (`taskDrawer` in `assets/js/workspace.js`, shared by every screen) draws an existing task as a page, built by `assets/js/workspace_task_read.js` (`PGWsTaskRead.page()`), from the drawing `workspace-task-read-drawing.md`.
- Head: the number, the status control (a pill button with a caret, its menu the five statuses; "Open it again" first on a finished task), "Saved" after a save, the ⋯ menu (`taskMenu()`), Close. On a phone a Back arrow takes Close's place.
- Then the title changed in place (Enter saves, Esc gives up), the value rows (Responsible people, Starts, Due, Task priority, Department, Channel, Repeat, Remind, Estimate), the description with its checklist, the parts folded (Tagged records, Assistant, Time spent, Dependencies, Customer approval, the survey answer, Notes) and "Created by …".
- Channel is a link: a task's channel is not changed. A reader who may not change the task gets rows without a pencil or picker and no "Edit".

## How is one value saved?
- `taskDrawer.render()` still builds today's whole form; the page moves it into a hidden `.ws-tr-form`. A row's picker writes that form's field and `saveValue()` sends `taskDrawer.values()` to `ws_task_save`, the form's own action: the clash check, "Save it anyway" and the store's refusals are unchanged.
- After a save the page is drawn again from `ws_task_get` (head says "Saved"); a refusal draws it again from the task as it was, with the store's sentence in rose under the row (`state.why`).
- Repeat and Remind show their form boxes (`workspace_recurrence.js`, `reminderField()`) under the row with Save and Cancel; closing without Save draws the page again, so nothing half-changed rides along with the next save.
- The due time is the reminder box's time field (`reminderField()` hands it out as `time`), set from the date picker's "Due time".
- A tagged record added or removed saves at once (`taskDrawer.refsChanged`, called by `drawRefs()`).

## What is not a save of the task?
- A checklist tick (`ws_task_tick`) and an item added (`ws_task_item_add`), as before: whoever may tick may tick here. The description's lists are replaced by the task's own list (`checklist.own_html`, who ticked and when); a list carried by a channel message keeps today's work box.
- The description is the one value saved by a button: "Edit" opens the form's writing box with "Cancel" and "Save"; Ctrl+Enter saves; Esc asks first only when something was written.
- Status changes go through `taskDrawer.setStatus()`, which still asks a survey follow-up's outcome and confirms starting a task that waits for others.

## What does New task show?
- `PGWsTaskRead.newForm()` rearranges the same form: Title, Description, Responsible people, Due beside Task priority, then "More" (Starts, due time, reminder, repeat, estimate, department, Post the card in, Tagged records). "More" starts open when the place it was opened from set one of those (`defaults`).

## Keys and phone?
- Esc closes an open picker first, then the drawer; arrows move in a menu and by a day and a week in the month; focus goes back to the row when its picker closes. Folds keep open while the same task is drawn again.
- At 575.98 px and under, pickers are bottom sheets over a scrim with 44 px days and rows; the label column is 104 px.

## Where are the styles and texts?
- `backend.src.css`, the `.ws-tr-*` block before the channel's Tasks tab rules; the title size is the token `--pg-text-record-title`. Texts are the `tr_*` keys of `ws_js_strings()` (`includes/workspace/screen.php`).
- Shipped in TM3 of plan `workspace-tasks-modern`.
