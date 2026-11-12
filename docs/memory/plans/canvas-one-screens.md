---
name: canvas-one-screens
description: CO0 drawings of plans/canvas-one.md in look A (plans/canvas-one/): the canvas card with its parts and line of tables, one list of versions, the first-write question and its states, the Data list, the phone
---

## Where are the drawings, and how are they made again?
- In `plans/canvas-one/`, each as its source (`.html` on `../panel-design-language/sheet.css` and `canvas.css`) and as pictures in both themes (`-dark.jpg`, `-light.jpg`); `?light` on a source draws the light theme. Open a source with Inter and Inter Display installed.
- The owner approves them on the PR of CO0 (#8922); a later step's screen is approved from a real panel's screenshots.

## What does the canvas card show? (`canvas-card`)
- At rest: the name, the time of the newest version, the parts as small chips (Drawing, Page, Data with its count), the agent's line ("@handle made it. It is the agent's work, not the panel's."), a still area that says the page runs when opened, the line of tables it uses ("Orders — reads, writes", "Products — reads"), Open (filled), Full screen, Versions.
- Open: a segmented control between Drawing and Page; data is no view of its own, it is the line of tables. The agent's line stays outside the frame. "Bind a table" (ghost) shows only to whoever may edit both the canvas and the table.
- A binding by an agent is a channel line: "@handle bound Products to Sales board · reads" with Undo.
- Versions: one drawer, the parts' versions merged by time, each row "Page · version 7" or "Drawing · version 2" with its maker; a segmented control All / Page / Drawing; Show and Restore on a row.

## What does the first-write question show? (`first-write`)
- A dialog the panel draws over the card, never the page: "Sales board writes in your name?", the tables listed with what is done to each (adds and changes, cannot delete; or only reads) and a status pill (Writes amber, Reads gray), a hint that each write shows "through Sales board" and that a new page version asks again; Don't allow (quiet), Allow (filled).
- Refused: an amber band "Write permission not given. The page only reads." with Change. Stopped at a bound: a rose band naming the bound passed, with Open again. A guest: one line, "This canvas's data is open to the team only".

## What does the Data list show? (`data-list`)
- The workflow tables screen named Data (CO6) on the list rules of `panel-design-language-sheet.md`: title with the count, Import from file (quiet) and New table (filled); search, a segmented control All / Mine / Channels / Shared with me and, at the right, Tables / Deleted.
- Columns: name with its description, rows, columns, the canvases that use it (chips), where it belongs (a channel, "Mine", or who shared it and at which level), updated, actions. Channel tables are their own group (S5).

## What changes on a phone? (`phone`)
- 390 px. The card's part chips go under the name; the line of tables wraps; Full screen and Versions at the card's foot.
- The first-write question is a sheet from the bottom with both buttons full width, Allow at the right.
- The Data list's rows become cards (name, rows, place, time, the canvases); New table is a floating button.
