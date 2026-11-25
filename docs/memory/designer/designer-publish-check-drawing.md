---
name: designer-publish-check-drawing
description: The drawing of the Publish check (PC4 #11577, PC5 #11607): the sheet, its seven boards and what each step builds from it
---

## Where is the drawing?
- `designer/designer-publish-check-drawing/`: `publish-check.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`.
- The Publish sheet is the designer's own window and is dark in both pictures. It is drawn as it is today (head, pick line, rows, hold note, foot with its four buttons), 620 px wide instead of 500.
- The sample pages are "Home", "Shop", "Summer sale", "Contact", "About us". Names, counts, paths and the weight figures are examples; the labels are the steps'.

## Which board belongs to which step?
- PC4, part "The results and the findings": P1 the result of each checked page (busy at opening, the three results); P2 a row opened into the five groups, a check that could not run, the designer after a press on a path; P3 a blocking page (the row, the four blocking findings, "Publish anyway" with its reason); P4 narrow. Details: `designer-publish-check-drawing-results.md`.
- PC5, part "Fix, Show me, Ignore and the weight": P5 "Fix" before and after, "Show me"; P6 "Ignore on this page" (the reason, the ignored list); P7 the Weight group with its four measures, its findings and the list of unused rules. Details: `designer-publish-check-drawing-findings.md`.

## What does the drawing add to the designer's bars?
- Nothing. No button is added to the page bar, the canvas bar or a panel's head (`designer-light-and-simple.md`), and no menu row or palette row: the check lives inside the Publish sheet, which opens as today.
- Over the canvas only one line, and only after a press on a finding's path or on "Show me": the finding's sentence with "Back to Publish". It goes with Escape or with the next selection.
- The buttons inside the sheet are quiet and small: "Fix", "Show me", "Try again", "Undo", "Ignore", "Cancel", "Stop ignoring", "Publish anyway". The solid button stays "Publish selected".

## Which states are drawn?
- Busy: "Checking…" in a row's result place and "Checking n pages…" in the foot (P1). Filled: P1, P2. Nothing found: a group says "Nothing found"; a page with no finding says "Ready".
- Error: a check that could not run (P2). Refused: a blocking page (P3); "Publish selected" off while a reason is missing (P3). Narrow: P4.
- Not drawn: a page not saved yet. The groups that read the server's render say in one line that they run once the page is saved, as the audit panel does (`editor-audit-panel.md`).

## Which parts are drawn from a Design example, and which are new?
- From the Design examples: the Result line ("Fixed: …" with "Undo").
- The designer's own parts, as they are today: the Publish sheet and its rows (`designer-publish-sheet.md`), the held row's "Publish anyway" with a required reason field (`designer-publish-sheet-held-row.md`).
- New: the result at the end of a row (a filled tag with a mark and a chevron, green, amber or rose), the findings area under a row with its five group heads, the finding card, the four weight bars with the budget's mark, the list of unused rules, the line over the canvas.
