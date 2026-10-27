---
name: widget-hide-if-empty
description: pg-hide-if-empty in every widget kind (W23, #2225): how an element is judged, the value element, the finish pass, the variant wait, the editor switch and its canvas state
module: widgets_finish
---

## What does the mark do?
- An element of a widget tree with class `pg-hide-if-empty` is left out of the page when it came out empty, with its frame and its words (`pg_sw_hide_empty_fields()`). Any element can carry it; marked elements inside marked ones go first.
- It is judged by its first element with class `pg-field-value` (a "label: value" row, a heading over a list), else by everything inside it. Words, a picture with an address, a video, a frame, a drawing, a link and a form control count (`pg_sw_has_content()`); a condition's carrier does not.

## Where is it judged?
- Owner (2026-10-27): in every kind, with a switch in the panel; starter templates unchanged (their 465 marks all sit in the kinds that judged it before); a hand-written mark in another kind now really removes the box.
- The form list and item views and both comments kinds judge it in their renderer; the finish pass judges again around a cut. Every other kind is judged in the finish pass, stage 4 ([[widget-finish-pass]]).

## What about a value a variant fills later?
- On a product page with the variant picker (`pgCivVariants.push(` in the markup, `catalog_item_view`), an empty marked element holding a `data-pg-bind` element stays with `hidden data-pg-empty-wait`; an outer pass leaves it.
- `pg_civ_variants.js` (`settleEmptyBoxes()`, after a variant and after the API refresh) shows or hides every marked element around a `data-pg-bind` value by the same rule, hidden children not counting; a `data-pg-variant-flag` element stays the flag's.
- `[data-pg-empty-wait][hidden]{display:none!important}` is printed once before the widget, so Bootstrap's `d-flex` cannot show it.

## What does the editor give?
- Bind Data, "Hide when empty" on any element of a widget but root and loops (`_sdHideEmptyRows()`); inside a marked box also "Value of the box around it" (`pg-field-value`). Turning the first on for a box holding one list and no value element marks the list's holder as the value (`_sdHideEmptyPickValue()`), so the heading goes with an empty list.
- Canvas: a tree with a mark offers the condition states ([[widget-condition-canvas]]); `_sdHideEmptyCanvasOff()` hides a marked node the state empties: every bound value and list empty in "Empty values", a value without a sample in "Sample values", nothing in "All elements". Tested in `tests/widget_condition_eval_test.php`.
