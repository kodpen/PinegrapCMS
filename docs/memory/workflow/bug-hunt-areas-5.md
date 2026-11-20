---
name: bug-hunt-areas-5
description: Bug hunt areas given on the evening of 2026-11-20: order profit and shipping labels (K3), the bookings' visitor side and stock per location (K2); files, what to look at, what is left alone
---

## Where are the hunts before?
- `bug-hunt-areas.md` to `bug-hunt-areas-4.md`. The next area is none of the five files nor of a `bug-hunt-<area>.md` file in this folder.

## Which two areas did K3 get? (architect, 2026-11-20, channel message 10833)
- Order profit, NP1 to NP6 having landed: `includes/erp/order_profit.php`, `order_profit_deductions.php`, `order_profit_view.php`, `profitability.php`, `erp_order_profit_job.php`, `erp_profitability.php`. Hunted in #10792.
  - To look at: a cost row written twice by a pass that runs again, conversion and rounding (minor units, `round()`), the profit of a cancelled or refunded order, the marketplace deductions' resumable read, who sees profit.
  - Left alone: `includes/fn/ecommerce.php` (held by SH2 #10759 at the time).
- Shipping labels: `includes/fn/shipment_labels.php`, `shipment_label_auto.php`, `carrier_labels.php`, `shipping_label_rates.php`, `shipment_label.php`. Hunted in #10795.
  - To look at: the automatic label after payment made twice for one address, a bulk run cut half way, a label's cancel and its cost row, a marketplace's own label, rights.
  - Left alone: `shipment_tracking.php`, hunted in #10661 (`commerce/shipment-tracking-hunt.md`).
- Why these: both were built in the last weeks, no hunt had read them, and the delivery tower and pick and pack plans build on labels.

## Which area did K2 get? (architect, 2026-11-20, channel message 10888)
- The bookings' visitor side; the panel screens were hunted in #10399. Files: `booking_slots.php`, `booking_manage.php`, `includes/fn/bookings.php` (the slot engine, hold and confirm), `includes/fn/booking_manage.php`, `widgets_booking.php`, `booking_deposits.php`, `booking_deposit_pay.php`.
  - To look at: two visitors on one slot between hold and confirm, daylight saving and buffers at a day's edge, a hold that ran out, cancel and move inside and outside the policy window, what the signed link opens, the deposit.
  - Decided, no fault: `commerce/bookings-decisions.md`, `commerce/booking-deposit-late-payment.md` (#10378).
  - Left alone: `booking_messages.php` and `includes/fn/events.php` (the satisfaction loop's step FD3 enters them), the meeting link's files (`booking_links.php`, `book_with.php`).

## Which second area did K2 get? (architect, 2026-11-20, channel message 10907)
- Stock per location and the barcode stock screens, hunted in #10818: `includes/fn/stock_locations.php`, `stock_moves.php`, `stock_location_screens.php`, `stock_pickup.php`, `stock_sales.php`, `barcode_increase_inventory.php`, `barcode_decrease_inventory.php`, `barcode_menu.php`, `view_stock_moves.php`.
  - Decided, no fault: `commerce/stock-locations-decisions.md`, `commerce/barcode-stock-scan-checks.md`, `commerce/barcode-product-code-overlap.md`.
  - Left alone: `stock_holds.php` and `includes/fn/ecommerce.php` (the stock holds line), `stock_notify.php`, `erp_stock*.php`.
- Why: not hunted before, and pick and pack (PP2 to PP5) builds on them.

## What did each find?
- Written by the hunt's own PR, in a `bug-hunt-<area>.md` file or the area's topic file.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-4.md
