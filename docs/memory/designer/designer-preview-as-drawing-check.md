---
name: designer-preview-as-drawing-check
description: "Check all cases" of the Preview as drawn (boards P6, P7 of designer-preview-as-drawing): where it starts, the run, the list of findings, a row opening its case, the labels
---

## Where does it start?
- From the last row of the "Case" menu and from the row "Check all cases" of the designer's command palette (marked "Preview"); from the palette the Preview window opens first when it is closed. No button of the bar.
- The cases are the page's own: each case of "Case" for each visitor the page has a rule for. A page with one case has no such row.

## What shows while it runs?
- The three choosers show the case on its way and are dimmed; the page under them is each case as it is drawn. At the bar's right a small spinner with "Checking 3 of 6…" and "Stop" (quiet small).
- It is measured in the Preview's own frame at the width the window has.

## What is the list?
- A 300px panel over the page's right edge with a shadow. It never narrows the page, so what was measured is what a visitor sees at this width.
- Head: "3 of 6 cases to look at", under it "A row opens its case.", and the X that closes it.
- Only cases with a finding are listed. A row is the case and the visitor, then the finding with its amber mark and the name of the place: "A text leaves its box", "A picture is missing", "A widget draws nothing".
- A case that fails is a row of its own with a rose mark, "Could not be drawn" and "Try again" (quiet small); the others are still checked.
- Foot: the width that was checked, as "Checked at 742px wide.", and "Check again" (quiet small), which runs it once more, for example after an edit.
- "Stop" keeps what was found so far; the head then says "Stopped after 3 of 6."
- Nothing found: no list, one teal line under the bar, "Every case looks right. 6 cases drawn."
- The list is not kept when the window closes.

## What does a row do?
- It opens its case: the choosers change to it, the page is drawn and scrolled to the place, and the place is outlined in amber until the next click. The opened row carries an amber edge.
- When the place lies under the list, the list moves to the left edge. The list stays until its X.
- A text that leaves its box is outlined as far as it runs; the page is never changed to make it fit.

## What changes in a narrow window?
- The list is a sheet from the bottom, no higher than half the window, so the outlined place stays in sight above it; same head, rows and foot.

## What does the keyboard do?
- Down and Up move through the rows, Enter opens the case, Escape closes the list.
