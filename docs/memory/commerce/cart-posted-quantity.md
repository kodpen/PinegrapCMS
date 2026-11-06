---
name: cart-posted-quantity
description: How the designer cart widget (cart_action.php) reads a posted quantity, on cart rows and in quick add: pg_cart_posted_quantity(), whole numbers only (#6803)
module: ecommerce
---

## Which function reads a quantity the cart widget posts?

`pg_cart_posted_quantity()` (`includes/fn/ecommerce.php`): commas
(thousands separators) and surrounding spaces dropped, then 1 to 9 digits is
the quantity; anything else (empty, minus sign, decimal point, `1e3`, a
list) is `null`. The classic cart (`shopping_cart.php`) and express order
use the same whole-number rule.

## What does each caller do with null or 0?

- Cart rows (Update / Proceed to Checkout): `null` keeps the line's
  quantity, `0` removes it through `pg_cart_remove_line()`.
- Quick add (`quick_add_quantity`, selection type `quantity`): `null` or
  `0` marks "Please enter a valid quantity." and adds nothing.

## Why not `(int) preg_replace('/[^\d]/', '', …)`?

Quick add used it until #6803: every non-digit was stripped, so `1.5` added
15, `-3` added 3, `1e3` added 13, with no upper bound. The 9-digit limit of
the helper is the bound now.
