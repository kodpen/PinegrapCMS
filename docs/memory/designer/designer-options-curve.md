---
name: designer-options-curve
description: The easing curve as built (DO19): the Easing list with stored curves and "Custom curve…", the editor under the row (part 'curve'), the written forms, stored curves as design variables of kind curve
module: designer_fx
---

## Where is the curve offered?
- Every Easing row: Transition in Shadow and effect (core, `_sdTransRowsHtml()`) and Interactions (part `interactions`). Both draw `_sdEaseHtml()`: the list's lines (Transition first "Default"), the group "This design's curves", a "Custom curve" line when the element holds its own, then "Custom curve…", and the button "Edit the curve" at the row's end.
- "Page transition" (DO20) is to use the same `_sdEaseHtml()` / `_sdEaseWire()` pair.

## What is the editor?
- The part `curve` (`includes/designer_parts/curve.php`, when `always`): `assets/js/pg_easing_curve.js` (`window.PGCurve`, pure) and `assets/js/style_designer_curve.js`. Opened under the row by the button or "Custom curve…" (`_sdEaseOpen()`); Esc or the button shuts it. No window, no bar button.
- A square SVG graph (at most 220 px), handles held to x 0..1 and y -0.5..1.5, the bead's track with ten marks, the four numbers in one field, "Play", "Save as…", "Update “name”" after a stored curve was changed. Lines: overshoot, refused numbers, "Changed from …", reduced motion.
- Written when a handle is let go, the arrow keys rest 500 ms, or the numbers are entered: one undo step each. A drag redraws only the editor (no canvas work).
- A write redraws the panel; `_sdEaseOpenAt` reopens the editor and the part keeps per row ('trans:<id>', 'fx:<id>') the curve it came from and the focus.

## How is a curve written?
- `pg_fx_easing_clean()` (twin `_sdEaseClean()`): a named easing; `cubic-bezier(x1, y1, x2, y2)` with x in 0..1, y held to -0.5..1.5, three decimals at most; a stored curve as `var(--pg-var-c-<key>, cubic-bezier(...))`, its numbers the fallback. Anything else falls back to the default.
- Interactions: `props._fx.easing` → `data-pg-fx-easing`; `pg_fx.src.js` takes a curve of that form. Transition: the rule's `transition-timing-function` (`PGOptionStates.transWrite()` accepts the same forms).

## How is a curve stored?
- As one of the design's own variables, kind `curve`, value "x1, y1, x2, y2" (`pg_design_variables_normalize()`), written as `--pg-var-c-<key>: cubic-bezier(...)`, exported as a `cubicBezier` token. No table, no column; it counts among the 24.
- "Save as…" and "Update" call the part `variables`' `curve(host, op, c)`, which takes the model of the moment (asked once when the tab never ran) and refuses a taken name. Shown only to full designers (`variables_state` is theirs).
- The editor reads the curves from the Variables tab's model, else `sdDesign.curves` (`pg_design_variables_curves()` of the stored file).
- In "Your own" a curve row has its sign and its numbers; a refused value marks the field and is not sent. Removing a used curve asks first; each element keeps the numbers of its fallback. "Used in n places" counts `_fx.easing` and User Styles rules.

## What is not built?
- The curve's sign inside the native Easing list (an `<option>` holds no picture); it shows in "Your own".

## Where is it tested?
- `tests/designer_curve_test.php` (clean, numbers, variable kind, `PGCurve` under Node) and the curve cases of `tests/designer_fx_test.php` (PHP, editor and site script twins).

Related: docs/memory/designer/designer-options-curve-drawing-curve.md, docs/memory/designer/designer-options-transition.md, docs/memory/designer/designer-interactions-part.md, docs/memory/designer/design-variables-tab.md
