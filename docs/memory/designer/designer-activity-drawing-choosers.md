---
name: designer-activity-drawing-choosers
description: Drawing of the designer's History "Activity" tab, boards A3 to A5: the three choosers person, page and kind, the row that opens Compare on two versions, more rows, an empty design, narrow
---

## Where is the drawing?
- `designer/designer-activity-drawing/activity.html`; boards A1 and A2 (the tabs, a day's rows, the count) are in `designer-activity-drawing.md`.

## What are the three choosers? (A3)
- One row under the tabs, three menus of equal width: "Everyone", "Every page", "Everything".
- Person: "Everyone", "Me", then the people named in the rows read so far, then "Customers" (answers and comments on share links) and "The schedule". A content editor has no "Customers".
- Page: a search field, "Every page", the page that is open with "open now", then the design's pages by name. A page deleted for good is in no menu; its rows show under "Every page".
- Kind: "Everything", then "Page versions", "Pages on and off the site" (the bin and the schedule too), "Sharing", "The design", "Shared components", "Design files": the reader's six kinds. A content editor has the first two.
- A chooser that narrows is blue and shows its choice in place of its name; the first row of its menu clears it. Nothing is remembered: History opens with all three wide.
- Narrowed to nothing: the list's place reads "Nothing matches the three." with "Show everything", which clears all three.

## How do the choosers narrow?
- Architect (2026-11-25): the three go to the reader with the request, so a narrowed list is still fifty at a time from the whole design, never a filter over the rows already on the screen. AC3 adds the three arguments to the panel action `activity_list` and to the reader, with their tests.

## What does a version's row open? (A4)
- Compare as History opens it for a revision (`designer-compare-live-drawing.md`, board L6), with the revision before on the left and the row's revision on the right. Nothing in the window is new.
- The row's page need not be the one that is open: both sides are read as revisions. "Load this revision" is offered only for the open page, as History's rule is today.
- Closing gives History back on "Activity" with its choosers and its place in the list kept.
- AC3 therefore waits for CL2 of the compare plan, which gives the window its two side choosers.

## How do more rows come? (A4)
- Near the end of what is read, the next fifty are asked for with the reader's cursor; a quiet line with a spinner, "Reading earlier activity…", stands at the end meanwhile. A failed read puts "Try again" in that line and keeps the rows.
- After the last row one grey line: "This is where the design's activity begins." What the site no longer keeps is simply not there. A narrowed list ends with "That is all of it."

## What do an empty design and a failed read show? (A5)
- Empty: an icon, "Nothing has happened in this design yet." and "Publishing a page, importing one or sharing a link shows here, with who did it." The choosers stay, greyed.
- Failed: "The activity could not be read." and "Try again". "This page" is not touched by it.

## What changes in a narrow window? (A5)
- History stays the panel it is, as wide as the window lets it. The three choosers share the row and cut a long choice with "…"; their menus open under them, kept inside the window. A row's tap opens Compare's narrow form.
