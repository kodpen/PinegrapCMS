---
name: order-screen-redraw-fixes
description: View Order as landed after OS2 (#11139) against the drawing: two differences and the place of Payment, all for OS4 (card titles in two sizes, an empty Billing Information card)
priority: 6
---

## What is this?
- View Order (`view_order.php`) opened on a sandbox at development 1ba3f59d, 1440 px and 390 px, both themes, beside board O1 of `commerce/commerce-screens-drawing/order.html` (`workflow/architect-looks-at-landed-screen.md`). Orders looked at: complete with one address, incomplete, incomplete with no contact.
- OS4 (`plans/order-screen-redraw-steps.md`) takes these with its own work. Its close-out deletes this file.

## 1. Why are the side panel's titles in two sizes?
- "Customer" and "Order Information" are 15 px; "Payment", "Billing Information" and every other card's title are 17 px.
- Cause: on those two cards `.pg-title-section` sits on the `h2.card-header` itself, where the header's size wins; the other cards hold an `h2.pg-title-section` inside the header.
- Fix: the two cards get the same head as Billing Information. One title size on the screen.

## 2. Why is Billing Information an empty card?
- On an order with no billing address and no billing row (an incomplete order) the card prints its title over an empty body.
- Fix: one grey line "No billing address", drawn as the Customer card's "No contact" line. The copy button is not printed with nothing to copy.

## What must OS4 not miss about Payment?
- After OS2 Payment is the first card of the form column's left half, so beside it the screen is empty on an order with no shipment, and on a phone Payment stands over the shipments.
- The drawing's order, on every width: the head, the shipments, "Items not shipped", Payment, the later cards; the side panel beside them from `lg`, after them on a phone (`order-screen-redraw-screens.md`).
- Payment takes the form column's whole width, its two halves side by side (`.pg-order-pay` already does it when the card is wide).

## What was looked at and is as drawn?
- The head, its pills and grey line; the Payment card's method, rows and totals; the Customer card with and without a contact; the address block and "Same as the shipping address"; Order Information with Reference Code's copy button; the phone's cards.
- The rows of Customer carry labels where the drawing has icons: the labels stay, a `.pg-kv` as the plan's words say.

Related: docs/memory/plans/order-screen-redraw.md, docs/memory/plans/order-screen-redraw-steps.md, docs/memory/commerce/order-screen-side-panel.md
