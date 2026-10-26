---
name: widget-designability
description: Plan (done: W1 #1592, W2 #1582, W3 #1597, W4 #1665, W5 #1879 #1905, W6 #1680, W7 #1688, W8, W10, W11, W14 #1602 #1701, W15 #1734, W19): legacy-layout power in widgets
---

## What is built, and why?
- Legacy layouts (`data/layouts/<id>.php`) branch on any value, loop nested arrays, place every piece. Widgets: one loop, fixed flags (none in 7 kinds) and tokens, server-built blocks.
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
- Owner (2026-10-24): a value condition starts as one per element; OR stays open below.
- Owner (2026-10-26): words translatable wherever possible; design elements over placeholder tokens. So no "default text": a sibling with `show_if empty` holds it ([[widget-fill-shop]]).
- Owner (2026-10-26), detail and reasons in [[widget-designability-decisions]]: W16 equal look and behaviour, not bytes; W5 three sources, filtered, copied; W8 the allow-list of [[widget-entity-fields]].

## What does the owner still answer?
OR in conditions; canvas default state; pg-hide-if-empty everywhere; words beside a value; code areas; page-level facts; per-placement settings; parts with controls; express order lists; classic bridge.

## What are the steps? (one PR each)
- W1 done (#1592, [[widget-finish-pass]], [[widget-conditions]]).
- W2 done (#1582): palette double-click lands beside it.
- W3 done (#1597): row position ([[widget-loop-position]]).
- W4 done (#1665, [[widget-condition-panel]]).
- W5 done (#1879, #1905, [[widget-layout-gallery]]).
- W6 done (#1680, [[widget-lists]]). W7 done (#1688, [[widget-lists-editor]]). W8 Entity fields: products #1809, events #1828, quiz #1847, order #1873, account #1890, order line #1903, addresses and order list row tokens #1917; done ([[widget-entity-fields-list]]).
- W9 done #1752 #1768 ([[widget-lists-product]]). W10 done (#1783, [[widget-lists-order]]). W11 done #1796 ([[widget-lists-cart]]). W12 paging: W12a #1924, W12b #1947 ([[widget-list-paging]]); W12c left there. W13 done (#1758).
- W14 done (#1602, #1701, #1719, [[widget-fill-filters]], [[widget-fill-shop]]).
- W15 done (#1734). W16 default parts: order view #1808 #1824 #1863, stars #1908, crumbs #1945, cross-sell #XSELL ([[widget-default-parts]]); rest left.
- W17 Widget parts anywhere on the page. W18 Page-level conditions and page record context.
- W19 done #1904 #1914 #1928 ([[widget-condition-canvas]]).
- W20 Parts palette, starter library. W21 Per-placement settings. W22 Code areas, classic form-layout converter. W23 `eo_visible_if` and pg-hide-if-empty as engine aliases. W24 Express order lists.

## How are other sessions kept clear?
- Claim with a draft PR `[plan:widget-designability] Wn`. New kinds read saved-tree checks via `pg_sw_cond_tree_json()`.

## Where to start?
- W16, W19's loop ghosts.
