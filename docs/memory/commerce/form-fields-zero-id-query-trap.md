---
name: form-fields-zero-id-query-trap
description: Product-form reads must pin form_type = 'product' (template rows have page_id and product_id 0); the deleted-product receipt bug and the empty-id rule.
---

## Why must product-form queries pin `form_type`?
- Template rows have BOTH `page_id` and `product_id` = 0. Patterns `WHERE page_id = '$x'` and `WHERE product_id = '$x'` exist in the codebase; when `$x` is 0 they already returned the wrong set before 2026.4 (product-form rows have `page_id` 0, page-form rows have `product_id` 0). Template rows enlarge an existing bug class, not a new one.
- RULE: every query that reads a product form pins `form_type = 'product'`. Legacy behaviour is unchanged (those rows already are `'product'`) and template rows can never leak into render.
- Pinned reads (cart, checkout, order receipt, package list paths):
  - `get_form_info()` x2 (`includes/fn/content.php`: field query + office-use-only probe)
  - `get_submitted_product_form_content_with_form_fields()` (receipt / order view / package list)
  - `_pg_render_cart_item_form_data()` / `..._readonly()` (system widget cart and order view)
  - `duplicate_product()`
  - the pre-order prefill `(product_id, name)` lookup
  - `shopping_cart.php` / `get_shopping_cart.php` x3 (legacy cart)
  - `express_order.php` / `get_express_order.php` x3 (express order)

## What was the deleted-product receipt bug?
- `get_submitted_product_form_content_with_form_fields()` gets the product id from `order_items LEFT JOIN products`. If the product was deleted, `products.id` is NULL, the query becomes `WHERE product_id = ''`, MySQL turns it into 0 and ALL rows belonging to no product match (before 2026.4 that was page-form rows; template rows enlarge the set, and `pg_pb_delete_variant_set()` makes product deletion routine).
- The function now returns empty when the product does not exist.
- GENERAL RULE: before putting an id obtained via `LEFT JOIN products` into a query, check it is non-empty. Comparing an `INT` column with `''` does not error, it silently becomes 0.

Related: docs/memory/commerce/variant-set-form-template.md
