---
name: designer-overview-follows-selection
description: The designer's Overview tree follows the selection from every panel: one place opens and scrolls to the row (_sdTreeRevealSelection), identity lookup, instant scroll, when it must not move.
---

## How does the Overview find the selected element's row?
- Every drawing of the tree does it: `renderTree()` and the in-place selection mark `_sdTreeSelectInPlace()` (which `renderSelectionOnly()` uses). So a selection from the canvas, the HTML panel, a form control's mousedown, Ctrl/Shift click, the context menu, a paste or a new element all bring the row into view the same way. Do not add a scroll of your own in a selection handler.
- `_sdTreeOpenToSelection()` runs before the rows are drawn: for a newly selected node it opens the branches over it (`expandAncestorsAnywhere()`, widget chain included), so the row exists.
- `_sdTreeRevealSelection()` runs after: it takes the row with `_sdTreeRowOf(list, node)` (object identity) and scrolls only `#sd-overview-tree-wrap` (`_sdTreeScrollRow()`).

## Why did the Overview not keep up before (PR #494)?
- Only a plain canvas click scrolled it, from the click handler: 80 ms later, `scrollIntoView({behavior:'smooth', block:'nearest'})`. The HTML panel, form controls, the context menu and the rest never scrolled it, so the operator clicked the element above or below again and again to find the row.
- It took the row with `querySelector('li[data-nid="…"]')`, the first row with that id. Page trees and widget trees both count ids from `sd_1`, so on a page whose header widget comes first, a click on the page's `sd_3` scrolled to the header's `sd_3` row at the top of the tree.
- `nearest` left the row on the panel's very edge; the smooth scroll was still running when the next element was clicked.

## Where does the row land?
- Already fully in view: nothing moves (a click on a row in the tree itself never jumps).
- Cut by an edge: it moves just enough to be whole.
- Out of sight: to the middle of the panel, parent above, children below. Set with `scrollTop`, at once, not smooth.

## When must the tree not move?
- A drawing for the selection it already showed (`_sdTreeShownFor`): a property edit, a chevron, a validation pass. The operator's own scroll stays, and a branch closed over the selected node stays closed (`_sdTreeOpenedFor` opens it once per selection).
- A hidden tree (Overview section closed, Notes tab open) is measured as zero height and left alone; opening the section (it calls `renderTree()`) or switching back to the Tree tab shows the row.
- A tree replaced by undo, redo or the assistant has new node objects, so the selection counts as new and is shown again.
- Clicking the selected element again on the canvas forces the reveal (`_sdTreeRevealSelection(true)`): the way back after scrolling the tree away.
- "Show in Overview" / "Select Parent" use `scrollTreeToNode()`, which goes through the same `_sdTreeScrollRow()`.

Related: docs/memory/designer/designer-find-node-for-el.md, docs/memory/designer/designer-panel-element-identity.md
