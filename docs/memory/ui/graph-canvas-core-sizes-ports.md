---
name: graph-canvas-core-sizes-ports
description: Graph canvas core slice 4 for the Prototype view: size per box, lower zoom, named ports, spread arrow ends, other targets, stable arrow ids, partial redraw, thumbnails
---

## Why was it added?
- The designer's Prototype view (plans/designer-prototype-links.md) is the core's second user: pages are tall frames with a row per link (hotspot), and arrows go from a hotspot to a page or an end chip. Every addition is optional; Flows passes none of them and draws as before (unit tests and the harness, 30 of 30 with and without `--min`).

## Which adapter fields are new?
- `size(item)` → `{ w, h }` (else 208 x 80): `box()` sets the width and height, `fit()`, `reveal()` and the arrows read it. `viewport(host)` takes `size` too.
- `zoomMin`, `zoomMax` (else 0.25 and 2); never below `G.ZOOM_FLOOR` 0.05. `fit()` stays at most 1.25.
- `targets` (a selector) and `target(element)`: what an arrow may end on beside `[data-node]` (an end chip), and its item. The chip must sit inside `ui.boxes` to lose the target mark.
- `portAt(item, name)` → `{ y }` from the box's top; without it the core reads the row holding the `[data-port]` dot (`offsetTop` up to the box, plus half the row's height); a row not laid out leaves from the middle.
- `thumb(item, holder)`: a box drawn with `{ thumb: true }` gets `div.pgc-thumb` before its content, filled once it is in sight (an IntersectionObserver on `ui.viewport`, at once without one); `destroy()` disconnects it.
- `edges()` is optional: without it a box moved (or put back) redraws only its own arrows (`reroute([id])`).

## How are ports and arrows given?
- `canvas.port('out', name)` makes a named dot (`data-port`) for a row; the user makes the row its positioned parent, as wide as the box.
- `canvas.edge(id, from, to, { port, slot, slots })`: id is kept in `data-edge` as given; a press selects `{ type: 'edge', id }`, a number when it is all digits (Flows' index), else the string.
- `G.spread([{ to, y }])` → `{ slot, slots }` per arrow: the arrows into one box ordered by their start height, so they do not cross at its left edge.
- `canConnect(from, to, port)` and `connected(from, to, port)` get the port name (null for a box's own out port).

## Load without defer?
- The file only defines `window.PGGraphCanvas` at load, so the designer may print it with `pg_js_bundle_tags(..., false)` before `style_designer.min.js`, which is printed without defer.
