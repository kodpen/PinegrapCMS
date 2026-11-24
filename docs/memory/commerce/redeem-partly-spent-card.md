---
name: redeem-partly-spent-card
description: At order completion a gift card or store credit card another order spent part of gives what it still holds, written only over the balance read (pg_order_gift_card_redeem_take(), #11496)
module: order_completion
---

## What does a card give when its balance went down after the checkout?
- `pg_order_complete_gift_cards()` asks each applied card of this system for min(`old_balance`, what is still to redeem). The card gives `pg_order_gift_card_redeem_take($amount, $balance)`: that amount, or what it holds now when less; whole minor units, never below 0 (pure).
- The decrement is `balance = balance - ?` `WHERE (id = ?) AND (balance = ?)` with the balance read; a write that changed no row reads again, up to three times. A card that gave nothing (spent, deleted, three missed writes) is removed from the order as before.
- The next cards in applied order then cover the rest, each up to its own `old_balance`; only what none covers is the shortfall of the "Gift card error" mail (`pg_order_gift_card_error_message()`), which names each card that gave less than asked.

## Why?
- Before #11496 the decrement ran only `WHERE balance >= amount`: a card another order had spent part of (two orders at once on one card or one customer's credit) gave nothing at all, so the order was accepted with the whole card amount missing instead of the difference, and the store had to chase all of it.
- `applied_gift_cards.amount` keeps what the card gave, so `pg_order_gift_card_owed()` and the ERP settlement (`order-gift-card-owed.md`) count the part really received.

Test: `tests/order_completion_test.php` (`test_order_completion_gift_card_redeem_take`). Related: `order-completion-before-lock.md`, `givex-gift-card.md` (Givex cards are not changed).
