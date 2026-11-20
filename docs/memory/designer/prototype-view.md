---
name: prototype-view
description: The designer's Prototype view (_sdProtoOpen()): what it is, its controls in the canvas bar, how a drawing is built, how an arrow is written and opened in the editor, and which file covers each part
---

## What is it?
- The canvas bar's mode switch, its Prototype side (`#sd-vb-proto`, `_sdProtoToggle()`), lays `section#sd-proto` over `.sd-iframe-area`: a `pgc-stage` drawn by `PGGraphCanvas.create()` and the links panel at its right. Each page is a frame showing its own picture, each link a mark on it; only the selected frame's arrows are drawn. Rebuilt in #9864 to #10243 (owner 2026-11-15; designer/prototype-view-decisions.md).
- It is a snapshot: "Read the links again" redraws it. Escape or the switch's Design side hides it; while the view is zoomed to a frame (a double-click on it), Escape first goes back (designer/prototype-zoom.md).
- `includes/designer_screen.php` prints `pg_graph_canvas.min.js`, `pg_link_resolve.min.js` and `pg_proto_hotspots.min.js` with `pg_js_bundle_tags(..., false)` before `style_designer.min.js`. `backend.css` styles the `pgc-` classes; `style_designer.css` themes them under `.sd-proto`, light and dark.

## Where are its controls?
- In the canvas bar's Prototype mode (#10507, designer/designer-canvas-bar-prototype.md). "N broken" and "N to check" open the problems list (designer/prototype-list.md, designer/prototype-problems.md).
- "All arrows" (designer/prototype-focus.md), "Tidy up" (designer/prototype-arrangement.md), "Suggest targets · n" (designer/prototype-suggest.md), "Play" (designer/prototype-viewer-missed-click.md).

## How is a drawing built?
- `_sdProtoLoad()`: `PGProtoHotspots.design()` over `_pages` and `_sharedCache`, `prefetchShared()`, `_pgApiPost('prototype_map', request)` (designer/prototype-map-read.md), `mark()`, `layout()` (designer/prototype-hotspots.md). A later load or a close drops an earlier answer (`_sdProto.seq`).
- Frames `p:<tab key>` and `s:<shared id>` in a grid of tab-group sections, shared parts left, chips right: designer/prototype-arrangement.md. The picture: designer/prototype-page-picture.md, its sketch designer/prototype-thumbnails.md; the marks and where an arrow starts: designer/prototype-marks.md; routing: ui/graph-canvas-core-routing.md.
- Moved frames and chips: designer/prototype-places.md.

## How is an arrow written?
- From a mark, an arrow head, the target menu (L) or Apply of Suggest targets (designer/prototype-drawing.md), or a links panel row dragged onto a frame (designer/prototype-row-drag.md). Only `drawable()` links: href, attrs, btn, text; never a classic menu or region. To a tab's frame it is kept by that page's id (designer/page-link-by-id.md), to a chip of a site page, another site or a contact address that chip's href (`hrefTo()`).
- `_sdProtoConnect()`: the link's tab (`_sdProtoFrameTab()`) opened with `_pgTabsSwitch()`, a `_collabBeatNow()` answers its lock; `hrefPatch()` (null when the node no longer holds the href: the map is read again), `_sdMayEdit(node)` (`'text'` inside text), `saveState()`, the prop written, `_sdSharedMarkEdited()` for a shared frame, `render()`, the map read again. The normal Save writes it.
- Once per opening: the shared component warning (Save changes it everywhere) and the live page warning (Publish). Ctrl+Z / Ctrl+Y undo the open tab and read the map again; the editor's document shortcuts skip `#sd-proto` and the canvas bar in Prototype mode.

## How does a link open in the editor?
- The panel's "Open in the editor" (`_sdProtoOpenRow()`) closes the view, opens the tab (a shared frame: the open tab if it places it, else the first that does; a locked tab gets the folder-rights toast), selects the node (`findNodeById()`), `renderSelectionOnly()`, `renderProperties()`, `scrollCanvasToNode()`.
- Back: P in the editor opens the view with the selected element's link picked (designer/prototype-back-key.md).
- What a later change must keep: designer/prototype-view-rules.md.
