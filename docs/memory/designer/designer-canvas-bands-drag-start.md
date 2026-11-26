---
name: designer-canvas-bands-drag-start
description: Why a canvas spacing handle takes a drag (#11921): the handles' hit areas, the start value of an automatic or off-scale margin, the bands drawn again after the Prototype view closes
---

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

## What tests it?
- `tests/designer_canvas_bands_test.php`: `inner_handle_clears_the_outer`, `start_step`, `nearest_step_from_auto`, `key_step_from`. The hit areas and the drag itself were checked in Chromium on a sandbox (PR description).
