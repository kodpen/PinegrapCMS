---
name: order-view-surcharge-row
description: The order view's card surcharge row shows only when the order has a surcharge: has_surcharge on the starter row and stamped on saved trees
---

## When does the order view show "Card surcharge"?
- Only when `orders.surcharge > 0` (flag `has_surcharge`). The JS starter's row (`totalRow(…, 'has_surcharge')`, `style_designer.src.js`) carries `eo_visible_if: has_surcharge`.
- Saved trees from before get it at render: `pg_ov_stamp_surcharge_row()` (`widgets.php`), run just before `_eo_apply_visibility_bindings()`, stamps the outermost node whose only text-bound token is `__order_surcharge` (`_pg_op_subtree_marks()`, as `pg_op_stamp_method_classes()` does on the order preview). A node with its own `eo_visible_if` is left alone; a node holding another figure is not the row. Only the surcharge row.

## Why?
- #3458 (item 4): every order without a card surcharge printed "Card surcharge 0,00". Fixed in #3574.
