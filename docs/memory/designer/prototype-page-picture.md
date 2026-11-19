---
name: prototype-page-picture
description: The Prototype view's page picture in a frame (PV8): how it is built, the sandboxed frame, the link marker, the queue and the cap, the foot's count, what a redraw reuses
---

## What does a frame show?
- (PV8 #9896) Its head, then its page as the Preview draws it: the Preview's own document for the tab's tree in a frame 1280 wide, cut at 1600, scaled to 228 x 285 (`PGProtoHotspots.picSize()`; box 298 with padding and border, `thumb` of `layout()`). A shared frame shows its part alone at the part's height (PV9, designer/prototype-marks.md). Way and its reasons: designer/prototype-picture-check.md.
- The sketch (designer/prototype-thumbnails.md) is drawn under it while it loads and stays when it cannot be drawn. The picture fades in over 0.2 s, not with reduced motion.
- A page taller than the cut gets a fade over its bottom (`picLong()`); one ending at 1600 gets none. The foot says "+3 links below"; on the selected long page it is also the "Show the whole page" button (prototype-page-whole.md).
- Architect (2026-11-16, agent channel message 6079): the foot sits on the picture's lower edge above the fade, never as a row under it, so every frame keeps one height (the page opened whole aside); the arrows of links it counts leave from it (designer/prototype-marks.md).
- `vh` resolves against the 1600-high frame: a short page with `min-vh-100` fills the whole picture. Not a fault.

## How is it built and kept safe?
- `_sdProtoPicRaw()` calls `generateHTML(name, classes, tree)` with `_sdPreviewWidgetMarkers` and `_sdPicMarkers` on, both cleared in `finally`; `_sdPreviewWidgetPayload(raw, pageId)` asks `preview_widgets` with that tab's page id; `_sdPreviewDocHtml()` finishes it; `PGProtoHotspots.picDoc()` drops its `<script>` elements and puts `picHead()` (the CSP meta and the stilling rule, embedded frames and players hidden in place) first in its `<head>`.
- The frame's attributes are `PGProtoHotspots.PIC_FRAME`: `sandbox="allow-same-origin"` only, `tabindex="-1"`, `aria-hidden`, `pointer-events:none`.
- `_sdPicMarkHtml()` (end of `_sdEmitNode()`, never with a sink) writes `data-pg-pic` from `picMarks()`/`picMark()`: a node's own link on its first tag, a text's links on their `<a>` when the markup holds exactly the text's links. Inside a shared component `s<id>:` leads (`_sdPicShared`).

## When is a picture made and dropped?
- An IntersectionObserver on the stage's viewport (margin 200 px) queues a box; at most 6 load at once (`SD_PROTO_PIC`), the widget request among them; a picture that never loads frees its place after 15 s.
- At most 48 alive; past that `picOrder()` drops the ones farthest from the view's centre back to the sketch. 3.7 MB a picture (prototype-picture-check-numbers.md).
- (#10040) The observer never calls again for a box that stayed near the view, so one dropped at Fit stayed a sketch after zooming in. The core reads the view on every pan and zoom: `_sdProtoPicSoon()`, 300 ms after it settles, queues `picRefill()`: the 48 near boxes nearest the centre that are not alive (same order as the cap, so nothing churns). The cap counts shared frames too (`test_proto_hotspots_picture_refill_after_the_view_moves`).
- A redraw of the map (Read the links again, an arrow drawn, Tidy up) drops the frames and makes them again; a frame whose markup, widget payload and light/dark are unchanged takes its document from `_sdProto.pics.cache` without a request. Closing the view removes the frames.

## How is the foot counted?
- After `load` the markers are read in page pixels (`getBoundingClientRect()` inside the frame) and the marks put on the picture. `picFoot(rows, marks, sharedId)`: every link `picRects()` finds no place for (under the cut, no size, in a menu or region, which has no marker, not written); two rows of one element count once; a page frame skips `s<id>:` markers.

## What was checked how?
- `tests/proto_hotspots_test.php`: sizes, layout heights, marker values and writer, the flag cleared after a throwing build, the foot, the order, the frame attributes and head.
