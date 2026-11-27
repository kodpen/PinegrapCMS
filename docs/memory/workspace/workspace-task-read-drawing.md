---
name: workspace-task-read-drawing
description: The drawing of a task opened to read in My Tasks (step TM3 of plan workspace-tasks-modern): one sheet of eight boards, the read drawer, its states, New task, phone width, sizes and keys
---

## Where is the drawing?
- `workspace/workspace-task-read-drawing/`: `task.html` with `task-light.jpg` and `task-dark.jpg`. The sheet draws itself with a script; `?theme=dark` gives the dark look, `?f=A` (B to H) one board.
- Look A's tokens stand in its own style block; the fonts come from `pinegrap/assets/fonts/`.
- Where words and the sheet differ the sheet wins (`workflow/architect-draws-new-part.md`). Sample tasks, names, dates and counts are examples.

## Which board says what?
- A, today: the form `taskDrawer.render()` builds (`assets/js/workspace.js`), fields and parts in their order.
- B, the read drawer: head, title, value rows, description with its checklist, the parts as fold rows, no foot.
- C, a value row's states: at rest, pointed at, focused, picker open (people, date, menu), saving, saved, refused.
- D, title and description changed in place; a checklist item ticked. E, the status menu, a finished task, "Time spent" open.
- F, New task closed and with "More" open. G, phone width (390 px). H, sizes three times enlarged and the keys.

## What comes from a Design example and what is new?
- From the Design examples: the "Drawer" and "Form drawer", "Value rows", the fold row, the menu, the pill, the Workspace writing box, the date field's month.
- New and small: the status control (a pill-shaped button with a caret), a value row that opens a picker under its value, the head's "Saved" note, the people list with a search, the date picker as a bottom sheet.

## What does the sheet say that the plan's words do not?
- Labels are today's: "Responsible people" (several, not one assignee), "Task priority", "Remind", "Estimate", "Time spent", "Dependencies", "Customer approval". The number reads "T-128".
- Rows beyond the plan's list: "Starts" and "Department". "Channel" is a link: a task's channel is not changed today. Parts beyond it: "Tagged records", "Assistant", "Notes". A task has no history list: none is drawn.
- Measures: drawer 544 px as today, 20 px at the sides; head 56 px; status control 28 px, radius 14; title 22/28 px, weight 650; value row 44 px or more, label column 132 px (104 on a phone), 12 px to the value; fold row 48 px; picker 242 px wide, radius 12.
- Tones: To do gray, In progress blue, Waiting amber, Done green, Cancelled rose (the channel board's dots). Priority pills: High amber, Urgent rose, Low and Normal gray. A late due date is rose, in the words TM1's row prints; a finished task's due date is ink.
- An empty row reads quiet: "None", "No date", "Does not repeat".
- Saving: the new value quiet beside a small spinner; saved, the head says "Saved"; refused, the old value is back with the store's sentence under the row in rose.
- The title saves on Enter, Esc restores it; the hint reads "Enter saves · Esc gives up". Only the description is saved by a button: "Save" and "Cancel" end the writing box's tool row.
- A finished task: green control, title not struck through, rows still open their pickers. "Open it again" is the status menu's first row.
- New task: Title, Description, Responsible people, Due beside Task priority; "More" holds Starts, Due time, Remind, Repeat, Estimate (hours), Department, Post the card in, Tagged records. Foot: "Cancel" quiet, "Create the task" filled.
- Phone: a Back arrow for Close, pickers as bottom sheets with 44 px days; rows and checklist items 44 px.

## Which states have no board?
- The ⋯ menu open: today's task menu (copy the number or the link, put off).
- The "Repeat", "Remind", "Estimate" and "Department" pickers: a menu or a small field under the value, as board C's.
- "Tagged records", "Assistant", "Customer approval" open: what today's drawer holds. A follow-up task's survey answer: one more fold row.
- A task the reader may not change: rows with no pencil, picker or "Edit".
- Loading, a failed load: as today.
