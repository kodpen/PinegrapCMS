---
name: workspace-channel-timeline-zoom
description: The conversation timeline's zoom (buttons, + and -, Ctrl+wheel, pinch), the zoom a crowded strip opens at, the day hairlines and dates, the strip scrolling inside the tabs row
---

## Why does the strip zoom and mark days?
- Owner (2026-11-21, agent channel message 11680): in a long conversation the groups could not be told apart, days should read a little clearer without overdoing it, and the strip ran off a narrow screen.

## How does one zoom?
- Two quiet icon buttons at the strip's end (`.ws-ctl-zoom`, `pg-icon-button-sm`, zoom out then zoom in), disabled at either end. On the focused strip `+` (or `=`) and `-` step around the message on the card.
- They step along fixed levels 1, 1.5, 2, 3, 4, 6 … 256 (`timelineZoomStep()`). Ctrl or Cmd with the wheel and a touchpad pinch zoom smoothly around the pointer (`timelineZoomWheel()`); two fingers on the strip zoom around their middle. A zoom keeps the point under the pointer (`timelineZoomScroll()`).
- Zoom one is the whole conversation in the strip's width, as before. The top is where turns would stand about 48 px apart, never below the opening zoom (`timelineZoomTop()`); a short conversation has none.

## Where does a strip open?
- A crowded strip opens zoomed so far that no turn is under 6 px (`timelineOpenZoom()`, beside the seven tenths of the room `timelineLayout()` gives turns), at its newest end.
- The viewer's zoom is kept per channel in the browser (`localStorage` key `pg_ws_ctl_zoom_<channel id>`, read and written in try/catch) and wins over the opening zoom; without storage it lasts while the screen is open.

## How does a zoomed strip move?
- The track is wider than its port (`.ws-ctl-port`, sideways scroll, no scrollbar). The wheel moves it, a mouse drags it (a drag opens nothing), the slider's keys bring the message on the card into view.
- In the tabs row it follows the part of the conversation on the screen: when the conversation scrolls and the frame is out of view, the frame comes to the middle (`timelineFollow()`). New messages keep a strip at its newest end there.
- The port reaches 12 px over and 14 px under the track with the pointer passing there, so the decision diamond and the dates are not cut and the head's buttons stay reachable.

## How are days marked?
- A new day (site time zone, `ws-config` timezone) starts a new run 16 px from the one before, beside 10 px between runs, with a hairline in the middle of the gap (`.ws-ctl-day`).
- Its short date (day and month in the panel's locale, "Today" for today) stands beside the hairline only where 48 px or more are left to the next day or the strip's end (`timelineDayMarks()`). Nothing louder.

## What stays as it was?
- The read, the colours, the card and the keyboard slider. The move to the foot of Channel info under 220 px left; the zoom works there too.

## How was it checked?
- PR #11027: sandbox with 430 messages over five days at 1440, 1280, 1024, 900 (dark), 768 and 390 px (Channel info); buttons, Ctrl+wheel, wheel, drag, Home, `-`, the kept zoom after a reload. Before it, under 220 px the holder's padding stood past the card's edge (at 390 px 26 px past it).

Related: docs/memory/workspace/workspace-channel-timeline.md
