---
name: product-bulk-duplicate-code
description: The product code of a bulk copy (duplicate_products.php): last letter swapped for the typed text, cut by characters, then made unique; why the old "[1]" cut wrote duplicate codes
module: ecommerce
---

## How does the bulk copy name the new product?
- `duplicate_products()` (`duplicate_products.php`) asks `pg_duplicate_products_base_name($name, $newletter)` (`includes/fn/ecommerce.php`): the original's code minus its last character, plus the typed letter or word ("SHIRT-A" + "B" → "SHIRT-B"). A result that would be empty keeps the original.
- That code then goes through `get_unique_name()` (type `product`), so a taken one becomes "SHIRT-B[1]".
- The "Number of Letters to Remove" field still applies to the short description only.

## What was wrong before?
- The code was `substr(get_unique_name($original), 0, -4) . $newletter`: the unique "SHIRT-A[1]" lost "[1]" and one more byte. The result was never checked, so running the same copy twice (or two originals ending the same) wrote a second "SHIRT-B", which every other screen refuses (a product code is one product's, `barcode-product-code-overlap.md`). From "[10]" on the cut left "SHIRT-A[" behind, and a two-byte last letter (Ş, Ç) was split into invalid UTF-8.

## Tests?
- `tests/product_duplicate_bulk_name_test.php`. Not run against a database.
