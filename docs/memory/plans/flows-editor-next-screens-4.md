---
name: flows-editor-next-screens-4
description: Plan flows-editor-next, building in words (boards B1, B2): a step added at an arrow, several boxes chosen with their tools, copy and paste, frames, notes, Tidy
---

## How is a step added where it goes? (board B1)
- Pointing at an arrow shows a 20 px "+" at its middle; a dashed "+" stands after a box with a free outcome. Either opens the add list there, 280 px wide.
- The list: a search field ("/" focuses it anywhere on the canvas), "Often next" with three kinds, then the groups as chips with their counts, each opening its kinds.
- "Often next" is a fixed table in the script by the group of the box before (after a message: Wait, Condition, Task; after a Wait: Condition, a message, Task; else Wait, Condition, an e-mail). No use is counted.
- The chosen box lands between the two: the arrow is cut in two on the new box's first outcome, and the boxes after it move one column right when the gap is under 260 px. One undo step.
- An arrow's end dropped on the empty canvas opens the same list and connects the new box.

## How are several boxes handled? (board B2)
- Shift+click adds or removes a box; Shift+drag on the empty canvas draws a rectangle that chooses the boxes it touches; Ctrl+A chooses all. The panel is closed while several are chosen.
- Their tools float under them: "2 boxes", Duplicate, Copy, Frame, Delete. A drag moves them together; arrow keys move them a grid step.
- Duplicate and Copy keep the arrows and threads among them. Ctrl+C, Ctrl+V and Ctrl+D work; the copy is kept in localStorage, so it can be pasted into another flow; new ids as `duplicateNode()` makes them. A start box is never copied into a flow that has one.
- Each of these is one undo step.

## What are a frame and a note?
- A note: a card of text on the canvas (the store's `notes`: text, place, size), added from the strip's Note button, edited in place, resized at its corner, with its writer's first name and the day.
- A frame: a named rectangle behind boxes (`frames`: id, name, place, size, folded). "Frame" on several boxes draws one round them; its name is edited in place; a drag of its head moves it with the boxes inside; folded it is one row with its name and count.
- Neither is read by a run or by the check. Limits: 200 notes (today's), 30 frames.

## What does Tidy do?
- It places every box by `ws_flow_auto_layout()` with no box kept (columns by the longest way in), frames drawn again round their boxes, notes left. One undo step; with several boxes chosen only those are placed.

Related: docs/memory/plans/flows-editor-next.md, docs/memory/plans/flows-editor-next-screens-2.md, docs/memory/plans/flows-editor-next-screens-3.md
