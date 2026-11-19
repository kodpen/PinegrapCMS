---
name: prototype-zoom
description: The Prototype view's zoom to a frame (PX1, #10476): a double-click or Enter on a frame shows it whole; Esc or "All" goes back to the view from before; what forgets the zoom
---

## What does a double-click on a frame do?
- Architect (2026-11-19, agent channel, plans/prototype-next.md): zoom to a frame on double-click in the Prototype view; Esc or "All" goes back.
- (PX1 #10476) The view shows that frame whole in the middle of the canvas, 48 px clear of its edges, at most 200%: the graph canvas core's `view.zoomTo(item)` (ui/graph-canvas-core-sizes-ports.md). A page frame or a shared part's frame; never a chip.
- The canvas captures the pointer, so a frame gets no `dblclick`: as on the Flows canvas, two taps on one frame, the second within 450 ms (`PGProtoHotspots.ZOOM_TAP`), are the double-click. The adapter's `release` hands every release to `_sdProtoTap()`: a frame pressed and let go without a move is a tap, anything else (a link mark, its "+" handle, an arrow, the empty canvas, a drag) breaks the pair; `PGProtoHotspots.zoomTap()` decides.
- A mark's first click picks its link and may move the view to the link's other end, so a double-click on a mark only picks it. The foot button ("Show the whole page") keeps its clicks.
- Enter on a frame in focus does the same, for the keyboard. The first press selects the frame as before, so the frame zoomed to shows its arrows. The live region says "{name}: zoomed in. Esc goes back to the whole map."

## How does it go back?
- "All" in the canvas's top left corner (`div.sd-proto-zoomed`, the frame's name beside it, shown only while zoomed) or Esc on the canvas puts back the view from before the first zoom. A double-click on another frame meanwhile zooms to it and keeps that first view; panning and zooming by hand keep "All" too.
- Esc goes back first; only when the view is not zoomed does it close the view.
- Fit (the bar's button or the 0 key) and a new arrangement (the first drawing, Tidy up) forget the zoom: they show everything. Closing the view puts the view from before back for the next opening. A redraw (an arrow drawn, "Read the links again") keeps it; a frame gone from the map leaves "All" without a name.
- Nothing is stored, on the design or in the browser.
- Two quick clicks select no words of a frame: `.sd-proto .pgc-boxes` has `user-select: none`.

## How is it checked?
- `tests/graph_canvas_test.php`: `test_graph_canvas_zoom_to_a_box`. `tests/proto_hotspots_test.php`: `test_proto_hotspots_zoom_tap_of_a_double_click`, `test_proto_hotspots_view_zooms_to_a_frame` (the wiring and the CSS).
