---
name: erp-parasut-legacy-shipment-deleted-products
description: E-Shipment refuses an order whose lines name a product deleted from the catalogue, naming those lines, instead of leaving them off the waybill (#PR)
---

## What happened to a line of a deleted product?
- Deleting a product (`pg_pb_delete_product()`) leaves `order_items.product_id` pointing at no row. E-Shipment's LEFT JOIN read such a line as untracked (`inventory` NULL) and skipped it: the waybill went without those goods and the screen said it was created. With every line gone, the refusal said "no shippable (stock-tracked) items", the wrong reason.

## What does it do now? (#PR)
- `parasut_shipment_deleted_products_refusal()` refuses, naming the lines (product name, else `#id`; a product on two lines once). Whether the product kept stock and which Paraşüt card it had went with the row, so the shipment is written in Paraşüt by hand.
- A line with no product (`product_id` 0) is left out as before.

## Where does the refusal stand?
- After the shipped-order guard. For an order keeping a shipment id, that guard takes a token and reads the document; one Paraşüt no longer holds is forgotten (`orders.parasut_shipment_id` cleared, a notice added) before the refusal (erp-parasut-legacy-stale-ids.md).
- Before everything else: E-Shipment's own token, the invoice link, buyer card, warehouse, product cards, stock step and document.

## How was it checked?
- Unit tests only (`tests/parasut_product_stock_test.php`: the refusal and where it stands): the demo key expired on 2026-10-25. The refusal on the order screen never ran live.
