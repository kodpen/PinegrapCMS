---
name: designer-record-edit-drawing
description: Drawing of record edit in place, boards R1 to R3: the switch "Edit content here" of the "Preview as" bar, the dashed frames and their five states, each field's control in place
---

## Where is the drawing?
- `designer/designer-record-edit-drawing/record-edit.html` with `record-edit-dark.jpg` and `record-edit-light.jpg`; six boards. R4 to R6 are in `designer-record-edit-drawing-save.md`.
- It continues the "Preview as" sheet (`designer-preview-as-drawing.md`): the bar, the menus and the foot bar are the designer's dark chrome, the page under them is the site's.
- Steps RE3 and RE4 of `plans/designer-record-edit-in-place.md` build it (`workflow/screen-follows-drawing.md`).

## Where is the switch "Edit content here"? (R1, RE3)
- The last thing of the "Preview as" bar, at its right end, after the choosers; "Reset" keeps to its left. On, the label and the switch turn green.
- Shown only when the record drawn is a collection item and the person may edit that item on its own screen. A page that draws no collection item has no switch.
- Off every time the Preview opens: the record, the visitor and the case are remembered for the page, the switch is not.
- Where the read says the change would wait for approval, and for a product, an event or another kind of record, the link "Open the record" stands in its place and opens the record's own screen in a new tab (RE3 draws the first, RE4 the others).
- Tab reaches the switch after the choosers, Space turns it; then Tab goes through the frames in the page's order, Enter edits one, Escape leaves it as it was.
- While on, a click on a frame edits; a click anywhere else does what it does in the Preview. A framed text inside a link is edited, not followed.

## What do the frames look like? (R2, RE3)
- A dashed 1.5 px outline in the content colour, the green of an editable area (`.sd-editable-area`), around each spot RE2's list gives (`record-edit-in-place-spots.md`). It takes no room and is never part of the page saved or published.
- Five states: at rest dashed; under the pointer or the focus solid with the field's name in a small green tag above it; being edited solid 2 px on a white ground with a soft green ring; changed and not saved solid with a green dot at its upper right corner; refused 2 px red with the reason in a red tag under it.
- The name is the field's label as the collection's form has it.
- Not framed: the design's own words, the items of a list, another record's values, an address, a summary cut from a longer text, a field whose control is none of the five.
- A field drawn twice on the page is framed in both places; typing in one changes the other as it is typed.
- The dot stays until the field is saved or discarded; a field put back to what the record holds loses it.

## How is each kind of field edited? (R3, RE3)
- A line of text: typed where it stands. Enter or a click outside keeps it, Escape gives back what was there; no line break, a paste comes in as plain text.
- Formatted text: a small dark bar above the frame with Bold, Italic, Link, a bulleted and a numbered list, nothing more. Enter starts a paragraph. What the field holds beyond these (a table, a picture) stays as stored.
- A number: digits and the site's decimal mark. A unit beside it is the design's and is outside the frame.
- A date: the designer's calendar opens under the frame; the page shows the date in its own form. A field of date and time is not one of the five.
- A picture: the frame shows "Change picture" in its middle, which opens a small menu: "Choose a picture…" (the designer's picture chooser), "Alternative text" (a text field, only when the page binds the alternative text to a field), "Remove the picture" (only when the field may be empty). A replaced file is never deleted.
- Refused in place: a required field left empty ("This field cannot be empty.") or a number that is none ("A number is needed.") turns the frame red with the reason under it. It is not counted as a change until put right; Escape gives back the old value.
