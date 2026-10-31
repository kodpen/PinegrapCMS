---
name: edit-product-undrawn-columns-kept
description: Edit Product's save leaves out the shipping, customs, zone, gift card and unlabelled custom-field columns the screen does not draw.
---

## Which columns does the Edit Product save leave alone?

`pg_pb_update_product()` passes `pg_pb_common_from_post()` through
`pg_pb_drop_undrawn_columns()`:

- Shipping off (`ECOMMERCE_SHIPPING`): `shippable`, `weight`, `length`,
  `width`, `height`, both weight points, `container_required`,
  `preparation_time`, `free_shipping`, `extra_shipping_cost`, `hs_code`,
  `origin_country`; and `products_zones_xref` is not rewritten.
- Gift cards off (`ECOMMERCE_GIFT_CARD`): `gift_card` and the four
  `gift_card_email_*` columns.
- `custom_field_N` whose label (`pg_pb_custom_product_field_labels()`) is
  empty.

## Why?

A control the screen does not draw posts nothing, so an off switch read 0
and an absent box ''. Editing any product while Shipping was off made it
unshippable, zeroed its weight and dimensions, emptied customs and deleted
every allowed zone; gift card set-up and custom fields were lost the same
way (#4007). The same rule already held for start, affiliate, PayPal Pro
and Sage columns.

## Does create do the same?

No. `pg_pb_save_new_product()` writes every column; on a new row the post's
empty values are the defaults anyway. A new gated block on the product
screen adds its columns to `pg_pb_drop_undrawn_columns()`
(`tests/product_builder_kept_columns_test.php`).
