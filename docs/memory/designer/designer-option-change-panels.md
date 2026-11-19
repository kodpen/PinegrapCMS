---
name: designer-option-change-panels
description: One Options panel select or switch change draws the Options panel and the node now; Layers rows, status bar, Attributes, HTML tree after the pause; badges of the redrawn element (#10013)
---

## What does one option change draw right away?
- A select or switch of the Options panel calls `_sdRenderNode(node, render)`. When `renderCanvas()` draws only that node's element again (`_sdCanvasRedrawNode()` sets `_sdCanvasRedrawnEl`), `render()` draws the Options panel, the Styles panel and the context bar now, and puts the issue badges on the new element alone (`applyIssueBadges(el)`).
- The Layers rows, status bar, Attributes panel, HTML tree and live preview are handed to `_sdPanelsSoon(node)`, as typing is: they are drawn after a 180 ms pause, once for a run of changes.
- A full canvas rebuild (structure change, a prop in `_SD_SCOPE_FULL_PROPS`, no single wrapper) still draws every panel and every badge in the same task, and cancels a pending run.

## Why?
- On a 145-node page one Overflow change cost about 250 ms, of which the canvas was 1.4 ms: Attributes 75 ms, status bar 59 ms (`_positionSizeBadge()` layout reads), two badge passes of 33 ms, HTML tree 23 ms (#10013).
- The badges of the rest of the canvas were not rebuilt, so removing and putting back all of them was wasted; the debounced validation (`scheduleValidation()`, 500 ms) still refreshes every badge.

## How does the Attributes panel find a chip's control?
- `_sdClassControlIndex(panel, node)` reads the Options panel once per draw (toggle buttons and checkboxes by class, utility, style group and box model selects in document order); `_sdClassControlFind(idx, cls)` looks each chip up in the same order the old per-chip search used. `findControlForClass(cls)` (chip click) builds a one-off index.

## How is it tested?
- `tests/designer_option_change_panels_test.php` runs the section between `// ── Class control lookup` and `// ── end of class control lookup` under Node with a stand-in panel, and checks the `render()` wiring.

Related: docs/memory/designer/designer-panels-on-demand.md, docs/memory/designer/designer-incremental-canvas.md
