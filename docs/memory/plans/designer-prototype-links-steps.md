---
name: designer-prototype-links-steps
description: Steps of plans/designer-prototype-links.md slice 1 (read-only Prototype map), one PR each, about 1,000 product lines
---

## Which steps make slice 1?
- 0. Done #5401: graph canvas core slice 4 (sizes, named ports, other targets, stable ids, thumbnails): ui/graph-canvas-core-sizes-ports.md.
- 1a. Done #5492: the resolver's JS twin with a parity test (platform/link-resolve-js-twin.md).
- 1b. Done #5603: the map's server read (designer/prototype-map-read.md).
- 1c. Done #5619 (designer/prototype-hotspots.md): hotspots of a tree (`props.href`, the `_attrs` href when the prop is empty, btn, `<a>` in text; no Bootstrap toggles), shared components once with their pages count, each hotspot's mark (page tab, draft, broken, external, contact, anchor) from the twin and 1b's maps. Node tests.
- 1d. The Prototype view: a toolbar toggle, frames per tab and shared component on `PGGraphCanvas.create()` read-only, named ports per hotspot, arrows spread, end chips, a hotspot click selects its node (`_sdFindNodeForEl()`), positions per browser; print pg_graph_canvas and pg_link_resolve before style_designer.min.js without defer; `_sdT()` texts by `php tools/tr_add.php`.

## Where to start?
- 1d next, once #5619 merged: print pg_link_resolve.min.js and pg_proto_hotspots.min.js before style_designer.min.js; call `PGProtoHotspots.design(_pages with the open tab's live tree, _sharedCache)`, send its `request` to the 1b read, `mark()` the answer and draw the frames (designer/prototype-hotspots.md).
