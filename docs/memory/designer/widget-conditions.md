---
name: widget-conditions
description: Value conditions on system widget nodes (show_if): operators, how a value reads as text or a number, refused tokens, tree checks, fail closed (#NNNN).
---

## What is show_if?
- A node of a widget tree drawn only when a value passes a test: `props._bindings.show_if = '__discount_percent'` (a token, sanitised like every binding) and `props._bindParams.show_if = {op, value}` (missing: `not_empty`). Never a class.
- Works in every kind: `_render_tree_node()` wraps the node while a widget renders and the finish pass keeps or cuts it ([[widget-finish-pass]]).
- Pure functions in `includes/fn/widgets_conditions.php`. No panel row yet (W4): set it in a design-template tree or by hand. `_setBinding()` keeps it when a text binding changes.

## Which operators exist, and how do they compare?
- `not_empty` `empty` `eq` `neq` `contains` `not_contains` `starts` `gt` `gte` `lt` `lte` `multiple_of` (`pg_sw_cond_ops()`; the editor's labels are `_sdT()`).
- `eq`/`neq`: numbers when both sides are, else folded text. `gt`..`lte`: false unless both are numbers. `multiple_of`: whole numbers, operand above 0. `₺0,00` is not empty but not `gt 0`.

## How is a value read as text?
- Markup stripped, entities decoded, white space collapsed (`pg_sw_cond_text()`, `pg_sw_cond_plain()`).
- White space is one written-out set, not `\s` (PCRE and JavaScript differ): ASCII spaces, U+00A0, the Unicode spaces, U+0085, U+180E, U+2028/2029 and U+FEFF. A zero-width space is not one.
- Folded with `mb_strtolower`, U+0307 dropped and `ı` read as `i`: `İstanbul` = `istanbul`, `IRMAK` = `ırmak`.

## When is a value a number?
- `pg_sw_cond_number()`: optional minus, optional unit, figure, optional unit or `%`: `-₺12,50`, `€12,50 EUR`, `10%`, `US$12`, `TRY 12,50`.
- A unit is a currency sign, an all-capital three-letter code, `TL`, or one or two capitals with `$`. Any other word makes it text: `Kat 2`, `No 5`, `12 tl`. An all-capital word such as `KAT 2` still reads as a code.
- The figure must be grouped as an amount (`pg_sw_cond_figure_ok()`): the last `.`/`,` found once is the decimal point; separators before it are one kind, first group 1-3 digits, the others exactly 3. So `1.234.567,89` and `1 234,56` are numbers; `24.10.2026`, `1.11.2026`, `1.2,5` and `2 5` are text (dates compare only with `eq`/`neq`/`contains`).
- One separator is a decimal point (`1.10` = `1.1`), except three digits after it (`1.500`), which follows the site language via `pg_parse_amount()`; such values stay out of `tests/fixtures/widget_condition_vectors.json`, which the editor's twin runs too.

## Which tokens cannot be conditions, and why?
- A carrier repeats the token in the rendered template, so a token a renderer looks for there would count as placed and the renderer would drop its own block.
- Refused (`pg_sw_cond_denied_tokens()` and `*_html`): `__edit_url`, `__comment_edit_url`, `__empty_message`, `__variant_picker`; the catalog filter blocks (`__filter_chips`, `__filters_button`, `__filters_offcanvas`, `__clear_filters_button`); the sentinels `__page_link`, `__link_label` and their `_N` forms. A new `strpos()` of a token in a rendered template adds it here.
- A renderer that looks in the saved tree (order view: legal links, `__withdrawal_deadline`, `__return_form`, `__returns`, `__not_found_message`; cart `has_items`; account `not_logged_in`) reads it through `pg_sw_cond_tree_json()`, which drops every `show_if`, so those tokens stay allowed. A new check of that kind uses it too; the calendar's detail probe reads the raw tree on purpose, so a condition on `__event_location` loads it.
- A plain list: `tools/check_bindings.php` counts quoted `'__token' =>` keys in `includes/fn/widgets*.php` as tokens a renderer fills.

## What happens when a condition is invalid?
- Fail closed: unknown op, missing operand, empty or refused token: the node draws nothing inside a widget. Outside a widget scope it draws as before, with no marker.
- `root`, `loop_area` and `recipient_loop_area` take no condition.
