---
name: order-cancel-gift-cards
description: Gift cards on order cancel and restore: issued cards expire with the old date kept in the note, restore puts that date back, cards that paid the order raise REFUND ACTION REQUIRED
---

## What happens to the gift cards an order issued when it is cancelled?
- `process_order_cancellation()` sets each card's `expiration_date` to yesterday when it had none or a later one; cards already expired stay as they are.
- Each expired card gets a note line naming the order and the date it had: "…because order #1042 was cancelled. Its expiration date was 2027-01-31." / "…It had no expiration date." The order number and the ISO date are what restore reads, so the line works in any panel language.

## What does restoring the order do to them?
- `pg_order_restore_gift_cards($order_id, $order_number, $cancelled_at)` (view_order.php restore) puts back the date from the note (`pg_gift_card_expiration_before_cancel()`, last line naming the order wins), only for cards whose expiration is still the day before `cancelled_at`.
- A card that had expired on its own, or whose date was changed after the cancel, stays as it is. A card cancelled before this note existed (old line without the order number) is not reopened: change its date in Gift Cards.
- Symptom it fixed (#733): restore set every card of the order with a past date to "no expiration", so cards that had expired before the cancel, and dated cards, became spendable for good.

## What about gift cards that paid the order?
- Their amount does not go back to the card by itself. `pg_order_gift_card_refund_line()` writes a "REFUND ACTION REQUIRED" site log line (masked codes, the shop card's id, Givex marked, amounts) and `refund_status` becomes `manual_required`, also after an automatic card refund.
- `orders.total` has the gift card part taken off already, so an order paid by gift card alone raised no flag before (#733). Crediting the card automatically would need restore to take it again from a balance that may be spent; not done.

Related: docs/memory/commerce/order-cancellation-flow.md, docs/memory/commerce/givex-gift-card.md
