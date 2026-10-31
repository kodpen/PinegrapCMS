---
name: panel-stepper-and-product-names
description: Accessible names of number steppers, catalog landmarks, the Modify Selected (Products) value boxes and the product screen fields, and the tests that pin them
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
- Each variant row's Advanced Settings toggle is named "Advanced Settings: combination" (`advancedToggle(index, combo)`), points `aria-controls` at its `<tr id="pg_pb_adv_<index>">` and carries `aria-expanded`, which the click handler sets beside `d-none`; the chevron is `aria-hidden`. Before, every row's button had the same name and no open state.
- The advanced row's fields have per-row ids (`pg_pb_v_gtin_<index>`, `_barcode_`, `_start_`, `_payments_`, `_period_`) and labels with `for`.
- An image chip's remove button has `aria-label` "Remove image" and is hidden with `opacity: 0` (not `display: none`, which took it out of the tab order so `:focus-within` never matched); the CSS shows it on `:hover`, `:focus-within` and always under `@media (hover: none)`. The upload screens' keyboard path: `upload-keyboard.md`.
- The New Attribute dialog and `#image_code` carry `aria-labelledby` their title; the 'No Thanks' checkbox sits in a `<label>`; an attribute card's Default Option select has a `<label for="pg_pb_attr_default_<id>">`; the barcode type select is named Barcode Type; a variant set row checkbox "Select {var:1}".
- The attribute card's Add Option box is named "New option for {attribute}" (it had only the placeholder "Label"). The New Attribute dialog's option rows are named "Option n" by `numberOptionRows()` (run on reset, add and remove), and `#pg_pb_attr_options` is a `role=group` labelled by the 'Attribute Options' caption, which has no field of its own to point `for` at.
- Pinned by `tests/product_screen_accessible_names_test.php`.

## What names do the Modify Selected (Products) value boxes have?
- `includes/templates/edit_products.php`: `#price_value` has a visible `<label id="price_value_label">` that `change_price_methodfunc()` switches between Amount and Percent beside the currency/% add-on. The fixed `12,30`/`30` placeholders are gone: they were its only name and wrote a comma decimal whatever the panel language.
- `#tax_rate_value` is labelled New Tax Rate (the 'Tax Rate' floating label names the method select) and `aria-describedby` its 'Used only when the rate is being set.' hint; the stepper box `#inventory_quantity` is labelled Quantity.
- Pinned by `tests/modify_selected_value_names_test.php`.
