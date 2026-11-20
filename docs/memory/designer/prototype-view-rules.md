---
name: prototype-view-rules
description: What a later change to the designer's Prototype view must keep (K1 to K9), how its parts are split between the view and the graph canvas core, and what was left out of its rebuild
---

## What must the view keep (K1 to K9)?
- (K1) An arrow is the link's href: drawing writes `props.href`, the `_attrs` href, the btn or the nth link of a text (`hrefPatch()`), kept by page id for a tab. The normal Save or Publish writes it; the view has no save path of its own.
- (K2) Who may draw: roles 0-1 everywhere, roles 2-3 inside editable areas; a locked tab draws nothing; classic menus, legacy regions and widget page settings are read-only arrows.
- (K3) The two warnings, once per opening (once per batch for Suggest targets): a shared component changes everywhere, a live page's link goes live on Publish.
- (K4) Ctrl+Z and Ctrl+Y undo the open tab and read the map again; the editor's document shortcuts skip the view.
- (K5) Broken has one definition, `pg_link_resolve()` and its JS twin; no hidden page name reaches a content-level operator.
- (K6) Moved frames of saved tabs and shared components are kept on the design (`proto` of the tab layout, both normalisers), the rest per browser.
- (K7) The view is a snapshot; a later load or a close drops an earlier answer (`_sdProto.seq`).
- (K8) Bootstrap toggle links are no hotspots; a classic menu or region gives one link per item.
- (K9) `pg_proto_hotspots.js` stays pure and tested in Node (`tests/proto_hotspots_test.php`).
- The plan `designer-panels` names these as its K11.

## How is the work split between the view and the core?
- What a box-and-arrow canvas needs whatever its boxes mean (order, arrangement, focus, routing, checks, dashed arrows) is in `pg_graph_canvas.js` as pure helpers or optional adapter fields; a user that passes none draws as before. Nothing meaning page, link or prototype goes into the core; nothing generic is written in the view (ui/graph-canvas-core-module.md).
- A change to the core runs `tests/graph_canvas_test.php`, `tests/workspace_flows_canvas_test.php` and the Flows harness (workspace/workspace-flows-canvas-harness.md).
- New core classes are `pgc-` and read only `--pgc-*` tokens; the view themes them under `.sd-proto` for light and dark. Texts: `_sdT()` in the designer, a `pgc_*` key in `pg_graph_canvas_js_strings()` for the core.
- No schema change and no new endpoint was needed; a need for either is a question to the architect.

## What did the plan prototype-next add (PX1 to PX6)?
- Zoom to a frame (designer/prototype-zoom.md), a links panel row dragged onto a frame (prototype-row-drag.md), the amber count as a button (prototype-badge-list.md), Ctrl+F (prototype-search.md), the shortest click path (prototype-shortest-path.md), the viewer's missed click and Play's start (prototype-viewer-missed-click.md). Each kept K1 to K9; none needed a schema change or an endpoint.

## What was left out?
- Flows using the new core parts (Tidy up, the checks, routing): only on a later yes of the owner.
- A stored screenshot of a page: a picture is the real page (`workflow/decisions-made-product-35.md`).
- `G.check()`'s `stuck` boxes (no way out) are shown on no screen.
