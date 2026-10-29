---
name: widget-designability
description: Legacy-layout power in system widgets (W1-W26, done): the finish pass, its rules, the owner's decisions, and the topic file of each piece
---

## What was built, and why?
- Legacy layouts (`data/layouts/<id>.php`) branch on any value, loop nested arrays, place every piece; widgets had one loop and fixed tokens.
- One mechanism for every kind: a finish pass in the outer callback of `_expand_system_widgets()` (page, e-mail, Preview, ghosts, nested) resolves the markers `_render_tree_node()` and `_split_widget_tree()` write. `_apply_bindings()` is not edited; legacy `render_layout()` is not touched.

## What are the rules?
- A condition is a binding: `_bindings.show_if` + `_bindParams.show_if {op, value}`; a list is a `loop_area` with `props.source`. Never a class.
- Markers carry a per-request nonce, only inside a widget render; a tree without new props renders byte-identical.
- Values reach the pass through carriers (comments around `^^token^^`) the renderer's fill fills; unfilled reads as empty. No carrier for a token a renderer looks for in the template or builds as a block; checks of the saved tree skip conditions ([[widget-conditions]]). New kinds read saved-tree checks via `pg_sw_cond_tree_json()`.
- Values in attributes use plain-character placeholders: `h()` escapes comments in href/src.
- Unknown operator or list source: nothing on the site, faded on the canvas.
- New object props go into `pg_designer_tree_objects()`.

## What did the owner decide?
- 2026-10-24, of three designs: the finish pass, with the layout gallery, widget parts, condition vectors, filters and code areas.
- 2026-10-26: words translatable wherever possible; design elements over placeholder tokens. So no "default text": a sibling with `show_if empty` holds it ([[widget-fill-shop]]). W16 equal look and behaviour, not bytes; W5 three sources, filtered, copied; W8 the allow-list of [[widget-entity-fields]] ([[widget-designability-decisions]]).
- 2026-10-27: every proposal accepted ([[widget-designability-decisions-2]]). W17/W18 (#2409): the record is the one the address opens; values outside widgets are W17; a form record's text, number, date and pick-list fields join the allow-list.
- W20b (#3179, architect): starters shipped as files in the gallery ([[widget-starters]]).
- Page types still missing as widgets: [[system-widget-missing-types]], built on this engine.

## Where is each piece?
- Finish pass and conditions: [[widget-finish-pass]], [[widget-conditions]], [[widget-condition-panel]], [[widget-condition-canvas]], [[widget-condition-rows]], [[page-conditions]].
- Lists: [[widget-lists]], [[widget-lists-editor]], [[widget-lists-product]], [[widget-lists-order]], [[widget-lists-cart]], [[widget-lists-checkout]], [[widget-list-paging]], [[widget-loop-position]].
- Values: [[widget-entity-fields-list]], [[widget-fill-filters]], [[widget-fill-shop]], [[page-record-values]], [[sentence-with-values]].
- Parts and layouts: [[widget-default-parts]], [[widget-parts-anywhere]], [[widget-parts-palette]], [[widget-layout-gallery]], [[widget-starters]], [[layout-form-converter]].
