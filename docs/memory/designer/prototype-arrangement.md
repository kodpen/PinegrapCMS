---
name: prototype-arrangement
description: The Prototype view's layout(): frames, rows, chips, arrows; a grid of tab-group sections in flow order, shared parts left (wrapped at the grid's height), chips right, Start, Fit's corner, Tidy up
---

## What does layout() build?
- `PGProtoHotspots.layout(marked, saved, opts)`: frames `p:<tab key>` and `s:<shared id>`, each 248 wide (`FRAME`), the 44 head, the picture's box (`thumb`; 30 without one) and 10 high, the rows taking no room (designer/prototype-marks.md); a menu or region gives one row per link (label "menu › item"), one broken row when the read knows no such name.
- One chip (`c:<n>`) per mark and href outside the tabs; arrows `<frame id>|<port>` to the tab frame or chip.
- Answers `{ frames, chips, edges, headings, start }`.

## Where does each go?
- Places come from the graph canvas core (`ui/graph-canvas-core-logic.md`): `G.arrange()` with `GRID`: 8 a row, gaps 96, headings 40, side columns 160 from the grid.
- `opts.sections` (`[{ id, label, keys }]`, the tab groups in the strip's order): one band each; pages in no group under `opts.rest`; without sections one band with no heading.
- Architect (2026-11-16, agent channel message 5878): the start's band leads. When the first tab is in no group, the "Pages" band comes first and the groups follow in the strip's order; when it is in a group, "Pages" is last. So the start is always the first box of its band and the flow runs down.
- Inside a band: `G.order()` over the tab-to-tab arrows from the first tab (`start`, its frame `start: true`, the head says "Start"); between equals the strip's order; pages nothing reaches last.
- Shared frames in a column before the grid under `opts.shared`; chips after it under `opts.outside`, one sub-group per mark (page, anchor, contact, external, hidden, broken).
- Many shared parts (PV4b): the shared column passes `maxH: 'grid'`, so past the page grid's height it goes on in further columns beside it, at least four frames each, never below the grid. Architect (2026-11-16, agent channel message 6079): with about 50 the single column left Fit at its smallest zoom on the middle of that column, the page grid out of view.
- Architect (2026-11-16, agent channel message 6252): the chips' column (outside this design) takes no `maxH`. A chip is about a quarter of a shared frame, and Fit on a design too large shows the grid's start, so a long chip column keeps the grid in view. If a real design shows trouble, it gets the same `maxH: 'grid'`, no new core code.
- Fit on a design too large for 10%: the view passes the core's `start()` from `PGProtoHotspots.fitStart(layout)`, the start frame's top left (raised to its band's heading right above it), so Fit shows the grid's start, not the middle (`test_proto_hotspots_many_shared_parts`).
## What does Tidy up clear?
- "Tidy up" in the canvas bar's Prototype tools (icon and name; the name folds with the bar's labels, title and aria-label stay) asks first, then draws the arrangement again. An operator who may arrange tabs (`_pgTabLayoutEditable()`) and is not in view mode (`_collab.mayEdit`) clears `_pgTabProto` through `_pgTabsLayoutSave()` and this browser's places; the confirmation says it is for every editor of the design.
- Anyone else, and view mode (the design open in another tab), clears only this browser's places: the confirmation and a toast say the shared places stay.

## What else?
- `saved` (id → x, y; designer/prototype-places.md) overrides a place. The headings are drawn once the view passes the core's `groups()`.
