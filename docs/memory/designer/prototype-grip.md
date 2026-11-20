---
name: prototype-grip
description: The Prototype view's "+" handle beside a drawable mark (PL4, #10444): when it shows, where it goes, 20 px at any zoom, a press on it is the mark's; a small mark's 4 px reach
---

## What is the "+" handle?
- (PL4 #10444) The pointer or the focus on a drawable mark of the selected frame (`sd-proto-hot-own` and `sd-proto-hot-draw`, K2) shows a round "+" beside it: `span.sd-proto-grip` in that picture's `.sd-proto-hots`, one for the view (`ui.grip`, `_sdProtoGrip()`). It is `aria-hidden`; the keyboard keeps L (designer/prototype-drawing.md).
- It stands for its mark: the view's `press` reads a press on it as a press on `_sdProto.grip.hot`, so a drag draws the link's arrow (kind `row`, then the core's `connect`) and a click picks the link. Nothing new is written (K1, K3 unchanged).
- Owner (2026-11-19, agent channel message 9492, workflow/decisions-made-product-78.md): a 1280 px page is drawn 228 px wide, so a button's mark is a few pixels and a drag from it was fine work.

## Where does it go, and how large?
- `PGProtoHotspots.markGrip(rect, room, k, others)`: 20 screen pixels (`MARK.grip`) at any zoom, 4 (`MARK.gap`) from the mark, level with its middle. Right of the mark; else left, under or over it, the first that stays inside the picture's box (the layer and the box's padding, `FRAME.pic.x`, `.y`; the thumb clips) and covers none of the frame's other marks; else the first inside the box; else over the mark's right end.
- Its box spans the gap, so the pointer crosses from the mark onto it; it is laid out in screen pixels (24 x 20, or 20 x 24 under or over) and drawn with `scale(1 / k)` from its top left.
- The pointer comes first, then the focus (`_sdProtoGripFor()`). Off a mark it stays 250 ms (`_sdProtoGripHover()`), for a tall mark whose side the pointer leaves away from the handle. A held button leaves it where it is; while an arrow is drawn it is hidden (`.sd-proto-drawing`).
- Redrawn when the marks shown change (`_sdProtoFocusMarks()`, a picture loaded, the page opened whole) and at a new zoom (`_sdProtoZoomSoon()` from the core's `view` callback, once a frame). Closing the view hides it.

## What does a small mark take?
- A mark shown (own, lit or shared) under 12 screen pixels wide or high (`MARK.small`, drawn at least `MARK.min` 6) takes presses 4 screen pixels around it: `markReach(rect, k)` in frame units, set as `--sd-proto-reach` with `sd-proto-hot-reach`; the CSS draws a transparent `::before` that far past the border. Set again at a new zoom.

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_mark_reach_for_a_small_mark`, `_mark_grip_beside_the_mark` (sides, zoom, a menu row's neighbours, the box's edge), `_view_grip_and_reach` (the wiring and the CSS).
- Not tried in a browser when it was built.
