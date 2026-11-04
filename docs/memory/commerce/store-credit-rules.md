---
name: store-credit-rules
description: What binds store credit (owner, 2026-11-02, #5039, #6306): a customer-bound gift card, not a payment method, never pays for a gift card product, the reward goal kept, money in minor units
---

## What is store credit?
- A gift card bound to a customer's site account (`gift_cards.user_id`), never a second table (#5039). A card with no `user_id` is a plain gift card as before; a site that sets no points value and issues no credit changes nothing. Model: `store-credit-model.md`.
- It is spent through the gift card path (`applied_gift_cards`), so totals, receipts, the cancel refund of an order and reports are the gift card ones (`store-credit-checkout.md`).

## What is it not?
- Not a payment method: no driver, no charge, no refund to a payment service. A refund to credit is a refund with `refund_method = credit` and the card id as its reference (`store-credit-refunds.md`).
- It never pays for a gift card product: the rule `submit_order.php` already keeps for gift cards.

## Which owner decisions bind it?
- The reward goal (membership, reward mail) keeps working beside the conversion (`store-credit-points.md`).
- Money in integer minor units rounded with `round()`; the conversion rate is read as `pg_parse_amount()` reads amounts.
- An order's points are taken back on cancellation and refund; a shortfall after a conversion reduces or expires the newest points card, never below zero; not retroactive (#6306, `workflow/decisions-made-product-25.md`, `store-credit-points-take-back.md`).
- Mails `credit_issued` and `credit_expiring` are campaign profile actions; the expiring notice goes 14 days ahead (`store-credit-mails.md`).
- Erasing a customer voids their credit and clears `user_id` (`api/api-customer-credit.md`).

## Where is the rest?
- Screens: `store-credit-screens.md`; API and webhooks: `api/api-customer-credit.md`; gift card screens: `gift-card-screens-store-credit.md`.
