---
name: shipping-bulk-import
description: Bulk "mark shipped" on the order list and the tracking-number CSV upload (import_shipments.php): what each writes through update_order(), what is refused and why
---

## What does "Mark Shipped Selected" on view_orders.php do?
- `edit_orders.php` action `mark_shipped` (#1877): per order one `update_order()` call with every address as `pg_shipping_bulk_mark_recipient()` builds it: `shipped` true (the customer gets the order_shipped e-mail), `ship_date` today, the address's own `delivery_date` sent back (update_order() writes the two together; one alone blanks the other), no `tracking_numbers` key, so the list is untouched.
- No parcel row is written, so a marked order still counts as unshipped for the customer's cancel gate (`order-cancel-shipping-gate.md`) and no `order.shipped` event goes out (that needs a new number).
- Skipped and counted (`pg_shipping_bulk_mark_skip()`): cancelled, incomplete, no address, and an order that already has a tracking number (its customer was mailed when the number went in). Marking the same order twice mails twice; the confirm dialog says what happens.

## What does the CSV upload accept?
- `import_shipments.php`, linked from the order list's toolbar; gate `validate_ecommerce_access()` + token. Columns `order_number,number,carrier` (carrier optional, header optional, comma, semicolon or tab, BOM stripped), read with `fgetcsv` by `pg_shipping_import_read()`; 1 MB and 2,000 rows at most.
- A row is refused for a missing order or tracking number, a number over 100 characters, a carrier `pg_shipping_method_carrier()` does not know, an unknown order number, a cancelled or incomplete order, an order with no address, or one with several addresses (the file cannot say which parcel goes where; such orders are entered on the order screen).
- A number of the wrong shape is saved with the usual format warning, never refused.

## How is the address's list written?
- `update_order()` replaces the address's list, so `pg_shipping_import_entries()` sends the numbers it has (plain, each keeps its own carrier) plus the file's new ones (with the row's carrier when named). A number already there, or repeated in the file, is reported "Already on the order" (compared case-insensitively, like the unique key). Source `csv`.
- All rows of one order go in one call. An address without a ship date gets today's (with its delivery date sent back). "Send customers the shipping e-mail" off sends `shipped` false: the e-mail and SMS stay back, `order.shipped` and the invoice on shipment do not.

## What was not verified?
- The screens ran on no sandbox; the pure helpers are pinned in `tests/shipping_import_test.php`.
