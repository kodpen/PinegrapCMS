---
name: designer-selection-one-layout
description: A selection and a render() measure once after their last write (the measure batch, _sdMeasureBegin/_sdMeasureFlush); what it holds, the flush order, the Store template numbers (#11974, issue #11960)
---

## What does a paint pass do with measurements?
- `renderSelectionOnly()` and `render()` open a batch (`_sdMeasureBegin()`) and close it at their end (`_sdMeasureFlush()`). While it is open these only note their wish: `_sdTreeRevealSelection()`, `_positionSizeBadge()`, the Options panel's scroll (`_sdPropsScrollTo()`), the HTML tree row's `scrollIntoView`, `applyIssueBadges()`'s chrome placing, and the selection's `_sdPlaceSelectedChrome()`. `_sdTreeMapMarks()` also only notes its wish.
- The batch reads the Options panel's scroll and the four panels' visibility (`_sdPanelHidden()`, `getClientRects`) before the first write. During the pass `_sdPanelHidden()` answers from that.
- A nested pass leaves the measuring to the outer one. A microtask closes a batch that a thrown error left open.
- A new read on these paths goes into the batch; never read a measurement right after a panel write.

## In what order does the flush run?
- The designer's own panels first (the Layers row, the Options scroll, the HTML row), then the canvas chrome (toolbar spot, chrome, size badge). After a canvas write, a read in the designer lays the canvas frame out again.
- The tree map's marks run on the next animation frame (`_sdTreeMapMarksSoon()`), using the frame's own layout. Read after the Layers scroll, they cost one more layout.
- The "You are editing a shared component" toast shows in a `setTimeout` after the paint, because showing it reads its computed style.

## Why (symptom)?
- Store template, 1,862 canvas elements: each selection wrote and then read four or five times, and each read laid out the designer and the canvas again. Five selections took 2.4 s on the issue's sandbox, against 0.12 s in a small design. `renderStatusBar()` was almost all the forced layout of `_positionSizeBadge()`.

## What were the numbers (#11974)?
- Sandbox Chromium 1440×900, built bundle, three runs. Five canvas clicks:
  - Long tasks: 1,017–1,263 ms before, 876–914 ms after.
  - Paint table: 69–72 ms per action before, 18–20 after.
  - `renderStatusBar`: 139–142 ms before, 0 after.
- One Row insert:
  - Long tasks: 859–948 ms before, 754–806 ms after.
  - `render`: 357–406 ms before, 244–247 after.
- CPU profile with the source files served: `renderSelectionOnly()` 981 → 476 ms.
- Each of those layouts was mostly a style pass over the whole designer and canvas, which #11987 removed: numbers in `designer-performance.md`, cause in `ui/css-has-outside-subject.md`.

Related: docs/memory/designer/designer-canvas-redraw-paths.md, docs/memory/designer/designer-performance.md, docs/memory/designer/designer-selection-in-place.md
