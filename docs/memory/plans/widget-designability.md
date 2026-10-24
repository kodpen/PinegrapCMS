---
name: widget-designability
description: Plan (2026-10-24, in progress, W1 done #NNNN): legacy-layout power in system widgets via one finish pass: value conditions, row position, nested lists, record fields, formats, placement, variants
---

## What is built, and why?
- Legacy custom layouts (`data/layouts/<id>.php`, 89 in the starter site) branch on any value, loop nested arrays and place every piece. Widgets narrowed this: one loop, fixed flag lists (none in 7 kinds), fixed token tables, server-built HTML blocks.
- One mechanism for every kind (30 today): a finish pass in the outer callback of `_expand_system_widgets()` (page, e-mail, Preview, ghosts, nested widgets) resolves the markers `_render_tree_node()` and `_split_widget_tree()` write. `_apply_bindings()` is not edited.

## What are the rules?
- A condition is a binding: `_bindings.show_if` + `_bindParams.show_if {op, value}`; a list is a `loop_area` with `props.source`. Never a class.
- Markers carry a per-request nonce and exist only inside a widget render; a tree without new props renders byte-identical.
- Values reach the pass through carriers (comment pair around `^^token^^`) filled by the renderer's own fill; an unfilled carrier reads as empty. No carrier for tokens a renderer looks for or builds as a block (`*_html`, `__variant_picker`, catalog filter blocks, `__edit_url`, `__empty_message`, `__page_link`, `__link_label`).
- Values in attributes use plain-character placeholders: `h()` escapes comments in href/src.
- Unknown operator or list source: nothing on the site, faded on the canvas.
- New object props go into `pg_designer_tree_objects()`. Legacy `render_layout()` is not touched.

## What is decided?
- Chosen 2026-10-24 of three designs: the finish pass, with the layout gallery and widget parts (variety design) and condition vectors, filters and code areas (code design).
- Missing page types stay in [[system-widget-missing-types]], built on this engine.

## What does the owner still answer?
OR in conditions; pg-hide-if-empty everywhere; entity columns; byte-identical old blocks; words beside a value; code areas; page-level facts; per-placement settings; gallery sources; parts with controls; express order lists; classic bridge.

## What are the steps? (one PR each)
- W1 done (#NNNN): finish pass and value conditions, server side ([[widget-conditions]]).
- W2 Palette double-click lands beside the selection, inside a widget too (shared_ref items stop at the widget).
- W3 Row position `__loop_index/count/is_first/is_last/is_odd/is_even`; row marks only when used.
- W4 Bind Data "Show only when a value…" for every kind, canvas chip, position group.
- W5 Layout gallery: Default, From templates, On this site; Try/Keep/Cancel.
- W6 Lists engine + form field values and attachments. W7 Lists in the editor. W8 Entity fields (allow-list).
- W9 Product lists. W10 Order and account lists (receipt e-mail too). W11 Cart lists, mini cart. W12 Search groups, pagination list, paging tokens, own page parameter, offset. W13 Calendar grid.
- W14 Typed fill and filters. W15 Attribute bindings. W16 Placed beats pushed; old HTML tokens drawn by default parts.
- W17 Widget parts anywhere on the page. W18 Page-level conditions and page record context.
- W19 Canvas: ghosts for every loop, state preview, `_sdCondEval` twin.
- W20 Parts palette, starter library. W21 Per-placement settings. W22 Code areas, classic form-layout converter. W23 `eo_visible_if` and pg-hide-if-empty as engine aliases. W24 Express order lists.

## How are other sessions kept clear?
- Claim `[plan:widget-designability] Wn`. W4 after #1567 and #1560; `widgets_catalog.php` after #1476; calendar after #1553; form list after #1545.

## Where to start?
- W3, then W4; W2's code is independent (it needs only this file).
