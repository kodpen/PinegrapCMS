---
name: store-credit-no-delete
description: Store credit (gift_cards.user_id > 0) has no Delete on edit_gift_card.php; it is voided on the customer's screen, and a posted delete is refused
---

## Can store credit be deleted on the gift card screen?
- No. `edit_gift_card.php` draws no Delete button for a card with `user_id` > 0; in its place a note says it is voided on the customer's Store credit card, with a link to `edit_user.php#store_credit` when the staff member may open that user.
- A posted `submit_delete` for such a card is refused on the server: `pg_gift_card_form_delete_refusal($gift_card)` (`includes/forms/gift_card_form.php`, pure, `tests/gift_card_form_test.php`) returns the message and the screen reopens with it.
- Ordinary gift cards (`user_id` = 0) are deleted as before.

## Why?
- Owner decision (#6688, `workflow/decisions-made-product-27.md`): Delete removed the row with only a Site Log line, so the customer's credit vanished with no audit line and the redemption history, the customer's list and the audit link lost their card. `pg_credit_void()` keeps the row, writes a note and the audit line and is guarded against a concurrent spend (`store-credit-model.md`). Rejected: deleting with an audit line (records the loss, does not prevent it).
