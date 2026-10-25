---
name: api-catalogue-writes
description: External API writes to products, groups, pages and key codes: the screen's create function, list fields that replace, barcode, SEO rescoring, Recycle Bin deletes, key code vs offer code
---

## How does POST /products write?
- Through the screen's own `pg_pb_create_product()`: there is no second INSERT. `pg_pb_save_submit_form_fields()` takes its source as a parameter (`$_POST` by default) and `$product['user']` can be given by the caller.
- A taken name answers 409 `name_taken`; `get_unique_name()` would silently have made it `NAME[1]`.
- POST /product-groups writes through `pg_catalog_group_create()`, shared with the Workspace, which announces `product_group.created` itself (`commerce/catalog-group-writer.md`).

## What does a product update replace?
- `group_ids`, `attributes` and `images` REPLACE what the product has; an empty list removes all of them. Stock is not written here: the inventory endpoint adjusts atomically.
- `barcode` only gives a product without one its code; a barcode already on it is not replaced (labels may carry it), and one another product carries is 422.

## What must a write that changes SEO inputs do?
- Score the record before answering: `api_products_rescore()` runs `pg_catalog_seo_rescore('product', $id)`, i.e. `pg_seo_recalculate('product', array($id))` (root `seo.php`); a group create through the writer is scored the same way; pages and group updates call it with their own type. Writing only `seo_analysis_current = 0` leaves the client reading the old score until the nightly job.

## How do deletes work?
- Products and product groups go to the catalogue Recycle Bin by default; `permanent=true` deletes outright with the screen's whole cleanup. Lists hide binned rows; a single read still shows them with `recycled: true`. 409 `recycle_bin_unavailable` on a site without the bin, 409 `already_recycled`. `product.deleted` carries `recycled`. Pages work the same way (`permanent=true`); a file can only be binned, never deleted outright through the API.
- Every delete has a POST twin for IIS: `external-api-iis-environment.md`.

## Why may a key code not equal an offer's code?
- Checkout looks a typed code up in `offers.code` first and reads `key_codes.code` only when no offer has it, so a key code equal to an offer's code is never applied. Every place that writes or generates a code checks both tables: `api_key_code_own_or_fail()`, `api_key_code_generate()` (`includes/api/resources/key_codes.php`); codes compare as the database collation does, so case does not make a second code.

## Where are the related rules?
- Text limits of these routes: `api-text-field-limits.md`. Bulk stock quantities: `api-bulk-inventory-quantity.md`. Product fields and variants: `products-variant-model.md`.
