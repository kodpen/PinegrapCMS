---
name: bug-hunt-areas-gift-cards-store-credit
description: The bug hunt of gift cards and store credit (area 52, 2026-11-24): partial redemption, the cancelled credit refund's gift card part, the cancel refund line; what was read clean
---

## Which area was hunted?
- Area 52, gift cards and store credit: issue (`pg_order_complete_line_gift_cards()`, `add_gift_card.php`, `pg_credit_issue()`), apply (`order_preview.php`, `express_order.php`, `pg_credit_checkout_sync()`), the submit check (`submit_order.php`), redemption (`pg_order_complete_gift_cards()`), refunds as credit (`store_credit_refunds.php`, `pg_return_refund()`), cancel and restore, edits (`gift_card_form.php`, the list's quick edit, the API), points, void and expiry.
- Looked at: balances in minor units, partial use, two orders on one card at once, refunds back to the card, the totals cap.

## What did the hunt's PR fix? (#11496)
- A card another order spent part of gave nothing at completion; it now gives what it holds (`commerce/redeem-partly-spent-card.md`).
- A cancelled order's refund given as store credit left its gift card part owed, so after a restore it could be given again; it now writes a `return_gift_card` row (`commerce/order-gift-card-owed.md`).
- The cancel's "REFUND ACTION REQUIRED" line asked for what a return had given back already; it names what is still owed (`commerce/order-cancel-gift-cards.md`).
- The order preview took a gift card on a cart whose only other line was saved for later, which the submit then removed; its door now skips saved lines as the express order and the submit do.

## What was read clean?
- Money: every amount is minor units, `(int) round()`, no `(int)` cut; Givex replies go through `pg_givex_minor_units()`.
- Edits: the screen, quick edit and API write a balance only over the one read; store credit is voided, never deleted; `pg_credit_void()`, `pg_credit_reduce()` and the expiry pass are compare-and-set.
- Apply: a customer's credit is usable only by that customer; repeats are dropped at submit (`pg_gift_card_applied_repeats()`); the sync is under a lock per order; the cap keeps cards off gift card lines.
- `expiration_date` edits not moving `expires_at` read as meant (`commerce/store-credit-model.md`).

## What was left?
- Givex redemption still takes the whole asked amount or fails; the service gives no partial redeem here.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-5.md
