---
name: designer-start-import-drawing-home-line
description: The line a Figma import puts on the design's card of the designs home, as drawn (figma.html, board B11, step ST5b): its four states, "Continue" and "Stop", and "Report" until "Got it" or 14 days
---

## Where does a Figma import show after the New design window is closed?
- On the design's card of the designs home, as one quiet line at the card's foot. The card is there from the first frame made; nothing else on the home changes.
- No button is added to the card's head or the home's bar, and the card's menu gets no row.
- The run is built like the bulk move to the designer (`designer/legacy-to-designer-run.md`): a cursor file, the page's own requests while the designs home is open, the site's general job where one runs.

## Which four states does the line have?
- Running: a spinner and "Importing from Figma · n of n frames".
- Waiting for Figma's limit: "Waiting for Figma's limit · tries again at <time>"; it needs no press.
- Paused (the home was left and no job runs): "Paused · n of n frames" with "Continue" (quiet, small) and "Stop" (ghost, small). "Continue" goes on from the frame it stopped at.
- Done: "Imported from Figma · Report", "Report" being a link in the line.

## What does "Stop" on the card do?
- It asks first with the question dialog, then removes the pages made and what was fetched into the file manager. "Stop" in the running window asks nothing, since no page is kept there either.

## How is the report read again?
- "Report" opens the New design window at the report's pane (board B9) with the same counts and frame rows; its foot then reads "Copy the report" (ghost, left), "Got it" (quiet), "Open the design" (filled).
- The line leaves the card when "Got it" is pressed there, or 14 days after the import ended. Opening the design does not remove it.
- There is no other place where the report is read.

## What changes at 390 px?
- "Continue" and "Stop" go under the line's words, side by side.
