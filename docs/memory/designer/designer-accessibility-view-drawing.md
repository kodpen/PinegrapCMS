---
name: designer-accessibility-view-drawing
description: The drawing of the designer's accessibility view (built by AV2 #11657 and AV3 #11685): boards V1 the View row and the card, V2 Tab order, V6 folded, nothing found, narrow
---

## Where is the drawing?
- `designer/designer-accessibility-view-drawing/accessibility-view.html` on `ui/look-sheet/sheet.css`, with `accessibility-view-dark.jpg` and `-light.jpg`. Six boards: V1, V2 and V6 here; V3 Headings, V4 Names and V5 "See as" in `designer-accessibility-view-marks-drawing.md`.
- AV2 builds V1 to V4 and V6, AV3 builds V5 and V6. What is counted and named comes from the reader (`accessibility-reader.md`); a fault's sentence and "Fix" are the audit's (`editor-audit-panel.md`).

## How is the view turned on? (V1)
- "Accessibility view" is a switch row after the View menu's other switches, counted in the View button's badge, with "Show accessibility view" / "Hide accessibility view" in the command palette. No button in the canvas bar, no key (`designer-light-and-simple.md`). Design mode only.
- On or off, the kind chosen and the fold are remembered in the browser as the View menu's other switches are. "See as" is not: it is "Off" whenever the designer opens.

## What is the card? (V1)
- 258 px, in the canvas's bottom right corner, 12 px from its edges, over the page where there is no room beside it. Head: the name, a chevron that folds it to its head, a close button that turns the view off as the switch does.
- Three rows, "Tab order", "Headings", "Names", are one choice: one kind of mark at a time, "Tab order" at the start. Pressing the chosen kind again clears the canvas of marks, so a filter can be looked at alone.
- Each row says what it found: the count, and in amber with the warning icon the number of marked places. The numbers are worked out when the view goes on and again when the hand rests after a change.
- "See as" is a select under the three, not one of them: a filter stays on whatever kind is chosen.

## Which colours do the marks use? (V1)
- One colour for every mark, fuchsia: apart from the blue of what is chosen, the green of shared components and the indigo of system widgets. What is marked as wrong is amber, dashed, and always carries the warning icon or a word; colour is never the only sign.
- The marks are drawn in the canvas's own layer and take no room on the page. Nothing enters the tree or the Preview.

## How is the tab order drawn? (V2)
- A number, 20 px high, sits on the top left corner of every element the keyboard reaches, half outside it. A 1.5 px line joins the numbers' middles in the order of the stops, under the numbers and over the page.
- A step that jumps back on the page (the reader's `back`, `jumps()`): the number it arrives at is amber and reads "2 · jumps back", and that step's line is amber and dashed.
- A positive tabindex is written on its number ("1 · tabindex 1"): it is why the order differs.
- Not reached, no mark: a hidden or off element, a closed fold's content, a negative tabindex. Where a script moves focus the view says nothing.
- Under the pointer a number grows a ring and a tip names the stop as a screen reader would: "8 · Link “See the timetable”". A click chooses the element.
- The card's foot: "15 stops. One step jumps back on the page." A jump is marked, not listed as a fault: the audit has no rule for it.

## What do the other states look like? (V6)
- Folded: the head alone, with the kind chosen in grey beside the name. The marks stay.
- Nothing found, one sentence in the card's foot: "The keyboard reaches nothing on this page.", "This page has no heading.", "This page has no region, picture or unnamed control."
- Under 768 px the card is a bar at the canvas's foot: the kind and "See as" as two selects, the count of marked places, the close button. The outline is not shown there.
