---
name: widget-finish-pass
description: The finish pass every system widget render goes through: scope, markers, carriers, nonce, row positions, cut points, pg-hide-if-empty, the edit pencil, the strip (#1592).
module: widgets_finish
---

## Where does the pass run?
- In the outer callback of `_expand_system_widgets()`, the one door of every widget render (page, e-mail, Preview, ghosts, nested widgets): a scope (widget id, regionType; early form list names count as `form_list_view`), the render in `try`/`finally`, then `pg_sw_finish_widget_html()` before `pg_img_fluid_alias()`. Code: `includes/fn/widgets_finish.php`.
- Outside a scope no marker is written: a saved page body holds none; a tree without `show_if` renders byte-identical.

## How does a value reach the pass?
- `_render_tree_node()` writes a node with a condition ([[widget-conditions]]) as `<!--pg-if:NONCE:N:OP:B64-->` node `<!--pg-cv:NONCE:N-->^^token^^<!--/pg-cv:NONCE:N-->` `<!--/pg-if:NONCE:N-->` (operand: URL-safe base64); a list as a span of carriers ([[widget-lists]]).
- The renderer's own fill writes the carrier (`pg_sw_apply_tokens()`, `strtr()`, `str_replace()`): each row copy carries its row's value. Swept before it is read, an unfilled token reads as empty.
- The carrier comes after the node: the row id passes skip only leading comments.
- `pg_sw_has_content()` strips carriers, so pg-hide-if-empty measures only what the visitor sees.

## Why a nonce?
- `pg_sw_marker_nonce()` is random per request: a value shaped like a marker is inert; only this request's markers go.

## How are spans found?
- With `strpos()`, outermost first, never one regex over a body (`pcre.backtrack_limit`). A span's value is the last carrier of its number in its body; an open marker of the same number inside it is counted, so a template nested in a copy of itself (a reply in its comment) pairs right; it is sought only before the next close, so the cost stays linear.
- A marker without its pair is left; the strip takes it out, the node shows.

## What does a cut leave?
- A cut point `<!--pg-cut:NONCE-->`. When the cut node's first element has class `pg-field-value` (as the form starters bind it), an empty `<span class="pg-field-value">` follows, so its "label: value" row reads as blank and goes.

## In what order do the stages run?
- First the rows published for the lists are taken (`pg_sw_list_take()`): none outlive the pass.
1. Row positions ([[widget-loop-position]]): row spans numbered, marks and position tokens left removed.
2. Lists ([[widget-lists]]): every list span drawn from the published rows; an empty one leaves a cut point.
3. Conditions (`pg_sw_finish_resolve()`), list rows included; then attribute values ([[widget-attr-bindings]]).
4. pg-hide-if-empty ([[widget-hide-if-empty]]): in `pg_sw_finish_judged_kinds()` after a cut only, judging the marked elements holding a cut point (one that goes leaves the point, so the box around it is judged in turn); in every other kind every marked element (`pg_sw_finish_hide_empty()`), even without a marker.
5. Every carrier, cut point, empty value element and marker of this request still standing removed.
6. Each record's edit pencil put in (below), then pencil markers left removed.
- Without the nonce and `^^__loop_` only stage 4 runs (outside the judged kinds); without the nonce, stages 1 and 4.

## Why does the edit pencil wait for the pass?
- Put in before, it went with a cut first element or a dropped row, and counted as content in a pg-hide-if-empty box.
- `pg_sw_add_edit_chip()` on a record holding a condition marker writes the record between `<!--pg-chip:NONCE:N:B64-->` … `<!--/pg-chip:NONCE:N-->` (address, title, tone as JSON). Stage 6 (`pg_sw_finish_place_chips()`) puts it in the first element the record kept, by `pg_sw_insert_edit_chip()`'s rules: none if that element cannot hold one or nothing is left; a record cut whole takes it along. A record without a condition gets it at once, as before.

## What is still open?
- A plain shared component in a widget has no scope: `show_if` ignored (visit facts aside: [[page-conditions]]).
