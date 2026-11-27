---
name: designer-performance
description: How the visual designer's speed is measured (the ?sd_perf=1 probe, the paint table, the big fixture page and its Node timing) and the numbers measured so far
---

## How is the editor's speed measured?
- Open the designer with `sd_perf=1` in the address (`style_designer.src.js`, `_sdProbe*`). Five measures, each a console line `[sd-perf] <measure> <ms> ms (<nodes> nodes)` and a badge in the bottom-left corner: `open` (navigation start to the end of the first draw), `first draw` (the first `render()`), `edit` (every later outermost `render()` outside a tab switch), `tab switch` (`_pgTabsSwitch()`, its synchronous part with its draw), `save` (`saveAjax()` call to its `onSuccess`; a failed save is not counted). `StyleDesigner.perf('probe')` prints count, median and worst per measure.
- Without the parameter nothing is wrapped.
- `StyleDesigner.perf()` (always on, older) is the per-surface paint table: calls, total and worst ms of `render`, `renderCanvas`, `renderTree`, the panels, `saveState`; `perf('watch')` logs each click's burst of paints.

## What is the fixture page?
- `tests/fixtures/designer/big_page.json`: the `main` of the cafe, wedding, gym and realestate home pages in one tree, 1,539 nodes (`sd_0`…`sd_1538`), 245 KB; a real page is at most about 400. Paste it into a page's code view or load it with `StyleDesigner.loadTree()`.
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

- So the serialising is a few ms per edit; the cost an operator feels is the DOM (`renderCanvas()`, the panels). The probe's browser numbers on the fixture are not taken yet: `edit` after D2 (#8048, `designer-incremental-canvas.md`), `open` after D5 (`designer-parts-on-demand.md`).

## What does selecting an element cost in the browser?
- After #9498 a click on a Store home page text took 182-187 ms on the sandbox (the Options panel's naming pass, `designer-options-panel-names.md`). The `?sd_perf=1` probe counts no selection (no `render()`).
- Forced layouts of a selection and a render (#11974): `designer-selection-one-layout.md`.
- Issue #11987, same session, built bundle, sandbox Chromium 1440x900, Store template unsaved (142 nodes, 1,862 canvas elements). Five clicks at 12/30/50/70/88 % of the canvas height, then a palette Row double-click; sum of long tasks, three runs:

| | before | after |
|---|---|---|
| five selections | 1,352 / 1,366 / 1,414 ms | 317 / 337 / 349 ms |
| Row insert | 899 / 980 / 989 ms | 375 / 401 / 456 ms |
| `render` on the insert | 306-362 ms | 178-226 ms |

- A new design with three CTA blocks (102 elements): no long task before or after.
- Cause: style passes, not layout. A `:has()` outside its selector's subject restyled every `[class]` element of the designer on any DOM change, and the canvas's `:has()` over hover and selection restyled the whole canvas (`ui/css-has-outside-subject.md`).
- Left: a click that draws a new branch of the Layers tree (`renderTree()`, about 60 ms of style and layout there).

## What did the speed work (D1-D7) leave as it was?
- The save path and its checks (`pg_designer_save_page()`) and its lock, the drafts model (`page-drafts.md`), the canvas's Bootstrap 5.3.8 output and the three places of a widget's default layout (`system-widget-change-checklist.md`). A change to one of them is a question for the architect, not part of a speed step.
