---
name: system-widget-bindings
description: System widget data flow via _bindings.* (text, src, href, action, eo_field, value, section, eo_visible_if, show_if) and _bindFormats, action bindings, href-bound UI rule, HTML token previews.
---

## What is the rule for data flow in system widgets?
- ALL data flow and functions in system widgets go through data binding. No magic CSS classes.
- Designer selects an element, picks the binding in the right-panel dropdown (text, src, href, action, eo_field, section, value); the backend processes the element by its binding.

## Which binding types exist?
| Binding | Element | Effect |
|---|---|---|
| `_bindings.text=X` | content heading/paragraph/text, semantic | writes backend token X into text |
| `_bindings.src=X` | content[image], semantic `<img>` | token to `src` |
| `_bindings.href=X` | content[link], semantic `<a>` | replaces href with token URL |
| `_bindings.href=__page_link` + `_bindParams.href` | link in a form list/detail | opens another page with record values ([[record-value-page-links]]) |
| `_bindings.action=X` | semantic `<button>`/`<a>`, content[link], component[btn] | binds to an action (submit, navigate, qty stepper, remove) |
| `_bindings.eo_field=X` | semantic input/select/textarea | Express Order form field (name, id, value pre-fill) |
| `_bindings.value=X` | semantic `<input>` | system-managed input in cart loop (e.g. cart_qty) |
| `_bindings.section=X` | semantic `<div>` | container filled with backend section HTML |
| `_bindings.eo_visible_if=X` | any semantic container | dropped from node tree if backend flag is false |
| `_bindings.show_if=X` + `_bindParams.show_if` | any node of a widget, every kind | drawn only when token X passes `{op, value}` (missing: not empty); the finish pass decides ([[widget-conditions]]) |
| `_bindFormats.text=F` | any bound value of a widget | the value's format after the token: a date format or a text filter (`upper`, `number:2` …) ([[widget-fill-filters]]) |

## What do the action bindings do?
- `add_to_cart` (catalog_item_view button); `catalog_add_to_cart` (catalog_listing card, per card).
- `cart_update` / `cart_checkout` (shopping_cart button); `cart_qty_inc` / `cart_qty_dec` (shopping_cart loop button).
- `eo_submit_purchase` (complete order), `eo_submit_update` (update cart/totals), `eo_qty_inc` / `eo_qty_dec` (EO loop button).
- `remove_from_cart`: shopping_cart + EO loop, tag-agnostic per-row removal (a -> href, button -> `data-pg-remove-href` + JS).
- `apply_coupon` (shopping_cart button); `coupon_form` (shopping_cart form: action/method + CSRF).
- `open_filters` / `clear_filters` / `submit_search` (catalog_listing): filter offcanvas, clear, search.

## What happens to the URL field when href is bound?
- Hidden via `_sdHrefIsBound()` + `_sdBoundHrefNotice()` (style_designer.js) in four panels: Link Options, Button Options, semantic `<a>`, generic component href row. Reason: server rewrites href at render, so a typed URL did nothing ("visible but non-functional" is forbidden).
- Changing the binding select calls `renderProperties()` **only for `href`**: the custom token input calls `_setBinding()` on every keystroke and a re-render there would steal focus.

## How do HTML-producing tokens preview on the canvas?
- `_SD_HTML_PREVIEW_TOKENS` (style_designer.js) holds real markup instead of a bracket placeholder ("[Status badge — HTML on the server]" in the old Turkish wording); semantic nodes use `insertAdjacentHTML`, paragraph content already uses `innerHTML`.
- Adding a new HTML-emitting token requires adding a preview here too, otherwise the designer sees a blank on the canvas.
