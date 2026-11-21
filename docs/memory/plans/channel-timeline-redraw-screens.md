---
name: channel-timeline-redraw-screens
description: The redrawn conversation timeline in words: the well and its size, days as columns under dates, runs as bars, the decision diamond, the on-screen line, zoom buttons, wheel and keys
---

## Where does it stand, and how big is it?
- One well at the end of the channel card's tabs row (boards 1 and 5): 30 px high, radius 9 px, the card tint with a hairline, no shadow. It takes the room the tabs leave, 420 px at most and 220 px at least, and ends 16 px from the card's edge. The row grows no taller for it.
- Under 220 px left it leaves the row for the foot of Channel info, titled "Conversation" (board 6): full width, 34 px high, the writers as pills under it. The line of the first and last time goes; the dates say it.

## What is inside the well?
- Left the port, which cuts and never shows a scrollbar; right, after a hairline, two 24 px quiet icon buttons, zoom out then zoom in (28 px in Channel info). The one at its end is dimmed; their titles name the keys.
- A day is a column: its short date at the top left, 4 px down (10.5 px, quiet; "Today" one step stronger), its bars under it. Before every day but the first in sight stands a hairline with 8 px either side.
- A date shortens to its day number where its column is under 34 px and goes under 13 px (board 3). Zoomed in, the date of the day cut by the left edge stays in sight at that edge (board 4).
- A run is one bar 6 px high (board 7): its turns stand 1 px apart, square inside, 3 px round only at the run's two ends. The writer's colour flat, 82% over the surface; no gradient, no shadow, no rail behind.
- Runs stand 8 px or more apart, by the wait. Zoomed all the way out a turn may fall to 2 px and a gap to 3 px.
- A decision is an amber diamond on its turn's bar with a 2 px ring of the surface. No other tool is marked on the strip; the card says them.
- The part of the conversation on the screen is a 2 px accent line under the bars, 2 px over the well's foot. The soft window behind the turns goes.
- An end where the strip is cut fades over 22 px.

## How does it answer the pointer?
- Pointing (board 2): the turn grows to 10 px in its full colour with a soft ring, the rest dims to 38%; the playhead and the card as built, the card 8 px under the well.
- A click opens the message. A drag moves the strip and opens nothing.

## Wheel and keys? (owner, 2026-11-21; board 8)
- Ctrl or Cmd with the wheel zooms around the pointer; a pinch does the same. Shift with the wheel moves along the conversation; a sideways swipe does the same.
- The plain wheel over the well is left to the page.
- Focused, the well is a slider as built: `+` and `-` a zoom step, `0` the whole conversation; the arrows a message, with Shift a run, Page Up and Page Down a day; Home and End the first and the newest message; Enter opens it, Escape hides the card.

Related: docs/memory/plans/channel-timeline-redraw.md, docs/memory/workflow/decisions-made-workspace-46.md
