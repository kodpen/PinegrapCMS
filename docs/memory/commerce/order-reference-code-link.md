---
name: order-reference-code-link
description: How the cart, quote and express order pages read the ?r= / ?reference_code= saved-cart link (one string, list = not given), and the order history screens' translated messages.
---

## Which parameter carries a saved cart's reference code?
- `?r=` (current links) or the older `?reference_code=`, `r` first. `retrieve_order_reference_code()` in `pinegrap/retrieve_order.php` returns the first one that is a non-empty string, else `''`.
- `get_shopping_cart.php` and `get_express_order.php` (cart, quote and express order pages) call `retrieve_order()` only when that reader returns a code; `retrieve_order()` uses the same reader.
- The designer cart and express order widgets restore the cart in `_eo_restore_cart_from_reference_code()` (`includes/fn/widgets_express_order.php`); it reads only `?r=` and returns on a list or a non-alphanumeric value.

## What happens with a list or a missing parameter?
- A list (`?r[]=x`, `?reference_code[]=x`) counts as not given: the page is the same as without it, apart from `send_to` echoing the URL. With `?r[]=x&reference_code=CODE` the code is used.
- Before PR #471 a list stopped the page with an error page (500, `mysqli_real_escape_string()` got an array), and a link with only `?reference_code=` wrote two "Undefined array key r" warnings. Normal links (`?r=CODE`, unknown or completed codes) render exactly as before.

## Which language do the order history screens print?
- `order_history_retrieve_order.php`, `order_history_reorder.php` and `order_history_delete_order.php` pass every message through `lang()`: "Query failed.", the reorder notices ("A new order has been created…", "Your previous order has been saved…") and "The order has been deleted.". Before PR #471 these printed English on Turkish sites.

Test: `tests/order_reference_code_test.php`. Related: docs/memory/core/request-parameter-arrays.md
