---
name: layout-type-converter-order-preview
description: Order preview layout files into the order preview widget (LE9d, #11667): its map, the new flags and tokens, the converter rules it added, differences, starting sites; view order moved to LE9e
module: layout_convert
---

## What does the map do?
- `_pg_lc_order_preview_map()` (`includes/fn/layout_convert.php`) via `pg_layout_piece_map()` ([[layout-type-converter]]). The `<form>` tags go (`forms: strip`): the widget draws its own form posting to `order_preview.php`; `record`, no list.
- `$recipients as $recipient` → section `order_recipients` (the recipients, their items, gift cards and forms, drawn by the widget). `$payment_periods` → section `recurring_payments`, the billing form's `$fields` → section `billing_form`, `$installment_table` → section `installment_table`.
- `$applied_offers` and `$applied_gift_cards` loops → the widget's lists `checkout.offers` and `checkout.gift_cards`, their rows the layout's markup. `$payment_driver_methods` → loop `widget`: the widget adds those rows by copying a method's radio row.

## Which flags and tokens came with it?
- `pg_op_flags()` (`includes/fn/widgets_order_preview.php`): `has_shipping_cost`, `no_surcharge`, `has_applied_gift_cards`, `can_purchase_in_totals` (the button beside the totals, no recurring item), `one_payment_method` (its opposite in the layout is `many_payment_methods`), `has_installment_table`, `shows_custom_field_1/2` (label and value), `has_billing_form_title`.
- The billing lines: `billing_has_name`, `billing_has_company`, `billing_has_address_1/2`, `billing_has_city_line` (city, state or zip), `billing_has_city`, `billing_has_country`, `billing_has_phone`, `billing_has_fax`, `billing_has_email`.
- Tokens: the billing address's parts by the order view's names (`__billing_salutation`, `__billing_first_name`, `__billing_last_name`, `__billing_address_1/2`, `__billing_city`, `__billing_state`, `__billing_zip`, `__billing_country`, `__billing_fax`) and `__cart_total_with_surcharge_charged`.

## Which converter rules came with it?
- A second loop on a section the map names writes nothing: the widget draws the section once (the recurring part's second recipients loop).
- A flag with `in_tag => keep` inside a tag's attributes is drawn always (the button's `data-paypal-label`, `data-iyzico-label`); a plain flag there is still dropped. Loop `widget`: rows the widget adds, not read, not reported.
- A `<dl>` holding only the widget's list or section is replaced by it, as `ul`, `ol` and `table` are.
- `$in_tag()` now sees a tag opened at the very start of the text (it read none before).

## Known differences
- The recipients and their items are the widget's section, not the layout's table rows; the "Recurring Charges" part keeps its heading and an empty table, its items are in the one recipients section above.
- The stock template reports the store credit box (`condition`), the surcharge sentence and the terms sentence built with `lang()` vars (`value`). Its plural headings become the plural words.

## Starting sites and tests?
- 4 file pages (101 and 1077 on both sites), 4 texts: all convert with nothing dropped. The Turkish texts' installments and 3-D Secure box are under `has_installments`, `has_installment_table`, `has_threedsecure`.
- `tests/layout_convert_test.php`, `tests/order_preview_layout_test.php`.

## And view order?
- Split to step LE9e: the `order_view` widget's lists (`order.shipments`, `order.items`, `order.parcels`, `order.item_gift_cards`, `order.item_forms`) lack the row parts the layout's items read (today and recurring items apart, an event, a recurring schedule, a donation, shipped quantities). No starting-site page has a view order file.

Related: docs/memory/designer/layout-type-overview.md
