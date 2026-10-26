---
name: shipping-bulk-import
description: Bulk "mark shipped" on the order list and the tracking-number CSV upload (import_shipments.php): what each writes through update_order(), what is refused and why
---

## What does "Mark Shipped Selected" on view_orders.php do?
- `edit_orders.php` action `mark_shipped` (#1877): per order one `update_order()` call with every address as `pg_shipping_bulk_mark_recipient()` builds it: `shipped` true (the customer gets the order_shipped e-mail), `ship_date` today, the address's own `delivery_date` sent back (update_order() writes the two together; one alone blanks the other), every line's `shipped_quantity` = `quantity` (so the Unshipped filter, the shipping report and dashboard widget 9 see it shipped), no `tracking_numbers` key, so the list is untouched.
- No parcel row is written, so a marked order still counts as unshipped for the customer's cancel gate (`order-cancel-shipping-gate.md`) and no `order.shipped` event goes out (that needs a new number).
- Skipped and counted (`pg_shipping_bulk_mark_skip()`): cancelled, incomplete, no address, an order that already has a tracking number, and one whose lines are all shipped (`shipped_in_full`), so a second click does not mail the customer again. Before #1877's review the mark wrote no line quantities and left marked orders "unshipped".

## What does the CSV upload accept?
- `import_shipments.php`, linked from the order list's toolbar; gate `validate_ecommerce_access()` + token. Columns `order_number,number,carrier` (carrier optional, header optional, comma, semicolon or tab, BOM stripped), read with `fgetcsv` by `pg_shipping_import_read()`; 1 MB and 2,000 rows at most.
- `order_number` must be 1–18 digits: the column is a BIGINT, so `'10045-1'`, `'10045abc'` or a UTF-16 cell matched another order by its numeric prefix and mailed the wrong customer (found in review of #1877).
- A row is also refused for a missing order or tracking number, a number over 100 characters, a carrier `pg_shipping_method_carrier()` does not know, an unknown order number, a cancelled or incomplete order, an order with no address, or one with several addresses (the file cannot say which parcel goes where; such orders are entered on the order screen).
- A number of the wrong shape is saved with the usual format warning, never refused.

## How is the address's list written?
- `update_order()` replaces the address's list, so `pg_shipping_import_entries()` sends the numbers it has (plain, each keeps its own carrier) plus the file's new ones (with the row's carrier when named). A number already there, or repeated in the file, is reported "Already on the order" (compared case-insensitively, like the unique key). Source `csv`.
- All rows of one order go in one call. An address without a ship date gets today's (with its delivery date sent back). "Send customers the shipping e-mail" off sends `shipped` false: the e-mail and SMS stay back, `order.shipped` and the invoice on shipment do not.

## Who is never mailed?
- A marketplace order's buyer: `update_order()` reads `orders.type` and, for `marketplace` (`pg_order_cancellation_mails_customer()`), holds back the order_shipped campaign, the review request, the shipped SMS and the delivery `notify` for every caller (order screen, API, Workspace, both bulk paths). The webhook events and the invoice on shipment still follow.

## What was not verified?
- The screens ran on no sandbox; the pure helpers are pinned in `tests/shipping_import_test.php`.
