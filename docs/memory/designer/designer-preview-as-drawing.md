---
name: designer-preview-as-drawing
description: The drawing of the "Preview as" bar (steps PW3 and PW4, `designer-preview-as-rules.md`): the sheet, where each part is described, which board belongs to which step
---

## Where is the drawing?
- `designer/designer-preview-as-drawing/preview-as.html`, with a `-dark.jpg` and a `-light.jpg` picture; its `drawing.css` stands on top of `ui/look-sheet/sheet.css`.
- The bar and its menus are the designer's dark chrome in both pictures; the page under them is a stand-in for a site's page in its own theme. Only the sheet around them follows the theme.
- The shop, the records, the prices and the counts are examples; the labels are the steps'. Where the plan and the sheet differ the sheet wins (`workflow/architect-draws-new-part.md`).
- The bar, its one line and the narrow window: `designer-preview-as-drawing-bar.md`. The three menus: `designer-preview-as-drawing-choosers.md`. "Check all cases": `designer-preview-as-drawing-check.md`.

## Which board belongs to which step?
- PW3: P1 the Preview window with the bar (as it opens, the first-use line, a page with "Visitor" alone, a page with no bar), P2 the "Record" menu and its states, P3 the "Visitor" menu on three kinds of page, P4 the "Case" menu and what a case sets, P5 the bar after a choice (drawing, "Reset", a case that could not be drawn, a record that is gone).
- PW4: P6 "Check all cases" (running, the list, nothing found, stopped, a case that failed, the command palette row). PW4 adds the "Check all cases" row to the menu of P4.
- PW3 and PW4: P7 the window too narrow for the choosers (the one button, its sheet, the list as a sheet).
- PW1 and PW2 draw nothing: the case model and the server. How a masked address, e-mail or phone reads is PW2's; the sheet only says that they are masked.

## What does the drawing add to the designer's bars?
- Nothing. The bar is in the Preview window, which had no chrome but the "Hotspots" button; the page bar, the canvas bar and the panels' heads of the designer are untouched (`designer-light-and-simple.md`).
- "Check all cases" is the last row of the "Case" menu and a row of the designer's command palette, never a button of the bar.

## Which parts are drawn from a Design example, and which are new?
- The menus are the designer's own menus of today (rows, captions, rules, the tick); the search field and the segmented row are the designer's field and segmented control.
- "Reset" is a ghost small button; "Got it", "Try again", "Stop" and "Check again" are quiet small buttons. No filled and no danger button is drawn: nothing here writes or removes.
- The line under the bar follows "Result line" in tone (blue first use, teal all right, amber a record gone, rose could not be drawn) but is one flat line of the bar, with no tile.
- New: the bar itself, a chooser (name in grey, value in white, caret; blue tint when changed, dimmed when a case sets it), the list of "Check all cases" over the page's edge, and the amber outline on a place of the page.
