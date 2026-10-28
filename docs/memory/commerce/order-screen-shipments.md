---
name: order-screen-shipments
description: The panel order screen's shipping cards (view_order.php): one card per address, the parcel list with per-number carrier select, status badge and format warning, and how Save writes them
---

## What does the shipping card show? (PR #931)
- One card per address that has order lines (the recipient list comes from `order_items`). Before, `$output_shipping_details` was assigned rather than appended, so an order to two addresses showed only the last card.
- Under "Tracking Numbers" every parcel of `pg_shipment_rows()` is listed, also numbers whose carrier is unknown (before, only linked numbers were): the number (linked when `pg_shipment_view()` finds a URL: the row's own `tracking_url`, else the named or guessed carrier's pattern), a status badge (`pg_shipment_status_label()` / `pg_shipment_status_class()`; delivered shows the date as its title), a carrier select and, when `format_warning` is set, a yellow note (`pg_shipment_warning_text()`, naming the carrier checked against). The note never blocks the save.
- The tag field for adding and removing numbers stays; its id is `ship_to_id_tracking_numbers_<ship_to_id>`, so each address gets its own `tagin` (the shared id left the second one a plain text box).

## How does the carrier select work?
- Name `ship_to_id_<ship_to_id>_carrier_<row id>`; options `pg_shipment_carrier_options()`: "Automatic (<carrier it resolves to>)" with value '', then every carrier key, then a name the row keeps as typed (value escaped like the label, #2715). Selected: the row's own carrier, else Automatic.
- "Automatic" resolves to `shipping_methods.carrier` when the method has one, else to the read-time guess (method code, site default, number shape).
- Save sends each listed number as `array('number', 'carrier')` when its select was posted; `update_order()` passes that to `pg_shipment_record()` with `carrier_chosen`, so Automatic ('') drops the row's own carrier and the method's carrier, if any, is stored again. A number typed in the same save has no select yet and takes the automatic carrier. Marketplace and Workspace send plain strings, the API too unless the call names a carrier: their rows keep whatever carrier they have.

## What does Save write?
- Only addresses whose tracking field was posted (`array_key_exists` on `$_POST`). An address without order lines is not drawn; before, Save sent it as "no tracking numbers" and `pg_shipment_prune()` deleted its parcels (reproduced on a sandbox: the first address of a two-address order lost its number on every save).
- Selects are matched to typed numbers lower-cased and trimmed, the way the unique key compares them.

## What else changed on the way?
- `tagin()` (`backend.src.js`) escaped nothing: a tag's text went into `innerHTML`, so a tracking number from the API or a marketplace containing markup ran in the operator's browser. `escapeTag()` now escapes it; `getTags()` reads `textContent`, so the field's value is unchanged. Menu class fields use the same helper.
- A custom shipping field another address answered and this one did not no longer raises "Undefined array key" while drawing the second card.

## What happens when only the case of a number is corrected?
- `1z…` saved again as `1Z…` is the same parcel (the column's collation, as `uk_parcel`), and `pg_shipment_record()` now writes the new spelling too, so the link and the format warning follow what was typed; before (PR #1125) the row kept the old spelling while the warning was worked out from the new one. A marketplace poll (`source` marketplace) leaves the spelling alone, so it cannot undo a correction; `$recorded['number']` is the stored spelling and `update_order()` hands it to the event and the answer.

## What is not done here?
- `print_order.php` builds `$output_shipping_details` but never prints it (seen, not changed).
- Customer side, mail-merge fields and the API fields are later steps of `plans/shipping-management.md`.
