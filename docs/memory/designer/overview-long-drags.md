---
name: overview-long-drags
description: Designer Overview long drags: tree folded to its sections, a closed branch opened after 600 ms, 48 px scroll zones, the tree given back after Esc or a drop
---

## What happens when a row drag starts?
- `_sdTreeLongStart()` (called at the end of a row's `dragstart`) saves every branch's open state (`_sdTreeDragSave()`, shared components' trees included through `_sdTreeKids()`) and the tree's scroll.
- In the next task (a row changed inside `dragstart` cancels the drag in Chromium) every section with rows under it closes (`_sdTreeDragFold()`), and the dragged row's section is scrolled to where the row was. That section does not open again by waiting until the pointer has been on another row.
- Only drags started on an Overview row fold; a palette drag over the tree only gets the scroll zones.

## How does a closed branch open?
- A capture `dragover` on the document tracks the closed row under the pointer (`_sdTreeDragHover()`): after 600 ms on it, it opens (`_sdTreeLongOpen()`); another row starts the time again.
- While the time runs the row is framed and shows "opening" ("açılıyor") over its count (`.sd-tree-opening`, `.sd-tree-opening-pill`).

## How does the tree scroll?
- The top and bottom 48 px of `#sd-overview-tree-wrap` scroll it from 6 px a frame at the zone's inner edge to 24 at the edge (`_sdTreeDragScroll()`), in a `requestAnimationFrame` loop that stops 500 ms after the last `dragover`.
- During a row drag the zones are drawn (`.sd-tree-scroll-zone`, fixed, `pointer-events: none`): a double chevron, shown while the tree can scroll that way, accent while it does.

## What does the tree look like after the drag?
- Esc, a refused drop or a drop outside the tree: every branch as it was and the old scroll (`_sdTreeLongEnd(null)`).
- A drop: as it was, but the branches opened on the way that the drop went into (its parent and the branches over it, `_sdTreeDragKeep()`) stay open; the moved element is selected and its branches open as before (`_sdTreeMoveSelect()`).
- The drop's own redraw draws the restored tree; `_sdTreeDraws` tells `_sdTreeLongEnd()` whether it still has to draw.

## Why is the dragged row parked?
- A row removed from the document gets no `dragend`, so a redraw during the drag moves it into a hidden `#sd-tree-drag-park` (`_sdTreeLongPark()` in `renderTree()`); it is emptied after `dragend`. A pointer move with no button held while a drag is still marked ends it as Esc does.

## Which functions are pure, and where is the test?
- `_sdTreeDragSave()`, `_sdTreeDragFold()`, `_sdTreeDragRestore()`, `_sdTreeDragKeep()`, `_sdTreeDragHover()`, `_sdTreeDragScroll()` in `style_designer.src.js`, run under Node by `tests/designer_overview_long_drag_test.php`: the tree after Esc and after a drop, a branch opened after 600 ms kept open, the speed by distance.
