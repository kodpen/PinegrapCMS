---
name: panel-stepper-and-product-names
description: Accessible names of number steppers, catalog landmarks and the product screen (variant matrix, advanced row, image chip remove, attribute dialog), and the tests that pin them
---

## How is a number stepper named?
- A `.number-controls` `minus`/`plus` button carries `aria-label` `h(lang('Decrease'))` / `h(lang('Increase'))` (Azalt / Artır). Without it the Material icon ligature names it ("remove" even sounds like delete). The ligature text and classes stay: `backend.src.js` binds the buttons by class.
- `tests/panel_steppers_names_test.php` scans the whole tree for stepper buttons, so a new stepper without a name fails the test.

## What else did the catalog sweep name?
- Landmark `nav` names (`data edit buttons`, `Button Bar`) on the currency, zone, tax zone, shipping method, gift card, arrival date, ship date adjustment, verified address, product attribute, folder, country, order and Modify Products screens go through `h(lang())`.
- The offer editor's product/group/method tag remove link is named "Remove: <tag>", its × is `aria-hidden` (`tools/bs6_switch_texts.txt` pins the line).
- The Arrival Date Code label points at `code`, not `name`.

## What names does the product screen give?
- Variant matrix inputs (SKU, Short Description, Unit Price, Inventory Quantity): `cellName()` in `product_builder.js` gives "column: combination", because the visible cell label is hidden from lg up and tied to nothing.
- The advanced row's fields have per-row ids (`pg_pb_v_gtin_<index>`, `_barcode_`, `_start_`, `_payments_`, `_period_`) and labels with `for`.
- An image chip's remove button is in the tab order with `aria-label` "Remove image"; the CSS shows it on `:hover`, `:focus-within` and always under `@media (hover: none)`.
- The New Attribute dialog and `#image_code` carry `aria-labelledby` their title; the 'No Thanks' checkbox sits in a `<label>`; an attribute card's Default Option select has a `<label for="pg_pb_attr_default_<id>">`; the barcode type select is named Barcode Type; a variant set row checkbox "Select {var:1}".
- Pinned by `tests/product_screen_accessible_names_test.php`.
