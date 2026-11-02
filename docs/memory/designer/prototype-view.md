---
name: prototype-view
description: The visual designer's read-only Prototype view (_sdProtoOpen() in style_designer.src.js): the toolbar toggle, frames and chips on the graph canvas core, what a row click does, where positions are kept
---

## What is it?
- The view bar's Prototype toggle (`#sd-vb-proto`, `_sdProtoToggle()`) lays `section#sd-proto` over `.sd-iframe-area`: a bar (link count, zoom, fit, read again, close) and a `pgc-stage` drawn by `PGGraphCanvas.create()`. Escape or the close button hides it; it is a snapshot, "Read the links again" redraws it.
- `includes/designer_screen.php` prints `pg_graph_canvas.min.js`, `pg_link_resolve.min.js` and `pg_proto_hotspots.min.js` with `pg_js_bundle_tags(..., false)` before `style_designer.min.js` (`tests/proto_hotspots_test.php`). The panel's `backend.css` styles the `pgc-` classes; `style_designer.css` themes them (`.sd-proto`, its own `--pgc-*` values and arrowhead markers `#sd-proto-arrow(-on)`).

## How is it drawn?
- `_sdProtoLoad()`: `PGProtoHotspots.design()` over `_pages` (the open tab's live tree through `_pgTreeOf()`) and `_sharedCache`, `prefetchShared()` for components not fetched yet, `_pgApiPost('prototype_map', request)` (designer/prototype-map-read.md), `mark()`, then `layout()` (designer/prototype-hotspots.md). A later load or a close drops an earlier answer (`_sdProto.seq`).
- Frames (`p:<tab key>`, `s:<shared id>`) side by side, one row per link with a named port; chips per mark and href right of them; arrows spread with `G.spread()`, tab arrows in the accent colour, broken ones dashed red. A shared frame says on how many tabs it sits; the open tab's frame is outlined.

## What can be done in it?
- Frames and chips move (`editable()` is true, `canConnect()` false: no arrow is drawn by hand); a moved one is kept per browser in `localStorage` `pg_proto_pos_<style id>` (id → x, y). Shared positions (`style.style_tab_layout`) are slice 4.
- A row (click, Enter or Space) closes the view, opens its tab (`_pgTabsSwitch()`; for a shared frame the open tab if it places the component, else the first tab that does; a locked tab only gets the folder-rights toast), then selects the node (`findNodeById()` in the tab's tree or the component's) with `renderSelectionOnly()`, `renderProperties()` and `scrollCanvasToNode()`.
- Drawing and re-pointing arrows is slice 2 (plans/designer-prototype-links.md).

## What was checked how?
- `tests/proto_hotspots_test.php` covers the collector, the layout and the print order. The view's wiring was run once in headless Chromium on a stub harness (frames, chips, arrows, a row switching the tab and selecting its node); there is no checked-in harness for it.
