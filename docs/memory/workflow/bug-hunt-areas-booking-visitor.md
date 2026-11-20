---
name: bug-hunt-areas-booking-visitor
description: The bug hunt of bookings on the visitor's side (2026-11-20): the widget's endpoint, the slot engine, hold and confirm, the manage page, the deposit payment; what it fixed and what read clean
---

## What was the area?
- Given on 2026-11-20 (channel message 10888, to K2): bookings on the visitor's side; the panel screens were hunted in #10399. Files: `booking_slots.php`, `booking_manage.php`, `includes/fn/bookings.php`, `includes/fn/booking_manage.php`, `includes/fn/widgets_booking.php`, `includes/fn/booking_deposits.php`, `includes/fn/booking_deposit_pay.php`.
- Not touched: `booking_messages.php`, `includes/fn/events.php` (held for FD3), the meeting link files (`booking_links.php`, `book_with.php`).
- Decided, no fault: `commerce/bookings-decisions.md`, `commerce/booking-deposit-late-payment.md` (#10378).

## What did it fix?
- Written as the hunt goes; the PR body lists each fault.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-4.md, docs/memory/commerce/bookings-model.md
