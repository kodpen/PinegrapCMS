---
name: order-withdrawal-deadline
description: Last day of an order's right of withdrawal: latest delivery's window with parcel arrivals, estimate rules, none for a dateless delivery, order view token, fallback card, panel row
---

## Where is it shown?
- My Account order view (order view widget): `__withdrawal_deadline` (date text in the widget's own date format) with the flags `has_withdrawal_deadline`, `withdrawal_deadline_estimated` (only while still open) and `withdrawal_ended`. The starter tree prints it in the Legal Documents card, under the document links.
- A layout that binds neither document link gets `pg_legal_documents_order_view_block()` after the widget; that card carries the line (`pg_legal_withdrawal_text()`) unless the layout binds `__withdrawal_deadline` somewhere itself.
- Panel order screen: a "Right of withdrawal until" row in the Legal Documents card (`pg_legal_documents_panel_card()`), marked Estimated or Ended. Both cards appear only while the order keeps a document copy; the token works in any layout.
- Never in the receipt e-mail: it leaves before anything ships, so it would only carry a guess.

## How is the date worked out?
- `pg_legal_withdrawal_of_order()` reads the order's recipients that have goods (`ship_tos` with `order_items`) and takes each one's `pg_return_delivery_state()['window']`, the same window the returns flow shows: 14 days from delivery. The delivery is known once every parcel has `shipping_tracking_numbers.delivered_at`; otherwise it is estimated as the latest of ship date + 3 days, the expected delivery date and the latest parcel that arrived. Downloads or services alone, and cancelled orders, give no date.
- `pg_legal_withdrawal_deadline()` (pure, tested) takes the latest deadline: for goods of one order delivered separately the period runs from the last delivery. It is an estimate when any delivery is estimated, because that one may still arrive later.
- A recipient with no known or planned date (`0000-00-00`) makes the whole order dateless: an earlier date from the other deliveries could cut the customer's period short.

## Why the latest date, and not one per delivery?
- The returns form works per delivery (a return names one recipient), but the right of withdrawal belongs to the order, and the regulation runs it from the last delivery for one order's separately delivered goods. One date keeps the card short.

## Related
- docs/memory/commerce/order-view-legal-documents.md, order-returns.md (the window), order-legal-documents.md.
- Built in PR #816. Its first version ignored parcel arrivals and used only the ship and expected dates, so an order whose parcels arrived after the expected date said the right had ended while the returns section on the same page still showed it open.
- Tests: tests/legal_withdrawal_deadline_test.php (`known_delivery_after_estimate`, `partial_arrival`, `reads_parcel_arrivals` cover the parcel dates). The panel writes the date with `pg_return_day_text()`.
