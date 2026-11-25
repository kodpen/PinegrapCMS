---
name: designer-cleanup-drawing
description: The drawing of the designer's "Clean up" window (plan designer-cleanup, CU2 and CU3): boards U1 what opens it and U2 the window with its four lists and rows
---

## Where is the drawing?
- `designer/designer-cleanup-drawing/cleanup.html` on `ui/look-sheet/sheet.css`, with `cleanup-dark.jpg` and `-light.jpg`. CU2 builds boards U1 to U4; CU3 the foot of U3, the closing line and the row that stayed of U4. What is listed comes from the finder (`design-cleanup-finder.md`). Boards U3 and U4: `designer-cleanup-states-drawing.md`.

## What opens it? (U1)
- The command palette's row "Clean up", for whoever may save the design. No button in the page bar or the canvas bar, no menu row, no key (`designer-light-and-simple.md`).
- The publish check's folded list "Rules no page of the design uses" keeps its closing sentence and gains a quiet "Clean up" at its end. It opens the window over the Publish sheet with "Style rules" open; closing gives the sheet back.
- Nothing else opens it: not Publish, not a save, not a schedule.

## How is the window put together? (U2)
- The designer's own window, 720 px, dark. Head: "Clean up" and the design's name. One sentence under it: nothing is ticked or removed until the person says so, and a row the check is unsure of is not listed.
- Four lists, always in this order: "Style rules", "Files", "Shared components", "Variables". A list's head: the fold chevron, a box that ticks the whole list (a dash while only some rows are ticked), the name, the count and, for rules and files, the size its rows would free.
- The first list that has rows is open, the others folded; any number can be open. Past eight rows a list scrolls inside the window. Order: the largest first; components and variables by name.
- A row: the box, the name, one line of why it is thought unused, the size, "Show". A rule's name is its selector in a fixed-width face; its reason names the classes nothing uses, then its stylesheet and media condition ("Nothing uses the class “promo-ribbon”. · theme.css, from 992 px"). A file has a small picture or icon, a variable its value as a swatch.
- Foot: what is ticked in words, "Close", and "Remove", off while nothing is ticked.
