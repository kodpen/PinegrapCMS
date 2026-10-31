---
name: order-cancel-gift-cards
description: Gift cards on cancel and restore, REFUND ACTION REQUIRED for cards that paid; what a cancelled order still owes (0 payment when paid_at = 0); log lines name the order number
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
- Their amount does not go back to the card by itself. `pg_order_gift_card_refund_line($order_number, $rows)` writes a "REFUND ACTION REQUIRED" log line (`pg_log_lang()`; masked codes, shop card id, Givex marked).
- `refund_status` keeps what the payment step reported (a successful void stays `refunded`); the gift cards set `manual_required` only when the payment step left no outcome. `orders.total` has the gift card part taken off already (#733). Crediting the card automatically would need restore to take it again from a balance that may be spent; not done.

## How much does a cancelled order still owe?
- `pg_order_cancel_refund_owed($row)`: `payment` = `total - refunded_amount` (min 0), but 0 when cancelled before the payment arrived (`Offline Payment` or a pending-payment method with `paid_at = 0`; no `paid_at` column counts as paid); `gift_card` = `gift_card_discount`; `total` both. `pg_order_cancel_refund_owed_sql()` is the SUM form.
- Read by the view_order.php banner, dialog, provider sentence (only when `payment > 0` or no gift card part) and cancel notice, cancel_order.php, the orders list Refund Pending card and the dashboard lane. The cancel payment plan is unchanged. Fixed #4161 (PR #4221): an unpaid transfer showed total + gift card and the provider sentence; list and dashboard summed `total`.

## How do order log lines name the order?
- `pg_order_log_number()` (order number, the id only for a cart without one), `pg_order_log_status()` (panel language), `pg_order_log_money()` (plain symbol), never `prepare_amount()` (HTML entity) or `number_format()` (#4167, PR #4221).

Related: docs/memory/commerce/order-cancellation-flow.md, docs/memory/commerce/givex-gift-card.md
