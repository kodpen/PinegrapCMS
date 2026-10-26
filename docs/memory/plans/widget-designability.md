---
name: widget-designability
description: Plan (done: W1 #1592, W2 #1582, W3 #1597, W4 #1665, W6 #1680, W7 #1688, W14 #1602 #1701, W15 #1734): legacy-layout power in widgets by one finish pass
---

## What is built, and why?
- Legacy layouts (`data/layouts/<id>.php`, 89 in the starter site) branch on any value, loop nested arrays, place every piece. Widgets: one loop, fixed flags (none in 7 kinds) and tokens, server-built blocks.
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

## What does the owner still answer?
OR in conditions; pg-hide-if-empty everywhere; entity columns; byte-identical old blocks; words beside a value; code areas; page-level facts; per-placement settings; gallery sources; parts with controls; express order lists; classic bridge.

## What are the steps? (one PR each)
- W1 done (#1592): finish pass and value conditions, server side ([[widget-finish-pass]], [[widget-conditions]]).
- W2 done (#1582): palette double-click lands beside the selection, inside a widget too (shared_ref items stop at the widget).
- W3 done (#1597): row position ([[widget-loop-position]]).
- W4 done (#1665): Bind Data "Show only when a value…" for every kind, canvas chip; never on a required control, compared text translated ([[widget-condition-panel]]).
- W5 Layout gallery: Default, From templates, On this site; Try/Keep/Cancel.
- W6 done (#1680): lists engine, field values, attachments ([[widget-lists]]). W7 done (#1688): lists in the editor ([[widget-lists-editor]]). W8 Entity fields (allow-list).
- W9 Product lists. W10 Order and account lists (receipt e-mail too). W11 Cart lists, mini cart. W12 Search groups, pagination list, paging tokens, own page parameter, offset. W13 done (#PR): [[widget-lists-calendar]].
- W14 done (#1602, #1701, #1719): typed fill, formats, list rows too ([[widget-fill-filters]], [[widget-fill-shop]]).
- W15 done (#1734). W16 Placed beats pushed; old HTML tokens drawn by default parts.
- W17 Widget parts anywhere on the page. W18 Page-level conditions and page record context.
- W19 Canvas: ghosts for every loop, state preview, `_sdCondEval` twin.
- W20 Parts palette, starter library. W21 Per-placement settings. W22 Code areas, classic form-layout converter. W23 `eo_visible_if` and pg-hide-if-empty as engine aliases. W24 Express order lists.

## How are other sessions kept clear?
- Claim with a draft PR `[plan:widget-designability] Wn`. A new kind reads its saved-tree checks through `pg_sw_cond_tree_json()`.

## Where to start?
- W16; W8 waits for the owner's column list.
