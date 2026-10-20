---
name: order-withdrawal-deadline
description: Last day of an order's right of withdrawal: latest delivery's window, estimate rules, no date when one delivery has none, order view token and flags, fallback card line, panel card row
---

## Where is it shown?
- My Account order view (order view widget): `__withdrawal_deadline` (date text in the widget's own date format) with the flags `has_withdrawal_deadline`, `withdrawal_deadline_estimated` (only while still open) and `withdrawal_ended`. The starter tree prints it in the Legal Documents card, under the document links.
- A layout that binds neither document link gets `pg_legal_documents_order_view_block()` after the widget; that card carries the line (`pg_legal_withdrawal_text()`) unless the layout binds `__withdrawal_deadline` somewhere itself.
- Panel order screen: a "Right of withdrawal until" row in the Legal Documents card (`pg_legal_documents_panel_card()`), marked Estimated or Ended. Both cards appear only while the order keeps a document copy; the token works in any layout.
- Never in the receipt e-mail: it leaves before anything ships, so it would only carry a guess.

## How is the date worked out?
- `pg_legal_withdrawal_of_order()` reads the order's recipients that have goods (`ship_tos` with `order_items`) and runs each through `pg_return_window()` (14 days from delivery; without a delivery date the later of ship date + 3 days and the expected delivery date). Downloads or services alone, and cancelled orders, give no date.
- `pg_legal_withdrawal_deadline()` (pure, tested) takes the latest deadline: for goods of one order delivered separately the period runs from the last delivery. It is an estimate when any delivery is estimated, because that one may still arrive later.
- A recipient with no known or planned date (`0000-00-00`) makes the whole order dateless: an earlier date from the other deliveries could cut the customer's period short.
- Shipment rows keep no real delivery date yet, so today every date is an estimate; once they do, `pg_return_window()` gets `delivered` and the estimate steps aside on its own.

## Why the latest date, and not one per delivery?
- The returns form works per delivery (a return names one recipient), but the right of withdrawal belongs to the order, and the regulation runs it from the last delivery for one order's separately delivered goods. One date keeps the card short.

## Related
- docs/memory/commerce/order-view-legal-documents.md, order-returns.md (the window), order-legal-documents.md.
- Built in PR #816. Tests: tests/legal_withdrawal_deadline_test.php.
