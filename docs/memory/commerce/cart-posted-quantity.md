---
name: cart-posted-quantity
description: How storefront forms (cart, checkout, order form, product page, cart widget) read a typed quantity: pg_cart_posted_quantity(), site separators, "2,5" refused (#6803, #9068)
module: ecommerce
---

## Which function reads a quantity a storefront form posts?

`pg_cart_posted_quantity($raw, $separators = null)` (`includes/fn/ecommerce.php`),
read with the site's separators (`pg_number_separators()`): spaces around
dropped, a figure grouped by the thousands mark loses its marks ("1.250" on a
site that writes 4.999,00, "1,250" on one that writes 4,999.00), then 1 to 9
digits is the quantity. Anything else is `null`: empty, a sign, the decimal
mark ("2,5" on a Turkish site), a mark out of place ("2.5", "1,25"), `1e3`, a
list. A site grouping with a space takes a plain or a no-break space.

## Who calls it?

- Designer cart widget (`cart_action.php`): cart rows and quick add.
- Classic Shopping Cart (`shopping_cart.php`) and classic Express Order
  (`express_order.php`): cart lines and quick add.
- Order Form (`order_form.php`): the product list's quantity boxes and the
  single product's box; product page (`catalog_detail.php`). These assign the
  read number back to the field when it is one, so the existing
  `pg_order_form_posted_quantity()` checks see "1250".

## What does each caller do with null or 0?

- Cart lines (Update / Proceed to Checkout, classic cart and checkout):
  `null` keeps the line's quantity and marks the box "Please enter a valid
  quantity." (it was kept in silence before #9068); `0` removes the line
  (`pg_cart_remove_line()` in the cart, `remove_order_item()` in checkout).
- Designer cart rows: `null` keeps the line, `0` removes it.
- Quick add (selection type `quantity`): `null` or `0` marks "Please enter a
  valid quantity." and adds nothing.
- Order Form product list: a filled box that is `null` marks the same error;
  an empty box or 0 just leaves the product out.

## Why not strip commas?

Every handler ran `str_replace(',', '', …)` until #9068, so "2,5" on a site
that writes 4.999,00 put 25 units in the cart. Quick add stripped every
non-digit until #6803 (`1.5` added 15). The 9-digit limit of the helper is
the upper bound; gift card lines have their own (`gift-card-line-quantity-limit.md`).
