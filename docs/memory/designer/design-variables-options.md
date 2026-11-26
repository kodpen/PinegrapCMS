---
name: design-variables-options
description: Options controls offering a design's variables (DV3): which rows, the list, the chip and Detach, the expression a bound control holds, "Used in n places"
---

## Which controls offer variables?
- The single element's panel: Text Options "Color", "Text Bg", "Font Size"; Style "Text Color", "Background Color", Border "Color". `row(label, ctrl, vars)` with `vars` `'color:color'`, `'color:background-color'`, `'color:border-color'` or `'size:font-size'` wraps the control (`_sdVarCtl()`, after the button group is made).
- The spacing box's eight sides and the corners' centre (part `spacing`, PR #11622): `design-variables-spacing.md`.
- Not offered: several elements selected, a design without themes or Bootstrap.

## How does a person bind one?
- A quiet 22 px "Variables" button (braces icon) beside the control, shown on hover or focus; nothing in the panel's head. It opens the list of the part `variables` (`pick`), 252 px under the control: the first-use line with "Got it" (`localStorage` `sd_vars_tip`, never again once dismissed or a variable chosen), "Search variables", "Variables", "Your own", "Type scale" (size), then "Custom colour…" / "Custom value…", which drops a binding and opens today's control.
- The list closes on Escape, a click outside or a choice, focus back on the control's button; arrows walk the search and rows, Enter in the search takes the first row (taken in the list's document listener, before the right panel's Enter guard blurs it; #11835). Two document listeners, only while open.

## What does a bound control hold?
- An inline style in `props._attrs` style (`setAttrStyleProp()`), so it beats the utility class and needs no column: `var(--pg-var-<token>)`, a step `calc(var(--pg-var-size-base) * var(--pg-var-scale) …)` (a negative step divides), a multiple `calc(var(--pg-var-space-unit) * n)`. `_sdVarExpr()` writes, `_sdVarParse()` reads back; anything else is a typed value.
- Steps: XS -2, S -1, L 1, XL 2, 2XL 3, 3XL 4, 4XL 5, 5XL 6 (`_SD_VAR_STEPS`); units 2, 3, 4, 6, 8 (`_SD_VAR_UNITS`). The expression works them out, so a change of base size, scale or unit moves every bound element.
- Binding in a design with no variables file writes the model into `style_variables` and tries its stylesheet on, so Save writes the file (`design-variables-tab.md`).

## What does a bound control show?
- The chip in the control's place (the control stays hidden in the row): the swatch (or the type icon), the name ("Brand", "Scale 2XL", "6 units"), "Detach". Names come from the part (`_sdVarNames`), before it runs from `_sdVarNamesMap()` or the key.
- "Detach" (part `detach`) writes the value of that moment: a colour's light value, a step or multiple in whole px. A typed value of the property then shows as a field (Enter or leaving writes it; `CSS.supports()` refuses a bad one with one red line; empty brings back today's control). Background colour has no field: "Custom Background Color" already shows it.

## How does it meet Font Size per screen size?
- The size chooser (#11571, part `spacing`, `sizes`) writes `font-size` into the element's custom style class per size; a bound or typed value here is inline, so it wins at every size. The chooser still swaps the row's select inside the wrapper.

## How is "Used in n places" counted?
- `_sdVarUsage()` over the trees of the pages open in the editor (`_pgTreeOf()`): per token, the elements whose style reads it, once per element. The Variables tab shows it per row ("Not used yet", "Used in 1 place", "Used in {var} places"); a step counts for Base size and Type scale.
- Pages not open in the editor and shared components' stored rows are not counted.

## What was measured?
- PR #11589 (Node 22): core `style_designer.min.js` 3,015,394 → 3,020,393 bytes (gzip 790,377 → 792,290); part `variables` 9,182 → 14,906 (gzip 3,186 → 5,052). `_sdVarUsage()` on four copies of the fixture page (6,156 nodes) 0.17 ms. No browser run.

Test: `tests/designer_options_variables_test.php` (pure helpers under Node).
