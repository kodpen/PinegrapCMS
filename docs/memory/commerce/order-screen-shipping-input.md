---
name: order-screen-shipping-input
description: Order screen shipping card input: cancelled order read only and refused by update_order(), ship and delivery dates checked, shipped quantity 0 to ordered, paid on row
module: order_view
---

## What does a cancelled order show and save?
- The dates, parcels (with their carrier as text) and shipped quantities read only; no tracking field, no Save button.
- `update_order()` refuses a cancelled order with the API's text ("This order was cancelled, so a shipment cannot be recorded against it."): no parcel row, `order.shipped`, mail, SMS, review request or invoice on shipment (#5278). Every caller is covered: the screen, the bulk mark-shipped, the API, the Workspace.
- A refused save comes back to the screen as an error (mark_error and redirect), no longer as `output_error()`.

## How are the ship and delivery dates read?
- `pg_order_ship_date_parse($text, $month_first)`: empty, ISO, or day/month/four-digit year split by `.` `/` `-` `,` in the site's order; `checkdate()` (#5281). A two-digit year, a day that does not exist or text is refused on the field and the saved dates stay.
- `pg_order_ship_dates_in_order()`: a delivery before the shipment is refused on the screen only. `update_order()` checks each date is a real day (or empty / `0000-00-00`) but not their order, so an API call on an order with older bad data is not blocked.
- The calendar is the panel's (`data-pg-date` empty): translated names, Monday first in Turkish (#5282).

## How is the shipped quantity read?
- `pg_order_shipped_quantity($value, $ordered)`: blank hides it, else a whole number from 0 to the ordered quantity; anything else is an error from `update_order()` (#5279).
- The field is an integer mask without minus or right alignment, `min=0`, `max=<ordered>` (the + button stops there), and is named by `aria-label` "Shipped Qty".

## Where is the payment date?
- The Payment Information card prints "Paid on" with `orders.paid_at` when it is set (#5285).

Related: docs/memory/commerce/shipment-write-path.md, docs/memory/ui/pg-datepicker.md
