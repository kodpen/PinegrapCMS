---
name: designer-options-transform
description: "Transform" in Shadow and effect as built (DO12): the rows, translate/rotate/scale per state, composed with Interactions, the canvas outline, the cost
---

## What do the Transform rows hold?
- In "Shadow and effect", after Transition, under the sub-head "Transform" (`_sdTfRowsHtml()`): "Move" (X and Y, a length; a bare number is px), "Scale" (slider 0.5 to 1.5 with its number, typed up to 10), "Rotate" (slider −180° to 180°, typed up to ±360).
- Under the section's "More" ("Less" when open): "Skew" (X and Y in degrees, ±89) and "Origin" (nine places, arrows move among them, the name and per cents beside). "More" opens by itself while Skew or Origin holds a value in Normal or the state.
- Shown for one element whose custom style is on (`_sdStateNode()`), as Transition. A value not set shows the default (or Normal's under a state) in grey.

## Where is it written?
- `PGOptionStates.tfWrite(text, cls, st, key, v)` (part `states`): Move as `translate`, Scale as `scale`, Rotate as `rotate`, Skew as `transform: skew()`, Origin as `transform-origin`, on `.pgs-xxxxxx` (Normal) or the state's rule, with no `!important`. Normal's defaults (0, 1, the middle) are not written.
- Under a state Move and Skew are one pair: the axis not set takes Normal's, and a later change of Normal's axis is carried into each state that took it. A pair equal to Normal's goes; a state's rule left empty goes.
- A `translate` or `transform` no row reads (three axes, `translate(-50%)`) is left as it is: the row reads empty and Skew does not write over it.
- Transition's "Size and place" now lists `transform, translate, rotate, scale, width, height, inset`; the older list still reads as Size and place.

## Why separate properties, and how does it meet Interactions?
- An Interactions effect writes an inline `transform`, which would replace the element's. `translate`, `rotate` and `scale` are properties of their own and apply under it. For Skew (and any stylesheet transform, like a centring class) `pg_fx.src.js` `base()` keeps the computed transform as `own` when the node has no inline one, and composes the effect after it; the inline transform it gives back is still the node's own inline one.
- A Skew set only under a state is not composed while a hover effect holds the element (the effect's base is taken once, in Normal).
- An element whose effect moves it (slide, scale, zoom out, rotate in, parallax, zoom or rise on scroll) shows one info line under the rows.

## What does the canvas show?
- While a Transform row is dragged or holds the focus, the part draws a dashed outline of the element's box with its own transform switched off ("The place it keeps") and the value as a tip; a dragged Scale or Rotate shows on the element at once (inline, `!important`, dropped on change or leaving the rows). Nothing of it is saved.
- Under a state the state's view (`#sd-state-view`) draws the state's transform, as for every state value.

## How does it load, and what did it cost?
- Rows are core markup; the model and the canvas outline are in part `states`, loaded when the User Styles hold a transform for the element or a row is first used. Core +6.1 KB minified (+1.7 KB gzip); part `states` 18.3 → 25.0 KB minified (6.5 → 8.9 KB gzip); `pg_fx.min.js` +0.1 KB.

## Where is it tested?
- `tests/designer_option_states_test.php` (`test_designer_option_states_transform`), `tests/designer_fx_test.php` (`test_designer_fx_script_keeps_the_stylesheet_transform`). The panel and canvas are not unit tested.

Related: docs/memory/designer/designer-options-transition.md, docs/memory/designer/designer-options-states.md, docs/memory/designer/designer-options-states-drawing-motion.md
