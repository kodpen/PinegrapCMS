---
name: prototype-arrangement
description: How the Prototype view's layout() builds and places frames, rows, chips and arrows: a grid of tab-group sections in flow order, shared parts left, chips right, Start, Tidy up
---

## What does layout() build?
- `PGProtoHotspots.layout(marked, saved, opts)`: frames `p:<tab key>` and `s:<shared id>`, each 248 wide (`FRAME`), 44 + 30 per row + 10 high; a menu or region gives one row per link (label "menu › item"), one broken row when the read knows no such name.
- One chip (`c:<n>`) per mark and href outside the tabs; arrows `<frame id>|<port>` to the tab frame or chip; `rowY()` is a row port's height.
- Answers `{ frames, chips, edges, headings, start }`.

## Where does each go?
- Places come from the graph canvas core (`ui/graph-canvas-core-logic.md`): `G.arrange()` with `GRID`: 8 a row, gaps 96, headings 40, side columns 160 from the grid.
- `opts.sections` (`[{ id, label, keys }]`, the tab groups in the strip's order): one band each; pages in no group under `opts.rest`; without sections one band with no heading.
- Architect (2026-11-16, agent channel message 5878): the start's band leads. When the first tab is in no group, the "Pages" band comes first and the groups follow in the strip's order; when it is in a group, "Pages" is last. So the start is always the first box of its band and the flow runs down.
- Inside a band: `G.order()` over the tab-to-tab arrows from the first tab (`start`, its frame `start: true`, the head says "Start"); between equals the strip's order; pages nothing reaches last.
- Shared frames in a column before the grid under `opts.shared`; chips after it under `opts.outside`, one sub-group per mark (page, anchor, contact, external, hidden, broken).
## What does Tidy up clear?
- "Tidy up" in the bar (icon and name from 768 px, icon alone on a phone; title and label on both) asks first, then draws the arrangement again. An operator who may arrange tabs (`_pgTabLayoutEditable()`) and is not in view mode (`_collab.mayEdit`) clears `_pgTabProto` through `_pgTabsLayoutSave()` and this browser's places; the confirmation says it is for every editor of the design.
- Anyone else, and view mode (the design open in another tab), clears only this browser's places: the confirmation and a toast say the shared places stay.

## What else?
- `saved` (id → x, y; designer/prototype-places.md) overrides a place. The headings are drawn once the view passes the core's `groups()`.
