---
name: booking-deposits
description: Booking deposits (BK9a): booking_services.deposit and deposit_product_id, bookings.order_id and paid_at (11.0.169), the deposit order, marked paid from the order's completion, confirm or requested
module: booking_deposits
---

## What is stored?
- Step `upgrade_2026_11_0_booking_deposit()` (11.0.169, decided in #6632, `workflow/decisions-made-schema-10.md`): `booking_services.deposit` (minor units, 0 = none), `deposit_product_id`; `bookings.order_id` (INDEX `idx_order_id`) and `bookings.paid_at` (0 = unpaid). The booking points at the order, the order carries nothing. Readers call `pg_booking_deposit_ready()` first.

## When does a service ask for a deposit?
- `pg_booking_deposit_due()`: deposit > 0, a product, a price > 0 and deposit <= price; else null. Settings › Bookings (`pg_booking_deposit_clean()` inside `pg_booking_settings_service_clean()`) refuses a deposit above the price or on a free service (`deposit`) and one without a product (`deposit_product`).
- The Deposit box and the product picker (first 2000 products by name, `pg_booking_settings_deposit_products()`) are drawn only with the columns and the store on. A posted row without the boxes leaves the stored deposit as it is.

## How is the order written?
- `pg_booking_deposit_order_write($booking_id)`: the form fee's one-line order (`pg_form_payment_order_columns()` + `pg_form_payment_order_insert()`, shared with `forms`), billing from the booking's name (split on the last space), e-mail, phone, user. Offline payment is allowed when the store allows it ("paid on arrival"). A booking with an order keeps it, so a retry charges the same order.

## How is it marked paid?
- `pg_booking_deposit_mark_paid($order_id)`, called beside `pg_form_payment_mark_paid()` by `pg_order_complete()` (only when the completion wrote `paid_at`) and `pg_order_mark_paid()`. One UPDATE joined on `orders.paid_at > 0`, `bookings.paid_at = 0`: held/requested with a live or no hold → confirmed; a held row whose hold ran out → requested (its time may be taken, staff decide); cancelled/no_show/done only record the payment. An offline completion marks nothing.
- A held row gets its manage token here and `pg_booking_created()` (created + confirmed mails); requested → confirmed calls `pg_booking_changed($id, 'confirmed')`.

## What is left for BK9b?
- The widget's payment at the details step (choices, card box, redirect return, retry), the hold kept during payment, and showing paid in the agenda, manage page and API (`plans/appointment-booking-steps-2.md`).

## Tested?
- `tests/booking_deposit_test.php`: the boxes, what is due, the order columns, the paid statement and its event, the wiring. No database run.
