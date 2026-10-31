---
name: cart-default-quantity
description: A product added without a quantity box enters the cart with pg_product_default_quantity(), at least 1; new products store default_quantity 1.
module: ecommerce
---

# Default quantity of a cart line

## Which quantity does a product without a quantity box get?

`pg_product_default_quantity($products_default_quantity)`
(`includes/fn/ecommerce.php`): the product's `default_quantity`, at least 1.
NULL, `''`, 0, a negative or non-numeric value all give 1. Every entry point
that adds a product without a visitor-given quantity calls it before
`add_order_item()`: the classic Order Form (checkbox, autoselect and the
donation without a quantity box in `order_form.php`), the quick add of
`cart_action.php`, `shopping_cart.php` and `express_order.php`. The catalog
detail page and the catalog widget already refuse or lift a value under 1.

## Why not clamp inside add_order_item()?

Its callers also pass quantities the visitor typed; those are validated (and
refused when 0) at the caller. The clamp is only for the product's default.

## What symptom did it fix?

Template catalog products (PGS-1001…) had `default_quantity` NULL. Checked on
a classic Order Form they were written as `order_items.quantity = 0`: the
cart showed 0 and ₺0,00, checkout said "No items have been added." The edit
screen shows NULL as `1`, so the operator could not see it (#4164).

## What do new products store?

`pg_pb_create_product()` writes `default_quantity = 1` when the key is
missing, empty or under 1, and the template catalog products pass 1, so the
`1` the edit screen shows is the saved value. Existing NULL rows are not
migrated: the clamp covers them.
