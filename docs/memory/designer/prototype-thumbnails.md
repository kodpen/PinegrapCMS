---
name: prototype-thumbnails
description: The Prototype view's frame sketch (PGProtoHotspots.sketch() bands, sketchBoxes(), _sdProtoThumb()): the page picture's loading state and fallback, and the 84 floor of a shared part's height
---

## What is the sketch for?
- Every frame with a tree is drawn as its page's picture (designer/prototype-page-picture.md); the sketch is drawn under the picture as its loading state and stays when the picture cannot be drawn or the cap of live pictures dropped it.
- A frame without a tree (a shared component not fetched yet) has neither: its head and one 30-high line, "No links" while it has no rows.
- It is drawn from the tree in the browser: no request, nothing saved.

## What does sketch(tree) answer?
- `PGProtoHotspots.sketch(tree)` (`assets/js/pg_proto_hotspots.js`): bands `{ role, weight, links }` for the root's children, looking through up to two lone plain wrappers. Null without a tree.
- Roles: `nav` (tag nav, componentType `navbar*`), `header`, `footer` (tag footer, componentType `footer*`), `shared` (a `shared_ref`, not expanded), `region`, `block`. `weight` is the node count, `links` the hotspots `collect()` finds in the band (a dot each, at most five).
- At most 12 bands; the rest join the last as a `block`.
- `design()` puts `sketch` on every page frame and on a loaded shared frame (null while it is not fetched); `mark()` copies it. A frame whose `sketch` is an array is a picture frame (`pic` of `layout()`).

## How is it placed and drawn?
- `layout()` gives a page frame `thumb: picSize().box` (298) and a shared frame `picPartBox(partH)`: its part's measured height, at least `FRAME.thumb` (84, the old sketch strip) and at most 298 (designer/prototype-marks.md). The frame is the head (44) plus `thumb` plus 10.
- `sketchBoxes(bands, w, h)`: heights by the square root of the weight, each at least 3, 2 apart, whole units, the last ending at `h`.
- `_sdProtoThumb()` (style_designer.src.js) draws an inline SVG of `rect.sd-proto-sk-<role>` and `circle.sd-proto-sk-link`, `aria-hidden` and not focusable (the links panel lists the links), `FRAME.w - 20` wide and `thumb - 13` high. `_sdProtoPicBox()` wraps it as `.sd-proto-pic` with the picture, the fade and the foot over it.

## What was checked how?
- `tests/proto_hotspots_test.php`: bands, roles, the 12 cap, boxes, the frame height with and without a picture, a part's height. The SVG drawing itself was not run in a browser.
