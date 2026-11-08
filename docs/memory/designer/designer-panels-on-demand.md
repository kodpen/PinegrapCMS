---
name: designer-panels-on-demand
description: The designer draws only the panels that can be seen, debounces them while a field is typed into, and redraws one node's Layers rows (D3, PR #8075)
---

## Which panels are drawn only while seen?
- The Layers tree (`#sd-overview-tree-wrap`), the HTML tree (`#sd-html-tree`), Attributes (`#sd-bp-attrs`) and Styles (`#sd-bp-styles`). Each drawing function asks `_sdPanelDefer(name)` first: a host that is not laid out (display:none on it or an ancestor: the Notes tab, a folded Overview) or sits in the folded bottom panel (`.sd-panel-bottom.collapsed`) is marked in `_sdPanelStale` and nothing is drawn.
- `renderTree()` still refreshes the notes list when it skips: that list may be the one on screen.
- The code view (`_sdCodeRefresh()`) is not deferred: it is asked before the check.
- The Properties panel and the status bar are always drawn.

## How does a skipped panel catch up?
- `_sdPanelsFlush()` draws every marked panel that can be seen now. It runs when the bottom panel unfolds, when the Overview's Tree tab is picked, and from a `ResizeObserver` put on a host the first time it is skipped (any other way of showing it).

## What happens while a field is typed into?
- The canvas is drawn on every key (D2's in-place redraw); the Layers tree, status bar, Attributes, HTML tree and live preview wait for a 180 ms pause (`_sdPanelsSoon(node)`, `SD_PANELS_TYPING_MS`). Paths: Properties `[data-prop]` text fields, `[data-nodeattr]` inputs, popover fields.
- Keys into two nodes before the pause draw the whole Layers tree. `render()` cancels a pending run (`_sdPanelsCancel()`): it draws everything itself.
- The validation was already debounced (`scheduleValidation()`, 500 ms).

## When is only one node's Layers row redrawn?
- `_sdTreeRefresh(node)`: from `render()` with the scope of `_sdRenderNode()` (read before `renderCanvas()` takes it), from the typing run, from the in-place text commit. `_sdTreeRowsRedraw(node)` builds the node's row and its `ul.sd-tree-children` again with `buildTreeItems()` at the row's depth and swaps them in; the list's epoch is set so `_sdTreeSelectInPlace()` keeps working.
- Ancestors' rows stay: a row shows only its own node (label, id, badges, icon).
- The whole tree instead: no node, the root, a node in a shared component, no row, a stale list or one drawn from another page.

## How is it tested?
- `tests/designer_panels_on_demand_test.php` runs the section between `// ── Panels on demand` and `// ── end of panels on demand` under Node with stand-ins, and checks the wiring.

Related: docs/memory/designer/designer-incremental-canvas.md, docs/memory/designer/designer-performance.md
