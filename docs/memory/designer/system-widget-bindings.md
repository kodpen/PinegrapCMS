---
name: system-widget-bindings
description: System widget data flow via _bindings.* (text, src, href, action, eo_field, value, section, eo_visible_if, show_if) and _bindFormats, action bindings, href-bound UI rule, HTML token previews.
---

## What is the rule for data flow in system widgets?
- ALL data flow and functions in system widgets go through data binding. No magic CSS classes. A value in any other attribute: [[widget-attr-bindings]].
- The designer picks an element's binding in the right panel (text, src, href, action, eo_field, section, value); the backend acts on it.

## Which binding types exist?
| Binding | Element | Effect |
|---|---|---|
| `_bindings.text=X` | content heading/paragraph/text, semantic | writes backend token X into text |
| `_bindings.src=X` | content[image], semantic `<img>` | token to `src` |
| `_bindings.href=X` | content[link], semantic `<a>` | replaces href with token URL |
| `_bindings.href=__page_link` + `_bindParams.href` | link in a form list/detail | opens another page with record values ([[record-value-page-links]]) |
| `_bindings.action=X` | semantic `<button>`/`<a>`, content[link], component[btn] | binds to an action (submit, navigate, qty stepper, remove) |
| `_bindings.eo_field=X` | semantic input/select/textarea | Express Order form field (name, id, value pre-fill) |
| `_bindings.value=X` | semantic `<input>` | system-managed input (cart_qty, quantity, stock_notify_email) |
| `_bindings.section=X` | semantic `<div>` | container filled with backend section HTML |
| `_bindings.eo_visible_if=X` | any semantic container | dropped from node tree if backend flag is false |
| `_bindings.show_if=X` + `_bindParams.show_if` | any node of a widget, every kind | drawn only when token X passes `{op, value}` (missing: not empty); the finish pass decides ([[widget-conditions]]) |
| `_bindFormats.text=F` | any bound value of a widget | the value's format after the token: a date format or a text filter (`upper`, `number:2` …) ([[widget-fill-filters]]) |

## What do the action bindings do?
- `add_to_cart` (catalog_item_view button); `catalog_add_to_cart` (catalog_listing card, per card).
- `wishlist` (catalog_item_view) / `catalog_wishlist` (catalog_listing card): the favourite heart, offered on a button only (btn component or `<button>`); a hidden `type="button"` marked `data-pg-wishlist`, shown by `pg_wishlist.js` (`commerce/wishlist-heart.md`).
- `stock_notify` (catalog_item_view button), field `stock_notify_email`: back-in-stock form ([[catalog-stock-notify-form]]).
- `cart_update` / `cart_checkout` (shopping_cart button); `cart_qty_inc` / `cart_qty_dec` (shopping_cart loop button).
- `eo_submit_purchase` (complete order), `eo_submit_update` (update cart/totals), `eo_qty_inc` / `eo_qty_dec` (EO loop button).
- `remove_from_cart`: shopping_cart + EO loop, tag-agnostic per-row removal (a -> href, button -> `data-pg-remove-href` + JS).
- `apply_coupon` (shopping_cart button); `coupon_form` (shopping_cart form: action/method + CSRF).
- `open_filters` / `clear_filters` / `submit_search` (catalog_listing): filter offcanvas, clear, search.
- `wishlist_remove` / `wishlist_add_to_cart` (wishlist loop button): a submit button for a hidden form printed after the widget (`wishlist-widget.md`).

## What happens to the URL field when href is bound?
- Hidden via `_sdHrefIsBound()` + `_sdBoundHrefNotice()` (style_designer.js) in four panels: Link Options, Button Options, semantic `<a>`, generic component href row. Reason: the server rewrites href, so a typed URL did nothing.
- Changing the binding select calls `renderProperties()` **only for `href`**: the custom token input calls `_setBinding()` on every keystroke and a re-render there would steal focus.

## How do HTML-producing tokens preview on the canvas?
- `_SD_HTML_PREVIEW_TOKENS` (style_designer.js) holds real markup, not a bracket placeholder; semantic nodes use `insertAdjacentHTML`, paragraph content `innerHTML`.
- Adding a new HTML-emitting token requires adding a preview here too, otherwise the designer sees a blank on the canvas.
