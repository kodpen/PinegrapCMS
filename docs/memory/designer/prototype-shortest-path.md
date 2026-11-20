---
name: prototype-shortest-path
description: The Prototype view's shortest click path (PX5, #10644): Shift+click, Shift+Enter or the bar's path button picks the page to go to; pathOf(), the core's trail(), "No path", Esc
---

## What does it do?
- Architect (2026-11-19, plans/prototype-next.md): "the shortest click path between two selected pages."
- (PX5 #10644) From the selected page (the core's `focus`) to another page: its frames ringed in green (`.sd-proto-path-on`), its arrows drawn green in place of the selection's, the pages named at the canvas's foot (`div.sd-proto-pathbar`): "2 clicks: Home → Shop → Item". A step through a shared part placed on the page reads "Pay via Header" and its frame is ringed too.
- None: "No path from {page} to {page}: no link leads there.", the bar's edge and the page's ring amber (`-pathbar-none`, `.sd-proto-path-none`); the selection's own arrows stay.

## How is the second page chosen?
- Shift+click on a page frame, Shift+Enter on a frame in focus, or the path button (`bi-signpost-split`, "Shortest path to another page (Shift+click it)", last of the bar's Prototype tools; `aria-pressed`) and then a click on a page: the way on a phone. The selection never moves.
- Only pages: a shared frame or a chip is no end. With no page selected the bar says "Select a page first, then the page to go to."
- The press is the adapter's own kind `path` (no move, no selection); any other release ends the button's wait.

## What clears it?
- Esc (before the zoom's Esc, then closing the view), the bar's Close, selecting another frame or none, closing the view. A redraw (an arrow drawn, "Read the links again") works it out again; an end gone from the map clears it. Nothing is stored.

## How is it worked out?
- `PGProtoHotspots.pathOf(layout, from, to)`: breadth first over `layout.edges` into page frames; a page leads where its own links do and where the links of the shared parts in its `parts` do. Fewest clicks, then the arrows' order. Answers `{ frames, edges, hops: [{ from, to, edge, via }] }` or null. Widget `reaches` and bound `urls` (problemsOf()) are no clicks of it.
- The arrows are the graph canvas core's `trail(ids)` (ui/graph-canvas-core-focus.md): routed like the focus's, the rest hidden or faint with "All arrows", the frames at neither end dimmed but the selected one.

## How is it checked?
- `tests/proto_hotspots_test.php`: `test_proto_hotspots_path_of_two_pages` (pure), `test_proto_hotspots_view_shortest_path` (wiring, CSS). `tests/graph_canvas_test.php`: `test_graph_canvas_trail`. Not tried in a browser when it was built.
