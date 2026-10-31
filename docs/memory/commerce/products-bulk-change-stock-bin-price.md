---
name: products-bulk-change-stock-bin-price
description: Change Selected (edit_products.php) and the Catalog Manager bulk edit: out_of_stock by the shared rule, binned products skipped, only real price changes reported (#4214, #4215, #4220, PR #4279)
---

## How do the two bulk edits set the out-of-stock mark?
- Through `pg_products_bulk_out_of_stock_sql($stored, $inventory, $new_quantity)` (`includes/explorer/handle_products_bulk.php`), which wraps `pg_product_out_of_stock_sql()`: tracked after the edit with a count of 0 is `out_of_stock = '1'`, stamped only when it was not marked yet; tracking off or a count above 0 is `'0'`. An edit that touches neither tracking nor the count writes no mark.
- Tracking switched on for a product with 0 in stock is marked too, as the product screen does (`pg_pb_apply_stock_flag()`).
- Symptom it fixed (#4214): both paths only ever cleared the mark, so a tracked product cut to 0 stayed out of the "Out of Stock" view until its next Edit Product save, stamped with that save's time.

## What does Change Selected do with a product in the Recycle Bin?
- Skips it before any write (`pg_catalog_product_binned()`), counts it and adds "{var:1} product(s) were not changed because they are in the Recycle Bin." The list hides binned rows, but a selection made before another tab binned the product still posts its id.
- Symptom it fixed (#4220): the binned product's stock, price, status and zones were written, counted as updated, sent to the marketplace queue and announced as a stock change. Edit Product and the Catalog Manager bulk edit already refused it.

## When does Change Selected say "price changed"?
- Only when at least one product's price really moved (`$number_of_price_changed_products`). A refused product (`pg_bulk_price_change()`, product-bulk-price-edit.md) gets no `price =` write and no history queue entry.
- When nothing beside the price was asked (`pg_products_mass_change_besides_price()`), a refused product is not written at all and not counted, so a run where every cut is refused shows only the refusal error, with no notice or Site Log line (#4215).

## Which tests cover it?
- `tests/products_bulk_change_test.php`: the two helpers and source pins on both paths. No browser run.
