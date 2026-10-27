---
name: widget-attr-bindings
description: A widget record's values in an element's attributes ({{token}} in the Attributes panel or a title): placeholder and carrier, the pass stage, refused attributes and tokens, editor completion (W15)
module: widgets_attrs
---

## What does an operator write?
- Inside a system widget, an Attributes panel entry (`props._attrs`) or the title may hold `{{token}}`: `data-id="{{submitted_form_id}}"`, `aria-label="Open {{title}}"`, `data-tag="{{__value_param}}"` in a list row. Words around the token stay; a worded attribute (`pg_tr_worded_attrs()`: title, aria-label, alt …) stays translatable, the token kept as text.
- A token is `{{` + `[A-Za-z0-9_-]` + `}}`, no spaces: `{{ x }}` and `{{tab:x}}` are text. Outside a widget render (a page body, a shared component outside a widget) the attribute prints as written.
- No new prop: a tree without `{{` in an attribute renders byte-identical.

## How is it drawn?
- `pg_sw_attr_value_html()` (`includes/fn/widgets_attrs.php`, called by `_render_extra_attrs()` and `_pg_node_title_attrs()`) writes a placeholder of plain characters, `pgav-NONCE-K-`, and queues a carrier `<!--pg-cv:NONCE:aK-->^^token^^<!--/pg-cv:NONCE:aK-->`. `_render_tree_node()` writes an element's queued carriers after it (`pg_sw_attr_mark()` / `pg_sw_attr_take()`): a comment cannot stand in an attribute, `h()` escapes it.
- The renderer's fill fills the carrier as it fills a condition's; a list row's fill fills a row token in it.
- Finish pass stage 3, after the conditions: `pg_sw_attr_resolve()` gives each placeholder its carrier's value as plain text (tags stripped, entities decoded, white space collapsed), escaped once. Placeholders and carriers pair like brackets, so a template drawn inside a copy of itself (a reply) reads its own. No carrier: empty. Stage 5 strips the carriers (`pg_sw_finish_strip_carriers()` takes `aK`), and `pg_sw_has_content()` ignores them, so pg-hide-if-empty judges only what a visitor sees.

## What is refused, and why?
- Attributes (`pg_sw_attr_name_ok()`): `id`, `class`, `style` (the element's own props), every `on*` handler, and those a browser reads as an address or markup (`href`, `src`, `srcset`, `srcdoc`, `action`, `formaction`, `poster`, `data`, `content`, `http-equiv` …: `pg_sw_attr_denied_names()`): a record's text could become a `javascript:` address. A link's address has its href binding and the page link.
- Tokens (`pg_sw_attr_token_ok()`): those a condition refuses and those whose value is markup (`pg_sw_raw_token_keys()`).
- A refused token prints nothing (fail closed); the words around it stay.

## What does the editor offer?
- Code view: `{{` in any attribute `_sdAttrBindNameOk()` passes, on an element inside a widget, offers `_swBindTokenOptions()` minus `_sdAttrTokenOk()`; href/src/alt/value keep their binding completion ([[designer-code-view-bindings]]). The Attributes panel takes the token as typed.
- Twins: `SW_ATTR_DENY_NAMES` = `pg_sw_attr_denied_names()`, `SW_ATTR_MARKUP` = `pg_sw_raw_token_keys()` (no TOKEN in their names: check_bindings reads those as palettes). `tests/widget_attr_bindings_test.php` checks them under Node.

## What is not done yet?
- No canvas preview of the value (the attribute shows `{{token}}`), no hint or `{{` list in the Attributes panel, no format suffix in an attribute. A browser round is still owed.
