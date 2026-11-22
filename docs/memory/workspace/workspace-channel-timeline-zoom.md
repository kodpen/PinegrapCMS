---
name: workspace-channel-timeline-zoom
description: The conversation timeline's zoom and movement: buttons in the well, Ctrl+wheel, Shift+wheel, pinch, the keys, where a strip opens, the zoom kept per channel
---

## Why does the strip zoom?
- Owner (2026-11-21, agent channel message 11680): in a long conversation the groups could not be told apart. Then, on the redraw: "klavye kısayolları ile çalışsın ctrl ve shift scroll ile yaklaş uzaklaş ve kaydır" (`workflow/decisions-made-workspace-46.md`).

## What is a zoom?
- A scale in the layout's units (`timelineLayout(runs, scale)`), not a multiple of the well. It opens at 5.2; the top is 48; the least is the scale at which the whole conversation fits the well, never above 5.2 (`timelineZoomRange()`). A conversation too long for the well even at its turns' least widths stops at the largest scale that still draws that least strip, so the first zoom in shows a change; it then moves along. A short conversation sits at the left at 5.2 and is not stretched.
- A strip opens at 5.2 at its newest end. The viewer's zoom is kept per channel in the browser (`localStorage` key `pg_ws_ctl_scale_<channel id>`, in try/catch), held between the least and the top.

## How does one zoom and move?
- The two buttons inside the well (`.ws-ctl-zoom`, `pg-icon-button pg-icon-button-sm`, zoom out then in) step ×1.5, snapping to an end within 1.2× of it (`timelineZoomStep()`); the one at its end is dimmed.
- Ctrl or Cmd with the wheel, and a touchpad pinch, zoom smoothly around the pointer (`timelineZoomWheel()`). Shift with the wheel, or a sideways swipe, moves along. The plain wheel is left to the page.
- A zoom keeps the message under the pointer (or on the card) where it was (`timelineAnchor()`).
- Pointer: a drag of 6 px or more moves the strip and opens nothing. Touch: a tap shows the card, a drag moves, two fingers zoom.

## Which keys work on the focused well? (board 8)
- `+` or `=` and `-`: a zoom step; `0`: the whole conversation.
- Left and Right: a message; with Shift, a run; Page Up and Page Down: a day (`timelineJump()`); Home and End: the first and the newest message.
- Enter or Space opens the message; Escape hides the card; Tab goes into the card's rows (`workspace-channel-timeline-card.md`).
- The well is a slider (`role="slider"`, its value the message on the card, its text who wrote it and when).

## How does a zoomed strip follow the screen?
- In the tabs row the strip follows the part of the conversation on the screen: when the conversation scrolls and that part is out of view, it comes back (`timelineFollow()`). New messages keep a strip at its newest end there.

## How was it checked?
- PR #11027 (the first zoom); PR #11047 for the scale, Ctrl and Shift with the wheel and the keys, its screen check in the PR body.

Related: docs/memory/workspace/workspace-channel-timeline.md, docs/memory/workspace/workspace-channel-timeline-card.md
