---
name: order-cancel-gift-cards
description: Gift cards on cancel and restore: issued cards keep their old date in the note and restore reads it from that line only; cards that paid the order raise REFUND ACTION REQUIRED, a gateway refund stays
---

## What happens to the gift cards an order issued when it is cancelled?
- `process_order_cancellation()` sets each card's `expiration_date` to yesterday when it had none or a later one; cards already expired stay as they are. Yesterday is counted from the same `time()` that is written to `cancelled_at` (one clock read), so restore finds the cards even when the cancel runs at midnight (#962).
- Each expired card gets a note line naming the order and the date it had: "…because order #1042 was cancelled. Its expiration date was 2027-01-31." / "…It had no expiration date." (`lang()`, so in the panel language).

## What does restoring the order do to them?
- `pg_order_restore_gift_cards($order_id, $order_number, $cancelled_at)` (view_order.php restore) puts back the date from the note (`pg_gift_card_expiration_before_cancel()`), only for cards whose expiration is still the day before `cancelled_at`.
- A line counts only when it is, after trimming, the cancellation template itself filled with the order number (and the ISO date in it): the English source, the current language and Turkish (`pg_gift_card_cancel_note_templates()`). Notes are free text, so an operator line naming the order ("Customer called about #1042", "#1042 refund promised by 2026-11-05") is skipped; it used to set that date or make the card non-expiring. A template line with an impossible date restores nothing. The last matching line wins (cancelled, restored, cancelled again).
- A card that had expired on its own, or whose date was changed after the cancel, stays as it is. A card cancelled before this note existed (old line without the order number) is not reopened: change its date in Gift Cards.
- Symptom it fixed (#733, #962): restore set every card of the order with a past date to "no expiration", so cards that had expired before the cancel, and dated cards, became spendable for good.

## What about gift cards that paid the order?
- Their amount does not go back to the card by itself. `pg_order_gift_card_refund_line()` writes a translated "REFUND ACTION REQUIRED" site log line (masked codes, the shop card's id, Givex marked, amounts).
- `refund_status` keeps what the payment step reported: a successful card void stays `refunded` (customer email, `order.cancelled`, `refunded_at` and the restore keep it) and the gift card part is then reported only by the log line and the cancel notice. `manual_required` is set by the gift cards only when the payment step left no outcome (gift-card-only order, or no auto refund and nothing else flagged).
- view_order.php: the Refund Pending banner shows total plus gift card part; the provider sentence only when `orders.total > 0`, the gift card sentence when `gift_card_discount > 0`. The cancel notice follows the same rule.
- `orders.total` has the gift card part taken off already, so an order paid by gift card alone raised no flag before (#733). Crediting the card automatically would need restore to take it again from a balance that may be spent; not done.

Related: docs/memory/commerce/order-cancellation-flow.md, docs/memory/commerce/givex-gift-card.md