---
name: designer-cleanup-states-drawing
description: The drawing of the designer's "Clean up" window, boards U3 and U4: "Show", ticked rows and the foot, what is not listed, the closing line after "Remove", the states
---

## Where is the drawing?
- Boards U3 and U4 of `designer/designer-cleanup-drawing/cleanup.html`; U1 and U2, and which PR built which board, are in `designer-cleanup-drawing.md`.

## What do "Show" and the ticks do? (U3)
- "Show" opens what the row is, in place under it, and turns into "Hide": a rule's text, a picture (another kind of file opens in a new tab), a shared component drawn as it looks. A variable has its value in its row and no "Show".
- While rows are ticked the foot says the count, the size and what can be brought back: "Rules and variables can be brought back from the snapshot “Before clean-up”; files and shared components leave as their own screens remove them." The button reads "Remove 10". There is no second question.
- Where the finder cannot tell for a stylesheet (a class set by a value, a sheet it cannot read), a line at the list's end names the sheet and why.
- A list with nothing stays in its place with the count 0 and "Nothing unused." when opened.

## What is seen after "Remove", and in the other states? (U4)
- While it removes, the button shows a spinner and "Removing…" and the rows are off. The window stays open with the lists read again.
- The closing line is the Result line of the Design examples, over the lists: "Removed 10: 5 style rules, 2 files, 1 shared component, 2 variables. 1.2 MB lighter.", then where the design as it was is kept ("Before clean-up · 25 November" in History, under Local snapshots) and that "Load" brings the rules and the variables back.
- A row that could not be removed stays listed with the reason and a warning icon; the closing line says "Removed 9 of 10."
- Opening: one spinner and "Looking through the design…"; the lists come together. A read that fails says so with "Try again".
- Architect (2026-11-25): with unsaved changes the lists are not drawn, as the finder reads the saved design and could call unused what was just put to use: "Save the design first" and "Save and look again", which saves as Save does and then reads.
- Nothing unused: "This design has nothing unused" and "Close". Under 768 px the window fills the screen; a row's size and "Show" go under its text.
