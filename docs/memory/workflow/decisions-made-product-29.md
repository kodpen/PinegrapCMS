---
name: decisions-made-product-29
description: Decisions list, product part 29 (2026-11-04 evening on): the external API does not delete a store credit card
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Does the external API delete a store credit card? (#6783)
- Decided (architect, 2026-11-04, the owner's #6688 applied to the API door): no. `api_gift_cards_delete()` refuses a card with `gift_cards.user_id` > 0 with 409 `store_credit_not_deletable` and a message naming the void route (`POST /customers/{id}/credit/{card_id}/void`, `api/api-customer-credit.md`), which already writes the note and the audit line; ordinary gift cards are deleted as before. No new void operation: the route exists. Reason: the owner's reason (the credit vanished with no audit line; the redemption history, the customer's list and the audit link broke) is about the row, not the screen, and the resource's own comment promises the screen's behaviour. Refused: leaving the API delete as it is.
