---
name: designer-prototype-links-steps
description: Steps of plans/designer-prototype-links.md slice 1 (read-only Prototype map), one PR each, about 1,000 product lines
---

## Which steps make slice 1?
- 0. Done #5401: graph canvas core slice 4 (sizes, named ports, other targets, stable ids, thumbnails): ui/graph-canvas-core-sizes-ports.md.
- 1a. Done #5492: the resolver's JS twin with a parity test (platform/link-resolve-js-twin.md).
- 1b. Done #5603: the map's server read (designer/prototype-map-read.md).
- 1c. Pure collection in the designer: hotspots of a tree (`props.href`, the `_attrs` href when the prop is empty, btn, `<a>` in text; no Bootstrap toggles), shared components once with their pages count, each hotspot's mark (page tab, draft, broken, external, contact, anchor) from the twin and 1b's maps. Node tests.
- 1d. The Prototype view: a toolbar toggle, frames per tab and shared component on `PGGraphCanvas.create()` read-only, named ports per hotspot, arrows spread, end chips, a hotspot click selects its node (`_sdFindNodeForEl()`), positions per browser; print pg_graph_canvas and pg_link_resolve before style_designer.min.js without defer; `_sdT()` texts by `php tools/tr_add.php`.

## Where to start?
- 1c next, once #5603 merged: a pure collector of a tree's hotspots (style_designer.src.js or a small bundle of its own, Node tests like tests/graph_canvas_test.php), asking the 1b read with the hrefs, menu and region names it found.
