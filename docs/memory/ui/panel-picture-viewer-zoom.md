---
name: panel-picture-viewer-zoom
description: Panel picture viewer (pg_lightbox.js): zoom measured from the fitted size, a zoomed picture pans by drag and by the arrow keys, Left/Right change the picture only at the fit
---

## How do zoom and pan work?
- `scale` is against the natural size; `fitScale` (contain fit, at most 1) is the lower bound and the baseline: `isZoomed()` is `scale > fitScale * 1.01`. Wheel, `+`/`-`, the toolbar buttons and a pinch clamp to `[fitScale, MAX_ZOOM]` (6x natural); double-click toggles between the fit and `fitScale * DBLCLICK_ZOOM` (2.4).
- Zoomed: a drag pans in every direction, and the four arrow keys pan a tenth of the stage (Shift: a third) through `panBy()` and `clampPan()`, so `+` then the arrows reach every part of the picture without a pointer.
- At the fitted size a horizontal drag over 60 px (or a fast flick) and Left/Right change the picture; Up/Down do nothing. The Previous/Next buttons always change it.
- `showImageAt()` sets `fitScale = 1` with `scale = 1` while the next picture loads, so quick arrow presses keep changing pictures instead of counting as zoomed.
- `applyTransform()` keeps the `pgl-zoomed` class (zoom-out cursor) in step, also after a wheel or keyboard zoom.

## What did it fix?
- The baseline used to be 1:1 (`MIN_ZOOM = 1`) while a large photo opens at its fit (below 1): the first wheel notch in or out, `+` or a pinch jumped straight to 1:1, Zoom out at 1:1 did nothing, at 1:1 a drag counted as a swipe (the overflowing top and bottom could not be reached, a sideways drag changed the picture), and the arrow keys always changed the picture, dropping the zoom.
- Pinned by `tests/lightbox_zoom_test.php`; checked headless in Chromium with a 1200x1200 picture on a 1366x700 window (wheel, `+`/`-`, arrows, Shift+arrow, drag, double-click, quick Right presses).
- Focus and closing rules: `panel-picture-viewer-focus.md`.
