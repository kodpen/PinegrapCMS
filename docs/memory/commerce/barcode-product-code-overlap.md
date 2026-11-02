---
name: barcode-product-code-overlap
description: A barcode may not be another product's product code and a product code may not be another product's barcode; a scanned code that is both, on two products, is refused (#4715)
---

## Why do barcodes and product codes have to stay apart?
- The barcode stock screens resolve a scanned code in `product_barcodes` first and then by `products.name`, which older labels carry. A barcode equal to another product's code shadowed that code: scanning it moved the barcode holder's stock, and the screen said the transaction succeeded (#4715). Stores that use the EAN as the SKU meet this with one mistyped barcode.

## Which checks refuse the overlap?
- Barcode → code: `save_product_barcode` (`pg_panel_save_product_barcode()`, `includes/panel/commerce.php`) and Create Product's typed barcodes (`pg_pb_barcodes_problem()`, `product_builder.php`) ask `pg_product_name_holder($barcode, $product_id, false)` (product codes only, the product's own left out) and answer `pg_barcode_code_taken_text()`: "The barcode X is the product code of Y." A product's own code stays a valid barcode for it.
- Inside one variant set post: `pg_pb_posted_barcode_on_other_row()` refuses a row's barcode that is another row's code (case aside).
- Code → barcode: `pg_product_name_holder($name, $except_id)` also looks in `product_barcodes` (joined to `products`, the excepted product left out) when no product has the code, and returns the holder with `barcode` '1'. `pg_product_name_taken_text()` then says "The product code X is already a barcode of Y." This covers the product screen's save, the live field check (`product_name_check`) and the Catalog Manager rename. The external API's `api_products_name_or_fail()` checks product codes only.
- Comparison follows the columns' collation: a case difference is the same code. A binned product still holds both.

## What does a scan of a code that is both do?
- `pg_barcode_scan_product($code)` (`includes/fn/ecommerce.php`), used by `barcode_increase_inventory.php` and `barcode_decrease_inventory.php`, looks up the barcode holder and another product whose code it is; `pg_barcode_scan_choice()` (pure) picks the barcode's product, else the code's, and null when they are two products. The screen then answers "X is a barcode of Y and the product code of another product. Change one of them, then scan again." and writes nothing.
- Overlaps written before the checks stay in the data until someone changes one side; the scan refuses them instead of guessing.

## Tests?
- `tests/barcode_stock_checks_test.php`, `tests/product_barcode_typed_test.php`, `tests/product_name_unique_test.php`. Not scanned on a live instance.
