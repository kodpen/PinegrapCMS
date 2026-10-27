---
name: api-product-name-and-dimensions
description: External API product name (SKU) rule on create and update (trimmed, required, free: 409 name_taken) and the weight/length/width/height bounds that fit decimal(12,4) unsigned
---

## What does the API require of a product's name?
- The name is the merchant SKU. `api_products_name_or_fail($raw, $except_id)` (`includes/api/resources/products.php`) trims it, answers 422 `validation_failed` ("name is required") when it is empty, and 409 `name_taken` ("A product with the name … already exists (id …)") when another product has it, compared by the column's collation (a case difference is the same SKU).
- `POST /products` calls it for every create. `POST /products/{id}` (and PATCH, and the MCP tool) calls it when `name` is sent and differs from the stored name, excluding the product itself; resending the product's own name is not checked, so a catalogue that already holds a duplicate still takes an update that sends the whole record. The trimmed name is written.

## Why on update too?
- Before (issue #874, fixed by PR #1027) the update wrote `name` like any text column: another product's name gave 200 and two products of one name (`import_products.php` and other lookups by name then cannot tell which is meant); `""` gave 200 and an empty name. The create already refused both.
- The product screen refuses a taken code on create and on edit too (`product-code-unique.md`).

## What bounds do the dimensions have?
- `weight`, `length`, `width`, `height` are `decimal(12,4) unsigned`. Both product routes declare `'min' => 0, 'max' => 99999999.9999` (`includes/api/schema.php`); the router answers 422 outside it. Before, the update declared neither: `-5` gave 200 and stored 0 (the non-strict session clamps an unsigned column), and on both routes a figure past the top was stored as the top.
- `tests/api_decimal_bounds_test.php` reads the columns from both setup SQL files and checks every decimal body parameter of the two routes that names one declares a floor and a top the column holds. Text limits have the same guard: `api/api-text-field-limits.md`.
