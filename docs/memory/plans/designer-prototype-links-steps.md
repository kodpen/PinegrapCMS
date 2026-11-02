---
name: designer-prototype-links-steps
description: Steps of plans/designer-prototype-links.md slice 1 (read-only Prototype map), one PR each, about 1,000 product lines
---

## Which steps make slice 1?
- 0. Done #5401: graph canvas core slice 4 (sizes, named ports, other targets, stable ids, thumbnails): ui/graph-canvas-core-sizes-ports.md.
- 1a. Done #(this PR): the resolver's JS twin with a parity test (platform/link-resolve-js-twin.md).
- 1b. The map's server read (an api.php action, `pg_panel_actions()` row, roles as the designer): name maps of the hrefs the design's trees hold (`pg_link_maps_for_items()`), the draft and bin folder ids, classic menus (`pg_menu_tree()`) and legacy regions by region name; at content level no page of a hidden folder by name (an unknown answer instead). Pure shaping in includes/fn with tests.
- 1c. Pure collection in the designer: hotspots of a tree (`props.href`, the `_attrs` href when the prop is empty, btn, `<a>` in text; no Bootstrap toggles), shared components once with their pages count, each hotspot's mark (page tab, draft, broken, external, contact, anchor) from the twin and 1b's maps. Node tests.
- 1d. The Prototype view: a toolbar toggle, frames per tab and shared component on `PGGraphCanvas.create()` read-only, named ports per hotspot, arrows spread, end chips, a hotspot click selects its node (`_sdFindNodeForEl()`), positions per browser; print pg_graph_canvas and pg_link_resolve before style_designer.min.js without defer; `_sdT()` texts by `php tools/tr_add.php`.

## Where to start?
- The first step no open PR holds, after the one before it merged.
