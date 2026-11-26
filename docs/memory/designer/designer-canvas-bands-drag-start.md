---
name: designer-canvas-bands-drag-start
description: Canvas spacing bands fixes: handles take a drag (#11921: late PGSpacingBox, hit areas, auto start), back after Prototype, hidden once the selection empties (#11939)
---

## Why do the handles work with Side by side on at load?
- The part reads `window.PGSpacingBox` where a change writes (`_bdSpacing()`), never once in its factory. The mirrors' first-use line asks the part `tour` (`_sdPartCall('tour', 'model', [])`), which brings `PGDesignerTour`.
- Before #11921 the mirrors' `cfg.tour()` loaded the part `bands` at start, before the part `spacing`; the factory kept `B = undefined` for the session and every drag threw `Cannot read properties of undefined (reading 'sides')`, writing nothing.

## Why can a margin handle be held on a side with no spacing?
- An inner handle sits at least one handle (6 px, `HANDLE`) inside the frame in `PGCanvasBands.layout()`, also for a padding thinner than that on screen; the outer one sits on the frame.
- Each handle's hit area (`.sd-cgb-h::before`, 6 px) reaches out only away from its pair: an outer handle outwards, an inner one inwards (`data-k` and `data-e` rules in `style_designer.css`).
- Before #11921 the reach went all round and the inner handle, later in the layer, covered the outer pill's middle: an outward drag on the margin handle wrote nothing, an inward one wrote padding.

## Where does a drag start?
- At the value the band shows (`C.startStep(px, scale, auto)`): a scale step when it is one (within 0.5 px); `{ step: 'auto' }` when the element's classes give that side `auto` at the canvas's size (`_bdAuto()`, `PGSpacingBox.read()`, e.g. `mx-auto`); `{ step: '' }` for any other off-scale value (a container's 12 px).
- A drag keeps that start until a scale step is nearer (`C.nearestStep(raw, scale, from)`); the arrows go to the next scale step that way, or stay (`C.keyStepFrom()`). The label shows "auto 186 px", or just the pixels for `''`.
- An `''` start never writes; `auto` with Shift or Alt writes `auto` to those sides.
- Before #11921 the start was the nearest step: 186 px of `mx-auto` at 1200 px read as step 5, an outward drag wrote nothing, 60 px inwards wrote `ms-xl-0` beside `mx-auto` and the element jumped to the edge.

## Why are the bands back after the Prototype view?
- `_sdProtoButtonState()` (run on open and close) calls `_sdBandsSoon()` beside `_sdLinkMarksSoon()`. Before #11921 nothing asked for a draw on close and the layer stayed hidden until another element was selected.

## Why do the bands go when nothing is selected any more?
- `renderStatusBar()` calls `_sdBandsSoon()` when it runs with no selection; `_sdBandsDraw()` finds no node and calls the part's `hide()`. Every full `render()` and `_sdPanelsNow()` run it, so a page tab switch (`_pgTabsSwitch()`), a removed page, deleting the selected element, an undo or redo that drops it and a tree laid again all hide the bands.
- Before #11939 (PR #11942) the only regular draw request was `_positionSizeBadge()`, which every caller skips without a selection: the old element's bands and handles stayed over the next page's canvas until another element was selected.
- A selection kept but not on the shown canvas still reaches `_sdBandsDraw()` through `_positionSizeBadge()`, which hides when `_sdCanvasInnerOf(node)` is missing.

## What tests it?
- `tests/designer_canvas_bands_test.php`: `read_the_spacing_box_late`, `inner_handle_clears_the_outer`, `start_step`, `nearest_step_from_auto`, `key_step_from`. The hit areas and the drag itself were checked in Chromium on a sandbox (PR description); the empty-selection hide too (tab switch, Delete, undo/redo; PR #11942).
