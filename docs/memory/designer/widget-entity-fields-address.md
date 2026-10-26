---
name: widget-entity-fields-address
description: W8 address values: the order's recipients (ship_to, order view, receipt e-mail, recipient list) and the address book's saved recipients; tokens, words, dates, row tokens of the order lists.
---

## Which recipient values does the order view offer?
- `__ship_to_name`, `__shipping_salutation`, `__shipping_address_type` (words: "Residential address" / "Business address"), `__shipping_ship_date` (the stored ship date, which may be a planned one), `__shipping_delivered_date`. Editor group "More recipient details" (`SW_SHIP_TO_ENTITY_TOKENS`).
- Outside a list they are the first recipient's, like the other `__shipping_` tokens; in a list of the order's recipients (`order.shipments`) each row's. Names and address lines stay the order view's own `__shipping_` tokens.
- Read by the ship_tos queries the order view already sends (`pg_sw_entity_select('ship_to', 'ship_tos')`), no extra query; dates through the widget's own date formatter. Shipping cost, zone, offer, verification and package data stay off.

## Which values does the address book offer?
- In its row loop: `__recipient_salutation`, `__recipient_first_name`, `__recipient_last_name`, `__recipient_address_1` / `_2`, `__recipient_city`, `__recipient_state`, `__recipient_zip`, `__recipient_country` (the country's name, as the one-line address prints it), `__recipient_address_type` (words, only when the widget asks for the address type). Editor group "More recipient details (loop)" (`SW_ADDRESS_BOOK_ENTITY_TOKENS`). Only the signed-in member's own saved recipients.

## Which row tokens do the order lists carry?
- `order.shipments` names the ship_to values; `order.items` names the order line and product values (`pg_sw_list_order_entity_tokens()`, labels twin of the editor lists). Being the list's own tokens, a row fills them (empty when the order view did not read them) instead of the outer record.
- A yes/no value in a list row is marked `'cond'` (third element, on both sides): Bind Data's "Row of the list" group and a page link's row values skip it; conditions offer it.
