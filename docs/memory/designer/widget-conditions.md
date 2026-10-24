---
name: widget-conditions
description: Value conditions on system widget nodes (show_if): operators, number and text comparison, carriers, refused tokens, fail closed, the nonce and the finish pass stages (#NNNN).
---

## What is show_if?
- A node of a widget tree drawn only when a value passes a test: `props._bindings.show_if = '__discount_percent'` (a token, sanitised like every binding) and `props._bindParams.show_if = {op, value}` (missing: `not_empty`). Never a class.
- Works in every kind: `_render_tree_node()` wraps the node while a widget renders; the finish pass (`includes/fn/widgets_finish.php`) in the outer callback of `_expand_system_widgets()` keeps or cuts it, for the page, e-mail, Preview, ghosts and nested widgets.
- Pure functions in `includes/fn/widgets_conditions.php`. No panel row yet: set it in a design-template tree or by hand. `_setBinding()` keeps it when a text binding changes.

## Which operators exist, and how do they compare?
- `not_empty` `empty` `eq` `neq` `contains` `not_contains` `starts` `gt` `gte` `lt` `lte` `multiple_of` (`pg_sw_cond_ops()`, no labels: the editor's labels are `_sdT()`).
- Text: markup stripped, entities decoded, U+00A0 a space, white space collapsed (`pg_sw_cond_text()`); folded with `mb_strtolower` and U+0307 dropped, so `İstanbul` = `istanbul`.
- Numbers as the site writes them: `-₺12,50`, `€12,50 EUR`, `10%`, `US$12` (`pg_sw_cond_number()`, figure read by `pg_parse_amount()`). A single separator with three digits (`1.500`) follows the site language, so such values stay out of `tests/fixtures/widget_condition_vectors.json`, which the editor's twin runs too.
- `eq`/`neq`: numbers when both sides are, else folded text. `gt`..`lte`: false unless both are numbers. `multiple_of`: whole numbers, operand above 0. `₺0,00` is not empty but not `gt 0`.

## How does a value reach the pass?
- After the node: `<!--pg-cv:NONCE:N-->^^token^^<!--/pg-cv:NONCE:N-->` (the carrier), between `<!--pg-if:NONCE:N:OP:B64-->` and `<!--/pg-if:NONCE:N-->` (operand: URL-safe base64).
- The renderer's own fill writes the value (`pg_sw_apply_tokens()`, `strtr()`, `str_replace()`), so each row copy carries its row's value; a form list selects a standard field named only in a carrier.
- The carrier is swept before it is read: an unfilled token reads as empty. `pg_sw_has_content()` strips carriers, so pg-hide-if-empty measures only what the visitor sees.
- After the element: the edit pencil and the row id passes skip only leading comments.

## Which tokens cannot be conditions, and why?
- `*_html`, and `pg_sw_cond_denied_tokens()`: tokens a renderer looks for in the template (`__edit_url`, `__comment_edit_url`, `__empty_message`, `__variant_picker`); the catalog filter blocks (`__filter_chips`, `__filters_button`, `__filters_offcanvas`, `__clear_filters_button`); sentinels rewritten per node (`__page_link`, `__link_label`, their `_N` forms).
- A plain list: `tools/check_bindings.php` counts quoted `'__token' =>` keys in `includes/fn/widgets*.php` as tokens a renderer fills.

## What happens when a condition is invalid?
- Fail closed: unknown op, missing operand, empty or refused token: the node draws nothing inside a widget. Outside a widget scope the node draws as before and no marker is written, so a saved page body never holds one.
- `root`, `loop_area` and `recipient_loop_area` take no condition.

## Why a nonce?
- `pg_sw_marker_nonce()` is random per request: text in a value shaped like a marker is inert, and the strip stage removes only this request's markers.

## In what order do the stages run?
- `pg_sw_finish_widget_html()` returns markup without the nonce untouched. Then: conditions (outermost first, kept spans resolved inside); pg-hide-if-empty again after a cut, only in form_list_view (with its early names), form_item_view and comments; every leftover marker and carrier removed.
- A row-position stage is to run before the conditions, so a condition can read a row's position.
