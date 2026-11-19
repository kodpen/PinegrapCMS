---
name: prototype-page-whole
description: The Prototype view's selected page opened whole (PL3): the foot as a button, Show the whole page and Show less, the 6400 px limit, pinned viewport heights, the frames below moved, one open at a time
---

## What does "Show the whole page" do?
- (PL3) On the selected page frame whose page runs past the 1600 cut, the picture's foot is a button: "+3 links below · Show the whole page", or "Show the whole page" alone. It draws the picture as tall as the page, at most 6400 page pixels (`FRAME.pic.full`, `picOpenCut()`); the box is `picOpenBox()`.
- Every link above the limit gets its mark (`picRects(…, cut)`), its arrow leaves from it, and the foot counts only links past the limit or hidden. A page longer than 6400 keeps the fade.
- The frames below move down as for a measured shared part: `layout()` takes `opts.openH` (frame id => page height, from `_sdProtoSections()`), and `_sdProtoPicSettle()` resizes and places the boxes; pictures stay.
- Owner (2026-11-19, agent channel message 9492): the links under the cut could not be reached; architect (2026-11-19): "every frame keeps one height" ends for the one open frame only.

## How is it closed, and what is kept?
- The open frame's foot reads "Show less" (with the count when links lie past 6400). Selecting another frame, or none, closes it (`_sdProtoFocused()`), and so does closing the view; a redraw of the map (an arrow drawn, Read the links again) keeps it open.
- One frame open at a time (`_sdProto.pics.full`); nothing is stored, per browser or on the design.
- `picFootState({ below, long, open, selected, page })` decides the foot: `open` / `close` button or the count; a shared part's foot is never a button. Only a button foot carries `sd-proto-pic-foot-on`, the core's `ownClicks`, so the canvas takes no press or key from it; on other frames a press selects the frame as before.
- The cap of 48 live pictures never drops the open one.

## How is the taller frame drawn without stretching the page?
- `_sdProtoPicLoaded()` always measures at the cut's height (the frame set back to 1600 first; `pics.pageH`), then for the open page pins the viewport heights and sets the frame's height to the shown pixels.
- `_sdProtoPicPinVh(doc)` rewrites vh, svh, lvh, dvh and vmax in the document's readable style rules and style attributes as pixels of the 1600 frame (`picVh(value, 1600)`) and adds `.vh-100` and `.min-vh-100` at 1600 px for a Bootstrap sheet of another origin, whose rules cannot be read. Without it a `min-vh-100` hero grew to 6400.
- Checked in headless Chromium on a test page: a `min-vh-100` section stayed 1600 high in a 6400 frame, a link under it kept its place.

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_page_opened_whole` (the cut, the box, the frames below, marks and foot past the cut, the foot's state, the units), `test_proto_hotspots_view_opens_the_page_whole` (the wiring).
