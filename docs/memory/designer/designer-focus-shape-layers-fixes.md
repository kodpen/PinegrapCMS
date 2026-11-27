---
name: designer-focus-shape-layers-fixes
description: Image focus, Clip shape, curve and background layer rules since the #12015 hunt: canvas width, shape outline, curve keys, Position and Repeat rows, stale focus
---

## Which screen does the Focus control write after the canvas bar changes?
- The one the bar shows now. The bar draws no panel, so `style_designer_focus.js` watches `#sd-iframe-wrap`'s width and draws the shown controls again through `K._sdFocusBind()` 150 ms after it settles, never during a drag or the keys.
- Symptom fixed (#12015): a control drawn at full width wrote `objectPosition` (desktop) after the bar was set to a phone.

## When does the dashed shape outline show?
- Only while the element's own rules (Normal's or a state's, outside media queries) hold a shape or a mask other than None: `PGClipShape.has(text, cls)`, asked by the part's `outline()`.
- Symptom fixed (#12015): another element's clip-path drew it on every element with a custom class. The core's "More" test answers the same since #12016 (`designer-options-shape.md`).

## What happens to the easing curve's arrow keys when the editor opens again?
- The keys' timer (500 ms) belongs to its editor. The same row opened again before it fires (a panel drawing) keeps the keys' curve and writes it when they rest; another row's editor is never written by it.
- Symptom fixed (#12015): the keys' curve was lost, and the old timer could write a stored curve as plain numbers, leaving its design variable.

## What do a layer's Position and Repeat rows show when the layer sets none?
- What CSS draws: Position the top left (`PGBgLayers.shownPlace()`), Repeat "Repeat" (`shownRepeat()`). "No repeat" writes `no-repeat` on a picture and a gradient (`repeatOf()`).
- Symptoms fixed (#12015): the nine places showed the middle, also right after the top left was chosen; a gradient's "No repeat" was written as repeat and jumped back.

## What happens to a focus per screen when a layer write replaces the position?
- A write whose `background-position` no longer reads `var(--pg-focus…)` (a place chosen, the picture removed) takes `--pg-focus-d/-t/-p` and the `pg-focus` class out, unless the style's `object-position` (a video's) still reads them: `PGBgLayers.focusLeft(style, vals)`.
- Symptom fixed (#12015): the Focus control showed again a phone's focus the page no longer drew.

Related: docs/memory/designer/image-focus-control.md, docs/memory/designer/designer-options-shape.md, docs/memory/designer/designer-options-curve.md, docs/memory/designer/designer-options-bg-layers.md
