---
name: prototype-view
description: The designer's Prototype view (_sdProtoOpen()): toolbar toggle, frames and chips on the graph canvas core, row clicks, arrows drawn by hand, where positions are kept
---

## What is it?
- The view bar's Prototype toggle (`#sd-vb-proto`, `_sdProtoToggle()`) lays `section#sd-proto` over `.sd-iframe-area`: a bar (link count, zoom, fit, read again, close) and a `pgc-stage` drawn by `PGGraphCanvas.create()`. Escape or the close button hides it; it is a snapshot, "Read the links again" redraws it.
- `includes/designer_screen.php` prints `pg_graph_canvas.min.js`, `pg_link_resolve.min.js` and `pg_proto_hotspots.min.js` with `pg_js_bundle_tags(..., false)` before `style_designer.min.js` (`tests/proto_hotspots_test.php`). The panel's `backend.css` styles the `pgc-` classes; `style_designer.css` themes them (`.sd-proto`, its own `--pgc-*` values and arrowhead markers `#sd-proto-arrow(-on)`).

## How is it drawn?
- `_sdProtoLoad()`: `PGProtoHotspots.design()` over `_pages` (the open tab's live tree through `_pgTreeOf()`) and `_sharedCache`, `prefetchShared()` for components not fetched yet, `_pgApiPost('prototype_map', request)` (designer/prototype-map-read.md), `mark()`, then `layout()` (designer/prototype-hotspots.md). A later load or a close drops an earlier answer (`_sdProto.seq`).
- Frames (`p:<tab key>`, `s:<shared id>`) side by side, a thumbnail (designer/prototype-thumbnails.md) above one row per link with a named port; chips per mark and href right of them; arrows spread with `G.spread()`, tab arrows in the accent colour, broken ones dashed red. A shared frame says on how many tabs it sits; the open tab's frame is outlined.

## What can be done in it?
- Frames and chips move; where a moved one is kept (on the design or in the browser): designer/prototype-places.md.
- A row (click, Enter or Space) closes the view, opens its tab (`_pgTabsSwitch()`; for a shared frame the open tab if it places the component, else the first tab that does; a locked tab only gets the folder-rights toast), then selects the node (`findNodeById()` in the tab's tree or the component's) with `renderSelectionOnly()`, `renderProperties()` and `scrollCanvasToNode()`.

## How is an arrow drawn or re-pointed (#6171)?
- A link row of a tree (`PGProtoHotspots.drawable()`: sources href, attrs, btn, text; not a classic menu or region) has a port; dragged to a tab's frame it writes `<env.path><page name>` (the open tab's name as typed), to a chip of a site page, another site or a contact address that chip's href (`hrefTo()`); anchor, hidden, broken chips and shared frames take none. A locked tab draws nothing.
- `_sdProtoConnect()`: the row's tab (`_sdProtoFrameTab()`, as a row click) is opened first with `_pgTabsSwitch()` and a `_collabBeatNow()` answers its lock; then `hrefPatch()` (a copy of `href`, `_attrs`, `text` or `html`; null when the node no longer holds the href the map showed: the map is read again), `_sdMayEdit(node)` (`'text'` for a link inside text), `saveState()`, the prop written, `_sdSharedMarkEdited()` for a shared frame, `render()`, the map read again. The normal Save writes it (lock, revision, `_pgPageUnchanged()`).
- Once per opening: a shared component's warning (Save changes it everywhere), a live page's (Publish).
- Ctrl+Z / Ctrl+Y in the view undo the open tab and read the map again; the editor's document shortcuts skip `#sd-proto` (a Delete there removed the canvas selection).
- Keyboard (#6201): L, the menu key or Shift+F10 on a row opens `_sdProtoMenuOpen()`, the targets of `drawTargets()`; a choice calls `_sdProtoConnect()`. Escape refocuses the row; a press outside, a redraw or a close shuts it.

## What was checked how?
- `tests/proto_hotspots_test.php` covers the collector, the layout, the print order and the arrow's href and patch; the drag's wiring in the designer was not run in a browser. The view's wiring was run once in headless Chromium on a stub harness (frames, chips, arrows, a row switching the tab and selecting its node); there is no checked-in harness for it.
