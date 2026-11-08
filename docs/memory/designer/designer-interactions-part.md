---
name: designer-interactions-part
description: The Interactions section of the designer's properties panel as the part 'interactions' (D5 part 7): what moved, what the core keeps, when it loads
---

## What is in the part?
- `assets/js/style_designer_interactions.js` (bundle `style_designer_interactions.min.js`, part `interactions` in `pg_designer_parts()`): the section's rows (`_sdFxRows()`: effect, trigger, duration, delay, easing, distance, stagger, every time, top offset, speed, the note), the field writer `_sdFxSet()`, the binding of the fields to `props._fx` (`_sdFxFill()`) and the Play button (`_sdFxReplay()`).
- Entry points: `fill(body, node)`, `rows(node)` (the HTML), `set(fx, field, value)`.
- Handed to every editor (`designer_screen.php`).

## What does the core keep, and why?
- What the page and the canvas need at open: `_sdFxSpec()`, `_sdFxNormalize()`, `_sdFxAttrs()` (twins of `includes/fn/designer_fx.php`), `_sdFxTakes()`, `_sdFxIntoFirstTag()`, `_sdFxCanvas()` (loads `pg_fx.min.js` into the frame).
- `propsInteractions(n)` draws the section with an empty `<div data-sd-fx-body>`; `_sdFxBind(n)` runs `_sdPartCall('interactions', 'fill', [body, n])` and then applies the panel's search box again.

## When does the part load?
- The first time an element that takes an interaction is selected with the section open. A collapsed section waits for its header to be opened; once the part has run, every panel draw fills the section (a promise tick later).
- Kit additions: `row`, `swAttr`, `_sdFxSpec`, `_sdFxTakes`, `_sdFxCanvas`, `_sdCanvasInnerOf`, `_sdUndoBeforeFirstInput`, `_sdLivePreviewSchedule`, `renderTree`, `renderStatusBar`, `renderAttrsPanel` and the getter `canvasDoc`.

## Which tests cover it?
- `tests/designer_fx_test.php` (`test_designer_fx_panel`) runs the core's twins with the part's `rows()` and `set()` under Node; `tests/designer_parts_test.php` (`interactions` cases) checks the split and runs `fill()` on a fake body.
