---
name: erp-parasut-product-card-opening-stock
description: The stock a tracked Paraşüt product card opens with: Pinegrap's count plus the units the documented sale already took out of it (#PR); what each caller adds, and the orders still counted out twice
---

## Which stock does a tracked card open with? (#PR)
- `initial_stock_count` = `inventory_quantity` (never below 0) + `$taken_out` (`parasut_product_attributes()`): the units the sale behind the document already took out of the count and the document takes out at Paraşüt. Checkout (`submit_order.php`), counter sale (`local_sale.php`) and marketplace import lower the count when the order is placed.
- Before #PR it opened at the count after the sale and the document took the same goods out again (10 in stock, order of 2: Pinegrap 8, Paraşüt 6). An untracked card is unchanged.

## What does each caller add?
- E-Shipment, Create Invoice with the shipment included (Paraşüt's default), the driver's invoice of an order: the document's units of the product, when the order took them.
- Create Invoice after E-Shipment, a driver invoice naming the order's shipment document: 0, they move no stock.
- A driver invoice not tied to an order: its own `sale` moves, wanted and applied (`erp_stock_moves`); 0 with stock documents off, an untracked product, a fractional quantity or a move still waiting.
- Create Invoice passes it as the body's `stock_taken` (`parasut_order_stock_taken()`); the driver's body builder works it out once (`parasut_invoice_stock_taken()`), reading nothing without a product line.

## Which orders took their stock? (`parasut_order_took_stock()`)
- Every placed order, a cancelled one too: `process_order_cancellation()` restocks nothing. Not a cart (`incomplete`).
- Not a marketplace order the marketplace cancelled: `mp_order_cancel()` cancels it with user 0 (`cancelled_by` 0), then restocks (`mp_order_return_stock()`). The shop's cancel of one names its user and restocks nothing.

## What is still counted out twice, or not at all?
- Only the opening document's units are added. Another order placed before the card opened and documented after it is taken out at Paraşüt from a card that never held it: stock 10, order A 2 (8), order B 3 (5); Create Invoice A opens the card at 7 and leaves 5, Create Invoice B leaves 2 while Pinegrap shows 5 (before #PR: 0). Which earlier orders will ever be documented at Paraşüt cannot be told; adding every undocumented one would overstate the card by all orders never sent there.
- A marketplace-cancelled order the shop restored: `cancelled_by` is 0 again, it reads as taken, nothing took its stock again: card overstated by its units. So is a product switched to tracking after the order.
- A driver invoice whose named shipment document is found deleted (404, sent again with the shipment): a card its first try opened kept the count alone.

## How was it checked?
- Unit tests only (`tests/parasut_product_stock_test.php`): the demo key expired on 2026-10-25. Live on 2026-10-24: `initial_stock_count` becomes `stock_count` with no movements; an invoice with the shipment included and the outgoing document take their goods out. A card opened with added units and then its document never ran live.
