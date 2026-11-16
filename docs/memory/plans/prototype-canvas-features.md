---
name: prototype-canvas-features
description: Plan prototype-canvas, features F1 to F14: what a frame of the Prototype view shows, how pages are arranged, which arrows are drawn and how an arrow runs
---

## What does a frame show?
- (F1) The page's own picture at desktop width, scaled to the frame, script-free, taking no click and no focus. While it loads, or when it cannot be drawn, today's band sketch.
- (F2) A head above the picture: the page's name; "Start" on the start page (the design's first tab); the selected frame's name in the accent colour.
- (F3) A long page is cut at a fixed height with a fade. A foot under it says how many links lie below the cut ("+3 links below"); their arrows leave from the foot.
- (F4) Shared components are frames of their own in a column at the left under "Shared parts", each saying on how many pages it sits. A page's picture shows them, as the page does, but marks no link inside them.
- (F5) A link is marked where it sits on the picture: a rounded rectangle over its element, blue when it leads somewhere, red when broken. The selected frame shows all its marks; another frame only the marks that start or end a drawn arrow.

## How are the frames arranged?
- (F6) A grid, not one row: eight frames a row, then the next row.
- (F7) Sections: one per tab group in the strip's order, headed by the group's name; pages in no group under "Pages" when the design has groups (first when the first tab is one of them, else last; architect, 2026-11-16, message 5878), with no heading when it has none. Inside a section the pages follow the flow from the start page (breadth-first over the links, the strip's order between equals); pages nothing reaches come last.
- (F8) Targets outside the design (other site pages, other sites, contact addresses, anchors, hidden, broken) stay chips, in a column at the right under "Outside this design", grouped as today.
- (F9) A frame or chip moved by hand keeps its place as today. "Tidy up" in the bar puts everything back to this arrangement, after a confirmation saying it does so for every editor of the design.

## Which arrows are drawn?
- (F10) Only the selected frame's: outgoing ones solid in the accent colour, incoming ones dashed amber, a broken one red. Opening the view selects the open tab's frame. A click on a frame selects it; a click on the empty canvas selects none and draws none.
- (F11) Frames and chips that take no part in the drawn arrows are dimmed, still clickable. The selected frame is outlined.
- (F12) "All arrows" in the bar also draws every other arrow, faint, under the selected frame's; the choice is kept per browser.
- (F13) A link of a shared part that leads to the selected page draws no arrow: its mark on the shared frame turns amber and a line under that frame names it ("Cart icon → cart"). Selecting the shared frame draws its own arrows.

## How does an arrow run?
- (F14) From the link's mark to the nearest edge of its target, in right angles with rounded corners, through the gaps between frames, never across a frame that is not one of its ends; arrows sharing a gap run side by side. Moving a frame routes its arrows again. Where no such way exists the arrow falls back to today's curve.

## What stands in until the picture lands?
- Until PV8 a frame keeps today's sketch; until PV9 its link rows are the marks: an arrow leaves from the frame's edge at its row's height.
