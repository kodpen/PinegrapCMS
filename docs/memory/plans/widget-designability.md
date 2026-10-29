---
name: widget-designability
description: Plan (all done but W20b): legacy-layout power in widgets
---

## What is built, and why?
- Legacy layouts (`data/layouts/<id>.php`) branch on any value, loop nested arrays, place every piece; widgets had one loop and fixed tokens.
- One mechanism for every kind (30 today): a finish pass in the outer callback of `_expand_system_widgets()` (page, e-mail, Preview, ghosts, nested) resolves the markers `_render_tree_node()` and `_split_widget_tree()` write. `_apply_bindings()` is not edited.

## What are the rules?
- A condition is a binding: `_bindings.show_if` + `_bindParams.show_if {op, value}`; a list is a `loop_area` with `props.source`. Never a class.
- Markers carry a per-request nonce, only inside a widget render; a tree without new props renders byte-identical.
- Values reach the pass through carriers (comments around `^^token^^`) the renderer's fill fills; unfilled reads as empty. No carrier for a token a renderer looks for in the template or builds as a block; checks of the saved tree skip conditions ([[widget-conditions]]).
- Values in attributes use plain-character placeholders: `h()` escapes comments in href/src.
- Unknown operator or list source: nothing on the site, faded on the canvas.
- New object props go into `pg_designer_tree_objects()`. Legacy `render_layout()` is not touched.

## What is decided?
- Chosen 2026-10-24 of three designs: the finish pass, with the layout gallery, widget parts, condition vectors, filters and code areas.
- Missing page types stay in [[system-widget-missing-types]], built on this engine.
- Owner (2026-10-26): words translatable wherever possible; design elements over placeholder tokens. So no "default text": a sibling with `show_if empty` holds it ([[widget-fill-shop]]).
- Owner (2026-10-26), detail and reasons in [[widget-designability-decisions]]: W16 equal look and behaviour, not bytes; W5 three sources, filtered, copied; W8 the allow-list of [[widget-entity-fields]].
- Owner (2026-10-27), every proposal accepted, reasons in [[widget-designability-decisions-2]].

## W17/W18? (#2409)
- Record = the one the address opens; values outside widgets are W17; a form record's text, number, date and pick-list fields join the allow-list.

## What are the steps? (one PR each)
- W1 done (#1592, [[widget-finish-pass]], [[widget-conditions]]).
- W2 done (#1582). W3 done (#1597, [[widget-loop-position]]). W4 done (#1665, [[widget-condition-panel]]).
- W5 done (#1879, #1905, [[widget-layout-gallery]]).
- W6 done (#1680, [[widget-lists]]). W7 done (#1688, [[widget-lists-editor]]). W8 done (seven PRs #1809 to #1917, [[widget-entity-fields-list]]).
- W9 done #1752 #1768 ([[widget-lists-product]]). W10 done (#1783, [[widget-lists-order]]). W11 done #1796 ([[widget-lists-cart]]). W12 done #1924 to #2245 ([[widget-list-paging]]). W13 done (#1758).
- W14 done (#1602, #1701, #1719, [[widget-fill-filters]], [[widget-fill-shop]]).
- W15 done (#1734). W16 done: #1808 to #2401 ([[widget-default-parts]]).
- W17 values outside widgets #2398 ([[page-record-values]]). Parts anywhere (#3074): W17a server #3085, W17b editor #3111 ([[widget-parts-anywhere]]).
- W17 form fields by name: server half #3007, editor half #3047 ([[page-record-values]]). W18 done #2308 #2334 ([[page-conditions]]).
- W19 done #1904 #1914 #1928, opening state #2232 ([[widget-condition-canvas]]).
- W20a done #3140 ([[widget-parts-palette]]). W20b decided #3179 (`workflow/decisions-made-product-4.md`): gallery starters; W20b-1 first kinds (open), W20b-2 the rest. W21 done #2326 #2345. W22 done: #2317, W22b #2368 ([[layout-form-converter]]). W23 done #2225 #2307. W24 done #2259 #2304 ([[widget-lists-checkout]]).
- W25 done (#2221, [[widget-condition-rows]]).
- W26 done #2264, order view's withdrawal line #2458 ([[sentence-with-values]]).

## How are other sessions kept clear?
- Claim with a draft PR `[plan:widget-designability] Wn`. New kinds read saved-tree checks via `pg_sw_cond_tree_json()`.

## Where to start?
- W20b-1 is claimed; W20b-2 waits for it.
