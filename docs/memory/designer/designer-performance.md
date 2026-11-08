---
name: designer-performance
description: How the visual designer's speed is measured (the ?sd_perf=1 probe, the paint table, the big fixture page and its Node timing) and the numbers measured so far
---

## How is the editor's speed measured?
- Open the designer with `sd_perf=1` in the address (`style_designer.src.js`, `_sdProbe*`). Five measures, each a console line `[sd-perf] <measure> <ms> ms (<nodes> nodes)` and a badge in the bottom-left corner: `open` (navigation start to the end of the first draw), `first draw` (the first `render()`), `edit` (every later outermost `render()` outside a tab switch), `tab switch` (`_pgTabsSwitch()`, its synchronous part with its draw), `save` (`saveAjax()` call to its `onSuccess`; a failed save is not counted). `StyleDesigner.perf('probe')` prints count, median and worst per measure.
- Without the parameter the wrappers return the function itself: nothing is wrapped, no behaviour change.
- `StyleDesigner.perf()` (always on, older) is the per-surface paint table: calls, total and worst ms of `render`, `renderCanvas`, `renderTree`, the panels, `saveState`; `perf('watch')` logs each click's burst of paints.

## What is the fixture page?
- `tests/fixtures/designer/big_page.json`: the `main` of the four largest template home pages (cafe, wedding, gym, realestate) in one tree, ids `sd_0`…`sd_1538`, 1,539 nodes, 245 KB. A real template page is at most about 400 nodes, so it is a heavy page, not a typical one. Paste it into a page's code view or load it with `StyleDesigner.loadTree()` to measure the canvas.
- `node tests/js/designer_render_timing.js` (`--json` for the test) times the parts of the edit cycle that need no DOM, cut out of the editor's source; `tests/designer_perf_probe_test.php` runs it and the probe's pure helpers.

## What are the numbers so far?
- Node 22, 4 cores, median of 25 runs on the fixture (PR #7987):

| Part | Who pays it | ms |
|---|---|---|
| `JSON.stringify(tree)` | undo snapshot, every `saveState()` | 0.76 |
| baseline JSON (`_pgBaselineTreeKey`) | dirty check `_pgBaselineOf()` | 1.22 |
| `_sdSharedTreeJSON()` | shared snapshot / `_sdSharedChanged()` per component | 1.18 |
| `JSON.parse` of the tree | undo, redo, revert | 0.98 |
| `findNodeById()` last node | each lookup | 0.08 |

- So the serialising is a few ms per edit even on this page; the cost an operator feels is the DOM: the whole canvas rebuilt by `renderCanvas()` and the eight panels redrawn by `render()`. Browser numbers of the probe on the fixture were not taken yet (no running instance in the measuring session); D2 (#8048, `designer-incremental-canvas.md`) made a one-node edit redraw only that element; its browser `edit` numbers are still to be taken with the probe. D5 records the `open` before and after.
