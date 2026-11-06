---
name: bug-hunt-modules-fn-2d
description: Module list of plans/bug-hunt.md, part 2d: the includes/fn/ modules widgets_cart and widgets_catalog (moved out of part 2b for size) and the booking modules, one line each with its state
---

## Which includes/fn/ modules (widgets_cart, widgets_catalog) are hunted?
- Done: widgets_cart #5140 #5207 #5211 #5229, widgets_catalog #5134 #5155 #5180 #5199
- Second round (2026-11-06): widgets_cart #7602 (read: the changes since 2026-11-01 in full, with _render_system_widget_express_order, pg_express_order_charged_totals, pg_express_order_tax_pass, pg_cart_estimate_tax and the recurring schedule helpers whole; the shopping cart renderer's recurring split, schedule seeding and checkout form read; the rest of the module only by a request-input grep).

## Which booking modules (bookings, booking_*, widgets_booking) are hunted?
- Taken by the bookings bug hunt: booking_deposit_pay #7082 (hold kept without the staff lock); bookings, booking_manage, booking_agenda, booking_messages, booking_settings, widgets_booking read in the same hunt, nothing else found
