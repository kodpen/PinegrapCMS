---
name: widget-finish-pass
description: The finish pass every system widget render goes through: scope, markers, carriers, nonce, cut points, the pg-hide-if-empty rerun, the edit pencil and the strip stage (#NNNN).
---

## Where does the pass run?
- In the outer callback of `_expand_system_widgets()`, the one door of every widget render: page, e-mail, Preview, ghosts, nested widgets. The callback opens a scope (widget id, regionType; the early form list names count as `form_list_view`), renders in `try`/`finally`, then calls `pg_sw_finish_widget_html()` before `pg_img_fluid_alias()`. Code: `includes/fn/widgets_finish.php`.
- Outside a scope no marker is written, so a saved page body never holds one, and a tree without `show_if` renders byte-identical.

## How does a value reach the pass?
- `_render_tree_node()` writes a node with a condition ([[widget-conditions]]) as `<!--pg-if:NONCE:N:OP:B64-->` node `<!--pg-cv:NONCE:N-->^^token^^<!--/pg-cv:NONCE:N-->` `<!--/pg-if:NONCE:N-->` (operand: URL-safe base64).
- The renderer's own fill writes the carrier (`pg_sw_apply_tokens()`, `strtr()`, `str_replace()`), so each row copy carries its row's value. The carrier is swept before it is read: an unfilled token reads as empty.
- The carrier comes after the node: the row id passes skip only leading comments.
- `pg_sw_has_content()` strips carriers, so pg-hide-if-empty measures only what the visitor sees.

## Why a nonce?
- `pg_sw_marker_nonce()` is random per request: text in a value shaped like a marker is inert, and only this request's markers are removed.

## How are spans found?
- With `strpos()`, outermost first, never one pattern over a body: no body size hits `pcre.backtrack_limit`. A span's value is the last carrier of its number in its body; an open marker of the same number inside it is counted, so a row template nested in a copy of itself (a reply in its comment) pairs right.
- A marker without its pair is left; the strip stage takes it out and the node shows.

## What does a cut leave?
- A cut point `<!--pg-cut:NONCE-->`. When the cut node's first element has class `pg-field-value` (the form starters bind the value element so), an empty `<span class="pg-field-value">` follows it, so its "label: value" row reads as a blank field and goes.

## In what order do the stages run?
1. Conditions (`pg_sw_finish_resolve()`).
2. After a cut, in form_list_view, form_item_view and comments: `pg_sw_hide_empty_fields($html, $cut_mark)`, which judges only the pg-hide-if-empty elements that hold a cut point and leaves the point where one goes, so the box around it is judged in turn. An element no cut touched (the static part of a form list, which the renderer never judges) stays as the renderer left it.
3. Every carrier, cut point, empty value element and marker of this request still standing removed.
4. Each record's edit pencil put in (below), then pencil markers left removed.
- Markup without the nonce comes back untouched. A row-position stage is to run before the conditions (W3).

## Why does the edit pencil wait for the pass?
- Renderers put the pencil in a record's first element before the pass. When that element was cut, or the rerun dropped the row holding it, the pencil went too; in a pg-hide-if-empty box it counted as content and kept the box.
- `pg_sw_add_edit_chip()` on a record holding a condition marker writes the record between `<!--pg-chip:NONCE:N:B64-->` … `<!--/pg-chip:NONCE:N-->` (address, title, tone as JSON). Stage 4 (`pg_sw_finish_place_chips()`) puts it in the first element the record kept, by `pg_sw_insert_edit_chip()`'s rules: none if that element cannot hold one or nothing is left; a record cut whole takes it along. A record without a condition gets it at once, as before.

## What is still open?
- A plain shared component inside a widget is expanded outside any scope: its `show_if` is ignored (page-level conditions, W18).
- Conditions on required form controls and operands on translated pages: [[widget-designability]].
