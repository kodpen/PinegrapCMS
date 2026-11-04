---
name: decisions-made-product-29
description: Decisions list, product part 29 (2026-11-04 evening on): the external API does not delete a store credit card; a gift card line takes at most 100 units
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Does the external API delete a store credit card? (#6783)
- Decided (architect, 2026-11-04, the owner's #6688 applied to the API door): no. `api_gift_cards_delete()` refuses a card with `gift_cards.user_id` > 0 with 409 `store_credit_not_deletable` and a message naming the void route (`POST /customers/{id}/credit/{card_id}/void`, `api/api-customer-credit.md`), which already writes the note and the audit line; ordinary gift cards are deleted as before. No new void operation: the route exists. Reason: the owner's reason (the credit vanished with no audit line; the redemption history, the customer's list and the audit link broke) is about the row, not the screen, and the resource's own comment promises the screen's behaviour. Refused: leaving the API delete as it is.

## Is a gift card line over 100 units refused? (#6821)
- Owner (2026-11-04): yes. A gift card line (`products.gift_card = 1`) takes at most 100 units: add to cart, cart update and `submit_order()` refuse more with one translated error ("A gift card line can hold at most 100 units") through the pure `pg_gift_card_line_quantity_allowed()` (`includes/fn/order_completion.php`); the 100 recipient forms and `pg_order_gift_card_count()` stay. A running site's cart that already holds more sees the error at checkout and lowers the quantity; a buyer who wants more places another order. The same rule holds at every door that writes a line quantity, the external API's order and quote creation included (applied by the architect, not asked again). No schema change. Reason: 150 × ₺100 was charged and only 100 cards (₺10,000) issued, with no warning (#6806); the charged amount must always equal the cards issued, and the form limit already exists. Refused: a card for every unit (units above 100 have no recipient form, so a recipient-less card, a new e-mail path and a cap anyway); one form per unit with no cap (breaks the page).
