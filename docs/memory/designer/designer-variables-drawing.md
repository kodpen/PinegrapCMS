---
name: designer-variables-drawing
description: The drawing of a design's variables (plans/designer-variables.md, DV2 and DV3): the two sheets, their boards, the Variables tab, its "More" menu and the Options chip
---

## Where is the drawing?
- `designer/designer-variables-drawing/`: `variables.html` (parts A and B) and `questions-and-files.html` (parts C and D), each with a `-dark.jpg` and a `-light.jpg` picture; both sheets share `drawing.css` on top of `ui/look-sheet/sheet.css`.
- The designer's own windows (Settings, Options) are dark in both pictures; the New design window follows the theme.
- Sample names, colours and counts are examples; the labels are the steps'. Where the plan and a sheet differ the sheet wins (`workflow/architect-draws-new-part.md`).
- Parts C and D (the start questions, Export and Import): `designer-variables-drawing-questions.md`.

## Which board belongs to which step?
- DV2, part A: A1 the Settings window with the Colour fold open, A2 Type, A3 Spacing, A4 Shape, A5 "Add a variable" and the limit of 24, A6 the "Remove" question, A7 empty, A8 busy, A9 a refused value and the contrast warning, A10 narrow, A11 "More" open.
- DV3, part B: B1 a typed value beside a bound one, B2 a colour control open, B3 a size control open, B4 spacing and corners bound, B5 the first-use line.
- DV4, part C: C1 to C6. DV5, part D: D1 to D3.

## How is the Variables tab laid out?
- The Theme pane of Settings gains a segmented control "Look and palette | Variables"; today's pane is the first tab, unchanged.
- The tab row's only button is a quiet 26 px "More" at its right; "Ask me again", "Import" and "Export" are rows of its menu, never buttons of their own (`designer-light-and-simple.md`). In a design with no variables "Export" is grey.
- The tab is reached by Settings, Theme, "Variables" and by one row of the command palette, "Variables". No button is added to the page bar, the canvas bar or a panel's head.
- A fold per group ("Colour", "Type", "Spacing", "Shape"), one open at a time; the closed fold's second line is written from its values. A row is 44 px: sample, name, value field ("Light" and "Dark" for a colour), "Used in n places".
- "Used in n places" is words, never a button ("Not used yet", "Used in 1 place"). Until DV3 counts them the column is left out, not shown as zeros.
- The designer's own variables sit under "Your own" in their fold, each with a bin; "+ Add a variable" is quiet and opens a form in its place. No button of the pane is filled: the window's filled button stays "Publish".
- A refused value keeps the last good one on the canvas and shows one red line under the row; weak contrast is an amber line and a "1 warning" pill on the fold.
- The pane's purpose line says what variables are for, so the tab has no first-use line.

## How does Options show a bound control?
- A chip in the field's place, 30 px high: the swatch (or the group's icon for a size), the name, and "Detach" as a small button at the right. "Detach" puts the variable's value of that moment into the field.
- The list is 252 px wide under the control: a search field, "Variables", "Your own", then "Custom colour…" or "Custom value…". A colour control lists every colour; a size control "Base size" and the sizes of your own; spacing "Base unit", "Section rhythm" and your own; corners "Corners" and your own.
- The first time a person opens the list its top shows one line of what a variable is, with "Got it"; choosing a variable closes it too, and it never comes back.
- Nothing is added to the Options panel's head or a section's head.
- Not drawn: type-scale steps and base-unit multiples. If DV3 offers them they are rows of the same list under their variable.
