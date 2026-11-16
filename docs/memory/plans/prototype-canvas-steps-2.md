---
name: prototype-canvas-steps-2
description: Plan prototype-canvas, the view steps PV2, PV4, PV4b and PV6: the grid arrangement and Tidy up, the selected frame's arrows, many shared parts, the list and the problems
---

## Which steps are there?
- PV2 (M) The arrangement. After PV1. Done (#9883).
  - `layout()` hands `G.arrange()` the sections of F7, the shared column of F4 and the chip column of F8, and `G.order()` the arrows between tabs. The eight-a-row constant and the gaps sit beside `FRAME`. `saved` still overrides a place (K6).
  - "Tidy up" (F9): after the confirmation it empties `_pgTabProto` through `_pgTabsLayoutSave()` and the browser's places, then draws again. For an operator who may not arrange tabs it clears only the browser's and says so.
  - "Start" on the first tab's frame (F2). Frames keep today's sketch and link rows.
  - Tests: forty pages in three groups give eight a row in three bands; the flow's order inside a band; unreached pages last; a saved place wins; `places()` and its PHP twin unchanged.
  - Features: F2 (Start), F4 (the column), F6, F7, F8, F9; K6, K9.
- PV4 (M) The selected frame's arrows. After PV2 and PV3. Done (#9904).
  - The view passes `focus` and `groups()`. Opening focuses the open tab's frame. "All arrows" beside Fit, kept in `localStorage` beside the places.
  - The amber row on the shared frame and the line under it, no arrow (F13).
  - Arrow colours by class in light and dark, a marker for each: outgoing accent, incoming amber dashed, broken red.
  - Until PV9 the frame's link rows are the marks: the selected frame's rows are full; on another frame only the rows that start or end a drawn arrow are highlighted.
  - Tests: which arrows a focus draws for a page, for a shared frame and for none; F13 gives no arrow and one note.
  - Features: F10, F11, F12, F13; K7.
- PV4b (S) Many shared parts. After PV4; PV5 waits for it (both edit `pg_graph_canvas.js` and the view). Done (#9919). Architect, 2026-11-16, agent channel message 6079: a design with about 50 left Fit at its smallest zoom showing the middle of their column, the page grid out of view.
  - `G.arrange()`'s side columns take an optional greatest height: a column taller than it goes on in further columns beside it, at least four boxes each. The view passes the grid's height (F4).
  - `fit()` takes an option the view passes: when the whole does not fit at the smallest zoom, the view shows the start box's corner, not the middle of the bounds. Flows passes neither.
  - Tests: fifty boxes beside five rows wrap, a short column stays one; the view's x and y when it does not fit.
  - Pictures, 1440 and 390: Fit on a design with about 50 shared parts, before and after. The look is the architect's. Features: F4.
- PV6 (M) The list and the problems. After PV5.
  - `aside.sd-proto-list` right of the stage inside `section#sd-proto`; rows are buttons. Its rows come from `G.linksOf()` and the marked design, never from the DOM.
  - One selection state (frame id and port) for a row click and a mark click; `reveal()` brings the other end into view.
  - "Change target" and "Open in the editor" call today's `_sdProtoMenuOpen()` and `_sdProtoRow()`; L and the menu key work on a row.
  - The bar's "N broken" and its list, the badge on a frame's head: from the same marked design.
  - Tests: the list's rows for a page with outgoing, incoming, broken and shared-part links; nothing selected; the design's broken list and each frame's count.
  - Features: F15, F16, F17, F18, F19, F20, F24, F25; K2, K5.
