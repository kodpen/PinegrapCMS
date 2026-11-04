---
name: product-bulk-duplicate-code
description: The bulk copy (duplicate_products.php): product code and description rules, why the old "[1]" cut wrote duplicate codes, binned products left out, attribute option field, export redirect
module: ecommerce
---

## How does the bulk copy name the new product?
- `duplicate_products()` (`duplicate_products.php`) asks `pg_duplicate_products_base_name($name, $newletter)` (`includes/fn/ecommerce.php`): the original's code minus its last character, plus the typed letter or word ("SHIRT-A" + "B" → "SHIRT-B"). A result that would be empty keeps the original.
- That code then goes through `get_unique_name()` (type `product`), so a taken one becomes "SHIRT-B[1]".
- The "Number of Letters to Remove" field applies to the short description only: `pg_duplicate_products_description()` drops that many letters (characters, not bytes) and appends the typed text. A byte `substr()` cut Turkish descriptions short and could leave half a letter (invalid UTF-8) (#5355).

## What was wrong before?
- The code was `substr(get_unique_name($original), 0, -4) . $newletter`: the unique "SHIRT-A[1]" lost "[1]" and one more byte. The result was never checked, so running the same copy twice (or two originals ending the same) wrote a second "SHIRT-B", which every other screen refuses (a product code is one product's, `barcode-product-code-overlap.md`). From "[10]" on the cut left "SHIRT-A[" behind, and a two-byte last letter (Ş, Ç) was split into invalid UTF-8.

## Tests?
- `tests/product_duplicate_bulk_name_test.php` (#5257), `tests/product_duplicate_bulk_description_test.php`. Not run against a database.

## Which products does the bulk copy list and copy?
- Only live ones: the list and every view count add `products.recycled = '0'` (before the bin upgrade nothing), and the POST skips a binned id (`pg_catalog_product_binned()`) with "{var:1} product(s) were not duplicated because they are in the Recycle Bin." Before, a binned product was listed and its copy was a live product.
- An empty selection goes back with "Please select at least one product."; the notice on the Products screen counts the copies ("{var:1} product(s) were duplicated.").
- The attribute option field lists the site's own `product_attribute_options`, one optgroup per attribute, and is left out when there are none. It replaced a hard-coded "Assortment" select of one old site's ids, which matched nothing elsewhere.
- `?submit_data=Export Products` here redirects to the Products screen's export (`export_products_f.php`); the screen's private CSV copy was deleted.
