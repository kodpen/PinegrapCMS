---
name: product-mass-edit-checks
description: Products › Modify Selected (edit_products.php) refuses an empty stock figure, a tax rate missing or outside 0..100, a zone in both cards and an empty submit, before any write
module: ecommerce
---

## What does Modify Selected refuse before touching a product?
- `pg_products_mass_edit_problem($_POST)` (`includes/explorer/handle_products_bulk.php`, pure) runs once for `action=edit`; any answer stops the whole submit with its message and no product is written, logged, audited or queued:
  - `quantity_missing`: a stock process chosen and the quantity empty, "Enter an inventory quantity.";
  - `tax_rate_missing`: "Set the tax rate" with an empty or non-numeric rate, "Enter a tax rate, or choose to follow the tax zone.";
  - `tax_rate_range`: a rate below 0 or above 100 (`pg_tax_rate_problem()`), "Enter a tax rate between 0 and 100.";
  - `zones_overlap`: one zone in both Allow and Disallow Shipping Zones, "A shipping zone cannot be both allowed and disallowed."
  - `hs_code_missing` / `origin_country_missing`: "Set to" with no value, "Enter an HS code, or choose to clear it." / "Choose a country of origin, or choose to clear it." (#8533);
  - `hs_code_invalid` / `origin_country_invalid`: a value the product screen refuses, with its message (`product-customs-fields.md`).
- A submit that asks for nothing gets "Select a field to change."; a refused price amount alone keeps only its own error.
- Before (#5309, #5311) 150 was stored as 100 and -5 as 0 (`parse_tax_rate()` holds to the bound) with an "updated" notice; an empty rate gave an error while every product still got its UPDATE (timestamp, user, audit, marketplace queue); an empty quantity or an empty submit came back without a word; a zone in both cards was added then removed, reported as "shipping zones changed".

## What does the window itself check?
- `includes/templates/edit_products.php` has no real submit (the button copies the values into the list's form), so the browser never checked the boxes' `min`/`max`. `edit_products()` asks `reportValidity()` of the tax rate box when the rate is being set, and stops a stock process without a figure. Ticking a zone in one card clears it in the other. An HS code or a country being set asks `reportValidity()` (pattern, or empty).

## Why does parse_tax_rate() still hold to 0..100?
- Its other callers (product screen, tax zones, API, Catalog Manager, surcharge setting) rely on it; the refusal is asked first with `pg_tax_rate_problem()` where a typed value must not be changed silently. The product import asks it too (`product-import-row-refusals.md`).

## What guards it?
- `tests/product_import_bulk_edit_checks_test.php` (the helpers and the two screens' checks), `tests/product_customs_mass_edit_test.php` (customs). The screens were not submitted on a live instance.
