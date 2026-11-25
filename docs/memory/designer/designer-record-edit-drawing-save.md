---
name: designer-record-edit-drawing-save
description: Drawing of record edit in place, boards R4 to R6: the foot bar with "Save to the record" and "Discard", a field changed meanwhile, the canvas menu's row, the narrow window
---

## Where is the drawing?
- `designer/designer-record-edit-drawing/record-edit.html`; boards R1 to R3 (the switch, the frames, the controls) are in `designer-record-edit-drawing.md`.

## What is the foot bar? (R4, RE3)
- A bar of the designer's dark chrome along the Preview's foot, there only while a field is changed; the page gets that much room under it and "Hotspots" moves above it.
- One row: a green dot, "2 changes to Blue Mountain Coffee" (fields are counted, not keystrokes), then at the right "Discard" (ghost) and "Save to the record" (primary). Under it one line: "This changes the record wherever it is shown."
- "Save to the record" writes every changed field at once. While it runs the row reads "Saving 2 changes to …" and both buttons are off.
- Saved: the bar leaves, the page is drawn again from the record and one line under the "Preview as" bar reads "Saved to (the item's title). The item's history keeps what it was before."
- Refused by the record's own rules: the field's frame turns red with the reason the record's screen would give, the row reads "1 of 2 changes was refused by the record. Nothing was saved." with a red dot. Nothing is written; the other changes stay counted.
- "Discard" gives every field back to what the record holds and asks nothing.
- Leaving with changes not saved (the switch turned off, another record, visitor or case chosen, the window closed): the bar turns amber and asks "Save your 2 changes to … first?" with "Keep editing", "Discard", "Save to the record".
- If the item is gone or may no longer be edited, the line under the "Preview as" bar says so and the switch leaves.
- Apart from the design: nothing here is part of Save, Publish or the design's undo. Ctrl+Z inside a field undoes its typing only. A design edit that refreshes the Preview keeps the changed fields and the open one.

## What happens when someone else changed a field meanwhile? (R5, RE3)
- The save writes nothing. The bar turns amber and grows: "(Field) was changed on the record while you were editing. Nothing was saved yet."
- Under it two cards side by side: "Theirs, as the record holds it now" with the value and "Keep theirs"; "Yours" with the value and "Keep mine". "Discard" stays at the right end.
- One field at a time, counted "2 of 2" when there are more. After the last answer the save goes on by itself; there is no second press on "Save to the record".
- "Keep mine" saves the person's value over theirs; "Keep theirs" drops the change of that field.
- Asked only for a field both changed. A field only the other person changed is simply theirs: the page shows it when drawn again.

## What does the canvas get? (R6, RE4)
- The menu of an element bound to a field of a collection item gets one row, "Edit this record's content"; the command palette has the same row while such an element is selected.
- It opens the Preview on that record with the switch on and the frame of that field focused.
- For another kind of record, and where the person's change would wait for approval, the row reads "Open the record" and opens the record's own screen in a new tab. For whoever may not edit the item there is no row.
- No button is added to the page bar, the canvas bar or a panel's head (`designer-light-and-simple.md`).

## What changes in a narrow window? (R6, RE3)
- The one button of the "Preview as" bar opens its sheet; "Edit content here" is a row with the switch under the choosers, above "Reset". Where the link stands in its place the row is "Open the record".
- The foot bar puts its words on the first line and its buttons on the second.
- The text bar, the calendar and the picture menu open as sheets from the bottom. A tap on a frame edits it.
