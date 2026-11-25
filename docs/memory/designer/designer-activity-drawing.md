---
name: designer-activity-drawing
description: Drawing of the designer's History "Activity" tab, boards A1 and A2: the two tabs "This page" and "Activity", a day's rows, the count of changes on a version's row, the marks
---

## Where is the drawing?
- `designer/designer-activity-drawing/activity.html` with `activity-dark.jpg` and `activity-light.jpg`; five boards. A3 to A5 (the choosers, Compare, more rows, empty, narrow) are in `designer-activity-drawing-choosers.md`.
- Built as `designer-activity-tab.md` (`workflow/screen-follows-drawing.md`); the start file is `designer-activity.md`. The events, their sentences and who sees which are the reader's (`designer-activity-reader.md`, `designer-activity-audit-lines.md`).

## What are the two tabs? (A1)
- History keeps its place, head and width. Under the head two tabs, each half the width: "This page" holds the published revisions and the local snapshots exactly as today; "Activity" is new.
- History opens on "This page" every time. No new button: the command palette gets one row, "Show the design's activity", which opens History on the Activity tab.
- The list is asked for the first time the tab is shown after History opens; grey rows stand in for it and the choosers wait. Shown again while History stays open, it is asked for again. Nothing polls.
- Keyboard: the two tabs are one stop, arrow keys go between them; then Tab reaches the choosers and the rows that open Compare.
- A content editor sees the rows of their pages only, as the reader gives them.

## What is a row? (A2)
- Rows are grouped by day under a small head: "Today", "Yesterday", then the weekday and the date ("Monday, 23 November"), with the year once it is not this one.
- A row: the kind's mark at the left, the person in bold, the reader's sentence as plain text, and under them the hour. The sentence wraps, never cut. The mark is grey for every kind: the words say what happened, never a colour.
- A row of a page's version (published, restored, imported, changed with the assistant, a content change applied, a release bundle) is a button: the whole row opens Compare, and a small Compare mark (`bi-arrow-left-right`) stands at its right end. A change applied names the editor after the hour ("change by Deniz Acar").
- Under the pointer the row gets the hover ground, the mark turns blue and the tip reads "Compare with the version before"; with the keyboard's focus it gets the blue ring.
- Any other row opens nothing and has no mark at the right.
- A person no longer on the site reads "A removed user", a guest with no name "The customer", a scheduled move "The schedule": the reader's words.

## What is the count on a version's row?
- After the hour: the number Compare's list would show for that version against the one before it (`designer-compare-changes.md`, `rows()`): "12 changes", "1 change"; "settings only" when the page's tree is the same; "first version" when the page has none before it (then the row is not a button).
- Architect (2026-11-25): no store holds the count, so it is counted in the browser after the list is drawn, for the version rows in view, one at a time, and kept while History is open. A row never waits for it; a count that cannot be made leaves its place empty.
- The plan's "how many elements changed" is this number, so the row and Compare's "What changed" say the same.

## Which marks?
- Published `cloud-arrow-up`, restored `arrow-counterclockwise`, imported `box-arrow-in-down`, assistant `stars`, change applied `pencil-square`, bundle `box-seam`.
- Bin `trash`, brought back `reply`, deleted for good `x-circle`; put on the site `eye`, taken off `eye-slash`.
- Share link `link-45deg`, revoked `slash-circle`, approved `check-circle`, changes asked `arrow-repeat`, comment `chat-left-text`.
- Design `palette` (a copy `copy`), shared component `puzzle`, design file `file-earmark-code`.

## What is only an example?
- The design "Northwood", its pages, the people, the hours and the counts.
