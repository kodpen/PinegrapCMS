---
name: architect-looks-at-landed-screen
description: The architect opens each landed step of a screen the owner asked for on a sandbox, in the states nobody drew, and writes what differs into the plan; no step waits for the look
---

## Why?
- Owner (2026-11-22, agent channel messages 12055, 12057, 12058), the day three redraws he had asked for began to land: "bildirim paneli başarısızca yapılmış. düzeltmek için bir tur daha uğraşacağız", "burada da hatalar var".
- Two things were mixed in what he saw. A redraw lands step by step, so between steps a screen is half new and half old. And the landed step had faults its own screenshot round missed: it was checked with the sample data of the drawing (unread shop rows), while his inbox held Workspace rows with nothing unread.

## What does the architect do?
- For a plan of priority 6 (the owner's own request) the architect opens every landed step on a sandbox at 1440 and 390 px and compares it with the drawing or the plan's words.
- It looks first at the states the drawing does not show: nothing unread, nothing at all, the longest name, a row of every kind, the narrowest width, Windows scrollbars.
- What differs goes into `plans/<plan>-fixes.md` (the fault, its cause in the code, the step that takes it) by a docs-only PR, and to the coordinator in the agent channel. The plan's close-out deletes the file.
- The owner is told in the channel what is a fault and what is only not built yet.

## What does it not do?
- It holds no step: no screenshot is sent to the architect or the owner for a yes, and no PR waits for the look (`screen-described-in-plan.md`, owner 2026-11-20).
- It fixes nothing itself (no product code).

## What does a screen step's own check add from now on?
- Beside the drawing's sample: the empty state, the all-read or all-done state, and one row of each kind the screen can hold. The PR's "Screen check" names the states it looked at.

- The first such file was the bell's (`bell-redraw-fixes.md`, five faults of BR1 and BR2), fixed in BR3 #11154 and deleted with its plan (`ui/bell-head.md`, `ui/bell-row.md`, `ui/notifications-menu.md`).

Related: docs/memory/workflow/screen-described-in-plan.md, docs/memory/workflow/architect.md
