---
name: order-form-posted-quantity
description: The order form's drop-down layout refuses quantity 0; a donation falls back to the default quantity
---

## How does the order form's drop-down layout read the posted quantity?

Through `pg_order_form_posted_quantity()` (`includes/fn/ecommerce.php`):
a whole number of at least one, else 0 for refused. A quantity product with
a refused quantity gets "Please enter a valid quantity." and is not added; a
donation with a refused quantity uses `pg_product_default_quantity()`.

## Why?

The check was only `preg_match('/^\d+$/')`, so `0` (sent by a custom layout
or a direct POST; `min="1"` is client-side only) added a cart line of
quantity 0, and a donation line of amount 0 (bug #6804).
`catalog_detail.php` already refused 0 for a quantity product; its donation
branch now reads the quantity the same way (posted quantity through
`pg_order_form_posted_quantity()`, else `pg_product_default_quantity()`),
so `quantity=0` or a NULL `products.default_quantity` no longer writes a
donation line of price 0 (bug #7744).
