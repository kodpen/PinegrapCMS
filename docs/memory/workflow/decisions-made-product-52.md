---
name: decisions-made-product-52
description: Decisions made, product part 52: deleting a user ends the account's open store credit (void, the row kept); a Void button for a card whose account is already gone
---

## What does deleting a user do with the account's open store credit? (owner, 2026-11-14, channel message 2638)
- What was wrong (#9016): `pg_user_delete()` left the account's cards open on a `user_id` that no longer exists. Such a card could not be voided (the customer's screen is gone) and Delete on it is refused (#6688, `decisions-made-product-27.md`).
- Asked whether the delete should warn and void, refuse, or void without a word, the owner answered: "Mağaza sorusu hesap silindiginde kredi silinsin."
- So: the open credit ends with the account. Deleting a user voids every card of the account that has a balance, at every door that deletes a user (Edit User, the users list's bulk delete, the API).
- The architect's reading, told to him in the channel (message 2639): "silinsin" is the credit ending, not the row going. The card is voided through `pg_credit_void()` with the note "Account deleted" and its audit line, as privacy erasure does (`api/api-customer-credit.md`); the row is kept, as #6688 asks. As built: `commerce/user-delete-store-credit.md` (#9379). No warning screen is added: he did not ask for one.
- Refused: refusing the delete while credit is open (bulk delete would stop on such accounts).

## May a store credit card whose account is gone be voided on Edit Gift Card? (architect, 2026-11-14, the owner's #6688 applied)
- Yes, by a follow-up PR: Edit Gift Card shows Void, never Delete, only for a card whose `user_id` names no user, through `pg_credit_void()` with a reason and the audit line. It closes the cards left open before the fix above.
- No sweep voids such cards by itself: an admin presses it. Reason: #6688's own (a credit never vanishes without its audit line), and the customer's screen that decision points to no longer exists for these cards.
