---
name: prototype-thumbnails
description: The Prototype view's frame sketch (PGProtoHotspots.sketch() bands, sketchBoxes()): the picture's loading state and fallback, and the 84-high strip of a frame without a picture
---

## What does a frame show above its rows?
- Its page's picture (PV8, designer/prototype-page-picture.md); the sketch is drawn under it as the loading state and stays when the picture cannot be drawn. A frame without a picture (a shared component not fetched) has neither.
- The sketch: one band per top-level node, top to bottom, with a dot per link in it (at most five). It is drawn from the tree in the browser: no request, nothing saved.
- `PGProtoHotspots.sketch(tree)` (`assets/js/pg_proto_hotspots.js`): bands `{ role, weight, links }` for the root's children, looking through up to two lone plain wrappers. Roles: `nav` (tag nav, componentType `navbar*`), `header`, `footer` (tag footer, componentType `footer*`), `shared` (a `shared_ref`, not expanded), `region`, `block`. `weight` is the node count, `links` the hotspots `collect()` finds in the band. At most 12 bands; the rest join the last as a `block`. Null without a tree.
- `design()` puts `sketch` on every page frame and on a loaded shared frame (null while it is not fetched); `mark()` copies it.

## How is it placed and drawn?
- `layout()` gives a picture frame `thumb: picSize().box` (298) and makes it that much taller; `rowY()` adds `frame.thumb`, so ports and arrows sit below the picture. The 84-high strip (`FRAME.thumb`) is only for a frame with bands and no picture.
- `sketchBoxes(bands, w, h)`: heights by the square root of the weight, each at least 3, 2 apart, whole units, the last ending at `h`.
- `_sdProtoThumb()` (style_designer.src.js) draws an inline SVG of `rect.sd-proto-sk-<role>` and `circle.sd-proto-sk-link`, `aria-hidden` (the rows already list the links); `.sd-proto-thumb` in `style_designer.css` is 84 high (a picture box sets its own height), border-box, 6px padding, 1px border, so the SVG is `thumb - 13` high and `FRAME.w - 20` wide.

## What was checked how?
- `tests/proto_hotspots_test.php`: bands, roles, the 12 cap, boxes, the frame height and port. The SVG drawing was not run in a browser.
