---
name: privacy-erase-store-credit
description: What the personal data erasure does with a person's store credit: void with one retry, user_id cleared only on cards at 0, a card that lost the race keeps its owner (#10261)
module: privacy
---

## What does the erasure do with store credit?
- Entry `store_credit` of `pg_privacy_erase_tables()` (table `gift_cards`, found by `user_ids`, `user_id` → 0) runs `_pg_privacy_erase_apply_store_credit()` (`includes/fn/privacy.php`) before the `user` row goes.
- Every card with a balance is voided through `pg_credit_void()` (note "Personal data erased", audit `revoke` on `store_credit`).
- A void that fails is tried once more: `pg_credit_void()` reads the card again and writes over that balance, so an order that spent part of it between the read and the write no longer stops the void.
- Then `user_id = 0` is written only on the person's cards with `balance = 0` (`($where) AND (balance = 0)` passed to `_pg_privacy_erase_write()`), as `pg_credit_forget_user()` does on account delete (`user-delete-store-credit.md`).

## What if a card still holds a balance?
- It keeps its `user_id`; staff void it on Edit Gift Card. A bound card pays only for its owner signed in.

## Why?
- Before, the void's result was ignored and every card lost its owner: a card whose void lost the race kept its balance as a plain gift card, its code shown on the gift card screens and through `gift_cards:read`, paying for anyone who typed it (#10261).

## Tests?
- `tests/privacy_erase_store_credit_test.php` (the retry, the balance-0 clear, the entry unchanged); the entry is also pinned in `tests/privacy_erase_test.php` and `tests/api_customer_credit_test.php`.
