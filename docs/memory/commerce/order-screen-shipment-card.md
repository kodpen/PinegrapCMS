---
name: order-screen-shipment-card
description: View Order's shipment cards (OS3, #11171): one card per address holding its address, its lines as line rows and its shipping fields; "Items not shipped" / "Products"; the line row
module: order_view
---

## What does a shipment card hold?
- One `section.card.pg-shipment` per address with lines, in the order the addresses are read, at the top of the form column. Title "Shipment", "Shipment 1 / 2" with two or more (`pg_order_view_shipment_title()`), the Ship to Name in grey, the address's pill from `pg_order_view_states()['addresses']`; right: Print Packing Slip and the label buttons (Create Label or the chooser, Create Return Label, Request Pickup, a marketplace's label with its boxes).
- Left, "Shipping address" with a copy button: the address block (`pg_order_view_address_block()`, the salutation before the name, the district before the city), phone and address type in grey, the postcode and district notes, "Address verified". Right, a `.pg-kv`: Shipping Method, Req. Arrival Date, Shipping Cost, Packages, the custom shipping fields.
- The lines (`pg_order_view_line_list()`), then on a hairline Ship Date, Delivery Date and Tracking Numbers in one row, the parcels under them (number with copy, status pill, carrier select, format note), then Shipping Labels. An incomplete order has no fields.
- The cards are built after the head's pills: the loop gathers `$shipment_cards` and `$shipment_lines`, drawn once `$head_states` is known.

## What did not change?
- Every field's name and id, `update_order()`, the `data-pg-chips` (tagin) field, the label form (`#shipment-label-form`) and the chooser's `data-pg-label-chooser`; the `trackin_number_from_view_orders` anchor is on the first card's fields.

## Where do lines with no address go?
- A card after the shipments: "Items not shipped" when the order has an address, "Products" when it has none (`pg_order_view_unshipped_title()`): the plan's "Items" is a key that already reads "Ürün Adedi", a count. Lines of address 0 in an order with addresses keep their shipped quantity field, as before.

## How is a line row drawn?
- `pg_order_view_line(array)`: a 44 px picture (`.pg-line-image`, a box icon when there is none), the name (short description, else the SKU; a link to the product) over a grey line (`meta`), "quantity × price", the shipped quantity over "/ ordered" (`pg_order_view_line_shipped()`: the stepper, or the number on a cancelled order), the total. Gift card and form boxes (`pg_order_view_line_part()`) sit under the row.
- The grey line: the SKU, a calendar event, "Taken from stock", the payment plan, the custom product fields, each "label: value" (`pg_order_view_line_meta_part()`).
- `pg_order_view_line_list($rows, $ships)`: the heading row (Product, Qty × Price, Shipped, Total; `aria-hidden`, each cell names itself), then the rows. On a phone a line is two rows. CSS "Look A: line row" in `backend.src.css`; Design examples "Line row" (`pg_look_examples_line_row_pattern()`).

Test: `tests/order_view_shipment_test.php`.
