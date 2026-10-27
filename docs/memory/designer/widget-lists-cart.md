---
name: widget-lists-cart
description: The cart as a list (W11): cart.items in the cart link (mini cart) and the cart widget, its key, tokens, where rows are read, the editor's mini cart starter, what is left
module: widgets_lists_cart
---

## What does the cart list give?
- `cart.items` (`includes/fn/widgets_lists_cart.php`, engine [[widget-lists]]), family `cart`, offered by `cart_link` (a mini cart in a header's dropdown or offcanvas) and `shopping_cart` (a read-only summary beside the cart's own editable rows). Lines saved for later are left out (`pg_order_item_active_sql()`).
- Opt-in: a tree without it renders byte-identical; the cart's loop and its recipient and recurring bands stay.

## Which key and tokens?
- Key `__ship_to_key`, as the order lists ([[widget-lists-order]]): both renderers fill it `0` (the whole cart); rows are filed under 0 and under each line's recipient, ready for a recipients list.
- Tokens are the cart loop's names: `__item_id`, `__item_name`, `__item_short_description`, `__item_qty`, `__item_price`, `__item_total`, `__item_image`, `__item_image_alt` (short description, else the name), `__item_url` (`#` without a detail page), `__item_remove_url` (remove_item_from_cart.php back to the page, with the CSRF token). No quantity input, no HTML blocks: list rows are escaped.

## Where are the rows read?
- The cart widget publishes from its own `$items` (no query), before its static values. The cart link reads them only when its tree drew the list (`pg_sw_list_cart_read()`: one query, the catalog detail page the site resolves, prices with the link's currency symbol).

## What does the editor give?
- Palette "Lists in a record": Products in the cart; `_sdOrderListStarter('cart.items')` lands a flush list group: a 48px picture, the display name linked, quantity × price, a × remove link with an aria-label. Never in the cart widget's item loop (amber band there).

## What is not done yet?
- Recipients of the cart as a list, totals per recipient, gift cards applied, the express order's lists (W24) and an update without a page load (the mini cart is drawn per request). A browser round is owed.
